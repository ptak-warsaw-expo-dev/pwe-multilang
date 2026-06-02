<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Core
{
    public static function run(): void
    {
        PWE_Multilang_Form_Global_Settings::init();

        if (class_exists('GFAPI')) {
            PWE_Multilang_Form_Global_Settings::enable_honeypot_on_all();
        }

        self::runForms();
        self::runNotifications();
    }

    private static function runForms(): void
    {
        $formsDir = dirname(__DIR__) . '/form-templates';
        $options = get_option('pwe_general_options', []);
        $forms_year = isset($options['pwe_create_forms_year']) ? (int) $options['pwe_create_forms_year'] : (int) date('Y');

        foreach (glob($formsDir . '/*', GLOB_ONLYDIR) ?: [] as $formDir) {
            foreach (glob($formDir . '/*.php') ?: [] as $file) {
                $before = get_declared_classes();
                require_once $file;
                $after = get_declared_classes();

                foreach (array_diff($after, $before) as $class) {
                    if (method_exists($class, 'apply')) {
                        $class::apply($forms_year);
                    }
                }
            }
        }
    }

    private static function runNotifications(): void
    {
        $notifDir = dirname(__DIR__) . '/notification';

        if (!is_dir($notifDir)) {
            return;
        }

        foreach (glob($notifDir . '/*.php') ?: [] as $file) {
            $before = get_declared_classes();
            require_once $file;
            $after = get_declared_classes();

            foreach (array_diff($after, $before) as $class) {
                if (method_exists($class, 'apply')) {
                    $class::apply();
                }
            }
        }
    }
}
