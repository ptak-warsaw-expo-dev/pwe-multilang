<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Renders notices/config and loads the read-only Gravity Forms UI assets. */
final class PWE_Multilang_Form_Lock_Admin_Decorator
{
    private const STYLE_HANDLE = 'pwe-multilang-form-lock';
    private const SCRIPT_HANDLE = 'pwe-multilang-form-lock';

    public static function enqueueAssets(): void
    {
        if (!PWE_Multilang_Form_Lock_Policy::isGravityFormsAdmin()) {
            return;
        }

        $css = PWE_MULTILANG_PATH . 'assets/css/form-lock.css';
        $js = PWE_MULTILANG_PATH . 'assets/js/form-lock.js';

        wp_enqueue_style(
            self::STYLE_HANDLE,
            PWE_MULTILANG_URL . 'assets/css/form-lock.css',
            [],
            self::assetVersion($css)
        );

        if (class_exists('GFAPI')) {
            wp_enqueue_script(
                self::SCRIPT_HANDLE,
                PWE_MULTILANG_URL . 'assets/js/form-lock.js',
                ['jquery'],
                self::assetVersion($js),
                true
            );
        }
    }

    public static function renderNotice(): void
    {
        if (!PWE_Multilang_Form_Lock_Policy::isGravityFormsAdmin()) {
            return;
        }

        $notice = PWE_Multilang_Form_Lock_Request_Guard::pullNotice();

        if ($notice !== '') {
            echo '<div class="notice notice-error"><p><strong>PWE Multilang:</strong> '
                . esc_html($notice)
                . '</p></div>';
        }
    }

    public static function renderConfig(): void
    {
        if (!PWE_Multilang_Form_Lock_Policy::isGravityFormsAdmin() || !class_exists('GFAPI')) {
            return;
        }

        $scope = '';
        $formId = PWE_Multilang_Form_Lock_Policy::currentFormId();

        if ($formId > 0 && PWE_Multilang_Form_Lock_Policy::isManagedForm($formId)) {
            $context = PWE_Multilang_Form_Lock_Policy::contextFromScreen();
            $scope = is_array($context) ? (string) ($context['scope'] ?? '') : '';
        }

        $managedIds = PWE_Multilang_Form_Lock_Policy::isFormsListPage()
            ? PWE_Multilang_Form_Lock_Policy::managedFormIds()
            : [];

        if ($scope === '' && empty($managedIds)) {
            return;
        }

        echo '<div id="pwe-mlg-form-lock-config" hidden'
            . ' data-message="' . esc_attr(PWE_Multilang_Form_Lock_Policy::message()) . '"'
            . ' data-scope="' . esc_attr($scope) . '"'
            . ' data-managed-ids="' . esc_attr(wp_json_encode(array_values($managedIds))) . '"'
            . '></div>';
    }

    private static function assetVersion(string $path): string
    {
        return file_exists($path)
            ? (string) filemtime($path)
            : (defined('PWE_MULTILANG_VERSION') ? PWE_MULTILANG_VERSION : '1');
    }
}
