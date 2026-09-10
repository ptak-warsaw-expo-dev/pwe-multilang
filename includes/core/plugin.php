<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Plugin
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        $is_admin = is_admin();

        require_once PWE_MULTILANG_PATH . 'includes/plugin-update-checker.php';

        add_action('init', [self::class, 'boot_updater']);

        if ($is_admin) {
            require_once PWE_MULTILANG_PATH . 'includes/admin/admin-module.php';
        }

        PWE_Multilang_Module_Registry::load_modules($is_admin);

        if ($is_admin) {
            PWE_Multilang_Admin::init();
        }

        PWE_Multilang_Module_Registry::boot_modules($is_admin);
    }

    public static function boot_updater(): void
    {
        new PWE_Multilang_Updater();
    }
}
