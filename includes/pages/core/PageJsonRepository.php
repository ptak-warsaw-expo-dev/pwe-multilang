<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Json_Repository
{
    public static function get_pages(): array
    {
        $json_path = PWE_Multilang_Page_Config::get_json_path();

        if (!file_exists($json_path)) {
            throw new RuntimeException('Nie znaleziono pliku website-translation.json: ' . $json_path);
        }

        $pages = json_decode((string) file_get_contents($json_path), true);

        if (!is_array($pages)) {
            throw new RuntimeException('Nieprawidłowy JSON.');
        }

        return $pages;
    }
}
