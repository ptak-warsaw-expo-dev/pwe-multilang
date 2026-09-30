<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Tests_Notifications
{
    public static function send(array $items, string $test_email): int
    {
        return (int) self::send_report($items, $test_email)['sent'];
    }

    public static function send_report(array $items, string $test_email): array
    {
        $report = ['sent' => 0, 'failed' => 0, 'errors' => []];

        if (!class_exists('GFAPI') || !class_exists('GFCommon') || !is_email($test_email)) {
            $report['failed'] = 1;
            $report['errors'][] = 'Gravity Forms jest niedostępne lub adres e-mail jest nieprawidłowy.';
            return $report;
        }

        foreach (array_values(array_unique(array_map('strval', $items))) as $item) {
            [$form_id, $notification_id] = array_pad(
                explode('|', sanitize_text_field($item), 2),
                2,
                ''
            );
            $form = PWE_Multilang_Tests_Gravity::get_managed_form(absint($form_id));

            if ($form === null || empty($form['notifications'][$notification_id])) {
                $report['failed']++;
                $report['errors'][] = 'Pominięto niezarządzany formularz lub nieznane powiadomienie.';
                continue;
            }

            $notification = $form['notifications'][$notification_id];
            $entry = PWE_Multilang_Tests_Entries::build_entry_for_notification(
                $form,
                $test_email,
                $notification
            );

            if (!$entry) {
                $report['failed']++;
                $report['errors'][] = 'Nie udało się utworzyć wpisu testowego.';
                continue;
            }

            try {
                $notification['toType'] = 'email';
                $notification['to'] = $test_email;
                $notification['isActive'] = true;
                $error = self::deliver($notification, $form, $entry);

                if ($error === '') {
                    $report['sent']++;
                } else {
                    $report['failed']++;
                    $report['errors'][] = $error;
                }
            } finally {
                if (!empty($entry['id'])) {
                    GFAPI::delete_entry((int) $entry['id']);
                }
            }
        }

        return $report;
    }

    public static function send_by_filters(
        string $test_email,
        string $form_filter = 'ALL',
        string $lang_filter = 'ALL',
        string $notification_filter = 'ALL'
    ): int {
        return (int) self::send_by_filters_report(
            $test_email,
            $form_filter,
            $lang_filter,
            $notification_filter
        )['sent'];
    }

    public static function send_by_filters_report(
        string $test_email,
        string $form_filter = 'ALL',
        string $lang_filter = 'ALL',
        string $notification_filter = 'ALL'
    ): array {
        $items = [];
        $form_filter = strtoupper($form_filter) === 'ALL' ? 'ALL' : (string) absint($form_filter);
        $lang_filter = strtoupper($lang_filter);
        $notification_filter = $notification_filter === '' ? 'ALL' : $notification_filter;

        foreach (PWE_Multilang_Tests_Gravity::get_managed_forms() as $form) {
            $form_id = (string) absint($form['id'] ?? 0);

            if ($form_id === '0' || ($form_filter !== 'ALL' && $form_filter !== $form_id)) {
                continue;
            }

            foreach (($form['notifications'] ?? []) as $notification_id => $notification) {
                $name = (string) ($notification['name'] ?? $notification_id);
                $language = self::detect_notification_lang($form, $notification);
                $base_title = self::detect_base_title($name, $language);

                if ($lang_filter !== 'ALL' && strtoupper($language ?: 'OTHER') !== $lang_filter) {
                    continue;
                }

                if ($notification_filter !== 'ALL' && $base_title !== $notification_filter) {
                    continue;
                }

                $items[] = $form_id . '|' . $notification_id;
            }
        }

        return self::send_report($items, $test_email);
    }

    public static function detect_lang(string $name, string $template = '', string $message = ''): string
    {
        return PWE_Multilang_GF_Notification_Name::detect_language(
            $name,
            PWE_Multilang_Tests_Gravity::get_notification_langs(),
            $template,
            $message
        );
    }

    public static function detect_notification_lang(array $form, array $notification): string
    {
        $detected = self::detect_lang(
            (string) ($notification['name'] ?? ''),
            (string) ($notification['template'] ?? ''),
            (string) ($notification['message'] ?? '')
        );

        if ($detected !== '') {
            return $detected;
        }

        if (!empty($form['_pwe_separate_lang'])) {
            return strtoupper((string) $form['_pwe_separate_lang']);
        }

        $group_languages = $form['_pwe_group_langs'] ?? [];

        return is_array($group_languages) && count($group_languages) === 1
            ? strtoupper((string) reset($group_languages))
            : '';
    }

    public static function detect_base_title(string $name, string $lang = ''): string
    {
        return PWE_Multilang_GF_Notification_Name::base_title($name, $lang);
    }

    private static function deliver(array $notification, array $form, array $entry): string
    {
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
            return $error->getMessage();
        } finally {
            remove_action('wp_mail_failed', $capture);
        }

        return $mail_error instanceof WP_Error ? $mail_error->get_error_message() : '';
    }
}
