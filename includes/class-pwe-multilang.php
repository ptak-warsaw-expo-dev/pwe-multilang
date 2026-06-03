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

        add_action('wp_loaded', function () {
            if (empty($_GET['gf_dump_id'])) {
                return;
            }

            if (!is_user_logged_in()) {
                wp_die('Brak dostępu.');
            }

            $user = wp_get_current_user();
            $allowed_email = 'piotr.krupniewski@warsawexpo.eu';

            if (
                empty($user->user_email)
                || strtolower($user->user_email) !== strtolower($allowed_email)
                || !current_user_can('manage_options')
            ) {
                wp_die('Brak dostępu.');
            }

            if (!class_exists('GFAPI')) {
                wp_die('GFAPI nie istnieje.');
            }

            $form_id = absint($_GET['gf_dump_id']);

            if (!$form_id) {
                wp_die('Brak ID formularza.');
            }

            $form = GFAPI::get_form($form_id);

            if (empty($form)) {
                wp_die('Nie znaleziono formularza ID: ' . esc_html($form_id));
            }

            header('Content-Type: text/plain; charset=utf-8');

            var_dump($form);
            exit;
        });
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