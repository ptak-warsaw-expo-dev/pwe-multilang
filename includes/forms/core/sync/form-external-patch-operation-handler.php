<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Applies standard external-patch operations to an in-memory form. */
final class PWE_Multilang_Form_External_Patch_Operation_Handler
{
    public static function apply(array &$form, array $operation, bool $addOnly, array &$item): bool
    {
        $type = sanitize_key((string) ($operation['type'] ?? ''));

        if ($type === '') {
            $item['errors'][] = 'operacja bez typu została pominięta';
            return false;
        }

        if ($type === 'set_form_value') {
            return self::setFormValue($form, $operation, $item);
        }

        if ($type === 'upsert_field') {
            return self::upsertField($form, $operation, $addOnly, $item);
        }

        if ($type === 'remove_field') {
            return self::removeField($form, $operation, $item);
        }

        if ($type === 'upsert_notification') {
            return self::upsertNotification($form, $operation, $addOnly, $item);
        }

        if ($type === 'remove_notification') {
            return self::removeNamedItem($form, $operation, 'notifications', 'notification', $item);
        }

        if ($type === 'upsert_confirmation') {
            return self::upsertConfirmation($form, $operation, $addOnly, $item);
        }

        if ($type === 'remove_confirmation') {
            return self::removeNamedItem($form, $operation, 'confirmations', 'confirmation', $item);
        }

        $item['errors'][] = 'nieznany typ operacji: ' . $type;
        return false;
    }

    public static function updateNextFieldId(array &$form): void
    {
        $max = 0;

        foreach ((array) ($form['fields'] ?? []) as $field) {
            $id = is_array($field) && isset($field['id'])
                ? (int) $field['id']
                : (is_object($field) && isset($field->id) ? (int) $field->id : null);

            if ($id !== null) {
                $max = max($max, $id);
            }
        }

        if ($max > 0) {
            $form['nextFieldId'] = $max + 1;
        }
    }

    private static function setFormValue(array &$form, array $operation, array &$item): bool
    {
        $key = (string) ($operation['key'] ?? '');

        if ($key === '') {
            $item['errors'][] = 'set_form_value wymaga key';
            return false;
        }

        $form[$key] = $operation['value'] ?? null;
        $item['updated'][] = 'form:' . $key;
        $item['details'][] = 'ustawiono wartość formularza ' . $key;
        return true;
    }

    private static function upsertField(array &$form, array $operation, bool $addOnly, array &$item): bool
    {
        $field = is_array($operation['field'] ?? null) ? $operation['field'] : null;

        if (empty($field)) {
            $item['errors'][] = 'upsert_field wymaga pola field';
            return false;
        }

        $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
        $adminLabel = (string) ($operation['adminLabel'] ?? ($field['adminLabel'] ?? ''));

        if ($adminLabel === '') {
            $item['errors'][] = 'upsert_field wymaga adminLabel';
            return false;
        }

        $index = self::findFieldIndex($fields, $adminLabel);
        $prepared = PWE_Multilang_Form_Preparer::prepareFieldsForSync([$field], $fields);
        $preparedField = $prepared[0] ?? null;

        if (!is_array($preparedField) && !is_object($preparedField)) {
            $item['errors'][] = 'nie udało się przygotować pola ' . $adminLabel;
            return false;
        }

        $preparedField = is_object($preparedField) ? (array) $preparedField : $preparedField;

        if ($index === null) {
            $fields[] = $preparedField;
            $form['fields'] = $fields;
            $item['created'][] = 'field:' . $adminLabel;
            $item['details'][] = 'dodano pole ' . $adminLabel;
            return true;
        }

        if ($addOnly) {
            $item['skipped'][] = 'field:' . $adminLabel;
            $item['details'][] = 'pominięto aktualizację pola ' . $adminLabel . ' przez tryb add_only';
            return false;
        }

        $existing = is_array($fields[$index]) ? $fields[$index] : (array) $fields[$index];

        if (isset($existing['id']) && empty($preparedField['id'])) {
            $preparedField['id'] = $existing['id'];
        }

        $fields[$index] = array_replace_recursive($existing, $preparedField);
        $form['fields'] = $fields;
        $item['updated'][] = 'field:' . $adminLabel;
        $item['details'][] = 'zaktualizowano pole ' . $adminLabel;
        return true;
    }

    private static function removeField(array &$form, array $operation, array &$item): bool
    {
        $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
        $adminLabel = (string) ($operation['adminLabel'] ?? '');

        if ($adminLabel === '') {
            $item['errors'][] = 'remove_field wymaga adminLabel';
            return false;
        }

        $index = self::findFieldIndex($fields, $adminLabel);

        if ($index === null) {
            $item['skipped'][] = 'field:' . $adminLabel;
            $item['details'][] = 'pole ' . $adminLabel . ' nie istnieje';
            return false;
        }

        unset($fields[$index]);
        $form['fields'] = array_values($fields);
        $item['updated'][] = 'field:' . $adminLabel;
        $item['details'][] = 'usunięto pole ' . $adminLabel;
        return true;
    }

    private static function upsertNotification(array &$form, array $operation, bool $addOnly, array &$item): bool
    {
        $notification = is_array($operation['notification'] ?? null) ? $operation['notification'] : null;

        if (empty($notification)) {
            $item['errors'][] = 'upsert_notification wymaga notification';
            return false;
        }

        [$lookupName, $payloadName] = self::resolveNames($notification, $operation);

        if ($lookupName === '' || $payloadName === '') {
            $item['errors'][] = 'upsert_notification wymaga notification.name albo operation.name';
            return false;
        }

        $prepared = PWE_Multilang_Form_Preparer::prepareNotificationsForSync(
            [$notification],
            $form['fields'] ?? [],
            (string) ($operation['form_dir'] ?? '')
        );
        $preparedItem = self::firstArray($prepared);

        if ($preparedItem === null) {
            $item['errors'][] = 'nie udało się przygotować notification ' . $payloadName;
            return false;
        }

        return self::storeNamedItem(
            $form,
            'notifications',
            'notification',
            $lookupName,
            $payloadName,
            $preparedItem,
            $addOnly,
            $item,
            static fn(string $name): string => md5($name . uniqid('', true))
        );
    }

    private static function upsertConfirmation(array &$form, array $operation, bool $addOnly, array &$item): bool
    {
        $confirmation = is_array($operation['confirmation'] ?? null) ? $operation['confirmation'] : null;

        if (empty($confirmation)) {
            $item['errors'][] = 'upsert_confirmation wymaga confirmation';
            return false;
        }

        [$lookupName, $payloadName] = self::resolveNames($confirmation, $operation);

        if ($lookupName === '' || $payloadName === '') {
            $item['errors'][] = 'upsert_confirmation wymaga confirmation.name albo operation.name';
            return false;
        }

        $prepared = PWE_Multilang_Form_Preparer::prepareConfirmationsForSync(
            [$confirmation],
            $form['fields'] ?? []
        );
        $preparedItem = self::firstArray($prepared);

        if ($preparedItem === null) {
            $item['errors'][] = 'nie udało się przygotować confirmation ' . $payloadName;
            return false;
        }

        return self::storeNamedItem(
            $form,
            'confirmations',
            'confirmation',
            $lookupName,
            $payloadName,
            $preparedItem,
            $addOnly,
            $item,
            static fn(string $name): string => uniqid('', true)
        );
    }

    private static function storeNamedItem(
        array &$form,
        string $section,
        string $label,
        string $lookupName,
        string $payloadName,
        array $prepared,
        bool $addOnly,
        array &$item,
        callable $idFactory
    ): bool {
        $prepared['name'] = $payloadName;
        $items = is_array($form[$section] ?? null) ? $form[$section] : [];
        $existingId = self::findNamedItemId($items, $lookupName);

        if ($existingId === null) {
            $newId = (string) ($prepared['id'] ?? $idFactory($payloadName));
            $prepared['id'] = $newId;
            $items[$newId] = $prepared;
            $form[$section] = $items;
            $item['created'][] = $label . ':' . $payloadName;
            $item['details'][] = 'dodano ' . ($label === 'notification' ? 'powiadomienie ' : 'potwierdzenie ') . $payloadName;
            return true;
        }

        if ($addOnly) {
            $item['skipped'][] = $label . ':' . $payloadName;
            $item['details'][] = 'pominięto aktualizację '
                . ($label === 'notification' ? 'powiadomienia ' : 'potwierdzenia ')
                . $payloadName . ' przez tryb add_only';
            return false;
        }

        $existing = is_array($items[$existingId] ?? null) ? $items[$existingId] : [];
        $prepared['id'] = $existingId;
        $items[$existingId] = array_replace_recursive($existing, $prepared);
        $form[$section] = $items;
        $item['updated'][] = $label . ':' . $payloadName;
        $item['details'][] = 'zaktualizowano '
            . ($label === 'notification' ? 'powiadomienie ' : 'potwierdzenie ')
            . $payloadName;
        return true;
    }

    private static function removeNamedItem(
        array &$form,
        array $operation,
        string $section,
        string $label,
        array &$item
    ): bool {
        $name = (string) ($operation['name'] ?? '');

        if ($name === '') {
            $item['errors'][] = 'remove_' . $label . ' wymaga name';
            return false;
        }

        $items = is_array($form[$section] ?? null) ? $form[$section] : [];
        $existingId = self::findNamedItemId($items, $name);

        if ($existingId === null) {
            $item['skipped'][] = $label . ':' . $name;
            $item['details'][] = $label . ' ' . $name . ' nie istnieje';
            return false;
        }

        unset($items[$existingId]);
        $form[$section] = $items;
        $item['updated'][] = $label . ':' . $name;
        $item['details'][] = 'usunięto ' . $label . ' ' . $name;
        return true;
    }

    private static function resolveNames(array $payload, array $operation): array
    {
        $lookup = (string) ($operation['lookup_name'] ?? ($operation['name'] ?? ($payload['name'] ?? '')));
        $name = (string) (($payload['name'] ?? '') !== ''
            ? $payload['name']
            : ($operation['name'] ?? ($operation['lookup_name'] ?? '')));

        return [$lookup !== '' ? $lookup : $name, $name !== '' ? $name : $lookup];
    }

    private static function findFieldIndex(array $fields, string $adminLabel): ?int
    {
        foreach ($fields as $index => $field) {
            $label = is_array($field)
                ? (string) ($field['adminLabel'] ?? '')
                : (is_object($field) ? (string) ($field->adminLabel ?? '') : '');

            if ($label !== '' && mb_strtolower($label) === mb_strtolower($adminLabel)) {
                return (int) $index;
            }
        }

        return null;
    }

    private static function findNamedItemId(array $items, string $name): ?string
    {
        $needle = self::normalizeName($name);

        foreach ($items as $id => $item) {
            $itemName = is_array($item) ? (string) ($item['name'] ?? '') : '';

            if ($itemName !== '' && self::normalizeName($itemName) === $needle) {
                return (string) $id;
            }
        }

        return null;
    }

    private static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', trim(mb_strtolower($name))));
    }

    private static function firstArray(array $items): ?array
    {
        foreach ($items as $item) {
            if (is_array($item)) {
                return $item;
            }
        }

        return null;
    }
}
