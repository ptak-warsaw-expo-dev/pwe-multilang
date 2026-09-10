<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Config
{
    public static function get_json_path(): string
    {
        return defined('PWE_MULTILANG_PATH')
            ? PWE_MULTILANG_PATH . 'website-translation.json'
            : dirname(__DIR__, 3) . '/website-translation.json';
    }

    public static function get_language_names(): array
    {
        return PWE_Multilang_Language_Catalog::admin_labels();
    }

    public static function get_skipped_languages(): array
    {
        return ['pl', 'en'];
    }
}
