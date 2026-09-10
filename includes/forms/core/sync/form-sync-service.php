<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performs form synchronization. Public entry points and locking stay in Form_Core.
 */
final class PWE_Multilang_Form_Sync_Service
{
    public static function sync(
        string $scope,
        array $templateSlugs,
        int $formsYear,
        bool $addOnly,
        array $overwriteModified = [],
        bool $allowCreate = true
    ): array {
        $result = PWE_Multilang_Form_Sync_Result::create($scope, $addOnly);

        if (!class_exists('GFAPI')) {
            $result['errors'][] = 'Gravity Forms / GFAPI nie jest dostępne.';
            return PWE_Multilang_Form_Sync_Result::finalize($result);
        }

        if (!in_array($scope, ['forms', 'fields', 'notifications', 'confirmations'], true)) {
            $result['errors'][] = 'Nieznany zakres synchronizacji.';
            return PWE_Multilang_Form_Sync_Result::finalize($result);
        }

        $templates = PWE_Multilang_Form_Template_Registry::getTemplates($formsYear);

        foreach (array_values(array_unique($templateSlugs)) as $slug) {
            if (!self::refreshOperationLock($result)) {
                break;
            }

            $slug = sanitize_key((string) $slug);

            if ($slug === '' || empty($templates[$slug])) {
                continue;
            }

            $template = $templates[$slug];
            $targets = $template['targets'] ?? [];

            if (empty($targets)) {
                $targets[] = [
                    'title' => $template['title'] ?? '',
                    'payload' => $template['payload'] ?? [],
                    'existing' => $template['existing'] ?? null,
                    'form_id' => $template['form_id'] ?? 0,
                    'lang' => null,
                    'is_separate' => false,
                    'target_key' => 'default',
                ];
            }

            foreach ($targets as $target) {
                if (!self::refreshOperationLock($result)) {
                    break 2;
                }

                $result['items'][] = self::syncTarget(
                    $template,
                    $target,
                    $scope,
                    $addOnly,
                    in_array($slug, $overwriteModified, true),
                    $allowCreate
                );
            }
        }

        if (empty($result['items'])) {
            $result['errors'][] = 'Nie wybrano żadnego prawidłowego template’u.';
        }

        return PWE_Multilang_Form_Sync_Result::finalize($result);
    }

    private static function refreshOperationLock(array &$result): bool
    {
        if (!PWE_Multilang_Form_Operation_Lock::ownedByCurrentRequest()) {
            return true;
        }

        if (PWE_Multilang_Form_Operation_Lock::refreshOwned()) {
            return true;
        }

        $result['errors'][] = 'Przerwano synchronizację, ponieważ utracono blokadę operacji.';
        return false;
    }

    private static function syncTarget(
        array $template,
        array $target,
        string $scope,
        bool $addOnly,
        bool $overwriteModified,
        bool $allowCreate
    ): array {
        $result = PWE_Multilang_Form_Sync_Result::item($template, $target, $scope);
        $payload = is_array($target['payload'] ?? null) ? $target['payload'] : [];
        $existing = is_array($target['existing'] ?? null) ? $target['existing'] : null;

        PWE_Multilang_Form_Preparer::consumeMergeTagWarnings();

        if ($existing !== null) {
            $result['diff'] = PWE_Multilang_Form_Diff::getUpdateLog($existing, $payload);
            $existing = PWE_Multilang_Form_Identity::copyToExisting($existing, $payload);
        }

        if ($existing === null) {
            if (!$allowCreate) {
                $result['skipped'][] = 'form';
                $result['details'][] = 'formularz nie istnieje - tworzenie jest wyłączone dla tej operacji';
                return self::finishItem($result, 'skipped');
            }

            $formId = PWE_Multilang_Form_Writer::create($payload);
            self::appendMergeTagWarningsToResult($result);

            if ($formId) {
                $result['form_id'] = $formId;
                $result['created'][] = 'form';
                $result['details'][] = 'utworzono formularz';
                return self::finishItem($result, 'success');
            }

            $result['errors'][] = 'nie udało się utworzyć formularza';
            return self::finishItem($result, 'failed');
        }

        $result['form_id'] = (int) ($existing['id'] ?? 0);
        $legacy = PWE_Multilang_Form_Core::isLegacyMultilangWithoutManagedFlag($existing);

        if (!PWE_Multilang_Form_Core::isManaged($existing) && !$legacy) {
            $result['skipped'][] = 'form';
            $result['details'][] = 'formularz pominięto jako niezarządzany / ręcznie edytowany';
            return self::finishItem($result, 'skipped');
        }

        if ($legacy) {
            $result['details'][] = 'formularz legacy Multilang bez flagi managed - dopuszczono aktualizację';
        }

        if (PWE_Multilang_Form_Core::isManualModified($existing) && !$overwriteModified) {
            $result['skipped'][] = 'form';
            $result['details'][] = 'formularz utworzony przez PWE Multilang został edytowany ręcznie - pominięto, bo nie potwierdzono nadpisania';
            PWE_Multilang_Form_Log_Service::warn(
                'FORM: Pominięto formularz edytowany ręcznie bez potwierdzenia nadpisania',
                [
                    'template' => $template['slug'] ?? '',
                    'form_id' => $existing['id'] ?? null,
                    'title' => $existing['title'] ?? '',
                    'lang' => $target['lang'] ?? null,
                ]
            );
            return self::finishItem($result, 'skipped');
        }

        if ($scope === 'forms') {
            return self::syncWholeForm($existing, $payload, $result, $addOnly);
        }

        if ($scope === 'fields') {
            return self::syncFields($existing, $payload, $result, $addOnly);
        }

        if ($scope === 'notifications') {
            return self::syncNotifications($existing, $payload, $result, $addOnly);
        }

        return self::syncConfirmations($existing, $payload, $result, $addOnly);
    }

    private static function syncWholeForm(array $existing, array $payload, array $result, bool $addOnly): array
    {
        if ($addOnly) {
            $result['skipped'][] = 'form';
            $result['details'][] = 'formularz istnieje - pominięto przez tryb tylko dodawania brakujących';
            return self::finishItem($result, 'skipped');
        }

        $updated = PWE_Multilang_Form_Writer::update($existing, $payload, [
            'confirmations' => 'replace',
            'notifications' => 'replace',
        ]);

        if ($updated) {
            $result['updated'][] = 'form';
            $result['details'][] = 'zaktualizowano cały formularz według template’u';
        } else {
            $result['errors'][] = 'nie udało się zaktualizować formularza';
        }

        self::appendMergeTagWarningsToResult($result);
        return self::finishItem($result, $updated ? 'success' : 'failed');
    }

    private static function syncFields(array $existing, array $payload, array $result, bool $addOnly): array
    {
        $existingFields = is_array($existing['fields'] ?? null) ? $existing['fields'] : [];
        $incomingFields = PWE_Multilang_Form_Preparer::prepareFieldsForSync($payload['fields'] ?? [], $existingFields);
        $byAdmin = self::indexFields($existingFields, 'admin');
        $byLabel = self::indexFields($existingFields, 'label');
        $byId = self::indexFieldsById($existingFields);
        $merged = $existingFields;

        foreach ($incomingFields as $field) {
            $adminLabel = self::fieldValue($field, 'adminLabel');
            $label = self::fieldValue($field, 'label');
            $display = $adminLabel !== '' ? $adminLabel : $label;
            $match = null;
            $matchKey = '';

            if ($adminLabel !== '' && isset($byAdmin[$adminLabel])) {
                $match = $byAdmin[$adminLabel];
                $matchKey = $adminLabel;
            } elseif ($label !== '' && isset($byLabel[$label])) {
                $match = $byLabel[$label];
                $matchKey = $label;
            }

            if ($display === '') {
                $result['skipped'][] = 'field';
                $result['details'][] = 'pominięto pole bez adminLabel i label';
                continue;
            }

            if ($match === null) {
                $merged[] = $field;
                $result['created'][] = 'field:' . $display;
                $result['details'][] = 'dodano pole ' . $display;
                continue;
            }

            if ($addOnly) {
                $result['skipped'][] = 'field:' . $display;
                $result['details'][] = 'pominięto pole ' . $display . ', bo istnieje i włączono tryb tylko dodawania brakujących';
                continue;
            }

            $id = self::fieldValue($match, 'id');

            if ($id !== '' && isset($byId[$id])) {
                $merged[$byId[$id]] = $field;
            }

            $result['updated'][] = 'field:' . ($matchKey !== '' ? $matchKey : $display);
            $result['details'][] = 'zaktualizowano pole ' . $display;
        }

        foreach ($existingFields as $existingField) {
            if (self::incomingFieldExists($incomingFields, $existingField)) {
                continue;
            }

            $display = self::fieldValue($existingField, 'adminLabel');
            $display = $display !== '' ? $display : self::fieldValue($existingField, 'label');

            if ($display !== '') {
                $result['skipped'][] = 'extra-field:' . $display;
                $result['details'][] = 'pozostawiono dodatkowe pole ' . $display . ', którego nie ma w template’cie';
            }
        }

        $existing['fields'] = $merged;
        return self::updatePartialForm($existing, $result);
    }

    private static function syncNotifications(array $existing, array $payload, array $result, bool $addOnly): array
    {
        $incoming = PWE_Multilang_Form_Preparer::prepareNotificationsForSync(
            $payload['notifications'] ?? [],
            $existing['fields'] ?? [],
            $payload['_formDir'] ?? ''
        );
        $existing['notifications'] = self::mergeNamedItems(
            is_array($existing['notifications'] ?? null) ? $existing['notifications'] : [],
            $incoming,
            'powiadomienie',
            'notification',
            $addOnly,
            $result
        );

        return self::updatePartialForm($existing, $result, true);
    }

    private static function syncConfirmations(array $existing, array $payload, array $result, bool $addOnly): array
    {
        $incoming = PWE_Multilang_Form_Preparer::prepareConfirmationsForSync(
            $payload['confirmations'] ?? [],
            $existing['fields'] ?? []
        );
        $existing['confirmations'] = self::mergeNamedItems(
            is_array($existing['confirmations'] ?? null) ? $existing['confirmations'] : [],
            $incoming,
            'potwierdzenie',
            'confirmation',
            $addOnly,
            $result
        );

        return self::updatePartialForm($existing, $result, true);
    }

    private static function updatePartialForm(array $form, array $result, bool $processQr = false): array
    {
        $form[PWE_Multilang_Form_Core::MANAGED_FLAG] = 1;
        $form[PWE_Multilang_Form_Core::MANUAL_MODIFIED_FLAG] = 0;
        $update = PWE_Multilang_Form_Manual_Modified::run(static function () use ($form) {
            return GFAPI::update_form($form);
        });

        if (is_wp_error($update) || $update === false) {
            $result['errors'][] = is_wp_error($update)
                ? $update->get_error_message()
                : 'nie udało się zaktualizować formularza';
            self::appendMergeTagWarningsToResult($result);
            return self::finishItem($result, 'failed');
        }

        PWE_Multilang_Form_Finder::resetCache();

        if ($processQr) {
            PWE_Multilang_Form_Manual_Modified::run(static function () use ($form): void {
                PWE_Multilang_Form_QR::processShortcodesOnly((int) ($form['id'] ?? 0), $form['title'] ?? '');
            });
        }

        self::appendMergeTagWarningsToResult($result);
        return self::finishItem($result, 'success');
    }

    private static function mergeNamedItems(
        array $existing,
        array $incoming,
        string $labelPl,
        string $bucket,
        bool $addOnly,
        array &$result
    ): array {
        $index = [];

        foreach ($existing as $id => $item) {
            if (is_array($item) && !empty($item['name'])) {
                $index[mb_strtolower((string) $item['name'])] = $id;
            }
        }

        foreach ($incoming as $newId => $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = (string) ($item['name'] ?? $newId);
            $key = mb_strtolower($name);

            if (!array_key_exists($key, $index)) {
                $existing[$newId] = $item;
                $result['created'][] = $bucket . ':' . $name;
                $result['details'][] = 'dodano ' . $labelPl . ' ' . $name;
                continue;
            }

            if ($addOnly) {
                $result['skipped'][] = $bucket . ':' . $name;
                $result['details'][] = 'pominięto ' . $labelPl . ' ' . $name . ', bo istnieje i włączono tryb tylko dodawania brakujących';
                continue;
            }

            $existingId = $index[$key];
            $item['id'] = $existingId;
            $base = is_array($existing[$existingId] ?? null) ? $existing[$existingId] : [];
            $existing[$existingId] = array_replace_recursive($base, $item);
            $result['updated'][] = $bucket . ':' . $name;
            $result['details'][] = 'zaktualizowano ' . $labelPl . ' ' . $name;
        }

        return $existing;
    }

    private static function appendMergeTagWarningsToResult(array &$result): void
    {
        foreach (PWE_Multilang_Form_Preparer::consumeMergeTagWarnings() as $warning) {
            if (!is_array($warning)) {
                continue;
            }

            $result['details'][] = sprintf(
                'WARN: nierozpoznany merge tag (%s:%s) %s',
                (string) ($warning['scope'] ?? '?'),
                (string) ($warning['field'] ?? '?'),
                (string) ($warning['tag'] ?? '?')
            );
        }
    }

    private static function finishItem(array $result, string $status): array
    {
        $result['status'] = !empty($result['errors']) ? 'failed' : $status;
        return $result;
    }

    private static function indexFields(array $fields, string $kind): array
    {
        $key = $kind === 'admin' ? 'adminLabel' : 'label';
        $out = [];

        foreach ($fields as $field) {
            $value = self::fieldValue($field, $key);

            if ($value !== '') {
                $out[$value] = $field;
            }
        }

        return $out;
    }

    private static function indexFieldsById(array $fields): array
    {
        $out = [];

        foreach ($fields as $index => $field) {
            $id = self::fieldValue($field, 'id');

            if ($id !== '') {
                $out[$id] = $index;
            }
        }

        return $out;
    }

    private static function incomingFieldExists(array $incoming, $existing): bool
    {
        $admin = self::fieldValue($existing, 'adminLabel');
        $label = self::fieldValue($existing, 'label');

        foreach ($incoming as $field) {
            if ($admin !== '' && self::fieldValue($field, 'adminLabel') === $admin) {
                return true;
            }

            if ($label !== '' && self::fieldValue($field, 'label') === $label) {
                return true;
            }
        }

        return false;
    }

    private static function fieldValue($field, string $key): string
    {
        if (is_object($field) && isset($field->{$key})) {
            return (string) $field->{$key};
        }

        if (is_array($field) && isset($field[$key])) {
            return (string) $field[$key];
        }

        return '';
    }
}
