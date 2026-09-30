<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared page lookup used by Pages and Replace Content.
 */
final class PWE_Multilang_Page_Locator
{
    public static function get_home_page_in_language(string $language_code): ?WP_Post
    {
        $front_id = (int) get_option('page_on_front');

        if ($front_id <= 0) {
            return null;
        }

        $translated_id = PWE_Multilang_Wpml_Gateway::get_translated_object_id(
            $front_id,
            'page',
            $language_code
        );

        if ($translated_id <= 0) {
            return null;
        }

        return self::as_page($translated_id);
    }

    public static function find_by_url_and_language(string $url, string $language_code): ?WP_Post
    {
        global $wpdb;

        $slug = PWE_Multilang_Page_Slug::from_url($url);

        if ($url === '/' || $slug === '') {
            return self::get_home_page_in_language($language_code);
        }

        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->prefix}icl_translations t
                    ON t.element_id = p.ID
                WHERE p.post_type = 'page'
                  AND p.post_name = %s
                  AND t.element_type = %s
                  AND t.language_code = %s
                  AND p.post_status NOT IN ('trash', 'auto-draft')
                LIMIT 1
                ",
                $slug,
                'post_page',
                $language_code
            )
        );

        return !empty($post_ids[0]) ? self::as_page((int) $post_ids[0]) : null;
    }

    public static function find_by_url(string $url): ?WP_Post
    {
        $path = wp_parse_url($url, PHP_URL_PATH);

        if (!is_string($path)) {
            return null;
        }

        $page_path = trim($path, '/');

        if ($page_path === '') {
            $front_page_id = (int) get_option('page_on_front');

            return $front_page_id > 0 ? self::as_page($front_page_id) : null;
        }

        $page_id = (int) url_to_postid(home_url('/' . $page_path . '/'));
        $page = $page_id > 0 ? self::as_page($page_id) : null;

        if ($page && $page->post_status !== 'trash') {
            return $page;
        }

        $page = get_page_by_path($page_path, OBJECT, 'page');

        return ($page instanceof WP_Post && $page->post_status !== 'trash') ? $page : null;
    }

    public static function get_translated_parent_id(int $source_parent_id, string $language_code): ?int
    {
        if ($source_parent_id <= 0) {
            return 0;
        }

        $translated_parent_id = PWE_Multilang_Wpml_Gateway::get_translated_object_id(
            $source_parent_id,
            'page',
            $language_code
        );

        return $translated_parent_id > 0 ? $translated_parent_id : null;
    }

    private static function as_page(int $post_id): ?WP_Post
    {
        $post = $post_id > 0 ? get_post($post_id) : null;

        return ($post instanceof WP_Post && $post->post_type === 'page') ? $post : null;
    }
}
