<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Conditional_Logic_Resolver
{
    public static function fields(array $fields): array
    {
        $map = [];

        foreach ($fields as $field) {
            if (is_array($field) && !empty($field['adminLabel']) && !empty($field['id'])) {
                $map[$field['adminLabel']] = $field['id'];
            }
        }

        foreach ($fields as &$field) {
            if (empty($field['conditionalLogic']['rules'])) {
                continue;
            }

            foreach ($field['conditionalLogic']['rules'] as &$rule) {
                self::resolveRule($rule, $map);
            }

            unset($rule);
        }

        unset($field);
        return $fields;
    }

    public static function notifications(array $notifications, array $fields): array
    {
        $map = self::fieldMap($fields);

        foreach ($notifications as &$notification) {
            if (empty($notification['conditionalLogic']['rules'])) {
                continue;
            }

            foreach ($notification['conditionalLogic']['rules'] as &$rule) {
                self::resolveRule($rule, $map);
            }

            unset($rule);
            $notification['notification_conditional_logic'] = '1';
            $notification['notification_conditional_logic_object'] = $notification['conditionalLogic'];
        }

        unset($notification);
        return $notifications;
    }

    public static function notificationRecipients(array $notifications, array $fields): array
    {
        $map = self::fieldMap($fields);

        foreach ($notifications as &$notification) {
            if (($notification['toType'] ?? '') !== 'field') {
                continue;
            }

            $to = $notification['to'] ?? $notification['toField'] ?? '';

            if ($to === '') {
                continue;
            }

            if (isset($map[$to])) {
                $fieldId = (string) $map[$to];
            } elseif (is_numeric($to)) {
                $fieldId = (string) $to;
            } else {
                continue;
            }

            $notification['toType'] = 'field';
            $notification['to'] = $fieldId;
            $notification['toField'] = $fieldId;
            $notification['toEmail'] = '';
        }

        unset($notification);
        return $notifications;
    }

    public static function confirmations(array $confirmations, array $fields): array
    {
        $map = self::fieldMap($fields);

        foreach ($confirmations as &$confirmation) {
            if (empty($confirmation['conditionalLogic']['rules'])) {
                continue;
            }

            foreach ($confirmation['conditionalLogic']['rules'] as &$rule) {
                self::resolveRule($rule, $map);
            }

            unset($rule);
        }

        unset($confirmation);
        return $confirmations;
    }

    public static function fieldMap(array $fields): array
    {
        $map = [];

        foreach ($fields as $field) {
            if (is_object($field) && !empty($field->adminLabel)) {
                $map[$field->adminLabel] = (string) $field->id;
            }
        }

        return $map;
    }

    private static function resolveRule(array &$rule, array $map): void
    {
        if (empty($rule['fieldId']) && !empty($rule['field']) && isset($map[$rule['field']])) {
            $rule['fieldId'] = $map[$rule['field']];
            unset($rule['field']);
        }
    }
}
