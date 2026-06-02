<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        // Autoupdate
        add_action('init', function() {
            new PWE_Multilang_Updater();
        });

        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies(): void
    {
        require_once PWE_MULTILANG_PATH . 'includes/admin/class-pwe-multilang-admin.php';
        require_once PWE_MULTILANG_PATH . 'includes/admin/class-pwe-multilang-gf-filter-ui.php';

        require_once PWE_MULTILANG_PATH . 'includes/forms/class-pwe-multilang-forms.php';
        require_once PWE_MULTILANG_PATH . 'includes/forms/class-pwe-multilang-form-translations.php';

        require_once PWE_MULTILANG_PATH . 'includes/pages/class-pwe-multilang-pages.php';

    }

    private function init_hooks(): void
    {
        if (is_admin()) {
            PWE_Multilang_Admin::init();
            PWE_Multilang_GF_Notifications_UI::init();
        }

        PWE_Multilang_Forms::init();
        PWE_Multilang_Form_Translations::init();

    }
}