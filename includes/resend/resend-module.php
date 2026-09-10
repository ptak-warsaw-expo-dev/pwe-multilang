<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Resend
{
    public const OPTION_JOB          = 'pwe_multilang_resend_job';
    public const DEFAULT_BATCH_SIZE  = 100;
    public const DEFAULT_DELAY       = 10;
    public const DEFAULT_MIN_AGE_DAYS = 14;
    public const PAGE_SIZE           = 300;

    public static function init(): void
    {
        self::includes();

        PWE_Multilang_Resend_Actions::init();
    }

    private static function includes(): void
    {
        $dir = __DIR__ . '/';

        require_once dirname(__DIR__) . '/gravity-forms/gravity-forms.php';
        require_once $dir . 'core/job-lock.php';
        require_once $dir . 'core/resend-job.php';
        require_once $dir . 'core/gravity-entries.php';
        require_once $dir . 'core/notification-matcher.php';
        require_once $dir . 'core/job-runner.php';
        require_once $dir . 'core/resend-actions.php';
        require_once $dir . 'core/admin-page.php';
    }

    public static function render_admin_page(): void
    {
        PWE_Multilang_Resend_Admin::render();
    }
}
