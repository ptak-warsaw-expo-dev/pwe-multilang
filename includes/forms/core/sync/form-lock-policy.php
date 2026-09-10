<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Decides which Gravity Forms screens and requests are read-only. */
final class PWE_Multilang_Form_Lock_Policy
{
    public const SCOPE_FORM_EDITOR = 'form_editor';
    public const SCOPE_FORM_SETTINGS = 'form_settings';
    public const SCOPE_CONFIRMATION_EXISTING = 'confirmation_existing';
    public const SCOPE_CONFIRMATION_NEW = 'confirmation_new';
    public const SCOPE_NOTIFICATION_EXISTING = 'notification_existing';
    public const SCOPE_NOTIFICATION_NEW = 'notification_new';
    public const SCOPE_QR_EXISTING = 'qr_existing';
    public const SCOPE_QR_NEW = 'qr_new';

    public static function contextFromRequest(): ?array
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
        $page = self::requestValue('page');
        $view = self::requestValue('view');
        $subview = self::requestValue('subview');
        $action = self::requestValue('action');

        if (!in_array($method, ['GET', 'POST'], true)) {
            return null;
        }

        if (wp_doing_ajax()) {
            $ajaxContext = self::contextFromAjaxAction($action);

            if ($ajaxContext !== null) {
                return $ajaxContext;
            }
        }

        if ($page !== 'gf_edit_forms') {
            return null;
        }

        if ($method === 'GET' && !self::hasExplicitMutationMarker()) {
            return null;
        }

        if ($view === '') {
            // The forms list shares this page with the editor. Selected POST
            // IDs belong to a list action, not to the form being edited.
            return self::isFormsListPage() ? null : ['scope' => self::SCOPE_FORM_EDITOR];
        }

        if ($view !== 'settings') {
            return null;
        }

        if ($subview === 'settings') {
            return ['scope' => self::SCOPE_FORM_SETTINGS];
        }

        if ($subview === 'confirmation') {
            return self::existingOrNewScope(
                self::requestValue('cid'),
                self::requestValue('confirmation_id'),
                self::SCOPE_CONFIRMATION_EXISTING,
                self::SCOPE_CONFIRMATION_NEW,
                true
            );
        }

        if ($subview === 'notification') {
            return self::existingOrNewScope(
                self::requestValue('nid'),
                self::requestValue('gform_notification_id'),
                self::SCOPE_NOTIFICATION_EXISTING,
                self::SCOPE_NOTIFICATION_NEW,
                true
            );
        }

        if (in_array($subview, ['pwe_qr', 'qr-code'], true)) {
            return self::existingOrNewScope(
                self::requestValue('fid'),
                self::requestValue('gf_feed_id'),
                self::SCOPE_QR_EXISTING,
                self::SCOPE_QR_NEW,
                true
            );
        }

        return null;
    }

    public static function contextFromScreen(): ?array
    {
        $page = self::getValue('page');
        $view = self::getValue('view');
        $subview = self::getValue('subview');

        if ($page !== 'gf_edit_forms') {
            return null;
        }

        if ($view === '') {
            return self::isFormsListPage() ? null : ['scope' => self::SCOPE_FORM_EDITOR];
        }

        if ($view !== 'settings') {
            return null;
        }

        if ($subview === 'settings') {
            return ['scope' => self::SCOPE_FORM_SETTINGS];
        }

        if ($subview === 'confirmation') {
            return ['scope' => self::getValue('cid') === '0' || self::getValue('cid') === ''
                ? self::SCOPE_CONFIRMATION_NEW
                : self::SCOPE_CONFIRMATION_EXISTING];
        }

        if ($subview === 'notification') {
            return ['scope' => self::getValue('nid') === '0' || self::getValue('nid') === ''
                ? self::SCOPE_NOTIFICATION_NEW
                : self::SCOPE_NOTIFICATION_EXISTING];
        }

        if (in_array($subview, ['pwe_qr', 'qr-code'], true)) {
            return ['scope' => self::getValue('fid') === '0' || self::getValue('fid') === ''
                ? self::SCOPE_QR_NEW
                : self::SCOPE_QR_EXISTING];
        }

        return null;
    }

    public static function isGravityFormsAdmin(): bool
    {
        return is_admin() && strpos(self::requestValue('page'), 'gf_') === 0;
    }

    public static function isFormsListPage(): bool
    {
        return self::requestValue('page') === 'gf_edit_forms' && empty($_GET['id']);
    }

    public static function currentFormId(): int
    {
        $ids = self::requestedFormIds();

        return $ids[0] ?? 0;
    }

    /** @return int[] */
    public static function requestedFormIds(): array
    {
        $ids = [];

        foreach (['id', 'form_id', 'formId', 'form', 'gform_form_id', 'form_ids', 'ids'] as $key) {
            if (!isset($_REQUEST[$key])) {
                continue;
            }

            $value = wp_unslash($_REQUEST[$key]);
            $candidates = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value);

            foreach ((array) $candidates as $candidate) {
                if (!is_scalar($candidate) || !preg_match('/^\d+$/', trim((string) $candidate))) {
                    continue;
                }

                $formId = absint($candidate);

                if ($formId > 0) {
                    $ids[] = $formId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function isManagedForm(int $formId): bool
    {
        if ($formId <= 0 || !class_exists('GFAPI')) {
            return false;
        }

        $form = GFAPI::get_form($formId);
        return is_array($form) && !empty($form[PWE_Multilang_Form_Core::MANAGED_FLAG]);
    }

    public static function managedFormIds(): array
    {
        if (!class_exists('GFAPI')) {
            return [];
        }

        $ids = [];

        foreach ((array) GFAPI::get_forms() as $summary) {
            $formId = (int) (is_array($summary) ? ($summary['id'] ?? 0) : ($summary->id ?? 0));

            if ($formId <= 0) {
                continue;
            }

            $managed = is_array($summary)
                ? !empty($summary[PWE_Multilang_Form_Core::MANAGED_FLAG])
                : !empty($summary->{PWE_Multilang_Form_Core::MANAGED_FLAG});

            if (!$managed) {
                $managed = self::isManagedForm($formId);
            }

            if ($managed) {
                $ids[] = $formId;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function message(): string
    {
        return 'Formularz jest zarządzany przez PWE Multilang. Ręczny zapis w Gravity Forms jest zablokowany. Zmiany wykonaj przez panel PWE Multilang albo aktualizację wtyczki.';
    }

    public static function redirectUrl(): string
    {
        $params = [];

        foreach (['page', 'id', 'view', 'subview', 'nid', 'cid', 'fid'] as $key) {
            if (!empty($_GET[$key])) {
                $params[$key] = sanitize_text_field(wp_unslash((string) $_GET[$key]));
            }
        }

        return !empty($params['page'])
            ? add_query_arg($params, admin_url('admin.php'))
            : admin_url('admin.php?page=gf_edit_forms');
    }

    private static function existingOrNewScope(
        string $shortId,
        string $longId,
        string $existingScope,
        string $newScope,
        bool $fallbackToExisting = false
    ): ?array {
        if ($shortId === '0' || $longId === '0') {
            return ['scope' => $newScope];
        }

        return $shortId !== '' || $longId !== '' || $fallbackToExisting
            ? ['scope' => $existingScope]
            : null;
    }

    private static function contextFromAjaxAction(string $action): ?array
    {
        $action = strtolower($action);

        if ($action === 'form_editor_save_form') {
            return ['scope' => self::SCOPE_FORM_EDITOR];
        }

        if (!self::containsMutationVerb($action)) {
            return null;
        }

        if (strpos($action, 'notification') !== false) {
            return ['scope' => self::SCOPE_NOTIFICATION_EXISTING];
        }

        if (strpos($action, 'confirmation') !== false) {
            return ['scope' => self::SCOPE_CONFIRMATION_EXISTING];
        }

        if (strpos($action, 'qr') !== false) {
            return ['scope' => self::SCOPE_QR_EXISTING];
        }

        return null;
    }

    private static function hasExplicitMutationMarker(): bool
    {
        foreach (
            ['duplicatedcid', 'duplicatednid', 'duplicatedfid', 'duplicatedfeedid', 'duplicate', 'delete', 'activate', 'deactivate']
            as $key
        ) {
            if (isset($_REQUEST[$key])) {
                return true;
            }
        }

        foreach (['action', 'gform_action', 'gf_action'] as $key) {
            if (self::containsMutationVerb(self::requestValue($key))) {
                return true;
            }
        }

        return false;
    }

    private static function containsMutationVerb(string $value): bool
    {
        $value = strtolower($value);

        foreach (['save', 'update', 'delete', 'duplicate', 'activate', 'deactivate', 'trash', 'restore'] as $verb) {
            if (strpos($value, $verb) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function requestValue(string $key): string
    {
        if (!isset($_REQUEST[$key])) {
            return '';
        }

        $value = wp_unslash($_REQUEST[$key]);

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    private static function getValue(string $key): string
    {
        if (!isset($_GET[$key])) {
            return '';
        }

        $value = wp_unslash($_GET[$key]);

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

}
