<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Blocks writes for managed forms while generator-owned writes remain allowed. */
final class PWE_Multilang_Form_Lock_Request_Guard
{
    private const BLOCKED_FLAG = 'pwe_mlg_form_save_blocked';
    private const NOTICE_TRANSIENT = 'pwe_mlg_form_lock_notice_';

    public static function wasSaveBlocked(): bool
    {
        return !empty($GLOBALS[self::BLOCKED_FLAG]);
    }

    public static function blockAdminSaveRequests(): void
    {
        if (
            PWE_Multilang_Form_Manual_Modified::isGeneratorRunning()
            || !is_admin()
            || !class_exists('GFAPI')
        ) {
            return;
        }

        $context = PWE_Multilang_Form_Lock_Policy::contextFromRequest();
        $formIds = PWE_Multilang_Form_Lock_Policy::requestedFormIds();
        $scope = is_array($context) ? (string) ($context['scope'] ?? '') : '';
        $isQrContext = in_array($scope, [
            PWE_Multilang_Form_Lock_Policy::SCOPE_QR_EXISTING,
            PWE_Multilang_Form_Lock_Policy::SCOPE_QR_NEW,
        ], true);
        $qrMutation = $context === null || $isQrContext
            ? self::getQrMutationFromRequest()
            : null;

        if ($context === null) {
            if ($qrMutation === null) {
                return;
            }

            $context = ['scope' => PWE_Multilang_Form_Lock_Policy::SCOPE_QR_EXISTING];
        }

        if ($qrMutation !== null && !empty($qrMutation['form_ids'])) {
            $formIds = $qrMutation['form_ids'];
        }

        $formId = self::firstManagedFormId($formIds);

        if ($formId <= 0) {
            return;
        }

        self::markBlocked($formId);

        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => PWE_Multilang_Form_Lock_Policy::message(),
                'pwe_mlg_blocked' => true,
                'scope' => $context['scope'],
            ], 403);
        }

        wp_safe_redirect(PWE_Multilang_Form_Lock_Policy::redirectUrl());
        exit;
    }

    public static function blockFormEditorAjax(): void
    {
        if (PWE_Multilang_Form_Manual_Modified::isGeneratorRunning() || !class_exists('GFAPI')) {
            return;
        }

        $formId = self::firstManagedFormId(PWE_Multilang_Form_Lock_Policy::requestedFormIds());

        if ($formId <= 0) {
            return;
        }

        self::markBlocked($formId);
        wp_send_json_error([
            'message' => PWE_Multilang_Form_Lock_Policy::message(),
            'pwe_mlg_blocked' => true,
            'scope' => PWE_Multilang_Form_Lock_Policy::SCOPE_FORM_EDITOR,
        ], 403);
    }

    public static function blockNotificationSave(array $notification, array $form): array
    {
        if (PWE_Multilang_Form_Manual_Modified::isGeneratorRunning()) {
            return $notification;
        }

        $context = PWE_Multilang_Form_Lock_Policy::contextFromRequest();

        if (
            $context === null
            || !in_array($context['scope'], [
                PWE_Multilang_Form_Lock_Policy::SCOPE_NOTIFICATION_EXISTING,
                PWE_Multilang_Form_Lock_Policy::SCOPE_NOTIFICATION_NEW,
            ], true)
        ) {
            return $notification;
        }

        $formId = !empty($form['id'])
            ? absint($form['id'])
            : PWE_Multilang_Form_Lock_Policy::currentFormId();

        if (!PWE_Multilang_Form_Lock_Policy::isManagedForm($formId)) {
            return $notification;
        }

        self::markBlocked($formId);
        $currentForm = GFAPI::get_form($formId);
        $notificationId = $notification['id'] ?? '';

        return $notificationId !== '' && !empty($currentForm['notifications'][$notificationId])
            ? $currentForm['notifications'][$notificationId]
            : [];
    }

    public static function blockConfirmationSave(array $confirmation, array $form): array
    {
        if (PWE_Multilang_Form_Manual_Modified::isGeneratorRunning()) {
            return $confirmation;
        }

        $context = PWE_Multilang_Form_Lock_Policy::contextFromRequest();

        if (
            $context === null
            || !in_array($context['scope'], [
                PWE_Multilang_Form_Lock_Policy::SCOPE_CONFIRMATION_EXISTING,
                PWE_Multilang_Form_Lock_Policy::SCOPE_CONFIRMATION_NEW,
            ], true)
        ) {
            return $confirmation;
        }

        $formId = !empty($form['id'])
            ? absint($form['id'])
            : PWE_Multilang_Form_Lock_Policy::currentFormId();

        if (!PWE_Multilang_Form_Lock_Policy::isManagedForm($formId)) {
            return $confirmation;
        }

        self::markBlocked($formId);
        $currentForm = GFAPI::get_form($formId);
        $confirmationId = $confirmation['id'] ?? '';

        return $confirmationId !== '' && !empty($currentForm['confirmations'][$confirmationId])
            ? $currentForm['confirmations'][$confirmationId]
            : [];
    }

    public static function restoreManagedFormMeta($meta, $formId, $metaName)
    {
        if (PWE_Multilang_Form_Manual_Modified::isGeneratorRunning()) {
            return $meta;
        }

        $formId = (int) $formId;

        if (
            !in_array($metaName, ['display_meta', 'notifications', 'confirmations'], true)
            || !PWE_Multilang_Form_Lock_Policy::isManagedForm($formId)
        ) {
            return $meta;
        }

        self::markBlocked($formId);
        $currentForm = GFAPI::get_form($formId);

        if (empty($currentForm) || !is_array($currentForm)) {
            return $meta;
        }

        if ($metaName === 'notifications') {
            return is_array($currentForm['notifications'] ?? null)
                ? $currentForm['notifications']
                : [];
        }

        if ($metaName === 'confirmations') {
            return is_array($currentForm['confirmations'] ?? null)
                ? $currentForm['confirmations']
                : [];
        }

        unset(
            $currentForm['notifications'],
            $currentForm['confirmations'],
            $currentForm['notification'],
            $currentForm['confirmation'],
            $currentForm['autoResponder']
        );

        return $currentForm;
    }

    public static function blockQrFeedDeletion($feedId, $addon): void
    {
        if (
            PWE_Multilang_Form_Manual_Modified::isGeneratorRunning()
            || !is_admin()
            || !class_exists('GFAPI')
        ) {
            return;
        }

        $feed = self::getFeed((int) $feedId, $addon);

        if (
            empty($feed)
            || !in_array((string) ($feed['addon_slug'] ?? ''), ['pwe_qr', 'qr-code'], true)
        ) {
            return;
        }

        $formId = (int) ($feed['form_id'] ?? 0);

        if (!PWE_Multilang_Form_Lock_Policy::isManagedForm($formId)) {
            return;
        }

        self::markBlocked($formId);

        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => PWE_Multilang_Form_Lock_Policy::message(),
                'pwe_mlg_blocked' => true,
                'scope' => PWE_Multilang_Form_Lock_Policy::SCOPE_QR_EXISTING,
            ], 403);
        }

        wp_safe_redirect(PWE_Multilang_Form_Lock_Policy::redirectUrl());
        exit;
    }

    public static function restoreManagedFormSettings($form)
    {
        if (PWE_Multilang_Form_Manual_Modified::isGeneratorRunning()) {
            return $form;
        }

        $context = PWE_Multilang_Form_Lock_Policy::contextFromRequest();
        $form = is_array($form) ? $form : (array) $form;
        $formId = !empty($form['id'])
            ? absint($form['id'])
            : PWE_Multilang_Form_Lock_Policy::currentFormId();

        if (
            $context === null
            || $context['scope'] !== PWE_Multilang_Form_Lock_Policy::SCOPE_FORM_SETTINGS
            || !PWE_Multilang_Form_Lock_Policy::isManagedForm($formId)
        ) {
            return $form;
        }

        self::markBlocked($formId);
        $currentForm = GFAPI::get_form($formId);

        return !empty($currentForm) ? $currentForm : $form;
    }

    public static function pullNotice(): string
    {
        $key = self::NOTICE_TRANSIENT . get_current_user_id();
        $notice = get_transient($key);

        if ($notice === false) {
            return '';
        }

        delete_transient($key);
        return (string) $notice;
    }

    private static function markBlocked(int $formId): void
    {
        $GLOBALS[self::BLOCKED_FLAG] = true;
        set_transient(
            self::NOTICE_TRANSIENT . get_current_user_id(),
            PWE_Multilang_Form_Lock_Policy::message(),
            60
        );
    }

    private static function getFeed(int $feedId, $addon): array
    {
        if ($feedId <= 0) {
            return [];
        }

        if (method_exists('GFAPI', 'get_feed')) {
            $feed = GFAPI::get_feed($feedId);

            if (is_array($feed)) {
                return $feed;
            }
        }

        if (is_object($addon) && method_exists($addon, 'get_feed')) {
            $feed = $addon->get_feed($feedId);

            return is_array($feed) ? $feed : [];
        }

        return [];
    }

    private static function firstManagedFormId(array $formIds): int
    {
        foreach ($formIds as $formId) {
            $formId = (int) $formId;

            if (PWE_Multilang_Form_Lock_Policy::isManagedForm($formId)) {
                return $formId;
            }
        }

        return 0;
    }

    private static function getQrMutationFromRequest(): ?array
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));

        if (!in_array($method, ['GET', 'POST'], true)) {
            return null;
        }

        if (!self::hasMutationMarker()) {
            return null;
        }

        $subview = self::requestValue('subview');
        $hasQrEvidence = in_array($subview, ['pwe_qr', 'qr-code'], true);

        foreach (['action', 'action2', 'gform_action', 'gf_action', 'bulk_action', 'bulk_action2'] as $key) {
            $action = strtolower(self::requestValue($key));

            if (strpos($action, 'pwe_qr') !== false || strpos($action, 'qr-code') !== false) {
                $hasQrEvidence = true;
            }
        }

        foreach (['addon', 'addon_slug', 'feed_addon_slug', 'slug'] as $key) {
            if (in_array(self::requestValue($key), ['pwe_qr', 'qr-code'], true)) {
                $hasQrEvidence = true;
            }
        }

        $hasExplicitFeedId = false;
        $feedFormIds = [];

        foreach (
            [
                'fid',
                'feed_id',
                'feedId',
                'gf_feed_id',
                'duplicatedfid',
                'duplicatedfeedid',
                'feed_ids',
                'feeds',
                'feed',
            ]
            as $key
        ) {
            if (isset($_REQUEST[$key])) {
                $hasExplicitFeedId = true;
            }

            $rawFeedIds = isset($_REQUEST[$key]) ? wp_unslash($_REQUEST[$key]) : null;

            foreach (self::numericIds($rawFeedIds) as $feedId) {
                $feed = self::getFeed($feedId, null);

                if (in_array((string) ($feed['addon_slug'] ?? ''), ['pwe_qr', 'qr-code'], true)) {
                    $feedFormIds[] = (int) ($feed['form_id'] ?? 0);
                }
            }
        }

        $feedFormIds = array_values(array_unique(array_filter($feedFormIds)));

        if ($feedFormIds !== []) {
            return ['form_ids' => $feedFormIds];
        }

        if ($hasQrEvidence && wp_doing_ajax() && !$hasExplicitFeedId && isset($_REQUEST['id'])) {
            $rawId = wp_unslash($_REQUEST['id']);
            $feed = self::getFeed(is_scalar($rawId) ? absint($rawId) : 0, null);

            if (in_array((string) ($feed['addon_slug'] ?? ''), ['pwe_qr', 'qr-code'], true)) {
                return ['form_ids' => [(int) ($feed['form_id'] ?? 0)]];
            }
        }

        return $hasQrEvidence
            ? ['form_ids' => PWE_Multilang_Form_Lock_Policy::requestedFormIds()]
            : null;
    }

    private static function requestValue(string $key): string
    {
        if (!isset($_REQUEST[$key])) {
            return '';
        }

        $value = wp_unslash($_REQUEST[$key]);

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    private static function hasMutationMarker(): bool
    {
        foreach (
            ['duplicatedfid', 'duplicatedfeedid', 'duplicate', 'delete', 'activate', 'deactivate']
            as $key
        ) {
            if (isset($_REQUEST[$key])) {
                return true;
            }
        }

        if (self::requestValue('gform-settings-save') === 'save') {
            return true;
        }

        foreach (['action', 'action2', 'gform_action', 'gf_action', 'bulk_action', 'bulk_action2'] as $key) {
            $value = strtolower(self::requestValue($key));

            foreach (['save', 'update', 'delete', 'duplicate', 'activate', 'deactivate', 'trash', 'restore'] as $verb) {
                if (strpos($value, $verb) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return int[] */
    private static function numericIds($value, int $depth = 0): array
    {
        if ($depth > 3) {
            return [];
        }

        if (is_array($value)) {
            $ids = [];

            foreach ($value as $item) {
                $ids = array_merge($ids, self::numericIds($item, $depth + 1));
            }

            return array_values(array_unique($ids));
        }

        if (!is_scalar($value)) {
            return [];
        }

        $ids = [];

        foreach (preg_split('/\s*,\s*/', trim((string) $value)) ?: [] as $candidate) {
            if (preg_match('/^\d+$/', $candidate) !== 1) {
                continue;
            }

            $id = absint($candidate);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
