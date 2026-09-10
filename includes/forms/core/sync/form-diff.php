<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Diff
{
    private const DEFAULT_CONFIRMATION_NAMES = [
        'domyslnie potwierdzenie',
        'domyślnie potwierdzenie',
        'default confirmation',
    ];

    private const DEFAULT_NOTIFICATION_NAMES = [
        'admin notification',
        'user notification',
        'powiadomienie dla administratora',
        'powiadomienie dla uzytkownika',
    ];

    public static function getUpdateLog(array $existing, array $payload): array
    {
        return [
            'fields_extra' => self::diffExtraFields($existing['fields'] ?? [], $payload['fields'] ?? []),
            'notifications_extra' => self::diffExtraNotifications($existing['notifications'] ?? [], $payload['notifications'] ?? []),
            'confirmations_extra' => self::diffExtraConfirmations($existing['confirmations'] ?? [], $payload['confirmations'] ?? []),
        ];
    }

    private static function diffExtraFields(array $existingFields, array $templateFields): array
    {
        $templateMap = [];

        foreach ($templateFields as $field) {
            if (is_array($field) && !empty($field['adminLabel'])) {
                $templateMap[mb_strtolower($field['adminLabel'])] = true;
            }
        }

        $extra = [];

        foreach ($existingFields as $field) {
            if (!is_object($field)) {
                continue;
            }

            $adminLabel = $field->adminLabel ?? '';

            if ($adminLabel === '' || isset($templateMap[mb_strtolower($adminLabel)])) {
                continue;
            }

            $extra[] = [
                'type' => 'field',
                'severity' => 'warning',
                'message' => 'Znaleziono pole poza template',
                'id' => $field->id ?? '',
                'name' => ($field->label ?? '') ?: $adminLabel,
                'adminLabel' => $adminLabel,
            ];
        }

        return $extra;
    }

    private static function diffExtraNotifications(array $existingNotifications, array $templateNotifications): array
    {
        return self::diffNamedItems($existingNotifications, $templateNotifications, 'notification', 'Znaleziono powiadomienie poza template');
    }

    private static function diffExtraConfirmations(array $existingConfirmations, array $templateConfirmations): array
    {
        return self::diffNamedItems($existingConfirmations, $templateConfirmations, 'confirmation', 'Znaleziono potwierdzenie poza template');
    }

    private static function diffNamedItems(array $existing, array $template, string $type, string $message): array
    {
        $templateMap = [];

        foreach ($template as $item) {
            if (!empty($item['name'])) {
                $normalized = self::normalizeName((string) $item['name']);

                if ($normalized !== '') {
                    $templateMap[$normalized] = true;
                }
            }
        }

        // If template does not define notifications, treat notifications as unmanaged
        // to avoid constant false positives for legacy/default GF setups.
        if ($type === 'notification' && !$templateMap) {
            return [];
        }

        $extra = [];

        foreach ($existing as $item) {
            $name = (string) ($item['name'] ?? '');
            $normalized = self::normalizeName($name);

            if ($normalized === '' || isset($templateMap[$normalized])) {
                continue;
            }

            if ($type === 'confirmation' && self::isDefaultConfirmationName($normalized)) {
                continue;
            }

            if ($type === 'notification' && self::isDefaultNotificationName($normalized)) {
                continue;
            }

            $extra[] = [
                'type' => $type,
                'severity' => 'warning',
                'message' => $message,
                'id' => $item['id'] ?? '',
                'name' => $item['name'],
            ];
        }

        return $extra;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim(mb_strtolower($name));

        if ($name === '') {
            return '';
        }

        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);

        return trim((string) $name);
    }

    private static function isDefaultConfirmationName(string $normalizedName): bool
    {
        return in_array($normalizedName, self::DEFAULT_CONFIRMATION_NAMES, true);
    }

    private static function isDefaultNotificationName(string $normalizedName): bool
    {
        return in_array($normalizedName, self::DEFAULT_NOTIFICATION_NAMES, true);
    }
}
