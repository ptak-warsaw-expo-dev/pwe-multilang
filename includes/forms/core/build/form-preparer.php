<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Backwards-compatible facade for payload preparation services. */
final class PWE_Multilang_Form_Preparer
{
    public static function consumeMergeTagWarnings(): array
    {
        return PWE_Multilang_Form_Merge_Tag_Resolver::consumeWarnings();
    }

    public static function preparePayloadForCreate(array $payload): array
    {
        if (!empty($payload['fields'])) {
            $payload['fields'] = self::prepareFieldsForSync($payload['fields']);
            $payload['nextFieldId'] = count($payload['fields']) + 1;
        }

        if (!empty($payload['confirmations'])) {
            $payload['confirmations'] = self::prepareConfirmationsForSync(
                $payload['confirmations'],
                $payload['fields'] ?? []
            );
        }

        if (!empty($payload['notifications'])) {
            $payload['notifications'] = self::prepareNotificationsForSync(
                $payload['notifications'],
                $payload['fields'] ?? [],
                $payload['_formDir'] ?? ''
            );
        }

        return $payload;
    }

    public static function prepareExistingForUpdate(array $existing, array $payload, array $options): array
    {
        unset($payload['qr']);

        foreach ([
            'title',
            'description',
            'labelPlacement',
            'descriptionPlacement',
            'button',
            'is_active',
            'enableHoneypot',
            'markupVersion',
            'pwe_multilang_managed',
            '_formDir',
            '_pwe_separate_forms',
            '_pwe_separate_lang',
            '_pwe_base_title',
            '_pwe_group_langs',
            '_pwe_excluded_separate_langs',
            'template_version',
            PWE_Multilang_Form_Identity::TEMPLATE_SLUG_KEY,
            PWE_Multilang_Form_Identity::TARGET_KEY,
            PWE_Multilang_Form_Core::MANUAL_MODIFIED_FLAG,
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                $existing[$key] = $payload[$key];
            }
        }

        if (!empty($payload['fields'])) {
            $existing['fields'] = self::prepareFieldsForSync(
                $payload['fields'],
                $existing['fields'] ?? []
            );
        }

        if (!empty($payload['confirmations'])) {
            $confirmations = self::prepareConfirmationsForSync(
                $payload['confirmations'],
                $existing['fields'] ?? []
            );
            $existing['confirmations'] = self::applyMerge(
                $existing['confirmations'] ?? [],
                $confirmations,
                $options['confirmations']
            );
        }

        if (!empty($payload['notifications'])) {
            $notifications = self::prepareNotificationsForSync(
                $payload['notifications'],
                $existing['fields'] ?? [],
                $payload['_formDir'] ?? ''
            );
            $existing['notifications'] = self::applyMerge(
                $existing['notifications'] ?? [],
                $notifications,
                $options['notifications']
            );
        }

        return $existing;
    }

    public static function prepareFieldsForSync(array $fields, array $existingFields = []): array
    {
        return PWE_Multilang_Form_Field_Id_Allocator::prepare($fields, $existingFields);
    }

    public static function prepareNotificationsForSync(
        array $notifications,
        array $fields,
        string $formDir = ''
    ): array {
        return PWE_Multilang_Form_Notification_Preparer::prepare($notifications, $fields, $formDir);
    }

    public static function prepareConfirmationsForSync(array $confirmations, array $fields): array
    {
        return PWE_Multilang_Form_Confirmation_Preparer::prepare($confirmations, $fields);
    }

    private static function applyMerge(array $existing, array $incoming, string $mode): array
    {
        if ($mode === 'replace') {
            return $incoming;
        }

        $index = [];

        foreach ($existing as $id => $item) {
            if (!empty($item['name'])) {
                $index[mb_strtolower($item['name'])] = $id;
            }
        }

        foreach ($incoming as $newId => $item) {
            $name = $item['name'] ?? null;

            if (!$name) {
                $existing[$newId] = $item;
                continue;
            }

            $key = mb_strtolower($name);

            if (isset($index[$key])) {
                $existing[$index[$key]] = array_replace_recursive($existing[$index[$key]], $item);
            } else {
                $existing[$newId] = $item;
            }
        }

        return $existing;
    }
}
