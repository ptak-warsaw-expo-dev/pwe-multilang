<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Field_Locator
{
    /** @var array<string, string|null> */
    private static array $cache = [];

    /**
     * @param string[] $names
     * @param string[] $types
     */
    public static function find_id(array $form, array $names = [], array $types = []): ?string
    {
        $normalised_names = array_values(array_unique(array_filter(array_map(
            static fn($name): string => strtolower(trim((string) $name)),
            $names
        ))));
        $normalised_types = array_values(array_unique(array_filter(array_map(
            static fn($type): string => strtolower(trim((string) $type)),
            $types
        ))));
        $cache_key = self::cache_key($form, $normalised_names, $normalised_types);

        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
        }

        foreach (($form['fields'] ?? []) as $field) {
            if (!is_object($field) && !is_array($field)) {
                continue;
            }

            $labels = [
                strtolower(trim((string) self::property($field, 'adminLabel'))),
                strtolower(trim((string) self::property($field, 'label'))),
                strtolower(trim((string) self::property($field, 'inputName'))),
            ];
            $type = strtolower(trim((string) self::property($field, 'type')));

            if (
                ($normalised_names && array_intersect($normalised_names, $labels))
                || ($normalised_types && in_array($type, $normalised_types, true))
            ) {
                return self::$cache[$cache_key] = (string) self::property($field, 'id');
            }
        }

        self::$cache[$cache_key] = null;

        return null;
    }

    /** @param string[] $names */
    public static function value(array $form, array $entry, array $names, array $types = []): string
    {
        $field_id = self::find_id($form, $names, $types);

        if ($field_id === null || $field_id === '') {
            return '';
        }

        return trim((string) rgar($entry, $field_id));
    }

    public static function clear_cache(): void
    {
        self::$cache = [];
    }

    /**
     * The form ID is not sufficient here: unsaved forms have no ID and Gravity
     * Forms can mutate the field collection during the same request. Including
     * the searchable field properties prevents a stale result from leaking to
     * another form revision while still keeping repeated entry lookups cheap.
     *
     * @param string[] $names
     * @param string[] $types
     */
    private static function cache_key(array $form, array $names, array $types): string
    {
        $fields = [];

        foreach (($form['fields'] ?? []) as $field) {
            if (!is_object($field) && !is_array($field)) {
                continue;
            }

            $fields[] = [
                'id'         => (string) self::property($field, 'id'),
                'adminLabel' => (string) self::property($field, 'adminLabel'),
                'label'      => (string) self::property($field, 'label'),
                'inputName'  => (string) self::property($field, 'inputName'),
                'type'       => (string) self::property($field, 'type'),
            ];
        }

        $fingerprint = wp_json_encode([$fields, $names, $types]);

        if (!is_string($fingerprint)) {
            $fingerprint = serialize([$fields, $names, $types]);
        }

        return (string) ($form['id'] ?? 'unsaved') . ':' . md5($fingerprint);
    }

    /** @param array<string, mixed>|object $field */
    private static function property($field, string $name)
    {
        return is_array($field)
            ? ($field[$name] ?? '')
            : ($field->{$name} ?? '');
    }
}
