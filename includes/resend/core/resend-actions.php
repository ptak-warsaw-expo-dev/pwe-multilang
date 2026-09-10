<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Resend_Actions
{
    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        add_action('admin_init', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        $action = sanitize_key((string) wp_unslash($_POST['pwe_resend_action'] ?? ''));

        if ($page !== 'pwe-multilang-resend' || $action === '') {
            return;
        }

        check_admin_referer('pwe_multilang_resend_action');

        if ($action === 'start') {
            $form_ids = array_values(array_unique(array_filter(array_map(
                'absint',
                (array) ($_POST['form_ids'] ?? [])
            ))));
            $batch = max(1, min(200, (int) ($_POST['batch_size'] ?? PWE_Multilang_Resend::DEFAULT_BATCH_SIZE)));
            $delay = max(3, min(60, (int) ($_POST['delay'] ?? PWE_Multilang_Resend::DEFAULT_DELAY)));
            $min_age_days = isset($_POST['min_age_days'])
                ? max(1, min(3650, absint(wp_unslash($_POST['min_age_days']))))
                : PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS;

            PWE_Multilang_Resend_Job::create(
                $form_ids,
                $batch,
                $delay,
                !empty($_POST['include_all_entries']),
                $min_age_days
            );
            self::safe_redirect(self::admin_url());
        }

        if ($action === 'run') {
            PWE_Multilang_Resend_Job::mutate(
                static function (array &$job, string $lock_token): void {
                    PWE_Multilang_Resend_Job_Runner::run($job, $lock_token);
                }
            );
            self::safe_redirect(self::admin_url(['pwe_resend_autorun' => 1]));
        }

        if ($action === 'pause') {
            PWE_Multilang_Resend_Job::mutate(
                static function (array &$job, string $lock_token): void {
                    $job['status'] = 'paused';
                }
            );
            self::safe_redirect(self::admin_url());
        }

        if ($action === 'resume') {
            PWE_Multilang_Resend_Job::mutate(
                static function (array &$job, string $lock_token): void {
                    $job['status'] = 'running';
                    PWE_Multilang_Resend_Job::clear_block($job);
                }
            );
            self::safe_redirect(self::admin_url(['pwe_resend_autorun' => 1]));
        }

        if ($action === 'reset') {
            PWE_Multilang_Resend_Job::delete();
            self::safe_redirect(self::admin_url());
        }

        wp_die('Nieznana akcja resend.');
    }

    public static function admin_url(array $args = []): string
    {
        return add_query_arg($args, admin_url('admin.php?page=pwe-multilang-resend'));
    }

    public static function action_form(string $action, string $label, string $class = 'button'): string
    {
        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url(self::admin_url()); ?>" class="pwe-inline-form">
            <?php wp_nonce_field('pwe_multilang_resend_action'); ?>
            <input type="hidden" name="pwe_resend_action" value="<?php echo esc_attr($action); ?>">
            <button type="submit" class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></button>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    private static function safe_redirect(string $url): void
    {
        wp_safe_redirect($url);
        exit;
    }
}
