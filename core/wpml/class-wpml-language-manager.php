<?php

declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

final class PWE_Multilang_WPML_Language_Manager
{
    public const BASE_LANGUAGES = ['pl', 'en'];

    public static function desired_languages(array $additional): array
    {
        $codes = self::BASE_LANGUAGES;
        foreach ($additional as $code) {
            if (is_string($code) && trim($code) !== '') {
                $codes[] = strtolower(trim($code));
            }
        }
        return array_values(array_unique($codes));
    }

    /** Read current WPML state without changing language activation. */
    public static function active_languages(): array
    {
        global $sitepress;
        if (!isset($sitepress) || !is_object($sitepress) || !method_exists($sitepress, 'get_active_languages')) {
            throw new RuntimeException('WPML jest niedostępny.');
        }
        $languages = $sitepress->get_active_languages(true);
        if (!is_array($languages)) {
            throw new RuntimeException('WPML nie zwrócił listy aktywnych języków.');
        }
        return $languages;
    }

    public static function sync_active_languages(array $desired_languages): array
    {
        global $wpdb, $sitepress;

        $desired = self::desired_languages($desired_languages);
        $report = ['desired' => $desired, 'before' => [], 'activated' => [], 'deactivated' => [],
            'already_active' => [], 'invalid' => [], 'errors' => [], 'after' => []];
        if (!isset($sitepress) || !is_object($sitepress) || !method_exists($sitepress, 'get_active_languages')) {
            $report['errors'][] = 'WPML jest niedostępny.';
            return $report;
        }
        $before = $sitepress->get_active_languages(true);
        if (!is_array($before)) {
            $report['errors'][] = 'WPML nie zwrócił listy aktywnych języków.';
            return $report;
        }
        $report['before'] = array_keys($before);
        $report['after'] = $report['before'];
        $catalog = $wpdb->get_col("SELECT code FROM {$wpdb->prefix}icl_languages");
        if ($wpdb->last_error !== '' || !is_array($catalog)) {
            $report['errors'][] = 'Odczyt katalogu WPML: ' . ($wpdb->last_error ?: 'brak danych');
            return $report;
        }
        $report['invalid'] = array_values(array_diff($desired, $catalog));
        $valid = array_values(array_intersect($desired, $catalog));
        $report['already_active'] = array_values(array_intersect($valid, $report['before']));
        if (array_diff(self::BASE_LANGUAGES, $catalog)) {
            $report['errors'][] = 'W katalogu WPML brakuje języka bazowego. Nie zmieniono aktywnych języków.';
            return $report;
        }
        $activate = array_diff($valid, $report['before']);
        $deactivate = array_diff($report['before'], $desired, self::BASE_LANGUAGES);
        foreach ([1 => $activate, 0 => $deactivate] as $active => $codes) {
            foreach ($codes as $code) {
                $updated = $wpdb->update($wpdb->prefix . 'icl_languages', ['active' => $active],
                    ['code' => $code], ['%d'], ['%s']);
                if ($updated === false || $wpdb->last_error !== '') {
                    $report['errors'][] = $code . ': ' . ($wpdb->last_error ?: 'Błąd zapisu aktywności języka.');
                }
            }
            // Do not deactivate languages if restoring base/desired languages failed.
            if ($report['errors']) { break; }
        }
        // Refresh every language context, not only the current language's cache entry.
        // Also run after partial writes so WPML reflects the actual database state.
        if ($activate || $deactivate) {
            $sitepress->get_language_name_cache()->clear();
            icl_cache_clear();
            (new WPML_WP_Cache('ls_languages'))->flush_group_cache();
            wpml_reload_active_languages_setting(true);
        }
        $after = $sitepress->get_active_languages(true);
        if (!is_array($after)) {
            $report['errors'][] = 'WPML nie potwierdził końcowego stanu języków.';
            return $report;
        }
        $report['after'] = array_keys($after);
        $report['activated'] = array_values(array_diff($report['after'], $report['before']));
        $report['deactivated'] = array_values(array_diff($report['before'], $report['after']));
        if (array_diff($valid, $report['after']) || array_diff($report['after'], $valid)) {
            $report['errors'][] = 'Aktywne języki WPML nie odpowiadają dostępnej liście docelowej.';
        }
        if ($report['activated'] || $report['deactivated']) {
            do_action('wpml_update_active_languages', $before);
        }
        return $report;
    }
}
