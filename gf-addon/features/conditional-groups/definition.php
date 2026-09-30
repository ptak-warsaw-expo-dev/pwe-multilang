<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Conditional_Groups_Definition
{
    public const PROPERTY = 'pweConditionalGroups';

    public static function normalise($definition): array
    {
        return self::normalise_definition($definition, true);
    }

    /**
     * Normalises an already trusted Gravity Forms definition for evaluation.
     * Rule values must stay byte-for-byte compatible with native conditional
     * logic (for example values containing angle brackets or line breaks).
     */
    public static function runtime($definition): array
    {
        return self::normalise_definition($definition, false);
    }

    private static function normalise_definition($definition, bool $sanitise_rules): array
    {
        if (is_string($definition)) {
            $decoded = json_decode($definition, true);

            if (!is_array($decoded)) {
                $decoded = json_decode(wp_unslash($definition), true);
            }

            $definition = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($definition)) {
            return self::empty();
        }

        $normalised = [
            'logicType' => self::logic_type($definition['logicType'] ?? 'any'),
            'groups'    => [],
        ];

        foreach (($definition['groups'] ?? []) as $group) {
            if (!is_array($group) || !is_array($group['rules'] ?? null)) {
                continue;
            }

            $rules = [];

            foreach ($group['rules'] as $rule) {
                if (!is_array($rule) || !isset($rule['fieldId'], $rule['operator'])) {
                    continue;
                }

                if (!is_scalar($rule['fieldId']) || !is_scalar($rule['operator'])) {
                    continue;
                }

                if (!$sanitise_rules) {
                    $rules[] = $rule;
                    continue;
                }

                $value = $rule['value'] ?? '';

                if (!is_scalar($value) && $value !== null) {
                    $value = '';
                }

                $rules[] = [
                    'fieldId'  => sanitize_text_field((string) $rule['fieldId']),
                    'operator' => self::sanitise_operator((string) $rule['operator']),
                    'value'    => sanitize_text_field((string) $value),
                ];
            }

            if ($rules) {
                $normalised['groups'][] = [
                    'logicType' => self::logic_type($group['logicType'] ?? 'all'),
                    'rules'     => $rules,
                ];
            }
        }

        return $normalised;
    }

    public static function posted(): array
    {
        $posted = function_exists('rgpost')
            ? rgpost(self::PROPERTY)
            : ($_POST[self::PROPERTY] ?? '');

        return self::normalise($posted);
    }

    public static function empty(): array
    {
        return ['logicType' => 'any', 'groups' => []];
    }

    public static function logic_type($value): string
    {
        return $value === 'all' ? 'all' : 'any';
    }

    private static function sanitise_operator(string $operator): string
    {
        $operator = sanitize_text_field($operator);

        return strlen($operator) <= 64 && preg_match('/^[a-z0-9_<>!=.-]+$/i', $operator) === 1
            ? $operator
            : 'is';
    }
}
