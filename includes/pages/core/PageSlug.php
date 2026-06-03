<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Slug
{
    public static function from_url(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === '') {
            return '';
        }

        $parts = explode('/', $path);
        $slug = end($parts);

        $slug = urldecode((string) $slug);
        $slug = remove_accents($slug);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        return sanitize_title($slug);
    }
}
