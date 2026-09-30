<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Language_Catalog
{
    private const LABELS = [
        'pl' => 'Polski',
        'en' => 'English',
        'cs' => 'Čeština',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'lt' => 'Lietuvių',
        'lv' => 'Latviešu',
        'sk' => 'Slovenčina',
        'uk' => 'Українська',
        'ro' => 'Română',
        'et' => 'Eesti',
        'hu' => 'Magyar',
        'fr' => 'Français',
        'es' => 'Español',
    ];

    /**
     * Labels historically used on the Pages screen. Kept separately from
     * native names so the refactor does not change the existing interface.
     */
    private const ADMIN_LABELS = [
        'cs' => 'Czeski',
        'de' => 'Niemiecki',
        'en' => 'Angielski',
        'it' => 'Włoski',
        'lt' => 'Litewski',
        'lv' => 'Łotewski',
        'pl' => 'Polski',
        'sk' => 'Słowacki',
        'uk' => 'Ukraiński',
        'ro' => 'Rumuński',
        'et' => 'Estoński',
        'fr' => 'Francuski',
        'es' => 'Hiszpański',
    ];

    public static function labels(): array
    {
        return self::LABELS;
    }

    /** @return string[] */
    public static function codes(): array
    {
        return array_keys(self::LABELS);
    }

    /** @return array<string, string> */
    public static function admin_labels(): array
    {
        return self::ADMIN_LABELS;
    }

    public static function label(string $code): string
    {
        $code = self::normalise($code);

        return self::LABELS[$code] ?? strtoupper($code);
    }

    public static function normalise(string $code): string
    {
        return strtolower(sanitize_key($code));
    }

    /** @return string[] */
    public static function active_wpml(array $excluded = [], array $fallback = []): array
    {
        $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
        $excluded = array_map([self::class, 'normalise'], $excluded);
        $normalise = static function (array $codes) use ($excluded): array {
            $codes = array_map([PWE_Multilang_Language_Catalog::class, 'normalise'], $codes);

            return array_values(array_unique(array_filter(
                $codes,
                static fn(string $code): bool => $code !== '' && !in_array($code, $excluded, true)
            )));
        };

        $codes = $normalise(is_array($languages) ? array_keys($languages) : []);

        return $codes !== [] ? $codes : $normalise($fallback);
    }
}
