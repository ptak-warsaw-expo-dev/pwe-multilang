<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Form_Repository
{
    public static function available(): bool
    {
        return class_exists('GFAPI');
    }

    public static function get(int $form_id): ?array
    {
        if (!self::available() || $form_id < 1) {
            return null;
        }

        $form = GFAPI::get_form($form_id);

        return is_array($form) ? $form : null;
    }

    public static function is_managed(array $form): bool
    {
        return !empty($form['pwe_multilang_managed']);
    }

    public static function is_managed_id(int $form_id): bool
    {
        $form = self::get($form_id);

        return $form !== null && self::is_managed($form);
    }

    /** @return array<int, array> */
    public static function managed(): array
    {
        if (!self::available()) {
            return [];
        }

        $managed = [];

        foreach ((array) GFAPI::get_forms() as $form_summary) {
            $form = self::get((int) ($form_summary['id'] ?? 0));

            if ($form !== null && self::is_managed($form)) {
                $managed[] = $form;
            }
        }

        return $managed;
    }
}

