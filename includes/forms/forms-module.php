<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Forms
{
    public static function init(): void
    {
        self::load_dependencies();

        PWE_Multilang_Form_Lock::init();
        PWE_Multilang_Form_Manual_Modified::init();
        PWE_Multilang_Form_Migrate_To_New_Forms::init();
        PWE_Multilang_Form_Actions::init();
        PWE_Multilang_Form_Update_Sync::init();
    }

    public static function render_admin_page(): void
    {
        PWE_Multilang_Form_Admin::render();
    }

    private static function load_dependencies(): void
    {
        $files = [
            'includes/forms/core/logging/form-log.php',
            'includes/forms/core/logging/form-log-service.php',
            'includes/forms/core/sync/form-identity.php',
            'includes/forms/core/sync/form-finder.php',
            'includes/forms/core/components/pwe-fields.php',
            'includes/forms/core/components/pwe-meta-settings.php',
            'includes/forms/core/components/pwe-confirmations.php',
            'includes/forms/core/components/pwe-notifications.php',
            'includes/forms/core/build/notification-templates.php',
            'includes/forms/core/build/form-conditional-logic-resolver.php',
            'includes/forms/core/build/form-field-id-allocator.php',
            'includes/forms/core/build/form-merge-tag-resolver.php',
            'includes/forms/core/build/form-notification-preparer.php',
            'includes/forms/core/build/form-confirmation-preparer.php',
            'includes/forms/core/build/form-preparer.php',
            'includes/forms/core/build/qr-handler.php',
            'includes/forms/core/build/template-languages.php',
            'includes/forms/core/build/form-field-translations.php',
            'includes/forms/core/build/form-payload-expander.php',
            'includes/forms/core/sync/form-diff.php',
            'includes/forms/core/template/form-template-capture.php',
            'includes/forms/core/template/form-template-provider-adapter.php',
            'includes/forms/core/template/form-template-registry.php',
            'includes/forms/core/sync/sync-result.php',
            'includes/forms/core/sync/form-operation-lock.php',
            'includes/forms/form-patches/external-patches.php',
            'includes/forms/core/sync/form-external-patch-operation-handler.php',
            'includes/forms/core/sync/form-external-patch-qr-migration.php',
            'includes/forms/core/sync/form-external-patches.php',
            'includes/forms/core/sync/sync-result-store.php',
            'includes/forms/core/sync/sync-actions.php',
            'includes/forms/core/admin-page/form-admin-template-presenter.php',
            'includes/forms/core/admin-page/form-admin-template-renderer.php',
            'includes/forms/core/admin-page/form-admin-result-renderer.php',
            'includes/forms/core/admin-page/form-admin-view.php',
            'includes/forms/core/admin-page/form-admin-page.php',
            'includes/forms/core/write/form-writer-create.php',
            'includes/forms/core/write/form-writer-update.php',
            'includes/forms/core/write/form-writer.php',
            'includes/forms/core/sync/form-sync-service.php',
            'includes/forms/core/forms-core.php',
            'includes/forms/core/migration/migrate-to-new-forms.php',
            'includes/forms/core/sync/form-update-sync.php',
            'includes/forms/core/sync/form-lock-policy.php',
            'includes/forms/core/sync/form-lock-request-guard.php',
            'includes/forms/core/sync/form-lock-admin-decorator.php',
            'includes/forms/core/sync/form-lock.php',
            'includes/forms/core/sync/form-manual-modified.php',
        ];
 
        foreach ($files as $file) {

            require_once PWE_MULTILANG_PATH . $file;
            
        }
    }
}
