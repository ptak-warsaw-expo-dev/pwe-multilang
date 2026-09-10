<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Conditional_Groups_Settings
{
    public static function add_hidden(array $fields, $value): array
    {
        if (!$fields) {
            return $fields;
        }

        $fields[0]['fields'][] = [
            'type'          => 'hidden',
            'name'          => PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY,
            'default_value' => wp_json_encode(
                PWE_Multilang_GF_Conditional_Groups_Definition::normalise($value)
            ),
            'class'         => 'pwe-conditional-groups-value',
        ];

        return $fields;
    }

    public static function add_feed_hidden(array $fields): array
    {
        if ($fields) {
            $fields[0]['fields'][] = [
                'type'  => 'hidden',
                'name'  => PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY,
                'class' => 'pwe-conditional-groups-value',
            ];
        }

        return $fields;
    }

    public static function save(array $item): array
    {
        $item[PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY] = wp_json_encode(
            PWE_Multilang_GF_Conditional_Groups_Definition::posted()
        );

        return $item;
    }

    public static function admin_fields(): array
    {
        $form_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if ($form_id < 1 || !class_exists('GFAPI')) {
            return [];
        }

        $form = GFAPI::get_form($form_id);

        if (!is_array($form)) {
            return [];
        }

        $fields = [];

        foreach (($form['fields'] ?? []) as $field) {
            if (is_object($field)) {
                $fields[] = [
                    'id'         => (string) ($field->id ?? ''),
                    'label'      => (string) ($field->label ?? ''),
                    'adminLabel' => (string) ($field->adminLabel ?? ''),
                    'type'       => (string) ($field->type ?? ''),
                    'inputType'  => (string) ($field->inputType ?? ''),
                    'choices'    => is_array($field->choices ?? null) ? $field->choices : [],
                ];
            }
        }

        return $fields;
    }
}

