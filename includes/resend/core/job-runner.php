<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Resend_Job_Runner
{
    private const HEARTBEAT_INTERVAL = 60;

    public static function run(array &$job, ?string $lock_token = null): void
    {
        if (($job['status'] ?? '') !== 'running') {
            return;
        }

        $owns_lock = false;

        if ($lock_token === null) {
            $lock_token = PWE_Multilang_Resend_Job_Lock::acquire();

            if ($lock_token === null) {
                return;
            }

            $owns_lock = true;
        }

        try {
            self::run_locked($job, $lock_token);
        } finally {
            if ($owns_lock) {
                PWE_Multilang_Resend_Job_Lock::release($lock_token);
            }
        }
    }

    private static function run_locked(array &$job, string $lock_token): void
    {
        if (!PWE_Multilang_Resend_Job_Lock::refresh($lock_token)) {
            return;
        }

        $limit = max(1, (int) ($job['batch_size'] ?? PWE_Multilang_Resend::DEFAULT_BATCH_SIZE));
        $scan_limit = max(PWE_Multilang_Resend::PAGE_SIZE, $limit * 20);
        $processed = 0;
        $examined = 0;
        $next_heartbeat = time() + self::HEARTBEAT_INTERVAL;
        $form_ids = array_values((array) ($job['form_ids'] ?? []));
        $cursor = array_merge(
            PWE_Multilang_Resend_Job::empty_cursor(),
            is_array($job['cursor'] ?? null) ? $job['cursor'] : []
        );

        while ($processed < $limit && $examined < $scan_limit) {
            if (time() >= $next_heartbeat) {
                if (!PWE_Multilang_Resend_Job_Lock::refresh($lock_token)) {
                    $job['cursor'] = $cursor;
                    return;
                }

                $next_heartbeat = time() + self::HEARTBEAT_INTERVAL;
            }

            $form_index = (int) $cursor['form'];

            if (!isset($form_ids[$form_index])) {
                $job['cursor'] = $cursor;
                PWE_Multilang_Resend_Job::complete($job);
                return;
            }

            $form = PWE_Multilang_GF_Form_Repository::get((int) $form_ids[$form_index]);

            if ($form === null) {
                $cursor = self::next_form($cursor);
                continue;
            }

            $notifications = array_values(PWE_Multilang_Resend_Gravity::get_resend_notifications($form));

            if (!$notifications) {
                $cursor = self::next_form($cursor);
                continue;
            }

            $page = PWE_Multilang_Resend_Gravity::get_entries_page(
                (int) $form['id'],
                (bool) ($job['include_all_entries'] ?? false),
                (int) $cursor['offset'],
                PWE_Multilang_Resend::PAGE_SIZE,
                (int) ($job['min_age_days'] ?? PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS)
            );

            if ((string) ($page['error'] ?? '') !== '') {
                $message = (string) $page['error']
                    . ' Kursor nie został przesunięty; po wznowieniu proces spróbuje ponownie.';
                $job['cursor'] = $cursor;
                PWE_Multilang_Resend_Job::block_for_entries_error(
                    $job,
                    $message,
                    (int) $form['id']
                );
                PWE_Multilang_Resend_Job::append_log(
                    $job,
                    0,
                    '',
                    'blocked',
                    $message
                );
                return;
            }

            $entries = (array) ($page['entries'] ?? []);

            if (!$entries) {
                $cursor = self::next_form($cursor);
                continue;
            }

            if (!PWE_Multilang_Resend_Job_Lock::refresh($lock_token)) {
                $job['cursor'] = $cursor;
                return;
            }

            $next_heartbeat = time() + self::HEARTBEAT_INTERVAL;

            $entry_index = (int) $cursor['entry'];

            if (!isset($entries[$entry_index])) {
                $cursor['offset'] = (int) $cursor['offset'] + count($entries);
                $cursor['entry'] = 0;
                $cursor['notification'] = 0;
                continue;
            }

            $entry = $entries[$entry_index];
            $notification_index = (int) $cursor['notification'];

            if (!isset($notifications[$notification_index])) {
                $cursor['entry'] = $entry_index + 1;
                $cursor['notification'] = 0;
                continue;
            }

            $notification = $notifications[$notification_index];
            $examined++;
            $result = self::maybe_send_notification($form, $entry, $notification);

            if (is_array($result) && ($result['status'] ?? '') === 'blocked') {
                $job['cursor'] = $cursor;
                PWE_Multilang_Resend_Job::block_for_delivery_error(
                    $job,
                    (int) ($entry['id'] ?? 0),
                    (string) ($notification['name'] ?? ''),
                    (string) ($result['message'] ?? 'Wysyłka została zablokowana.')
                );
                PWE_Multilang_Resend_Job::append_log(
                    $job,
                    (int) ($entry['id'] ?? 0),
                    (string) ($notification['name'] ?? ''),
                    'blocked',
                    (string) ($result['message'] ?? 'Wysyłka została zablokowana.')
                );
                return;
            }

            $cursor['notification'] = $notification_index + 1;

            if ($result === 'not_candidate') {
                continue;
            }

            $processed++;
            self::record_result($job, $entry, $notification, $result);
            $job['cursor'] = $cursor;

            // Checkpoint every completed candidate. Entry meta makes retries idempotent;
            // the cursor prevents every batch from rescanning from offset zero.
            if (!PWE_Multilang_Resend_Job::checkpoint($job, $lock_token)) {
                return;
            }

            $next_heartbeat = time() + self::HEARTBEAT_INTERVAL;
        }

        $job['cursor'] = $cursor;
    }

    private static function next_form(array $cursor): array
    {
        return [
            'form'         => (int) $cursor['form'] + 1,
            'offset'       => 0,
            'entry'        => 0,
            'notification' => 0,
        ];
    }

    private static function record_result(
        array &$job,
        array $entry,
        array $notification,
        $result
    ): void {
        $entry_id = (int) ($entry['id'] ?? 0);
        $name = (string) ($notification['name'] ?? '');

        if ($result === 'sent') {
            $job['sent'] = (int) ($job['sent'] ?? 0) + 1;
            PWE_Multilang_Resend_Job::append_log($job, $entry_id, $name, 'sent_to_smtp', 'Przekazano do SMTP.');
            return;
        }

        if ($result === 'skipped') {
            $job['skipped'] = (int) ($job['skipped'] ?? 0) + 1;
            return;
        }

        $job['failed'] = (int) ($job['failed'] ?? 0) + 1;
        PWE_Multilang_Resend_Job::append_log($job, $entry_id, $name, 'failed', (string) $result);
    }

    private static function maybe_send_notification(array $form, array $entry, array $notification)
    {
        if (!PWE_Multilang_Resend_Matcher::is_entry_matching_notification($form, $entry, $notification)) {
            return 'not_candidate';
        }

        if (PWE_Multilang_Resend_Matcher::is_already_processed($entry, $notification)) {
            return 'not_candidate';
        }

        $entry_id = (int) ($entry['id'] ?? 0);

        if (!PWE_Multilang_Resend_Matcher::notification_should_send($notification, $form, $entry)
            || !PWE_Multilang_Resend_Matcher::entry_has_valid_email($form, $entry)) {
            gform_update_meta(
                $entry_id,
                PWE_Multilang_Resend_Matcher::skipped_meta_key($notification),
                current_time('mysql')
            );
            return 'skipped';
        }

        $mail_error = null;
        $capture = static function (WP_Error $error) use (&$mail_error): void {
            $mail_error = $error;
        };

        add_action('wp_mail_failed', $capture);

        try {
            if (method_exists('GFCommon', 'send_notification')) {
                GFCommon::send_notification($notification, $form, $entry);
            } else {
                GFCommon::send_notifications([$notification], $form, $entry);
            }
        } catch (Throwable $error) {
            return ['status' => 'blocked', 'message' => $error->getMessage()];
        } finally {
            remove_action('wp_mail_failed', $capture);
        }

        if ($mail_error instanceof WP_Error) {
            return ['status' => 'blocked', 'message' => $mail_error->get_error_message()];
        }

        gform_update_meta(
            $entry_id,
            PWE_Multilang_Resend_Matcher::sent_meta_key($notification),
            current_time('mysql')
        );

        return 'sent';
    }
}
