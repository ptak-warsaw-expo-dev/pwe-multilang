<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Site_Group_Resolver
{
    public const LEGACY_GROUP = 'legacy';

    /** @var array<string, bool> */
    private static array $reported = [];

    public static function current(): string
    {
        if (!function_exists('shortcode_exists') || !shortcode_exists('trade_fair_group')) {
            return self::LEGACY_GROUP;
        }

        $value = do_shortcode('[trade_fair_group]');
        $value = sanitize_key(trim(wp_strip_all_tags((string) $value)));

        if (PWE_Multilang_Site_Group_Resource_Matrix::is_supported_group($value)) {
            return $value;
        }

        if ($value !== '' && empty(self::$reported[$value])) {
            self::$reported[$value] = true;
            do_action('pwe_multilang_unknown_site_group', $value);
        }

        return self::LEGACY_GROUP;
    }

    public static function label(): string
    {
        $group = self::current();

        return $group === self::LEGACY_GROUP
            ? 'wszystkie (brak rozpoznanej grupy)'
            : strtoupper($group);
    }
}
