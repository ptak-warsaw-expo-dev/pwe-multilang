<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Year_Resolver
{
    private const OPTION = 'pwe_general_options';
    private const KEY = 'pwe_create_forms_year';

    public static function current(): int
    {
        return (int) (function_exists('wp_date') ? wp_date('Y') : date('Y'));
    }

    public static function configured(): int
    {
        $options = get_option(self::OPTION, []);

        return self::normalise(is_array($options) ? ($options[self::KEY] ?? null) : null);
    }

    public static function normalise($year): int
    {
        $year = absint($year);

        return $year >= 2000 && $year <= 2100 ? $year : self::current();
    }

    public static function save($year): int
    {
        $year = self::normalise($year);
        $options = get_option(self::OPTION, []);
        $options = is_array($options) ? $options : [];
        $options[self::KEY] = $year;
        update_option(self::OPTION, $options, false);

        return $year;
    }
}

