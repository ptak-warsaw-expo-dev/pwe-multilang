<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Backwards-compatible facade for the managed-form edit lock. */
final class PWE_Multilang_Form_Lock
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'block_admin_save_requests'], 0);
        add_action('wp_ajax_form_editor_save_form', [self::class, 'block_form_editor_ajax'], 0);
        add_filter('gform_form_update_meta', [self::class, 'restore_managed_form_meta'], 1, 3);
        add_filter('gform_pre_form_settings_save', [self::class, 'restore_managed_form_settings'], 1);
        add_filter('gform_pre_notification_save', [self::class, 'block_notification_save'], 1, 2);
        add_filter('gform_pre_confirmation_save', [self::class, 'block_confirmation_save'], 1, 2);
        add_action('gform_pre_delete_feed', [self::class, 'block_qr_feed_deletion'], 1, 2);
        add_filter('gform_noconflict_scripts', [self::class, 'allow_noconflict_script']);
        add_filter('gform_noconflict_styles', [self::class, 'allow_noconflict_style']);
        add_action('admin_notices', [self::class, 'render_admin_notice']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);

        // Kept for compatibility with code that removes or invokes these hooks.
        add_action('admin_head', [self::class, 'render_admin_styles']);
        add_action('admin_footer', [self::class, 'render_admin_scripts']);
    }

    public static function was_save_blocked(): bool
    {
        return PWE_Multilang_Form_Lock_Request_Guard::wasSaveBlocked();
    }

    public static function block_admin_save_requests(): void
    {
        PWE_Multilang_Form_Lock_Request_Guard::blockAdminSaveRequests();
    }

    public static function block_form_editor_ajax(): void
    {
        PWE_Multilang_Form_Lock_Request_Guard::blockFormEditorAjax();
    }

    public static function block_notification_save(array $notification, array $form): array
    {
        return PWE_Multilang_Form_Lock_Request_Guard::blockNotificationSave($notification, $form);
    }

    public static function block_confirmation_save(array $confirmation, array $form): array
    {
        return PWE_Multilang_Form_Lock_Request_Guard::blockConfirmationSave($confirmation, $form);
    }

    public static function restore_managed_form_meta($meta, $form_id, $meta_name)
    {
        return PWE_Multilang_Form_Lock_Request_Guard::restoreManagedFormMeta($meta, $form_id, $meta_name);
    }

    public static function restore_managed_form_settings($form)
    {
        return PWE_Multilang_Form_Lock_Request_Guard::restoreManagedFormSettings($form);
    }

    public static function block_qr_feed_deletion($feed_id, $addon): void
    {
        PWE_Multilang_Form_Lock_Request_Guard::blockQrFeedDeletion($feed_id, $addon);
    }

    public static function render_admin_notice(): void
    {
        PWE_Multilang_Form_Lock_Admin_Decorator::renderNotice();
    }

    public static function enqueue_admin_assets(): void
    {
        PWE_Multilang_Form_Lock_Admin_Decorator::enqueueAssets();
    }

    public static function render_admin_styles(): void
    {
        PWE_Multilang_Form_Lock_Admin_Decorator::enqueueAssets();
    }

    public static function render_admin_scripts(): void
    {
        PWE_Multilang_Form_Lock_Admin_Decorator::renderConfig();
    }

    public static function allow_noconflict_script(array $scripts): array
    {
        $scripts[] = 'pwe-multilang-form-lock';

        return array_values(array_unique($scripts));
    }

    public static function allow_noconflict_style(array $styles): array
    {
        $styles[] = 'pwe-multilang-form-lock';

        return array_values(array_unique($styles));
    }
}
