<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Field_Id_Allocator
{
    public static function prepare(array $fields, array $existingFields = []): array
    {
        $fields = self::assignFieldIds($fields, $existingFields);
        $fields = self::assignInputIds($fields);
        $fields = PWE_Multilang_Form_Conditional_Logic_Resolver::fields($fields);

        return self::normalizeFields($fields);
    }

    private static function assignFieldIds(array $fields, array $existingFields): array
    {
        $existingMap = self::indexExistingFields($existingFields);
        $maxId = self::getMaxFieldId($existingFields);

        foreach ($fields as &$field) {
            if (!is_array($field)) {
                continue;
            }

            $adminLabel = $field['adminLabel'] ?? null;
            $label = $field['label'] ?? null;

            if ($adminLabel && isset($existingMap['admin'][(string) $adminLabel])) {
                $field['id'] = $existingMap['admin'][(string) $adminLabel];
                continue;
            }

            if ($label && isset($existingMap['label'][(string) $label])) {
                $field['id'] = $existingMap['label'][(string) $label];
                continue;
            }

            $field['id'] = ++$maxId;
        }

        unset($field);
        return $fields;
    }

    private static function assignInputIds(array $fields): array
    {
        foreach ($fields as &$field) {
            if (!is_array($field) || empty($field['id']) || empty($field['inputs']) || !is_array($field['inputs'])) {
                continue;
            }

            $fieldId = (int) $field['id'];

            foreach ($field['inputs'] as $index => &$input) {
                if (is_array($input)) {
                    $input['id'] = $fieldId . '.' . self::inputIdSuffix($input['id'] ?? null, $index);
                }
            }

            unset($input);
        }

        unset($field);
        return $fields;
    }

    private static function inputIdSuffix($inputId, int $index): int
    {
        if (is_numeric($inputId)) {
            $parts = explode('.', (string) $inputId);
            $suffix = (int) end($parts);

            if ($suffix > 0) {
                return $suffix;
            }
        }

        return $index + 1;
    }

    private static function normalizeFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (empty($field['type'])) {
                PWE_Multilang_Form_Log_Service::warn('Pominięto pole bez typu', ['field' => $field]);
                continue;
            }

            $normalized[] = class_exists('GF_Fields') && method_exists('GF_Fields', 'create')
                ? GF_Fields::create($field)
                : $field;
        }

        return $normalized;
    }

    private static function indexExistingFields(array $fields): array
    {
        $map = ['admin' => [], 'label' => []];

        foreach ($fields as $field) {
            if (!is_object($field) || !isset($field->id)) {
                continue;
            }

            if (!empty($field->adminLabel)) {
                $map['admin'][(string) $field->adminLabel] = (int) $field->id;
            }

            if (!empty($field->label)) {
                $map['label'][(string) $field->label] = (int) $field->id;
            }
        }

        return $map;
    }

    private static function getMaxFieldId(array $fields): int
    {
        $maxId = 0;

        foreach ($fields as $field) {
            if (is_object($field) && isset($field->id)) {
                $maxId = max($maxId, (int) $field->id);
            }
        }

        return $maxId;
    }
}
