<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small boundary around the WPML hooks used by page-related modules.
 *
 * Keeping those calls in one place makes language switching recoverable and
 * prevents Pages and Replace Content from implementing slightly different
 * WPML lookups.
 */
final class PWE_Multilang_Wpml_Gateway
{
    public static function is_active(): bool
    {
        return defined('ICL_SITEPRESS_VERSION');
    }

    public static function get_active_languages(): array
    {
        $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);

        return is_array($languages) ? $languages : [];
    }

    public static function get_element_type(string $post_type = 'page'): string
    {
        return (string) apply_filters('wpml_element_type', $post_type);
    }

    public static function get_trid(int $post_id, string $element_type): int
    {
        return (int) apply_filters('wpml_element_trid', null, $post_id, $element_type);
    }

    public static function get_translated_object_id(int $post_id, string $post_type, string $language_code): int
    {
        return (int) apply_filters('wpml_object_id', $post_id, $post_type, false, $language_code);
    }

    public static function get_element_translations(int $trid, string $element_type): array
    {
        if ($trid <= 0) {
            return [];
        }

        $translations = apply_filters('wpml_get_element_translations', [], $trid, $element_type);

        return is_array($translations) ? $translations : [];
    }

    public static function get_element_language(int $post_id, string $element_type): string
    {
        $details = apply_filters('wpml_element_language_details', null, [
            'element_id' => $post_id,
            'element_type' => $element_type,
        ]);

        if (is_object($details)) {
            return (string) ($details->language_code ?? '');
        }

        if (is_array($details)) {
            return (string) ($details['language_code'] ?? '');
        }

        return '';
    }

    public static function set_element_language_details(
        int $post_id,
        string $element_type,
        int $trid,
        string $language_code,
        string $source_language_code = 'en'
    ): void {
        do_action('wpml_set_element_language_details', [
            'element_id' => $post_id,
            'element_type' => $element_type,
            'trid' => $trid,
            'language_code' => $language_code,
            'source_language_code' => $source_language_code,
        ]);
    }

    /**
     * Executes a callback in the requested WPML language and always restores
     * the language which was active before the operation.
     *
     * @return mixed
     */
    public static function with_language(string $language_code, callable $callback)
    {
        $previous_language = (string) apply_filters('wpml_current_language', null);

        try {
            do_action('wpml_switch_language', $language_code);

            return $callback();
        } finally {
            do_action('wpml_switch_language', $previous_language !== '' ? $previous_language : null);
        }
    }
}
