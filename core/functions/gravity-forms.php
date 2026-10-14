<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Form_Repository
{
    public const MANAGED_FLAG = 'pwe_multilang_managed';

    public static function available(): bool
    {
        return class_exists('GFAPI');
    }

    /** Raw GFAPI result, including WP_Error, for callers handling API failures. */
    public static function fetch(int $form_id)
    {
        return self::available() ? GFAPI::get_form($form_id) : null;
    }

    /** Preserve GFAPI errors and its default active/trash filters. */
    public static function fetch_all(?bool $active = true, bool $trash = false)
    {
        return self::available() ? GFAPI::get_forms($active, $trash) : [];
    }

    public static function get(int $form_id): ?array
    {
        if (!self::available() || $form_id < 1) {
            return null;
        }

        $form = self::fetch($form_id);

        return is_array($form) ? $form : null;
    }

    /** List summaries; callers retain their own selection rules. */
    public static function all(?bool $active = true, bool $trash = false): array
    {
        if (!self::available()) {
            return [];
        }
        $forms = self::fetch_all($active, $trash);
        return is_array($forms) ? $forms : [];
    }

    public static function is_managed(?array $form): bool
    {
        return !empty($form[self::MANAGED_FLAG]);
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

        foreach (self::all() as $form_summary) {
            $form = self::get((int) ($form_summary['id'] ?? 0));

            if ($form !== null && self::is_managed($form)) {
                $managed[] = $form;
            }
        }

        return $managed;
    }
}

final class PWE_Multilang_Form_Title_Year
{
    public static function extract(string $title): int
    {
        return preg_match('/^\s*\((\d{4})\)\s*/', $title, $match) === 1 ? (int) $match[1] : 0;
    }

    public static function without_year(string $title): string
    {
        return trim((string) preg_replace('/^\s*\(\d{4}\)\s*/', '', $title));
    }
}

/** Read and write Gravity Forms records represented as arrays or objects. */
final class PWE_Multilang_GF_Properties
{
    public static function get($record, string $key, $default = null)
    {
        if (is_array($record) && array_key_exists($key, $record)) {
            return $record[$key];
        }
        if (is_object($record) && isset($record->{$key})) {
            return $record->{$key};
        }
        return $default;
    }

    public static function set(&$record, string $key, $value): void
    {
        if (is_array($record)) {
            $record[$key] = $value;
        } elseif (is_object($record)) {
            $record->{$key} = $value;
        }
    }
}

final class PWE_Multilang_Form_Language_Rules
{
    /** English covers languages without a dedicated notification/confirmation. */
    public static function build(string $language, array $languages, string $field, bool $case_insensitive = false): array
    {
        $comparison = $case_insensitive ? strtolower($language) : $language;
        if ($comparison !== 'en') {
            return [['field' => $field, 'operator' => 'is', 'value' => $language]];
        }
        $rules = [];
        foreach ($languages as $supported) {
            $supported = strtolower(trim((string) $supported));
            if ($supported !== '' && $supported !== 'en') {
                $rules[] = ['field' => $field, 'operator' => 'isnot', 'value' => $supported];
            }
        }
        return $rules;
    }
}
