<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Resend_Job
{
    private const SCHEMA_VERSION = 2;
    private const STATUSES = ['running', 'paused', 'blocked', 'completed'];

    public static function get(): ?array
    {
        $stored = get_option(PWE_Multilang_Resend::OPTION_JOB);

        if (!is_array($stored)) {
            return null;
        }

        $requires_total_migration = (int) ($stored['schema_version'] ?? 0) < self::SCHEMA_VERSION
            || !array_key_exists('total', $stored)
            || !empty($stored['total_migration_pending']);
        $job = self::normalise($stored);

        if (!$requires_total_migration) {
            return $job;
        }

        return self::migrate_legacy_total($job);
    }

    public static function save(array $job): void
    {
        update_option(PWE_Multilang_Resend::OPTION_JOB, self::normalise($job), false);
    }

    public static function checkpoint(array $job, string $lock_token): bool
    {
        if (!PWE_Multilang_Resend_Job_Lock::refresh($lock_token)) {
            return false;
        }

        self::save($job);
        return true;
    }

    public static function delete(): bool
    {
        $token = PWE_Multilang_Resend_Job_Lock::acquire();

        if ($token === null) {
            return false;
        }

        try {
            return delete_option(PWE_Multilang_Resend::OPTION_JOB);
        } finally {
            PWE_Multilang_Resend_Job_Lock::release($token);
        }
    }

    public static function create(
        array $form_ids,
        int $batch_size,
        int $delay,
        bool $include_all_entries,
        int $min_age_days
    ): bool {
        $token = PWE_Multilang_Resend_Job_Lock::acquire();

        if ($token === null) {
            return false;
        }

        try {
            if (self::get() !== null) {
                return false;
            }

            $selected = [];

            foreach ($form_ids as $form_id) {
                $form = PWE_Multilang_GF_Form_Repository::get((int) $form_id);

                if ($form === null || !PWE_Multilang_Resend_Gravity::get_resend_notifications($form)) {
                    continue;
                }

                $selected[] = (int) $form_id;
            }

            $job = self::normalise([
                'id'                  => wp_generate_uuid4(),
                'schema_version'      => self::SCHEMA_VERSION,
                'status'              => $selected ? 'running' : 'completed',
                'created_at'          => current_time('mysql'),
                'form_ids'            => $selected,
                'batch_size'          => max(1, min(500, $batch_size)),
                'delay'               => max(3, min(60, $delay)),
                'include_all_entries' => $include_all_entries,
                'min_age_days'        => max(1, min(3650, $min_age_days)),
                'cursor'              => self::empty_cursor(),
                'total'               => 0,
                'sent'                => 0,
                'failed'              => 0,
                'skipped'             => 0,
                'log'                 => [],
                'log_total'           => 0,
            ]);

            if ($selected) {
                $count = self::count_remaining($job, $token);

                if ($count['error'] !== '') {
                    $job['total_migration_pending'] = true;
                    $job['total_migration_blocked'] = true;
                    self::block_for_entries_error($job, $count['error'], (int) $count['form_id']);
                } else {
                    $job['total'] = (int) $count['remaining'];
                }
            }

            if (!PWE_Multilang_Resend_Job_Lock::refresh($token)) {
                return false;
            }

            self::save($job);
            return true;
        } finally {
            PWE_Multilang_Resend_Job_Lock::release($token);
        }
    }

    public static function mutate(callable $callback): bool
    {
        $token = PWE_Multilang_Resend_Job_Lock::acquire();

        if ($token === null) {
            return false;
        }

        try {
            $job = self::get();

            if ($job === null) {
                return false;
            }

            $callback($job, $token);

            if (!PWE_Multilang_Resend_Job_Lock::refresh($token)) {
                return false;
            }

            self::save($job);
            return true;
        } finally {
            PWE_Multilang_Resend_Job_Lock::release($token);
        }
    }

    public static function block_for_entries_error(array &$job, string $message, int $form_id = 0): void
    {
        if (($job['status'] ?? '') !== 'blocked') {
            $job['status_before_blocked'] = (string) ($job['status'] ?? 'running');
        }

        $job['status'] = 'blocked';
        $job['blocked_reason'] = 'entries_fetch';
        $job['blocked_message'] = trim($message);
        $job['blocked_form_id'] = max(0, $form_id);
        $job['blocked_at'] = current_time('mysql');
    }

    public static function block_for_delivery_error(
        array &$job,
        int $entry_id,
        string $notification,
        string $message
    ): void {
        if (($job['status'] ?? '') !== 'blocked') {
            $job['status_before_blocked'] = (string) ($job['status'] ?? 'running');
        }

        $job['status'] = 'blocked';
        $job['blocked_reason'] = 'delivery';
        $job['blocked_message'] = trim($message);
        $job['blocked_entry_id'] = max(0, $entry_id);
        $job['blocked_notification'] = $notification;
        $job['blocked_at'] = current_time('mysql');
    }

    public static function clear_block(array &$job): void
    {
        unset(
            $job['blocked_reason'],
            $job['blocked_message'],
            $job['blocked_form_id'],
            $job['blocked_entry_id'],
            $job['blocked_notification'],
            $job['blocked_at'],
            $job['status_before_blocked']
        );
    }

    public static function complete(array &$job): void
    {
        $job['schema_version'] = self::SCHEMA_VERSION;
        $job['status'] = 'completed';
        $job['total'] = self::completed_count($job);
        unset(
            $job['total_migration_pending'],
            $job['total_migration_error'],
            $job['total_migration_blocked']
        );
        self::clear_block($job);
    }

    public static function append_log(
        array &$job,
        int $entry_id,
        string $notification,
        string $status,
        string $message
    ): void {
        $job['log'][] = [
            'time'         => current_time('mysql'),
            'entry_id'     => $entry_id,
            'notification' => $notification,
            'status'       => $status,
            'message'      => $message,
        ];
        $job['log_total'] = (int) ($job['log_total'] ?? 0) + 1;

        if (count($job['log']) > 100) {
            $job['log'] = array_slice($job['log'], -100);
        }
    }

    public static function stats(array $job): array
    {
        $completed = self::completed_count($job);
        $total_known = empty($job['total_migration_pending']);

        return [
            'sent'        => (int) ($job['sent'] ?? 0),
            'failed'      => (int) ($job['failed'] ?? 0),
            'skipped'     => (int) ($job['skipped'] ?? 0),
            'pending'     => $total_known ? max(0, (int) ($job['total'] ?? 0) - $completed) : null,
            'total_known' => $total_known,
            'log_total'   => (int) ($job['log_total'] ?? 0),
        ];
    }

    public static function empty_cursor(): array
    {
        return ['form' => 0, 'offset' => 0, 'entry' => 0, 'notification' => 0];
    }

    private static function migrate_legacy_total(array $fallback_job): array
    {
        $token = PWE_Multilang_Resend_Job_Lock::acquire();

        if ($token === null) {
            return $fallback_job;
        }

        try {
            $stored = get_option(PWE_Multilang_Resend::OPTION_JOB);

            if (!is_array($stored)) {
                return $fallback_job;
            }

            if ((int) ($stored['schema_version'] ?? 0) >= self::SCHEMA_VERSION
                && array_key_exists('total', $stored)
                && empty($stored['total_migration_pending'])) {
                return self::normalise($stored);
            }

            $job = self::normalise($stored);

            if (($job['status'] ?? '') === 'completed') {
                $job['total'] = self::completed_count($job);
                $job['schema_version'] = self::SCHEMA_VERSION;
                unset(
                    $job['total_migration_pending'],
                    $job['total_migration_error'],
                    $job['total_migration_blocked']
                );

                if (PWE_Multilang_Resend_Job_Lock::refresh($token)) {
                    self::save($job);
                }

                return $job;
            }

            $count = self::count_remaining($job, $token);

            if ($count['error'] !== '') {
                $job['total_migration_pending'] = true;
                $job['total_migration_error'] = (string) $count['error'];

                if (($job['status'] ?? '') !== 'blocked'
                    || ($job['blocked_reason'] ?? '') === 'entries_fetch') {
                    $job['total_migration_blocked'] = true;
                    self::block_for_entries_error($job, $count['error'], (int) $count['form_id']);
                }
            } else {
                $job['total'] = self::completed_count($job) + (int) $count['remaining'];
                $job['schema_version'] = self::SCHEMA_VERSION;
                unset($job['total_migration_pending']);
                unset($job['total_migration_error']);

                if (!empty($job['total_migration_blocked'])
                    && ($job['status'] ?? '') === 'blocked'
                    && ($job['blocked_reason'] ?? '') === 'entries_fetch') {
                    $previous_status = (string) ($job['status_before_blocked'] ?? 'running');
                    $job['status'] = in_array($previous_status, ['running', 'paused'], true)
                        ? $previous_status
                        : 'running';
                    self::clear_block($job);
                }

                unset($job['total_migration_blocked']);
            }

            if (PWE_Multilang_Resend_Job_Lock::refresh($token)) {
                self::save($job);
            }

            return $job;
        } finally {
            PWE_Multilang_Resend_Job_Lock::release($token);
        }
    }

    private static function count_remaining(array $job, string $lock_token): array
    {
        $remaining = 0;

        foreach ((array) ($job['form_ids'] ?? []) as $form_id) {
            if (!PWE_Multilang_Resend_Job_Lock::refresh($lock_token)) {
                return [
                    'remaining' => $remaining,
                    'form_id'   => (int) $form_id,
                    'error'     => 'Utracono blokadę procesu podczas przeliczania wpisów. Spróbuj ponownie.',
                ];
            }

            $form = PWE_Multilang_GF_Form_Repository::get((int) $form_id);

            if ($form === null) {
                continue;
            }

            $notifications = PWE_Multilang_Resend_Gravity::get_resend_notifications($form);

            if (!$notifications) {
                continue;
            }

            $stats = PWE_Multilang_Resend_Gravity::scan_form_stats(
                $form,
                $notifications,
                (bool) ($job['include_all_entries'] ?? false),
                (int) ($job['min_age_days'] ?? PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS),
                true
            );

            if ((string) ($stats['error'] ?? '') !== '') {
                return [
                    'remaining' => $remaining,
                    'form_id'   => (int) $form_id,
                    'error'     => (string) $stats['error'],
                ];
            }

            $remaining += (int) ($stats['matched_count'] ?? 0);
        }

        return ['remaining' => $remaining, 'form_id' => 0, 'error' => ''];
    }

    private static function completed_count(array $job): int
    {
        return max(0, (int) ($job['sent'] ?? 0))
            + max(0, (int) ($job['failed'] ?? 0))
            + max(0, (int) ($job['skipped'] ?? 0));
    }

    private static function normalise(array $job): array
    {
        $job['schema_version'] = max(0, (int) ($job['schema_version'] ?? 0));
        $job['form_ids'] = array_values(array_unique(array_filter(array_map(
            'absint',
            (array) ($job['form_ids'] ?? [])
        ))));
        $job['cursor'] = self::normalise_cursor($job['cursor'] ?? null);
        $job['sent'] = max(0, (int) ($job['sent'] ?? 0));
        $job['failed'] = max(0, (int) ($job['failed'] ?? 0));
        $job['skipped'] = max(0, (int) ($job['skipped'] ?? 0));
        $completed = self::completed_count($job);

        if (!array_key_exists('total', $job)) {
            $job['total'] = $completed;
            $job['total_migration_pending'] = true;
        } else {
            $job['total'] = max($completed, (int) $job['total']);
        }

        $status = (string) ($job['status'] ?? 'paused');
        $job['status'] = in_array($status, self::STATUSES, true) ? $status : 'paused';
        $job['batch_size'] = max(1, min(500, (int) ($job['batch_size'] ?? PWE_Multilang_Resend::DEFAULT_BATCH_SIZE)));
        $job['delay'] = max(3, min(60, (int) ($job['delay'] ?? PWE_Multilang_Resend::DEFAULT_DELAY)));
        $job['min_age_days'] = max(1, min(
            3650,
            (int) ($job['min_age_days'] ?? PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS)
        ));
        $job['include_all_entries'] = !empty($job['include_all_entries']);
        $job['log'] = is_array($job['log'] ?? null) ? array_slice($job['log'], -100) : [];
        $job['log_total'] = max(count($job['log']), (int) ($job['log_total'] ?? 0));

        return $job;
    }

    private static function normalise_cursor($cursor): array
    {
        $cursor = array_merge(self::empty_cursor(), is_array($cursor) ? $cursor : []);

        foreach (array_keys(self::empty_cursor()) as $key) {
            $cursor[$key] = max(0, (int) $cursor[$key]);
        }

        return $cursor;
    }
}
