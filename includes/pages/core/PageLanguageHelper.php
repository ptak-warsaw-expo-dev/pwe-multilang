<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Language_Helper
{
    public static function get_active_languages(): array
    {
        $active_languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);

        return is_array($active_languages) ? $active_languages : [];
    }

    public static function get_json_language_codes(array $pages): array
    {
        $json_language_codes = [];

        foreach ($pages as $translations) {
            if (!is_array($translations)) {
                continue;
            }

            foreach ($translations as $lang_code => $data) {
                $json_language_codes[$lang_code] = true;
            }
        }

        $json_language_codes = array_keys($json_language_codes);
        sort($json_language_codes);

        return $json_language_codes;
    }

    public static function get_allowed_language_codes(array $pages): array
    {
        $skip_languages = PWE_Multilang_Page_Config::get_skipped_languages();
        $active_languages = self::get_active_languages();
        $active_language_codes = array_keys($active_languages);
        $allowed_language_codes = [];

        if (!isset($pages['home']) || !is_array($pages['home'])) {
            return [];
        }

        foreach ($pages['home'] as $home_lang_code => $home_data) {
            if (in_array($home_lang_code, $skip_languages, true)) {
                continue;
            }

            if (!in_array($home_lang_code, $active_language_codes, true)) {
                continue;
            }

            $allowed_language_codes[] = $home_lang_code;
        }

        return $allowed_language_codes;
    }

    public static function get_language_label(string $lang_code, array $active_languages): string
    {
        $language_names = PWE_Multilang_Page_Config::get_language_names();
        $label = $language_names[$lang_code] ?? $lang_code;

        if (!empty($active_languages[$lang_code]['translated_name'])) {
            $label = $active_languages[$lang_code]['translated_name'];
        } elseif (!empty($active_languages[$lang_code]['native_name'])) {
            $label = $active_languages[$lang_code]['native_name'];
        } elseif (!empty($active_languages[$lang_code]['display_name'])) {
            $label = $active_languages[$lang_code]['display_name'];
        }

        return $label;
    }

    public static function get_flag_url(string $lang_code, array $active_languages): string
    {
        if (!empty($active_languages[$lang_code]['country_flag_url'])) {
            return (string) $active_languages[$lang_code]['country_flag_url'];
        }

        if (defined('ICL_PLUGIN_URL')) {
            return ICL_PLUGIN_URL . '/res/flags/' . sanitize_key($lang_code) . '.png';
        }

        return '';
    }
}
