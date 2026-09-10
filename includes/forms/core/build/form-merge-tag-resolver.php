<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Merge_Tag_Resolver
{
    private static array $warnings = [];

    public static function consumeWarnings(): array
    {
        $warnings = array_values(self::$warnings);
        self::$warnings = [];
        return $warnings;
    }

    public static function confirmations(array $confirmations, array $fields): array
    {
        $fieldMap = PWE_Multilang_Form_Conditional_Logic_Resolver::fieldMap($fields);
        $tagMaps = self::buildFieldMaps($fields);

        foreach ($confirmations as &$confirmation) {
            foreach (['url', 'message', 'queryString'] as $key) {
                if (empty($confirmation[$key]) || !is_string($confirmation[$key])) {
                    continue;
                }

                $confirmation[$key] = preg_replace_callback(
                    '/\{UTM:([a-zA-Z0-9_\-]+)\}/',
                    static function ($matches) use ($fieldMap) {
                        return isset($fieldMap[$matches[1]])
                            ? '{UTM:' . $fieldMap[$matches[1]] . '}'
                            : $matches[0];
                    },
                    $confirmation[$key]
                );
                $confirmation[$key] = self::replaceFieldTags(
                    $confirmation[$key],
                    $tagMaps['admin'],
                    $tagMaps['label'],
                    'confirmation',
                    $key
                );
            }
        }

        unset($confirmation);
        return $confirmations;
    }

    public static function notifications(array $notifications, array $fields): array
    {
        $tagMaps = self::buildFieldMaps($fields);

        foreach ($notifications as &$notification) {
            foreach (['subject', 'message', 'from', 'fromName', 'replyTo', 'to', 'cc', 'bcc'] as $key) {
                if (empty($notification[$key]) || !is_string($notification[$key])) {
                    continue;
                }

                $notification[$key] = self::replaceFieldTags(
                    $notification[$key],
                    $tagMaps['admin'],
                    $tagMaps['label'],
                    'notification',
                    $key
                );
            }
        }

        unset($notification);
        return $notifications;
    }

    private static function replaceFieldTags(
        string $text,
        array $adminMap,
        array $labelMap,
        string $scope,
        string $fieldKey
    ): string {
        return (string) preg_replace_callback(
            '/\{([^{}]+?):([0-9]+)(\.[0-9]+)?\}/u',
            static function ($matches) use ($adminMap, $labelMap, $scope, $fieldKey) {
                $rawLabel = trim((string) ($matches[1] ?? ''));

                if ($rawLabel === '') {
                    return $matches[0];
                }

                $lookup = self::normalizeLookup($rawLabel);
                $resolvedId = $adminMap[$lookup] ?? ($labelMap[$lookup] ?? null);

                if ($resolvedId === null || $resolvedId === '') {
                    self::addWarning($scope, $fieldKey, (string) $matches[0], $rawLabel);
                    return $matches[0];
                }

                return '{' . $rawLabel . ':' . $resolvedId . (string) ($matches[3] ?? '') . '}';
            },
            $text
        );
    }

    private static function addWarning(string $scope, string $fieldKey, string $tag, string $lookupLabel): void
    {
        $key = mb_strtolower($scope . '|' . $fieldKey . '|' . $tag);

        if (isset(self::$warnings[$key])) {
            return;
        }

        $warning = [
            'scope' => $scope,
            'field' => $fieldKey,
            'tag' => $tag,
            'lookup_label' => $lookupLabel,
        ];
        self::$warnings[$key] = $warning;
        PWE_Multilang_Form_Log_Service::warn('Nierozpoznany merge tag pola podczas synchronizacji', $warning);
    }

    private static function buildFieldMaps(array $fields): array
    {
        $adminMap = [];
        $labelMap = [];

        foreach ($fields as $field) {
            $fieldId = self::fieldValue($field, 'id');

            if ($fieldId === '') {
                continue;
            }

            $adminLabel = self::fieldValue($field, 'adminLabel');
            $label = self::fieldValue($field, 'label');

            if ($adminLabel !== '') {
                $adminMap[self::normalizeLookup($adminLabel)] = $fieldId;
            }

            if ($label !== '') {
                $labelMap[self::normalizeLookup($label)] = $fieldId;
            }
        }

        return ['admin' => $adminMap, 'label' => $labelMap];
    }

    private static function fieldValue($field, string $key): string
    {
        if (is_object($field) && isset($field->{$key}) && $field->{$key} !== '') {
            return (string) $field->{$key};
        }

        if (is_array($field) && !empty($field[$key])) {
            return (string) $field[$key];
        }

        return '';
    }

    private static function normalizeLookup(string $value): string
    {
        return trim(mb_strtolower($value));
    }
}
