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
        PWE_Multilang_Form_Actions::init();
        PWE_Multilang_Form_Update_Sync::init();
    }

    public static function render_admin_page(): void
    {
        PWE_Multilang_Form_Admin::render();
    }

    public static function load_dependencies(): void
    {
        $files = [
            'modules/forms/core/logging/form-log.php',
            'modules/forms/core/logging/form-log-service.php',
            'modules/forms/core/sync/form-identity.php',
            'modules/forms/core/sync/form-finder.php',
            'modules/forms/core/components/pwe-fields.php',
            'modules/forms/core/components/pwe-meta-settings.php',
            'modules/forms/core/components/pwe-confirmations.php',
            'modules/forms/core/components/pwe-notifications.php',
            'modules/forms/core/build/notification-templates.php',
            'modules/forms/core/build/form-conditional-logic-resolver.php',
            'modules/forms/core/build/form-field-id-allocator.php',
            'modules/forms/core/build/form-merge-tag-resolver.php',
            'modules/forms/core/build/form-notification-preparer.php',
            'modules/forms/core/build/form-confirmation-preparer.php',
            'modules/forms/core/build/form-preparer.php',
            'modules/forms/core/build/qr-handler.php',
            'modules/forms/core/build/template-languages.php',
            'modules/form-translations/form-translations-module.php',
            'modules/forms/core/build/form-payload-expander.php',
            'modules/forms/core/sync/form-diff.php',
            'modules/forms/core/template/form-template-capture.php',
            'modules/forms/core/template/form-template-provider-adapter.php',
            'modules/forms/core/template/form-template-registry.php',
            'modules/forms/core/sync/sync-result.php',
            'modules/forms/core/sync/form-operation-lock.php',
            'modules/forms/form-patches/external-patches.php',
            'modules/forms/core/sync/form-external-patch-operation-handler.php',
            'modules/forms/core/sync/form-external-patch-qr-migration.php',
            'modules/forms/core/sync/form-external-patches.php',
            'modules/forms/core/sync/sync-result-store.php',
            'modules/forms/core/sync/sync-actions.php',
            'modules/forms/core/admin-page/form-admin-template-presenter.php',
            'modules/forms/core/admin-page/form-admin-template-renderer.php',
            'modules/forms/core/admin-page/form-admin-result-renderer.php',
            'modules/forms/core/admin-page/form-admin-view.php',
            'modules/forms/core/admin-page/form-admin-page.php',
            'modules/forms/core/write/form-writer.php',
            'modules/forms/core/sync/form-sync-service.php',
            'modules/forms/core/forms-core.php',
            'modules/forms/core/sync/form-update-sync.php',
            'modules/forms/core/sync/form-lock-policy.php',
            'modules/forms/core/sync/form-lock-request-guard.php',
            'modules/forms/core/sync/form-lock-admin-decorator.php',
            'modules/forms/core/sync/form-lock.php',
            'modules/forms/core/sync/form-manual-modified.php',
        ];
 
        foreach ($files as $file) {

            require_once PWE_MULTILANG_PATH . $file;
            
        }
    }
}
