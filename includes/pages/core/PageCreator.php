<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Creator
{
    public static function create_missing_wpml_pages_from_json(int $event_year = 0): array
    {
        $event_year = self::normalize_event_year($event_year);

        if (!defined('ICL_SITEPRESS_VERSION')) {
            return [
                'status' => 'error',
                'message' => 'WPML nie jest aktywny.',
            ];
        }

        $pages = PWE_Multilang_Page_Json_Repository::get_pages();
        $skip_languages = PWE_Multilang_Page_Config::get_skipped_languages();
        $allowed_language_codes = PWE_Multilang_Page_Language_Helper::get_allowed_language_codes($pages);

        $summary = [
            'event_year' => $event_year,
            'allowed_languages_by_home' => $allowed_language_codes,
            'created' => [],
            'skipped_existing' => [],
            'skipped_no_en_source' => [],
            'skipped_invalid_data' => [],
            'errors' => [],
        ];

        foreach ($pages as $page_key => $translations) {
            if (!is_array($translations) || empty($translations['en']['url'])) {
                $summary['skipped_invalid_data'][] = $page_key . ' — brak EN URL';
                continue;
            }

            $en_page = self::get_source_page($translations['en']['url']);

            if (!$en_page) {
                $summary['skipped_no_en_source'][] = $page_key . ' — nie znaleziono strony EN: ' . $translations['en']['url'];
                continue;
            }

            $element_type = apply_filters('wpml_element_type', 'page');
            $trid = apply_filters('wpml_element_trid', null, $en_page->ID, $element_type);

            if (!$trid) {
                $summary['errors'][] = $page_key . ' — nie udało się pobrać TRID strony EN';
                continue;
            }

            self::create_page_translations(
                (string) $page_key,
                $translations,
                $en_page,
                $element_type,
                $trid,
                $skip_languages,
                $allowed_language_codes,
                $summary,
                $event_year
            );
        }

        flush_rewrite_rules(false);

        return $summary;
    }

    private static function normalize_event_year(int $event_year): int
    {
        if ($event_year < 2000 || $event_year > 2100) {
            return (int) gmdate('Y');
        }

        return $event_year;
    }

    private static function get_source_page(string $source_url): ?WP_Post
    {
        if ($source_url === '/') {
            return PWE_Multilang_Page_Finder::get_home_page_in_lang('en');
        }

        return PWE_Multilang_Page_Finder::find_page_by_url_and_lang($source_url, 'en');
    }

    private static function create_page_translations(
        string $page_key,
        array $translations,
        WP_Post $en_page,
        string $element_type,
        int $trid,
        array $skip_languages,
        array $allowed_language_codes,
        array &$summary,
        int $event_year
    ): void {
        foreach ($translations as $lang_code => $data) {
            if (in_array($lang_code, $skip_languages, true)) {
                continue;
            }

            if (!in_array($lang_code, $allowed_language_codes, true)) {
                continue;
            }

            if (!is_array($data) || empty($data['label']) || !isset($data['url'])) {
                $summary['skipped_invalid_data'][] = $page_key . ' / ' . $lang_code . ' — brak label lub url';
                continue;
            }

            $existing_page = self::get_existing_page((string) $data['url'], (string) $lang_code);

            if ($existing_page) {
                $summary['skipped_existing'][] = $page_key . ' / ' . $lang_code . ' — istnieje: ' . $data['url'];
                continue;
            }

            $slug = self::get_slug((string) $data['url'], (string) $lang_code);

            if ($slug === '' && $data['url'] !== '/') {
                $summary['errors'][] = $page_key . ' / ' . $lang_code . ' — pusty slug po sanitizacji: ' . $data['url'];
                continue;
            }

            $new_page_id = self::insert_translation_page($en_page, $data, $slug, (string) $lang_code, $event_year);

            if (is_wp_error($new_page_id)) {
                $summary['errors'][] = $page_key . ' / ' . $lang_code . ' — wp_insert_post: ' . $new_page_id->get_error_message();
                continue;
            }

            self::connect_wpml_translation((int) $new_page_id, $element_type, $trid, (string) $lang_code);
            self::copy_source_data($en_page->ID, (int) $new_page_id, $page_key, (string) $lang_code, (string) $data['url']);

            $summary['created'][] = $page_key . ' / ' . $lang_code . ' — utworzono: ' . $data['url'];
        }
    }

    private static function get_existing_page(string $url, string $lang_code): ?WP_Post
    {
        if ($url === '/') {
            return PWE_Multilang_Page_Finder::get_home_page_in_lang($lang_code);
        }

        return PWE_Multilang_Page_Finder::find_page_by_url_and_lang($url, $lang_code);
    }

    private static function get_slug(string $url, string $lang_code): string
    {
        return $url === '/'
            ? 'home-' . sanitize_key($lang_code)
            : PWE_Multilang_Page_Slug::from_url($url);
    }

    private static function insert_translation_page(WP_Post $en_page, array $data, string $slug, string $lang_code, int $event_year)
    {
        $parent_id = PWE_Multilang_Page_Finder::get_translated_parent_id((int) $en_page->post_parent, $lang_code);
        $post_content = PWE_Multilang_Page_Content_Transformer::transform_for_language(
            (string) $en_page->post_content,
            $lang_code,
            $event_year
        );

        return wp_insert_post([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'post_title'     => sanitize_text_field((string) $data['label']),
            'post_name'      => $slug,
            'post_content'   => wp_slash($post_content),
            'post_excerpt'   => wp_slash($en_page->post_excerpt),
            'post_parent'    => $parent_id !== null ? $parent_id : 0,
            'comment_status' => $en_page->comment_status,
            'ping_status'    => $en_page->ping_status,
            'menu_order'     => $en_page->menu_order,
        ], true);
    }

    private static function connect_wpml_translation(int $new_page_id, string $element_type, int $trid, string $lang_code): void
    {
        do_action('wpml_set_element_language_details', [
            'element_id'           => $new_page_id,
            'element_type'         => $element_type,
            'trid'                 => $trid,
            'language_code'        => $lang_code,
            'source_language_code' => 'en',
        ]);
    }

    private static function copy_source_data(int $source_id, int $target_id, string $page_key, string $lang_code, string $url): void
    {
        PWE_Multilang_Page_Meta_Copier::copy_basic_page_meta($source_id, $target_id);

        PWE_Multilang_Page_Meta_Copier::copy_wp_rocket_page_meta($source_id, $target_id);

        PWE_Multilang_Page_Meta_Copier::copy_wp_rocket_cache_reject_uri($source_id, $target_id);

        update_post_meta($target_id, '_pwe_json_page_key', sanitize_key($page_key));
        update_post_meta($target_id, '_pwe_json_language', sanitize_key($lang_code));
        update_post_meta($target_id, '_pwe_json_url', esc_url_raw($url));
        update_post_meta($target_id, '_pwe_created_as_home_translation', $url === '/' ? '1' : '0');

        clean_post_cache($target_id);
    }
}
