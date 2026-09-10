<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Tests_Actions
{
    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        add_action('admin_post_pwe_multilang_create_test_entries', [self::class, 'create_entries']);
        add_action('admin_post_pwe_multilang_send_test_notifications', [self::class, 'send_notifications']);
        add_action('admin_post_pwe_multilang_reset_update_sync_versions', [self::class, 'reset_update_sync_versions']);
    }

    public static function create_entries(): void
    {
        self::guard('pwe_multilang_create_test_entries');

        $form_id = absint($_POST['form_id'] ?? 0);
        $count = absint($_POST['entries_count'] ?? 0);

        if ($form_id < 1 || PWE_Multilang_Tests_Gravity::get_managed_form($form_id) === null) {
            PWE_Multilang_Tests_Gravity::redirect('Nie wybrano zarządzanego formularza.');
        }

        if ($count < 1) {
            PWE_Multilang_Tests_Gravity::redirect('Podaj prawidłową liczbę wpisów.');
        }

        $result = PWE_Multilang_Tests_Entries::create_entries($form_id, $count);
        PWE_Multilang_Tests_Gravity::redirect((string) ($result['message'] ?? 'Zakończono.'));
    }

    public static function send_notifications(): void
    {
        self::guard('pwe_multilang_send_test_notifications');

        $test_email = sanitize_email(wp_unslash((string) ($_POST['test_email'] ?? '')));
        $notifications = $_POST['notifications'] ?? [];
        $form_filter = sanitize_text_field(wp_unslash((string) ($_POST['form_filter'] ?? 'ALL')));
        $lang_filter = sanitize_text_field(wp_unslash((string) ($_POST['lang_filter'] ?? 'ALL')));
        $notification_filter = sanitize_text_field(
            wp_unslash((string) ($_POST['notification_filter'] ?? 'ALL'))
        );

        if (!is_email($test_email)) {
            PWE_Multilang_Tests_Gravity::redirect('Podaj prawidłowy adres e-mail.');
        }

        if (is_array($notifications) && $notifications) {
            $items = array_map(
                static fn($item): string => sanitize_text_field(wp_unslash((string) $item)),
                $notifications
            );
            $report = PWE_Multilang_Tests_Notifications::send_report($items, $test_email);
        } else {
            $report = PWE_Multilang_Tests_Notifications::send_by_filters_report(
                $test_email,
                $form_filter,
                $lang_filter,
                $notification_filter
            );
        }

        PWE_Multilang_Tests_Gravity::redirect(
            'Wysłano testowo powiadomienia: ' . (int) ($report['sent'] ?? 0)
            . '. Błędy: ' . (int) ($report['failed'] ?? 0) . '.'
        );
    }

    public static function reset_update_sync_versions(): void
    {
        self::guard('pwe_multilang_reset_update_sync_versions');

        $mode = sanitize_key((string) ($_POST['mode'] ?? ''));

        if (!in_array($mode, ['run_now', 'set_1_0_0', 'clear'], true)) {
            PWE_Multilang_Tests_Gravity::redirect('Nieprawidłowy tryb akcji update-sync.');
        }

        if (!class_exists('PWE_Multilang_Form_Update_Sync')) {
            PWE_Multilang_Tests_Gravity::redirect('Moduł synchronizacji Forms jest niedostępny.');
        }

        if ($mode === 'run_now') {
            if (!class_exists('GFAPI')) {
                PWE_Multilang_Tests_Gravity::redirect('Nie można uruchomić update-sync: GFAPI niedostępne.');
            }

            PWE_Multilang_Form_Update_Sync::force_run_now();
            PWE_Multilang_Tests_Gravity::redirect(
                'Uruchomiono update-sync ręcznie. Sprawdź tabelę statusu oraz log synchronizacji.'
            );
        }

        if ($mode === 'set_1_0_0' && method_exists('PWE_Multilang_Form_Update_Sync', 'seed_versions')) {
            PWE_Multilang_Form_Update_Sync::seed_versions('1.0.0');
            PWE_Multilang_Tests_Gravity::redirect('Ustawiono zapisane wersje update-sync na 1.0.0.');
        }

        if ($mode === 'clear' && method_exists('PWE_Multilang_Form_Update_Sync', 'clear_state')) {
            PWE_Multilang_Form_Update_Sync::clear_state();
            PWE_Multilang_Tests_Gravity::redirect('Wyczyszczono zapisane wersje update-sync.');
        }

        PWE_Multilang_Tests_Gravity::redirect('Ta wersja modułu Forms nie obsługuje wybranej akcji.');
    }

    private static function guard(string $nonce_action): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            wp_die('Brak uprawnień.');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('Niedozwolona metoda żądania.');
        }

        check_admin_referer($nonce_action);
    }
}

