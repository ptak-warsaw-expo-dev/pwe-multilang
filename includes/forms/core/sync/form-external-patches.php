<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Coordinates configured, optionally versioned external form patches. */
final class PWE_Multilang_Form_External_Patches
{
    private const OPTION_VERSIONS = 'pwe_mlg_form_external_patch_versions';

    public static function applyRegistered(bool $addOnly = false, string $context = 'manual_sync'): array
    {
        if (!class_exists('GFAPI')) {
            return ['items' => [], 'errors' => ['Gravity Forms / GFAPI nie jest dostępne.']];
        }

        $token = PWE_Multilang_Form_Operation_Lock::acquire('external_patches');

        if ($token === null) {
            return PWE_Multilang_Form_Sync_Result::locked('external_patches', $addOnly);
        }

        try {
            return self::applyRegisteredUnlocked($addOnly, $context);
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }

    private static function applyRegisteredUnlocked(bool $addOnly, string $context): array
    {
        $allowUnmanaged = (bool) apply_filters('pwe_mlg_external_patches_allow_unmanaged', false, $context);
        $savedVersions = get_option(self::OPTION_VERSIONS, []);
        $savedVersions = is_array($savedVersions) ? $savedVersions : [];
        $result = ['items' => [], 'errors' => []];
        $lockLost = false;

        foreach (self::getProfiles() as $profileKey => $profile) {
            if (
                PWE_Multilang_Form_Operation_Lock::ownedByCurrentRequest()
                && !PWE_Multilang_Form_Operation_Lock::refreshOwned()
            ) {
                $result['errors'][] = 'Przerwano patche, ponieważ utracono blokadę operacji.';
                $lockLost = true;
                break;
            }

            if (!is_array($profile)) {
                continue;
            }

            $fallbackKey = is_string($profileKey) ? $profileKey : '';
            $resolvedKey = sanitize_key((string) ($profile['key'] ?? $fallbackKey));
            $version = trim((string) ($profile['version'] ?? ''));

            if ($resolvedKey !== '' && $version !== '' && (string) ($savedVersions[$resolvedKey] ?? '') === $version) {
                $result['items'][] = self::alreadyAppliedItem($resolvedKey, $version);
                continue;
            }

            $item = self::applyProfile($profile, $addOnly, $allowUnmanaged, $fallbackKey);
            $canRecordVersion =
                $resolvedKey !== ''
                && $version !== ''
                && empty($item['errors'])
                && !in_array('form', $item['skipped'] ?? [], true);

            if ($canRecordVersion) {
                $savedVersions[$resolvedKey] = $version;
            } elseif ($version !== '') {
                $item['status'] = 'failed';
            }

            $result['items'][] = $item;
        }

        if (
            !$lockLost
            && PWE_Multilang_Form_Operation_Lock::ownedByCurrentRequest()
            && !PWE_Multilang_Form_Operation_Lock::refreshOwned()
        ) {
            $result['errors'][] = 'Nie zapisano wersji patchy, ponieważ utracono blokadę operacji.';
            $lockLost = true;
        }

        if ($lockLost) {
            return $result;
        }

        update_option(self::OPTION_VERSIONS, $savedVersions, false);
        return $result;
    }

    private static function getProfiles(): array
    {
        $profiles = [];

        if (
            class_exists('PWE_Multilang_Form_External_Patches_Config')
            && method_exists('PWE_Multilang_Form_External_Patches_Config', 'get')
        ) {
            $configured = PWE_Multilang_Form_External_Patches_Config::get();
            $profiles = is_array($configured) ? $configured : [];
        }

        $profiles = apply_filters('pwe_mlg_external_form_patches', $profiles);
        return is_array($profiles) ? $profiles : [];
    }

    private static function applyProfile(
        array $profile,
        bool $addOnly,
        bool $allowUnmanaged,
        string $profileKey
    ): array {
        $key = sanitize_key((string) ($profile['key'] ?? $profileKey ?: 'external_patch'));

        if (self::hasOperationType($profile, 'migration_to_new_qr')) {
            return PWE_Multilang_Form_External_Patch_QR_Migration::apply($key);
        }

        $item = self::item($key);
        $target = is_array($profile['target'] ?? null) ? $profile['target'] : [];
        $form = self::resolveForm($target);

        if (empty($form)) {
            $item['title'] = (string) ($target['title'] ?? '');
            $item['details'][] = 'pominięto patch - nie znaleziono formularza';
            $item['skipped'][] = 'form';
            return $item;
        }

        $item['title'] = (string) ($form['title'] ?? '');
        $item['form_id'] = (int) ($form['id'] ?? 0);
        $applyToManaged = !empty($profile['apply_to_managed']);
        $legacy = PWE_Multilang_Form_Core::isLegacyMultilangWithoutManagedFlag($form);

        if (!$applyToManaged && !$allowUnmanaged && !$legacy) {
            $item['details'][] = 'pominięto patch - tymczasowo wyłączono patche dla formularzy niezarządzanych';
            $item['skipped'][] = 'form';
            return $item;
        }

        if (!$applyToManaged && $legacy) {
            $item['details'][] = 'formularz legacy Multilang bez flagi managed - dopuszczono patch';
        }

        if (!$applyToManaged && PWE_Multilang_Form_Core::isManaged($form)) {
            $item['details'][] = 'pominięto patch - formularz jest zarządzany przez template';
            $item['skipped'][] = 'form';
            return $item;
        }

        $operations = is_array($profile['operations'] ?? null) ? $profile['operations'] : [];

        if (empty($operations)) {
            $item['details'][] = 'brak operacji do wykonania';
            $item['skipped'][] = 'operations';
            return $item;
        }

        $changed = false;

        foreach ($operations as $operation) {
            if (
                is_array($operation)
                && PWE_Multilang_Form_External_Patch_Operation_Handler::apply(
                    $form,
                    $operation,
                    $addOnly,
                    $item
                )
            ) {
                $changed = true;
            }
        }

        if (!$changed) {
            $item['details'][] = 'brak zmian do zapisania';
            return $item;
        }

        PWE_Multilang_Form_External_Patch_Operation_Handler::updateNextFieldId($form);
        $updated = PWE_Multilang_Form_Manual_Modified::run(static function () use ($form) {
            return GFAPI::update_form($form);
        });

        if (is_wp_error($updated) || $updated === false) {
            $item['errors'][] = is_wp_error($updated)
                ? (string) $updated->get_error_message()
                : 'GFAPI::update_form zwróciło false';
            return $item;
        }

        $item['details'][] = 'zapisano patch formularza';
        PWE_Multilang_Form_Finder::resetCache();
        return $item;
    }

    private static function item(string $key): array
    {
        return [
            'template' => 'external-patch:' . ($key !== '' ? $key : 'unknown'),
            'title' => '',
            'form_id' => 0,
            'action' => 'external_patch',
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
            'details' => [],
            'diff' => [],
        ];
    }

    private static function alreadyAppliedItem(string $key, string $version): array
    {
        $item = self::item($key);
        $item['status'] = 'skipped';
        $item['skipped'][] = 'version:' . $version;
        $item['details'][] = 'patch w wersji ' . $version . ' został już zastosowany';
        return $item;
    }

    private static function resolveForm(array $target): ?array
    {
        if (!empty($target['id']) && is_numeric($target['id'])) {
            $form = GFAPI::get_form((int) $target['id']);
            return is_array($form) ? $form : null;
        }

        $title = (string) ($target['title'] ?? '');

        if ($title !== '') {
            return PWE_Multilang_Form_Finder::byTitle($title);
        }

        $contains = trim((string) ($target['title_contains'] ?? ''));

        if ($contains !== '') {
            return self::findByTitle(static function (string $candidate) use ($contains): bool {
                return mb_strpos(mb_strtolower($candidate), mb_strtolower($contains)) !== false;
            });
        }

        $regex = trim((string) ($target['title_regex'] ?? ''));

        if ($regex === '' || !self::isValidRegex($regex)) {
            return null;
        }

        return self::findByTitle(static function (string $candidate) use ($regex): bool {
            return preg_match($regex, $candidate) === 1;
        });
    }

    private static function findByTitle(callable $matches): ?array
    {
        $forms = GFAPI::get_forms();

        if (!is_array($forms)) {
            return null;
        }

        foreach ($forms as $summary) {
            $title = (string) ($summary['title'] ?? ($summary->title ?? ''));
            $id = (int) ($summary['id'] ?? ($summary->id ?? 0));

            if ($title !== '' && $id > 0 && $matches($title)) {
                $form = GFAPI::get_form($id);
                return is_array($form) ? $form : null;
            }
        }

        return null;
    }

    private static function isValidRegex(string $pattern): bool
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            return preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }

    private static function hasOperationType(array $profile, string $type): bool
    {
        foreach ((array) ($profile['operations'] ?? []) as $operation) {
            if (
                is_array($operation)
                && sanitize_key((string) ($operation['type'] ?? '')) === $type
            ) {
                return true;
            }
        }

        return false;
    }
}
