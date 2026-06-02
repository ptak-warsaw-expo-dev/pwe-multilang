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
                $value = maybe_unserialize($meta_value);

                if (self::contains_builder_payload($value)) {
                    continue;
                }

                $safe_values[] = $value;
            }

            if (empty($safe_values)) {
                continue;
            }

            delete_post_meta($target_id, $meta_key);

            foreach ($safe_values as $safe_value) {
                add_post_meta($target_id, $meta_key, $safe_value);
            }
        }

        self::copy_uncode_header_footer_meta($source_id, $target_id);
        self::cleanup_uncode_page_id_references($source_id, $target_id);

        $thumbnail_id = get_post_thumbnail_id($source_id);

        if ($thumbnail_id) {
            set_post_thumbnail($target_id, $thumbnail_id);
        }
    }

    private static function should_skip_meta_key(string $meta_key): bool
    {
        $lower_meta_key = strtolower($meta_key);

        /*
         * Uncode kopiujemy osobno, bo zwykłe kopiowanie _uncode_* potrafi ustawić
         * nowo tworzoną stronę jako Content Block i wtedy .page-header renderuje
         * tę samą treść drugi raz. Fantastyczna rozrywka, naprawdę.
         */
        if (strpos($lower_meta_key, '_uncode_') === 0 || strpos($lower_meta_key, 'uncode_') === 0) {
            return true;
        }

        $excluded_meta_keys = [
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',

            '_icl_lang_duplicate_of',
            '_wpml_media_featured',
            '_wpml_media_duplicate',

            '_pwe_json_page_key',
            '_pwe_json_language',
            '_pwe_json_url',
            '_pwe_created_as_home_translation',

            '_elementor_data',
            '_elementor_css',
            '_elementor_page_assets',

            '_vc_post_settings',
            '_vc_shortcodes_custom_css',
            '_vc_post_custom_layout',
            '_wpb_vc_js_status',
            '_wpb_shortcodes_custom_css',
            '_wpb_post_custom_layout',

            '_builder_content',
            '_builder_data',
            '_builder_json',
            '_builder_layout',
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

        return false;
    }

    /**
     * Kopiuje ustawienia Uncode header/footer/page-header.
     *
     * Najważniejsze: nie kopiujemy żadnych selectorów Content Blocka oraz
     * usuwamy wszystkie ukryte odwołania do ID strony źródłowej/docelowej.
     * Jeżeli takie ID zostanie w _uncode_* headera, Uncode potrafi wyrenderować
     * bieżącą stronę w .page-header. Tak, to jest tak głupie, jak brzmi.
     */
    private static function copy_uncode_header_footer_meta(int $source_id, int $target_id): void
    {
        $source_meta = get_post_meta($source_id);

        foreach ($source_meta as $meta_key => $meta_values) {
            $meta_key = (string) $meta_key;

            if (!self::is_uncode_header_footer_meta_key($meta_key)) {
                continue;
            }

            $safe_values = [];

            foreach ($meta_values as $meta_value) {
                $value = maybe_unserialize($meta_value);
                $cleaned_value = self::sanitize_uncode_header_footer_value($value, $meta_key, $source_id, $target_id);

                if ($cleaned_value === null || $cleaned_value === '') {
                    continue;
                }

                if (self::contains_builder_payload($cleaned_value)) {
                    continue;
                }

                $safe_values[] = $cleaned_value;
            }

            delete_post_meta($target_id, $meta_key);

            foreach ($safe_values as $safe_value) {
                add_post_meta($target_id, $meta_key, $safe_value);
            }
        }
    }

    private static function is_uncode_header_footer_meta_key(string $meta_key): bool
    {
        $key = strtolower($meta_key);

        if (strpos($key, '_uncode_') !== 0 && strpos($key, 'uncode_') !== 0) {
            return false;
        }

        $allowed_fragments = [
            'header',
            'headers',
            'footer',
            'footers',
            'page_header',
            'page-header',
            'menu',
            'navigation',
            'navbar',
            'logo',
            'title',
            'breadcrumb',
            'breadcrumbs',
        ];

        foreach ($allowed_fragments as $fragment) {
            if (strpos($key, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function sanitize_uncode_header_footer_value($value, string $context_key, int $source_id, int $target_id, int $depth = 0)
    {
        if ($depth > 12) {
            return $value;
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            $cleaned = [];

            foreach ($value as $key => $item) {
                $key_string = is_string($key) ? strtolower($key) : (string) $key;
                $child_context = strtolower($context_key . '.' . $key_string);

                if (self::contains_builder_payload($item)) {
                    continue;
                }

                /*
                 * Nie kopiujemy pola Content Block jako takiego.
                 * Jeżeli użytkownik chce, może je ustawić ręcznie, ale wtyczka nie ma
                 * prawa wstawić tam ID strony, bo wtedy strona staje się własnym headerem.
                 */
                if (self::is_uncode_content_block_selector_context($child_context)) {
                    continue;
                }

                /*
                 * Krytyczne: usuń każde ukryte ID strony źródłowej/docelowej z Uncode header/footer.
                 * W praktyce to właśnie takie ID wpada w Content Block i powoduje duplikację.
                 */
                if (self::value_is_post_id($item, $source_id) || self::value_is_post_id($item, $target_id)) {
                    continue;
                }

                $cleaned_item = self::sanitize_uncode_header_footer_value($item, $child_context, $source_id, $target_id, $depth + 1);

                if ($cleaned_item === null || $cleaned_item === '') {
                    continue;
                }

                $cleaned[$key] = $cleaned_item;
            }

            return empty($cleaned) ? null : $cleaned;
        }

        if (self::contains_builder_payload($value)) {
            return null;
        }

        if (self::value_is_post_id($value, $source_id) || self::value_is_post_id($value, $target_id)) {
            return null;
        }

        if (self::scalar_value_is_uncode_content_block_mode($value)) {
            return null;
        }

        return $value;
    }

    private static function is_uncode_content_block_selector_context(string $context): bool
    {
        $context = strtolower($context);

        $header_footer_context = (
            strpos($context, 'header') !== false
            || strpos($context, 'footer') !== false
            || strpos($context, 'page_header') !== false
            || strpos($context, 'page-header') !== false
        );

        if (!$header_footer_context) {
            return false;
        }

        $selector_fragments = [
            'content_block',
            'content-block',
            'contentblock',
            'uncode_block',
            'uncode-block',
            'uncodeblock',
            'block_id',
            'block-id',
            'header_block',
            'header-block',
            'footer_block',
            'footer-block',
            'page_header_block',
            'page-header-block',
            'headers.value',
            'header.value',
            'page_header.value',
            'page-header.value',
        ];

        foreach ($selector_fragments as $fragment) {
            if (strpos($context, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function value_is_post_id($value, int $post_id): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        if (is_int($value)) {
            return $value === $post_id;
        }

        if (is_float($value)) {
            return (int) $value === $post_id;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== '' && ctype_digit($trimmed) && (int) $trimmed === $post_id;
        }

        return false;
    }

    private static function scalar_value_is_uncode_content_block_mode($value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $values_to_check = [
            $value,
            urldecode($value),
            html_entity_decode($value, ENT_QUOTES, 'UTF-8'),
            html_entity_decode(urldecode($value), ENT_QUOTES, 'UTF-8'),
        ];

        $blocked_values = [
            'header-uncode-block',
            'header_uncode_block',
            'uncode-block',
            'uncode_block',
            'uncodeblock',
            'content-block',
            'content_block',
            'contentblock',
        ];

        foreach ($values_to_check as $value_to_check) {
            $lower_value = strtolower($value_to_check);

            foreach ($blocked_values as $blocked_value) {
                if (strpos($lower_value, $blocked_value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function cleanup_uncode_page_id_references(int $source_id, int $target_id): void
    {
        $target_meta = get_post_meta($target_id);

        foreach ($target_meta as $meta_key => $meta_values) {
            $meta_key = (string) $meta_key;

            if (!self::is_uncode_header_footer_meta_key($meta_key)) {
                continue;
            }

            $new_values = [];

            foreach ($meta_values as $meta_value) {
                $value = maybe_unserialize($meta_value);
                $cleaned_value = self::sanitize_uncode_header_footer_value($value, $meta_key, $source_id, $target_id);

                if ($cleaned_value === null || $cleaned_value === '') {
                    continue;
                }

                if (self::contains_builder_payload($cleaned_value)) {
                    continue;
                }

                $new_values[] = $cleaned_value;
            }

            delete_post_meta($target_id, $meta_key);

            foreach ($new_values as $new_value) {
                add_post_meta($target_id, $meta_key, $new_value);
            }
        }
    }

    private static function contains_builder_payload($value, int $depth = 0): bool
    {
        if ($depth > 8) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::contains_builder_payload($item, $depth + 1)) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value)) {
            return self::contains_builder_payload((array) $value, $depth + 1);
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

        $builder_markers = [
            '[vc_row',
            '[vc_column',
            '[vc_column_text',
            '[vc_raw_html',
            '[vc_empty_space',
            '[pwelement',
            'pwe_element=',
            'pwe_element=&quot;',
            'pwe_element=&#034;',
        ];

        foreach ($values_to_check as $value_to_check) {
            $lower_value = strtolower($value_to_check);

            foreach ($builder_markers as $marker) {
                if (strpos($lower_value, strtolower($marker)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function cleanup_uncode_target_self_references(int $target_id): void
    {
        $target_meta = get_post_meta($target_id);

        foreach ($target_meta as $meta_key => $meta_values) {
            $meta_key = (string) $meta_key;

            if (!self::is_uncode_header_footer_meta_key($meta_key)) {
                continue;
            }

            $new_values = [];

            foreach ($meta_values as $meta_value) {
                $value = maybe_unserialize($meta_value);
                $cleaned_value = self::sanitize_uncode_value($value, $meta_key, $target_id);

                if ($cleaned_value === null || $cleaned_value === '') {
                    continue;
                }

                if (self::contains_builder_payload($cleaned_value)) {
                    continue;
                }

                $new_values[] = $cleaned_value;
            }

            delete_post_meta($target_id, $meta_key);

            foreach ($new_values as $new_value) {
                add_post_meta($target_id, $meta_key, $new_value);
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
}
