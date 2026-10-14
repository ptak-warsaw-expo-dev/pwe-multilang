<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Template_Languages
{
    public static function resolve(array $templateLangs): array
    {
        $templateLangs = self::normalize($templateLangs);
        $wpmlLangs = self::getActiveWpmlLangs();

        if (empty($wpmlLangs)) {
            return $templateLangs;
        }

        return array_values(array_intersect($templateLangs, $wpmlLangs));
    }

    public static function resolveFromMaps(array ...$maps): array
    {
        $langs = [];

        foreach ($maps as $map) {
            foreach (array_keys($map) as $lang) {
                if (is_string($lang) && preg_match('/^[a-z]{2}$/', $lang)) {
                    $langs[] = strtolower($lang);
                }
            }
        }

        return self::resolve($langs);
    }

    private static function getActiveWpmlLangs(): array
    {
        return self::normalize(array_keys(PWE_Multilang_Language_Catalog::active_wpml_details()));
    }

    private static function normalize(array $langs): array
    {
        $out = [];

        foreach ($langs as $lang) {
            if (!is_string($lang)) {
                continue;
            }

            $lang = strtolower(trim($lang));

            if (!preg_match('/^[a-z]{2}$/', $lang)) {
                continue;
            }

            $out[] = $lang;
        }

        return array_values(array_unique($out));
    }
}
