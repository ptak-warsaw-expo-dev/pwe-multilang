<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Json_Repository
{
    /** @var array<string, array<string, mixed>> */
    private static $request_cache = [];

    /**
     * Returns the validated, unfiltered JSON document.
     */
    public static function get_all_pages(): array
    {
        $json_path = PWE_Multilang_Page_Config::get_json_path();
        $cache_key = wp_normalize_path($json_path);

        if (array_key_exists($cache_key, self::$request_cache)) {
            return self::$request_cache[$cache_key];
        }

        if (!is_file($json_path) || !is_readable($json_path)) {
            throw new RuntimeException('Nie znaleziono pliku website-translation.json: ' . $json_path);
        }

        $contents = file_get_contents($json_path);

        if ($contents === false) {
            throw new RuntimeException('Nie można odczytać pliku website-translation.json: ' . $json_path);
        }

        try {
            $pages = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Nieprawidłowy JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($pages)) {
            throw new RuntimeException('Nieprawidłowy JSON: główny element musi być obiektem.');
        }

        self::validate_pages($pages);
        self::$request_cache[$cache_key] = $pages;

        return $pages;
    }

    public static function get_pages(): array
    {
        return self::get_pages_for_current_group();
    }

    public static function get_pages_for_current_group(): array
    {
        return PWE_Multilang_Site_Group::filter_pages(self::get_all_pages());
    }

    private static function validate_pages(array $pages): void
    {
        foreach ($pages as $page_key => $translations) {
            if (!is_string($page_key) || sanitize_key($page_key) !== $page_key) {
                throw new RuntimeException('Nieprawidłowy klucz strony w website-translation.json.');
            }

            if (!is_array($translations)) {
                throw new RuntimeException('Nieprawidłowe tłumaczenia strony: ' . $page_key);
            }

            foreach ($translations as $language_code => $data) {
                if (!is_string($language_code) || sanitize_key($language_code) !== $language_code) {
                    throw new RuntimeException('Nieprawidłowy kod języka dla strony: ' . $page_key);
                }

                if (!is_array($data)) {
                    throw new RuntimeException('Nieprawidłowe dane strony: ' . $page_key . ' / ' . $language_code);
                }

                if (array_key_exists('label', $data) && !is_string($data['label'])) {
                    throw new RuntimeException('Nieprawidłowy label strony: ' . $page_key . ' / ' . $language_code);
                }

                if (array_key_exists('url', $data) && !is_string($data['url'])) {
                    throw new RuntimeException('Nieprawidłowy url strony: ' . $page_key . ' / ' . $language_code);
                }
            }
        }
    }
}
