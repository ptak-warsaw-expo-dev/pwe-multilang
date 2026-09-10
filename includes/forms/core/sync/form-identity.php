<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stable identity stored with managed Gravity Forms.
 * Titles remain a compatibility fallback for forms created by older releases.
 */
final class PWE_Multilang_Form_Identity
{
    public const TEMPLATE_SLUG_KEY = 'pwe_multilang_template_slug';
    public const TARGET_KEY = 'pwe_multilang_target_key';

    public static function targetKey(array $payload): string
    {
        $lang = sanitize_key((string) ($payload['_pwe_separate_lang'] ?? ''));

        if ($lang !== '') {
            return 'lang:' . $lang;
        }

        $groupLangs = self::normalizeLangs($payload['_pwe_group_langs'] ?? []);

        if (!empty($groupLangs)) {
            return 'group:' . implode(',', $groupLangs);
        }

        return 'default';
    }

    public static function attach(array $payload, string $templateSlug): array
    {
        $payload[self::TEMPLATE_SLUG_KEY] = sanitize_key($templateSlug);
        $payload[self::TARGET_KEY] = self::targetKey($payload);

        return $payload;
    }

    public static function copyToExisting(array $existing, array $payload): array
    {
        foreach ([self::TEMPLATE_SLUG_KEY, self::TARGET_KEY] as $key) {
            if (array_key_exists($key, $payload)) {
                $existing[$key] = $payload[$key];
            }
        }

        return $existing;
    }

    private static function normalizeLangs($langs): array
    {
        if (!is_array($langs)) {
            return [];
        }

        $normalized = [];

        foreach ($langs as $lang) {
            $lang = sanitize_key((string) $lang);

            if ($lang !== '') {
                $normalized[] = $lang;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
