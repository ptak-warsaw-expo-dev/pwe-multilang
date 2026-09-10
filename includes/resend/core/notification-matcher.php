<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Resend_Matcher
{
    public static function get_entry_lang(array $form, array $entry): string
    {
        $value = PWE_Multilang_GF_Field_Locator::value($form, $entry, ['lang']);

        return sanitize_key(strtolower($value));
    }

    public static function get_entry_location(array $form, array $entry): string
    {
        return mb_strtolower(PWE_Multilang_GF_Field_Locator::value($form, $entry, ['location']));
    }

    public static function entry_has_platyna(array $form, array $entry): bool
    {
        return strpos(self::get_entry_location($form, $entry), 'platyna') !== false;
    }

    public static function notification_is_platyna(array $notification): bool
    {
        return strpos(mb_strtolower((string) ($notification['name'] ?? '')), 'platyna') !== false;
    }

    public static function extract_lang_from_notification_name(string $name): string
    {
        return PWE_Multilang_GF_Notification_Name::strict_language($name);
    }

    public static function is_entry_matching_notification(
        array $form,
        array $entry,
        array $notification
    ): bool {
        $notification_lang = self::extract_lang_from_notification_name((string) ($notification['name'] ?? ''));
        $entry_lang = self::get_entry_lang($form, $entry);

        if ($notification_lang !== '' && ($entry_lang === '' || $notification_lang !== $entry_lang)) {
            return false;
        }

        $entry_is_platyna = self::entry_has_platyna($form, $entry);
        $notification_is_platyna = self::notification_is_platyna($notification);

        return $notification_is_platyna ? $entry_is_platyna : !$entry_is_platyna;
    }

    public static function notification_should_send(
        array $notification,
        array $form,
        array $entry
    ): bool {
        if (!self::is_entry_matching_notification($form, $entry, $notification)) {
            return false;
        }

        $logic = PWE_Multilang_GF_Conditional_Logic::compile(
            $notification['conditionalLogic'] ?? null,
            'notification',
            $notification
        );

        return PWE_Multilang_GF_Conditional_Logic::evaluate($logic, $form, $entry);
    }

    public static function entry_has_valid_email(array $form, array $entry): bool
    {
        $email = PWE_Multilang_GF_Field_Locator::value(
            $form,
            $entry,
            ['email', 'pwe_email'],
            ['email']
        );

        return $email !== '' && is_email($email);
    }

    public static function sent_meta_key(array $notification): string
    {
        $key = (string) ($notification['id'] ?? $notification['name'] ?? '');

        return '_pwe_multilang_resend_sent_' . md5($key);
    }

    public static function skipped_meta_key(array $notification): string
    {
        return self::sent_meta_key($notification) . '_skipped';
    }

    public static function is_already_processed(array $entry, array $notification): bool
    {
        $entry_id = (int) ($entry['id'] ?? 0);

        if ($entry_id < 1) {
            return true;
        }

        return (bool) gform_get_meta($entry_id, self::sent_meta_key($notification))
            || (bool) gform_get_meta($entry_id, self::skipped_meta_key($notification));
    }
}

