<?php

if (!defined('ABSPATH')) { exit; }

/** The single source consumed by Multilang Sync. */
function pwe_multilang_get_additional_languages(): array
{
    return PWE_Multilang_Additional_Languages::selected();
}

final class PWE_Multilang_Additional_Languages
{
    public const SHORTCODE_OPTION_KEY = 'pwe_multilang_shortcode_languages';

    /** Read and validate the complete list; never accept a partial malformed payload. */
    public static function from_shortcode(): array
    {
        if (!shortcode_exists('pwe_available_languages')) { return []; }
        $raw = trim(do_shortcode('[pwe_available_languages]'));
        if ($raw === '') { return []; }
        $decoded = json_decode($raw);
        if (!is_array($decoded)) { return []; }
        $codes = [];
        $available = array_unique(array_merge(self::available(), PWE_Multilang_Language_Catalog::codes()));
        foreach ($decoded as $code) {
            if (!is_string($code)) { return []; }
            $code = strtolower(trim($code));
            if (!in_array($code, $available, true)) { return []; }
            $codes[] = $code;
        }
        $codes = array_values(array_unique($codes));
        sort($codes);
        return $codes;
    }

    public static function shortcode_changed(array $codes): bool
    {
        return $codes !== [] && get_option(self::SHORTCODE_OPTION_KEY, null) !== $codes;
    }

    public const OPTION_KEY = 'pwe_multilang_additional_languages';

    /** Languages declared anywhere in the page translation map. */
    public static function available(): array
    {
        $path = PWE_MULTILANG_PATH . 'website-translation.json';
        $json = is_readable($path) ? file_get_contents($path) : false;
        $pages = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($pages) || $pages === []) {
            throw new RuntimeException('Nie można odczytać języków z website-translation.json.');
        }
        $codes = [];
        foreach ($pages as $translations) {
            if (!is_array($translations)) { continue; }
            foreach ($translations as $code => $translation) {
                if (is_string($code) && is_array($translation) && preg_match('/^[a-z]{2,3}(?:-[a-z0-9]+)*$/i', $code)) {
                    $codes[] = strtolower($code);
                }
            }
        }
        $codes = array_values(array_unique($codes));
        if ($codes === []) { throw new RuntimeException('Mapa stron nie zawiera poprawnych kodów języków.'); }
        sort($codes);
        return $codes;
    }

    public static function selected(): array
    {
        $saved = get_option(self::OPTION_KEY, null);
        // Until the first save, preserve the site's current WPML selection.
        if ($saved === null) {
            return array_values(array_diff(
                PWE_Multilang_Language_Catalog::active_wpml(),
                PWE_Multilang_WPML_Language_Manager::BASE_LANGUAGES
            ));
        }
        return self::normalize(is_array($saved) ? $saved : []);
    }

    public static function save(array $incoming): array
    {
        $selected = self::normalize($incoming);
        update_option(self::OPTION_KEY, $selected, false);
        if (get_option(self::OPTION_KEY, null) !== $selected) {
            throw new RuntimeException('Nie udało się zapisać wyboru języków.');
        }
        return $selected;
    }

    private static function normalize(array $incoming): array
    {
        $codes = [];
        foreach ($incoming as $code) {
            if (is_string($code)) { $codes[] = strtolower(trim($code)); }
        }
        $codes = array_values(array_diff(
            array_intersect(array_unique($codes), self::available()),
            PWE_Multilang_WPML_Language_Manager::BASE_LANGUAGES
        ));
        sort($codes);
        return $codes;
    }
}
