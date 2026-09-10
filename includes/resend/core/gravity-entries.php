<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Resend_Gravity
{
    /** @var array<string, array> */
    private static array $stats_cache = [];

    public static function get_resend_notifications(array $form): array
    {
        $result = [];

        foreach (($form['notifications'] ?? []) as $notification) {
            $name = mb_strtolower((string) ($notification['name'] ?? ''));

            // "reserd" is a legacy typo intentionally supported.
            if (strpos($name, 'resend') === false && strpos($name, 'reserd') === false) {
                continue;
            }

            $result[] = $notification;
        }

        return $result;
    }

    public static function get_entries_page(
        int $form_id,
        bool $include_all_entries,
        int $offset,
        int $page_size,
        int $min_age_days = PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS
    ): array {
        $search_criteria = ['status' => 'active'];

        if (!$include_all_entries) {
            $days = max(1, min(3650, $min_age_days));
            $search_criteria['end_date'] = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        }

        $entries = GFAPI::get_entries(
            $form_id,
            $search_criteria,
            ['key' => 'id', 'direction' => 'ASC'],
            ['offset' => max(0, $offset), 'page_size' => max(1, $page_size)]
        );

        if (is_wp_error($entries)) {
            return [
                'entries' => [],
                'error'   => self::entries_error_message($form_id, $entries->get_error_message()),
            ];
        }

        if (!is_array($entries)) {
            return [
                'entries' => [],
                'error'   => self::entries_error_message(
                    $form_id,
                    'Gravity Forms zwrócił nieprawidłowy format odpowiedzi.'
                ),
            ];
        }

        return ['entries' => array_values($entries), 'error' => ''];
    }

    public static function scan_form_stats(
        array $form,
        array $notifications,
        bool $include_all_entries,
        int $min_age_days = PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS,
        bool $only_unsent = false
    ): array {
        $notification_keys = array_map(
            static fn(array $notification): string => (string) ($notification['id'] ?? $notification['name'] ?? ''),
            $notifications
        );
        $cache_key = md5(wp_json_encode([
            (int) ($form['id'] ?? 0),
            $notification_keys,
            $include_all_entries,
            $min_age_days,
            $only_unsent,
        ]));

        if (isset(self::$stats_cache[$cache_key])) {
            return self::$stats_cache[$cache_key];
        }

        $form_id = (int) ($form['id'] ?? 0);
        $offset = 0;
        $entries_count = 0;
        $matched_count = 0;
        $notification_lang_counts = [];

        do {
            $page = self::get_entries_page(
                $form_id,
                $include_all_entries,
                $offset,
                PWE_Multilang_Resend::PAGE_SIZE,
                $min_age_days
            );

            if ((string) ($page['error'] ?? '') !== '') {
                return self::$stats_cache[$cache_key] = [
                    'entries_count'           => $entries_count,
                    'matched_count'           => $matched_count,
                    'notification_lang_counts' => $notification_lang_counts,
                    'error'                   => (string) $page['error'],
                ];
            }

            $entries = (array) ($page['entries'] ?? []);

            if (!$entries) {
                break;
            }

            foreach ($entries as $entry) {
                $entries_count++;

                foreach ($notifications as $notification) {
                    if (!PWE_Multilang_Resend_Matcher::is_entry_matching_notification($form, $entry, $notification)) {
                        continue;
                    }

                    if ($only_unsent && PWE_Multilang_Resend_Matcher::is_already_processed($entry, $notification)) {
                        continue;
                    }

                    $matched_count++;
                    $group_key = self::notification_group_key((string) ($notification['name'] ?? ''));
                    $language = PWE_Multilang_Resend_Matcher::extract_lang_from_notification_name(
                        (string) ($notification['name'] ?? '')
                    );
                    $language_key = $language !== '' ? $language : '__none__';
                    $notification_lang_counts[$group_key][$language_key] =
                        (int) ($notification_lang_counts[$group_key][$language_key] ?? 0) + 1;
                }
            }

            $count = count($entries);
            $offset += $count;
        } while ($count === PWE_Multilang_Resend::PAGE_SIZE);

        return self::$stats_cache[$cache_key] = [
            'entries_count' => $entries_count,
            'matched_count' => $matched_count,
            'notification_lang_counts' => $notification_lang_counts,
            'error' => '',
        ];
    }

    private static function entries_error_message(int $form_id, string $details): string
    {
        $details = trim($details);

        return sprintf(
            'Nie udało się pobrać wpisów formularza ID %d%s',
            $form_id,
            $details !== '' ? ': ' . $details : '.'
        );
    }

    private static function notification_group_key(string $name): string
    {
        return mb_strtolower(PWE_Multilang_GF_Notification_Name::base_title($name));
    }
}
