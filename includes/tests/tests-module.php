<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Tests
{
    public const PAGE_SLUG         = 'pwe-multilang-tests';
    public const MAX_ENTRIES       = 500;
    public const EXCLUDED_LANGS    = [];
    public const FALLBACK_LANGS    = ['pl', 'en', 'de', 'cs', 'lt', 'lv', 'sk', 'uk', 'ro', 'et', 'it', 'hu', 'fr', 'es'];

    public static function init(): void
    {
        self::includes();

        PWE_Multilang_Tests_Actions::init();
    }

    private static function includes(): void
    {
        $dir = __DIR__ . '/';

        require_once dirname(__DIR__) . '/gravity-forms/gravity-forms.php';
        require_once $dir . 'core/test-gravity-helper.php';
        require_once $dir . 'core/test-entries.php';
        require_once $dir . 'core/test-notifications.php';
        require_once $dir . 'core/test-actions.php';
        require_once $dir . 'core/admin-page.php';
    }

    public static function render_admin_page(): void
    {
        PWE_Multilang_Tests_Admin::render();
    }
}
