<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Conditional_Groups_Engine
{
    public const GROUP_OPERATOR = 'pwe_grouped_rules';
    public const DEPENDENCY_OPERATOR = 'pwe_group_dependency';

    public static function compile($logic, $definition)
    {
        if (!is_array($logic) || empty($logic['rules']) || self::is_compiled($logic)) {
            return $logic;
        }

        $definition = PWE_Multilang_GF_Conditional_Groups_Definition::runtime($definition);

        if (empty($definition['groups'])) {
            return $logic;
        }

        $groups = [[
            'actionType' => 'show',
            'logicType'  => PWE_Multilang_GF_Conditional_Groups_Definition::logic_type(
                $logic['logicType'] ?? 'all'
            ),
            'rules'      => array_values($logic['rules']),
        ]];

        foreach ($definition['groups'] as $group) {
            if (!empty($group['rules'])) {
                $groups[] = [
                    'actionType' => 'show',
                    'logicType'  => PWE_Multilang_GF_Conditional_Groups_Definition::logic_type(
                        $group['logicType'] ?? 'all'
                    ),
                    'rules'      => array_values($group['rules']),
                ];
            }
        }

        if (count($groups) < 2) {
            return $logic;
        }

        $anchor = (string) ($groups[0]['rules'][0]['fieldId'] ?? '0');
        $compiled_rules = [[
            'fieldId'   => $anchor,
            'operator'  => self::GROUP_OPERATOR,
            'value'     => '',
            'pweGroups' => [
                'logicType' => PWE_Multilang_GF_Conditional_Groups_Definition::logic_type(
                    $definition['logicType'] ?? 'any'
                ),
                'groups' => $groups,
            ],
        ]];

        foreach ($groups as $group) {
            foreach ($group['rules'] as $rule) {
                if (!isset($rule['fieldId'])) {
                    continue;
                }

                $compiled_rules[] = [
                    'fieldId'  => $rule['fieldId'],
                    'operator' => self::DEPENDENCY_OPERATOR,
                    'value'    => '',
                ];
            }
        }

        return [
            'actionType' => ($logic['actionType'] ?? 'show') === 'hide' ? 'hide' : 'show',
            'logicType'  => 'all',
            'rules'      => $compiled_rules,
        ];
    }

    public static function evaluate(array $definition, array $form, array $entry): bool
    {
        $definition = PWE_Multilang_GF_Conditional_Groups_Definition::runtime($definition);
        $groups = $definition['groups'] ?? [];

        if (!$groups || !class_exists('GFCommon') || !method_exists('GFCommon', 'evaluate_conditional_logic')) {
            return false;
        }

        $want_all = ($definition['logicType'] ?? 'any') === 'all';
        $evaluated = false;

        foreach ($groups as $group) {
            if (empty($group['rules'])) {
                continue;
            }

            $evaluated = true;
            $matches = (bool) GFCommon::evaluate_conditional_logic([
                'actionType' => 'show',
                'logicType'  => PWE_Multilang_GF_Conditional_Groups_Definition::logic_type(
                    $group['logicType'] ?? 'all'
                ),
                'rules' => $group['rules'],
            ], $form, $entry);

            if (!$want_all && $matches) {
                return true;
            }

            if ($want_all && !$matches) {
                return false;
            }
        }

        return $evaluated && $want_all;
    }

    public static function is_compiled(array $logic): bool
    {
        return ($logic['rules'][0]['operator'] ?? '') === self::GROUP_OPERATOR;
    }
}
