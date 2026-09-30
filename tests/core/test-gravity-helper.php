<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Tests_Gravity
{
    public static function get_managed_forms(): array
    {
        return PWE_Multilang_GF_Form_Repository::managed();
    }

    public static function get_managed_form(int $form_id): ?array
    {
        $form = PWE_Multilang_GF_Form_Repository::get($form_id);

        return $form !== null && PWE_Multilang_GF_Form_Repository::is_managed($form)
            ? $form
            : null;
    }

    public static function get_active_langs(): array
    {
        $languages = PWE_Multilang_Language_Catalog::active_wpml(
            PWE_Multilang_Tests::EXCLUDED_LANGS,
            PWE_Multilang_Tests::FALLBACK_LANGS
        );

        if (!$languages) {
            $languages = PWE_Multilang_Tests::FALLBACK_LANGS;
        }

        $excluded = array_map('strtolower', PWE_Multilang_Tests::EXCLUDED_LANGS);

        return array_values(array_unique(array_filter(
            array_map(
                static fn($language): string => strtolower(sanitize_key((string) $language)),
                $languages
            ),
            static fn(string $language): bool => $language !== ''
                && !in_array($language, $excluded, true)
        )));
    }

    /**
     * Notifications can exist in every language supported by Multilang. The
     * PL/EN exclusion applies only to the bulk test-entry generator.
     *
     * @return string[]
     */
    public static function get_notification_langs(): array
    {
        return PWE_Multilang_Language_Catalog::codes();
    }

    public static function redirect(string $message): void
    {
        wp_safe_redirect(add_query_arg([
            'page'       => PWE_Multilang_Tests::PAGE_SLUG,
            'pwe_status' => $message,
        ], admin_url('admin.php')));
        exit;
    }

    public static function get_choice_value(object $field, int $index, array $preferred = []): string
    {
        $choices = $field->choices ?? [];

        if (!is_array($choices) || !$choices) {
            return (string) ($preferred[0] ?? 'test');
        }

        foreach ($preferred as $preferred_value) {
            foreach ($choices as $choice) {
                $value = (string) ($choice['value'] ?? $choice['text'] ?? '');

                if (strcasecmp($value, (string) $preferred_value) === 0) {
                    return $value;
                }
            }
        }

        $choice = $choices[($index - 1) % count($choices)];

        return (string) ($choice['value'] ?? $choice['text'] ?? 'test');
    }

    public static function is_lang_field(string $admin_label, object $field): bool
    {
        $names = ['pwe_lang', 'lang', 'language', 'wpml_lang', 'język', 'jezyk'];

        return in_array(strtolower($admin_label), $names, true)
            || in_array(strtolower((string) ($field->label ?? '')), $names, true);
    }

    public static function update_sync_state(): array
    {
        if (class_exists('PWE_Multilang_Form_Update_Sync')
            && method_exists('PWE_Multilang_Form_Update_Sync', 'get_state')) {
            return PWE_Multilang_Form_Update_Sync::get_state();
        }

        return [
            'current_plugin_version' => defined('PWE_MULTILANG_VERSION') ? PWE_MULTILANG_VERSION : '',
            'saved_plugin_version'   => '',
            'template_versions'      => [],
            'last_result'            => [],
            'retry_after'            => 0,
            'locked'                 => false,
        ];
    }
}
