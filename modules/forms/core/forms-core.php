<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Backwards-compatible facade for the forms subsystem.
 */
final class PWE_Multilang_Form_Core
{
    public const MANAGED_FLAG = PWE_Multilang_GF_Form_Repository::MANAGED_FLAG;
    public const MANUAL_MODIFIED_FLAG = 'pwe_multilang_manual_modified';

    public static function getTemplateTitle(string $templateSlug, int $formsYear): ?string
    {
        return PWE_Multilang_Form_Template_Registry::getTemplateTitle($templateSlug, $formsYear);
    }

    public static function getTemplates(?int $formsYear = null): array
    {
        return PWE_Multilang_Form_Template_Registry::getTemplates($formsYear);
    }

    public static function sync(
        string $scope,
        array $templateSlugs,
        int $formsYear,
        bool $addOnly,
        array $overwriteModified = [],
        bool $allowCreate = true
    ): array {
        $token = PWE_Multilang_Form_Operation_Lock::acquire('form_core_sync');

        if ($token === null) {
            return PWE_Multilang_Form_Sync_Result::locked($scope, $addOnly);
        }

        try {
            return PWE_Multilang_Form_Sync_Service::sync(
                $scope,
                $templateSlugs,
                $formsYear,
                $addOnly,
                $overwriteModified,
                $allowCreate
            );
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }

    /** Full synchronization of managed forms, preserving each form ID and year. */
    public static function syncAll(array $overwriteModified = []): array
    {
        $token = PWE_Multilang_Form_Operation_Lock::acquire('all_forms_sync');
        if ($token === null) {
            return PWE_Multilang_Form_Sync_Result::locked('forms', false);
        }
        try {
            return PWE_Multilang_Form_Sync_Service::syncAll($overwriteModified);
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }

    public static function isManaged(?array $form): bool
    {
        return PWE_Multilang_GF_Form_Repository::is_managed($form);
    }

    public static function isLegacyMultilangWithoutManagedFlag(?array $form): bool
    {
        if (empty($form) || self::isManaged($form)) {
            return false;
        }

        $title = (string) ($form['title'] ?? '');
        return $title !== '' && stripos($title, 'multilang') !== false;
    }

    public static function isManualModified(?array $form): bool
    {
        return !empty($form)
            && self::isManaged($form)
            && !empty($form[self::MANUAL_MODIFIED_FLAG]);
    }
}
