<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Replace_Content_Config
{
    /**
     * @return array<string, string>
     */
    public static function get_map(): array
    {
        $map_file = dirname(__DIR__) . '/replace-content-map.php';
        $map = file_exists($map_file) ? require $map_file : [];

        if (!is_array($map)) {
            $map = [];
        }

        /**
         * Pozwala rozszerzyć mapę bez edycji plików wtyczki.
         *
         * @param array<string, string> $map
         */
        $map = apply_filters('pwe_multilang_replace_content_map', $map);

        if (!is_array($map)) {
            return [];
        }

        $normalized = [];

        foreach ($map as $url => $shortcode) {
            if (!is_string($url) || !is_string($shortcode)) {
                continue;
            }

            $path = wp_parse_url(trim($url), PHP_URL_PATH);
            $shortcode = trim($shortcode);

            if (!is_string($path) || $shortcode === '') {
                continue;
            }

            $path = '/' . trim($path, '/') . '/';

            if ($path === '//') {
                $path = '/';
            }

            $normalized[$path] = $shortcode;
        }

        return $normalized;
    }
}
