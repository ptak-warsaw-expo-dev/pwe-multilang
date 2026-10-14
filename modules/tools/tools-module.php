<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Tools
{
    public const PAGE_SLUG         = 'pwe-multilang-tools';
    public const MAX_ENTRIES       = 500;
    public const EXCLUDED_LANGS    = [];
    public const FALLBACK_LANGS    = ['pl', 'en', 'de', 'cs', 'lt', 'lv', 'sk', 'uk', 'ro', 'et', 'it', 'hu', 'fr', 'es'];

    public static function init(): void
    {
        self::includes();

        PWE_Multilang_Tools_Actions::init();
    }

    private static function includes(): void
    {
        $dir = __DIR__ . '/';

        require_once $dir . 'core/notification-name.php';
        require_once $dir . 'core/tools-gravity-helper.php';
        require_once $dir . 'core/tools-entries.php';
        require_once $dir . 'core/tools-notifications.php';
        require_once $dir . 'core/tools-actions.php';
        require_once $dir . 'core/admin-page.php';
    }

    public static function render_admin_page(): void
    {
        PWE_Multilang_Tools_Admin::render();
    }
}
