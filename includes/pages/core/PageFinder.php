<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Finder
{
    public static function get_home_page_in_lang(string $lang_code): ?WP_Post
    {
        $front_id = (int) get_option('page_on_front');

        if (!$front_id) {
            return null;
        }

        $translated_id = apply_filters('wpml_object_id', $front_id, 'page', false, $lang_code);

        if (!$translated_id) {
            return null;
        }

        $post = get_post((int) $translated_id);

        return ($post && $post->post_type === 'page') ? $post : null;
    }

    public static function find_page_by_url_and_lang(string $url, string $lang_code): ?WP_Post
    {
        global $wpdb;

        $slug = PWE_Multilang_Page_Slug::from_url($url);

        if ($url === '/' || $slug === '') {
            return self::get_home_page_in_lang($lang_code);
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
                $lang_code
            )
        );

        if (!empty($post_ids[0])) {
            return get_post((int) $post_ids[0]);
        }

        return null;
    }

    public static function get_translated_parent_id(int $en_parent_id, string $lang_code): ?int
    {
        $en_parent_id = (int) $en_parent_id;

        if ($en_parent_id <= 0) {
            return 0;
        }

        $translated_parent_id = apply_filters('wpml_object_id', $en_parent_id, 'page', false, $lang_code);

        return $translated_parent_id ? (int) $translated_parent_id : null;
    }
}
