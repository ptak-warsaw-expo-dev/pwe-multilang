<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Meta_Copier
{
    public static function copy_basic_page_meta(int $source_id, int $target_id): void
    {
        $source_meta = get_post_meta($source_id);

        foreach ($source_meta as $meta_key => $meta_values) {
            $meta_key = (string) $meta_key;

            if (self::should_skip_meta_key($meta_key)) {
                continue;
            }

            $safe_values = [];

            foreach ($meta_values as $meta_value) {
                if (self::is_builder_or_uncode_header_meta_value($meta_value)) {
                    continue;
                }

                $safe_values[] = maybe_unserialize($meta_value);
            }

            if (empty($safe_values)) {
                continue;
            }

            delete_post_meta($target_id, $meta_key);

            foreach ($safe_values as $safe_value) {
                add_post_meta($target_id, $meta_key, $safe_value);
            }
        }

        self::remove_uncode_header_meta_from_target($target_id);

        self::set_uncode_header_none($target_id);

        $thumbnail_id = get_post_thumbnail_id($source_id);

        if ($thumbnail_id) {
            set_post_thumbnail($target_id, $thumbnail_id);
        }
    }

    /**
     * Some page builders/themes keep a second copy of the page layout in post meta.
     *
     * In Uncode + WPBakery the duplicated layout may be rendered inside:
     * .page-header > .header-wrapper > .header-uncode-block
     *
     * The real page body is already copied through post_content in PageCreator.
     * We should not copy VC/WPBakery payloads or Uncode header-block settings,
     * because Uncode can then render the same content again as a page header block.
     */
    private static function should_skip_meta_key(string $meta_key): bool
    {
        $lower_meta_key = strtolower($meta_key);

        $excluded_meta_keys = [
            // WordPress internal/editor meta.
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',

            // WPML internal meta.
            '_icl_lang_duplicate_of',
            '_wpml_media_featured',
            '_wpml_media_duplicate',

            // PWE Multilang internal meta.
            '_pwe_json_page_key',
            '_pwe_json_language',
            '_pwe_json_url',
            '_pwe_created_as_home_translation',

            // Elementor, just in case the plugin is used on mixed sites.
            '_elementor_data',
            '_elementor_css',
            '_elementor_page_assets',

            // WPBakery / Visual Composer generated settings or cached layout data.
            '_vc_post_settings',
            '_vc_shortcodes_custom_css',
            '_vc_post_custom_layout',
            '_wpb_vc_js_status',
            '_wpb_shortcodes_custom_css',
            '_wpb_post_custom_layout',

            // Generic builder payloads.
            '_builder_content',
            '_builder_data',
            '_builder_json',
            '_builder_layout',

            // Common Uncode page-header / content-block meta keys.
            '_uncode_header_type',
            '_uncode_headers',
            '_uncode_header',
            '_uncode_header_block',
            '_uncode_header_blocks',
            '_uncode_page_header',
            '_uncode_specific_header',
            '_uncode_specific_header_type',
            '_uncode_specific_header_block',
            '_uncode_specific_headers',
            '_uncode_specific_page_header',
        ];

        if (in_array($lower_meta_key, $excluded_meta_keys, true)) {
            return true;
        }

        $blocked_key_fragments = [
            'builder_content',
            'builder_data',
            'builder_json',
            'builder_layout',
            'composer_content',
            'composer_data',
            'vc_content',
            'visual_composer_content',
            'wpbakery_content',
            'wpbakery_data',
            'wpbakery_layout',
        ];

        foreach ($blocked_key_fragments as $fragment) {
            if (strpos($lower_meta_key, $fragment) !== false) {
                return true;
            }
        }

        // Do not block all _uncode_* meta, because some of it controls normal page layout.
        // But Uncode header/page-header meta is exactly where the duplicated content is being rendered.
        if (strpos($lower_meta_key, 'uncode') !== false) {
            $uncode_header_fragments = [
                'header',
                'headers',
                'page_header',
                'page-header',
                'header_block',
                'header-block',
                'uncode_block',
                'uncode-block',
            ];

            foreach ($uncode_header_fragments as $fragment) {
                if (strpos($lower_meta_key, $fragment) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function is_builder_or_uncode_header_meta_value($meta_value): bool
    {
        $unserialized_value = maybe_unserialize($meta_value);

        return self::contains_blocked_content($unserialized_value);
    }

    private static function contains_blocked_content($value, int $depth = 0): bool
    {
        if ($depth > 6) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::contains_blocked_content($item, $depth + 1)) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value)) {
            return self::contains_blocked_content((array) $value, $depth + 1);
        }

        if (!is_string($value) || $value === '') {
            return false;
        }

        $values_to_check = [
            $value,
            urldecode($value),
            html_entity_decode($value, ENT_QUOTES, 'UTF-8'),
            html_entity_decode(urldecode($value), ENT_QUOTES, 'UTF-8'),
        ];

        $blocked_markers = [
            // WPBakery/PWE layout duplicated from post_content.
            '[vc_row',
            '[vc_column',
            '[vc_column_text',
            '[vc_raw_html',
            '[vc_empty_space',
            '[pwelement',
            'pwe_element=',
            'pwe_element=&quot;',
            'pwe_element=&#034;',

            // Uncode header/content-block render markers.
            'header-uncode-block',
            'header-wrapper',
            'page-header',
            'uncode-block',
            'uncode_block',
        ];

        foreach ($values_to_check as $value_to_check) {
            $lower_value = strtolower($value_to_check);

            foreach ($blocked_markers as $marker) {
                if (strpos($lower_value, strtolower($marker)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Safety cleanup for newly created translated pages.
     *
     * If WordPress/WPML/Uncode adds default header meta during insertion, remove only
     * Uncode header-related meta from the target. Do not remove all _uncode_* meta.
     */
    private static function remove_uncode_header_meta_from_target(int $target_id): void
    {
        $target_meta = get_post_meta($target_id);

        foreach (array_keys($target_meta) as $meta_key) {
            $meta_key = (string) $meta_key;

            if (self::should_skip_meta_key($meta_key)) {
                delete_post_meta($target_id, $meta_key);
            }
        }
    }

    public static function copy_wp_rocket_page_meta(int $source_id, int $target_id): void
    {
        $rocket_meta_keys = [
            '_rocket_exclude_minify_css',
            '_rocket_exclude_minify_js',
            '_rocket_exclude_defer_all_js',
            '_rocket_exclude_delay_js',
            '_rocket_exclude_lazyload',
            '_rocket_exclude_lazyload_iframes',

        ];

        foreach ($rocket_meta_keys as $meta_key) {
            $value = get_post_meta($source_id, $meta_key, true);

            delete_post_meta($target_id, $meta_key);

            if ($value !== '') {
                update_post_meta($target_id, $meta_key, $value);
            }
        }
    }

    public static function copy_wp_rocket_cache_reject_uri(int $source_id, int $target_id): void
    {
        $settings = get_option('wp_rocket_settings');

        if (!is_array($settings)) {
            return;
        }

        $source_uri = wp_parse_url(get_permalink($source_id), PHP_URL_PATH);
        $target_uri = wp_parse_url(get_permalink($target_id), PHP_URL_PATH);

        if (empty($source_uri) || empty($target_uri)) {
            return;
        }

        $source_uri = '/' . trim($source_uri, '/') . '/';
        $target_uri = '/' . trim($target_uri, '/') . '/';

        $cache_reject_uri = $settings['cache_reject_uri'] ?? [];

        if (!is_array($cache_reject_uri)) {
            $cache_reject_uri = [];
        }

        if (!in_array($source_uri, $cache_reject_uri, true)) {
            return;
        }

        if (!in_array($target_uri, $cache_reject_uri, true)) {
            $cache_reject_uri[] = $target_uri;
        }

        $settings['cache_reject_uri'] = array_values(array_unique($cache_reject_uri));

        update_option('wp_rocket_settings', $settings);
    }

    private static function set_uncode_header_none(int $target_id): void
    {
        delete_post_meta($target_id, '_uncode_blocks_list');
        delete_post_meta($target_id, '_uncode_header_type');

        update_post_meta($target_id, '_uncode_header_type', 'none');
    }
}