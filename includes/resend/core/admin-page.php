<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Resend_Admin
{
    public static function render(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            wp_die('Brak uprawnień.');
        }

        if (!class_exists('GFAPI') || !class_exists('GFCommon')) {
            PWE_Multilang_Admin_UI::cardOpen('pwe-card');
            PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-warning"></span> Gravity Forms wymagane');
            PWE_Multilang_Admin_UI::cardDesc('Gravity Forms nie jest aktywne. Aktywuj wtyczkę, aby korzystać z modułu Resend.');
            PWE_Multilang_Admin_UI::cardClose();
            return;
        }

        $job = PWE_Multilang_Resend_Job::get();

        PWE_Multilang_Admin_UI::cardOpen('pwe-card');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-email-alt2"></span> Resend');
        PWE_Multilang_Admin_UI::cardDesc('Masowa ponowna wysyłka powiadomień Gravity Forms dla formularzy z powiadomieniami resend. Wysyłka odbywa się partiami &mdash; bez ładowania wszystkich wpisów do pamięci jednocześnie.');
        echo '<hr class="pwe-divider">';

        if ($job) {
            self::render_job_screen($job);
        } else {
            self::render_start_screen();
        }

        PWE_Multilang_Admin_UI::cardClose();
    }

    /* ------------------------------------------------------------------ */
    /* Start screen                                                         */
    /* ------------------------------------------------------------------ */

    private static function render_start_screen(): void
    {
        $forms = GFAPI::get_forms();

        if (empty($forms)) {
            echo '<p class="pwe-empty-text">Brak formularzy Gravity Forms.</p>';
            return;
        }

        $preview_all = isset($_GET['pwe_resend_preview_all'])
            ? (bool) (int) $_GET['pwe_resend_preview_all']
            : true;

        self::render_preview_toggle($preview_all);

        $rows = self::build_form_rows($forms, $preview_all);

        if (!$rows) {
            echo '<p class="pwe-empty-text">Nie znaleziono formularzy z powiadomieniami resend.</p>';
            return;
        }

        echo '<form method="post">';
        wp_nonce_field('pwe_multilang_resend_action');
        echo '<input type="hidden" name="pwe_resend_action" value="start">';

        self::render_settings_bar();

        echo '<p class="pwe-section-heading">Formularze z resendami</p>';
        echo '<div class="pwe-resend-list">';

        foreach ($rows as $row) {
            self::render_form_card($row);
        }

        echo '</div>';

        echo '<div class="pwe-resend-actions">';
        PWE_Multilang_Admin_UI::button(['type' => 'submit', 'class' => 'pwe-btn'], '<span class="dashicons dashicons-update"></span> Utwórz proces resendów');
        echo '</div>';

        echo '</form>';
    }

    private static function render_preview_toggle(bool $preview_all): void
    {
        $toggle_url  = PWE_Multilang_Resend_Actions::admin_url(['pwe_resend_preview_all' => $preview_all ? 0 : 1]);
        $toggle_text = $preview_all
            ? 'Pokaż tylko starsze niż 14 dni'
            : 'Pokaż wszystkie wpisy';

        echo '<p class="pwe-resend-muted" style="margin-bottom:16px;">';
        echo 'Podgląd: <strong>' . ($preview_all ? 'wszystkie wpisy' : 'starsze niż 14 dni') . '</strong>';
        echo ' &mdash; <a href="' . esc_url($toggle_url) . '">' . esc_html($toggle_text) . '</a>';
        echo '</p>';
    }

    private static function build_form_rows(array $forms, bool $preview_all): array
    {
        $rows = [];

        foreach ($forms as $form) {
            $full_form = GFAPI::get_form((int) $form['id']);

            if (!$full_form) {
                continue;
            }

            $notifications = PWE_Multilang_Resend_Gravity::get_resend_notifications($full_form);

            if (!$notifications) {
                continue;
            }

            $stats = PWE_Multilang_Resend_Gravity::scan_form_stats(
                $full_form,
                $notifications,
                $preview_all,
                PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS,
                true
            );

            $rows[] = [
                'form'                => $full_form,
                'resend_count'        => count($notifications),
                'notification_groups' => self::group_notifications_for_display($notifications),
                'notification_lang_counts' => empty($stats['error'])
                    ? ($stats['notification_lang_counts'] ?? [])
                    : [],
                'entries_count'       => $stats['entries_count'],
                'matched_count'       => $stats['matched_count'],
                'error'               => (string) ($stats['error'] ?? ''),
            ];
        }

        usort($rows, static fn($a, $b) => $b['resend_count'] <=> $a['resend_count']);

        return $rows;
    }

    private static function render_form_card(array $row): void
    {
        $form    = $row['form'];
        $form_id = (int) $form['id'];
        ?>
        <div class="pwe-resend-form-card">

            <div class="pwe-resend-form-head">
                <?php
                PWE_Multilang_Admin_UI::checkbox(
                    [
                        'type' => 'checkbox',
                        'name' => 'form_ids[]',
                        'value' => (string) $form_id,
                    ],
                    'pwe-checkbox-container',
                    'label'
                );
                ?>
                <div style="flex:1;min-width:0;">
                    <div class="pwe-resend-title"><?php echo esc_html((string) $form['title']); ?></div>
                    <div class="pwe-resend-muted">
                        ID: <?php echo esc_html($form_id); ?>
                        &nbsp;&middot;&nbsp;
                        Wpisy w podglądzie: <?php echo $row['error'] === '' ? esc_html($row['entries_count']) : '&mdash;'; ?>
                        &nbsp;&middot;&nbsp;
                        Resendy do wysłania: <strong><?php echo $row['error'] === '' ? esc_html($row['matched_count']) : '&mdash;'; ?></strong>
                    </div>
                    <?php if ($row['error'] !== '') : ?>
                        <p class="pwe-resend-muted" style="color:#b32d2e;margin:6px 0 0;">
                            <?php echo esc_html($row['error']); ?> Spróbuj odświeżyć podgląd.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pwe-resend-section">
                <div class="pwe-resend-section-title">Powiadomienia (<?php echo esc_html($row['resend_count']); ?>)</div>
                <?php self::render_notification_groups($row['notification_groups'], $row['notification_lang_counts'] ?? []); ?>
            </div>

        </div>
        <?php
    }

    private static function render_settings_bar(): void
    {
        $batch_options = [25, 50, 100, 200];
        $delay_options = [5, 10, 15, 30, 60];
        ?>
        <div class="pwe-resend-settings">

            <div class="pwe-resend-settings-group">
                <label>Wielkość partii</label>
                <select name="batch_size">
                    <?php foreach ($batch_options as $size) : ?>
                        <option value="<?php echo esc_attr($size); ?>"
                            <?php selected($size, PWE_Multilang_Resend::DEFAULT_BATCH_SIZE); ?>>
                            <?php echo esc_html($size); ?> wiadomości
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pwe-resend-settings-group">
                <label>Przerwa między partiami</label>
                <select name="delay">
                    <?php foreach ($delay_options as $delay) : ?>
                        <option value="<?php echo esc_attr($delay); ?>"
                            <?php selected($delay, PWE_Multilang_Resend::DEFAULT_DELAY); ?>>
                            <?php echo esc_html($delay); ?> sekund
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pwe-resend-settings-group">
                <label for="pwe_resend_min_age_days">Wysyłaj wpisy starsze niż</label>
                <div class="pwe-resend-days-input">
                    <input
                        type="number"
                        id="pwe_resend_min_age_days"
                        name="min_age_days"
                        min="1"
                        max="3650"
                        step="1"
                        value="<?php echo esc_attr((string) PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS); ?>"
                        class="small-text"
                    >
                    <span class="pwe-resend-muted">dni</span>
                </div>
            </div>

            <div class="pwe-resend-settings-group">
                <label>Zakres wysyłki</label>
                <div class="pwe-resend-checkbox-row">
                    <?php
                    PWE_Multilang_Admin_UI::checkbox(
                        [
                            'type' => 'checkbox',
                            'name' => 'include_all_entries',
                            'value' => '1',
                        ],
                        'pwe-checkbox-container',
                        'label'
                    );
                    ?>
                    <span>Uwzględnij wszystkie wpisy</span>
                </div>
            </div>

        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Job screen                                                           */
    /* ------------------------------------------------------------------ */

    private static function render_job_screen(array $job): void
    {
        $stats = PWE_Multilang_Resend_Job::stats($job);

        echo '<p class="pwe-section-heading">Status procesu</p>';

        echo '<div class="pwe-resend-stat-row">';

        $stat_items = [
            'sent'    => 'Wysłano',
            'pending' => 'Pozostało',
            'failed'  => 'Błędy',
            'skipped' => 'Pominięto',
        ];

        foreach ($stat_items as $key => $label) {
            $value = $key === 'pending' && empty($stats['total_known'])
                ? '&mdash;'
                : esc_html((string) $stats[$key]);
            echo '<div class="pwe-resend-stat-box">';
            echo '<span>' . esc_html($label) . '</span>';
            echo '<strong>' . $value . '</strong>';
            echo '</div>';
        }

        echo '</div>';

        if (empty($stats['total_known'])) {
            $migration_error = trim((string) ($job['total_migration_error'] ?? ''));
            echo '<p class="pwe-resend-muted">Liczba pozostałych wysyłek zostanie uzupełniona po ponownym odczycie wpisów.';

            if ($migration_error !== '') {
                echo ' ' . esc_html($migration_error);
            }

            echo '</p>';
        }

        echo '<p class="pwe-resend-muted">';
        echo 'Partia: <strong>' . esc_html($job['batch_size']) . '</strong> &middot; ';
        echo 'Przerwa: <strong>' . esc_html($job['delay']) . ' s</strong> &middot; ';
        $min_age_days = (int) ($job['min_age_days'] ?? PWE_Multilang_Resend::DEFAULT_MIN_AGE_DAYS);
        echo 'Tryb: <strong>' . (!empty($job['include_all_entries']) ? 'wszystkie wpisy' : 'starsze niz ' . esc_html((string) $min_age_days) . ' dni') . '</strong>';
        echo '</p>';

        if (!empty($stats['log_total'])) {
            echo '<p class="pwe-resend-muted">Łącznie zdarzeń: <strong>' . esc_html($stats['log_total']) . '</strong> (wyświetlanych: ostatnie 20)</p>';
        }

        echo '<hr class="pwe-divider">';

        self::render_job_actions($job);

        echo '<hr class="pwe-divider">';

        echo PWE_Multilang_Resend_Actions::action_form('reset', 'Usuń proces', 'button button-secondary pwe-action-reset');

        self::render_recent_log($job);
    }

    private static function render_job_actions(array $job): void
    {
        $status = $job['status'];

        if ($status === 'running') {
            PWE_Multilang_Admin_UI::status('p', 'ok', 'dashicons-update', ' Proces aktywny &mdash; trwa wysyłka.');

            echo '<p>';
            echo PWE_Multilang_Resend_Actions::action_form('run', 'Wyślij następną partię teraz') . ' ';
            echo PWE_Multilang_Resend_Actions::action_form('pause', 'Wstrzymaj');
            echo '</p>';

            if (isset($_GET['pwe_resend_autorun'])) {
                $delay_ms  = (int) $job['delay'] * 1000;
                $nonce_val = wp_create_nonce('pwe_multilang_resend_action');

                echo '<p class="pwe-resend-muted">Kolejna partia za <strong>' . esc_html($job['delay']) . ' sekund</strong>...</p>';
                echo '<div class="pwe-resend-autorun"
                    data-action="' . esc_url(PWE_Multilang_Resend_Actions::admin_url(['pwe_resend_autorun' => 1])) . '"
                    data-delay="' . esc_attr((string) $delay_ms) . '"
                    data-nonce-name="_wpnonce"
                    data-nonce-value="' . esc_attr($nonce_val) . '">
                </div>';
            } else {
                echo PWE_Multilang_Resend_Actions::action_form('run', 'Uruchom auto-wysyłkę', 'button button-primary');
                echo '<p class="pwe-resend-muted pwe-resend-help">Auto-wysyłka odświeża stronę co ' . esc_html($job['delay']) . ' s i wysyła kolejną partię automatycznie.</p>';
            }

            return;
        }

        if ($status === 'paused') {
            PWE_Multilang_Admin_UI::status('p', 'warn', 'dashicons-controls-pause', ' Proces wstrzymany ręcznie.');
            echo PWE_Multilang_Resend_Actions::action_form('resume', 'Wznów wysyłkę', 'button button-primary');
            return;
        }

        if ($status === 'blocked') {
            $reason = (string) ($job['blocked_reason'] ?? 'delivery');
            $message = trim((string) ($job['blocked_message'] ?? ''));

            if ($reason === 'entries_fetch') {
                PWE_Multilang_Admin_UI::status('p', 'error', 'dashicons-warning', ' Proces zatrzymany &mdash; nie udało się pobrać wpisów z Gravity Forms.');

                if ($message !== '') {
                    echo '<p class="pwe-resend-muted">' . esc_html($message) . '</p>';
                }

                echo '<p class="pwe-resend-muted">Kursor nie został przesunięty. Ponowienie rozpocznie się dokładnie od tego samego miejsca.</p>';
                echo PWE_Multilang_Resend_Actions::action_form('resume', 'Ponów odczyt wpisów', 'button button-primary');
                return;
            }

            PWE_Multilang_Admin_UI::status('p', 'error', 'dashicons-warning', ' Wysyłka zatrzymana &mdash; możliwa blokada SMTP lub skrzynki pocztowej.');

            if ($message !== '') {
                echo '<p class="pwe-resend-muted">' . esc_html($message) . '</p>';
            }

            echo '<p class="pwe-resend-muted">Odblokuj skrzynkę ręcznie, a następnie wznów proces.</p>';
            echo PWE_Multilang_Resend_Actions::action_form('resume', 'Wznów po odblokowaniu', 'button button-primary');
            return;
        }

        if ($status === 'completed') {
            if ((int) ($job['failed'] ?? 0) > 0) {
                PWE_Multilang_Admin_UI::status('p', 'warn', 'dashicons-warning', ' Proces zakończony, ale część wysyłek zakończyła się błędem.');
            } else {
                PWE_Multilang_Admin_UI::status('p', 'ok', 'dashicons-yes-alt', ' Wysyłka zakończona pomyślnie.');
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    private static function group_notifications_for_display(array $notifications): array
    {
        $groups = [];

        foreach ($notifications as $notification) {
            $name = (string) ($notification['name'] ?? '');
            $lang = PWE_Multilang_Resend_Matcher::extract_lang_from_notification_name($name);
            $base = self::strip_lang_suffix($name);
            $key  = mb_strtolower($base);

            if (!isset($groups[$key])) {
                $groups[$key] = ['key' => $key, 'name' => $base, 'langs' => []];
            }

            if ($lang) {
                $groups[$key]['langs'][] = strtoupper($lang);
            }
        }

        foreach ($groups as &$group) {
            $group['langs'] = array_values(array_unique($group['langs']));
            sort($group['langs']);
        }

        unset($group);

        return $groups;
    }

    private static function strip_lang_suffix(string $name): string
    {
        return trim((string) preg_replace('/-\s*[a-z]{2}\s*$/i', '', trim($name)));
    }

    private static function render_notification_groups(array $groups, array $notification_lang_counts): void
    {
        if (!$groups) {
            echo '<span class="pwe-empty-text">Brak powiadomień</span>';
            return;
        }

        echo '<div class="pwe-resend-notification-list">';

        foreach ($groups as $group) {
            echo '<div class="pwe-resend-notification-card">';
            echo '<div class="pwe-resend-notification-name">';
            echo '<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>';
            echo esc_html($group['name']);
            echo '</div>';

            echo '<div class="pwe-resend-notification-langs">';

            if (!empty($group['langs'])) {
                foreach ($group['langs'] as $lang) {
                    $count = self::notification_lang_count($notification_lang_counts, (string) ($group['key'] ?? ''), mb_strtolower((string) $lang));
                    echo '<span class="pwe-lang-tag">' . esc_html($lang) . ' <strong>' . esc_html((string) $count) . '</strong></span>';
                }
            } else {
                $count = self::notification_lang_count($notification_lang_counts, (string) ($group['key'] ?? ''), '__none__');
                echo '<span class="pwe-lang-tag pwe-lang-tag--global">globalne <strong>' . esc_html((string) $count) . '</strong></span>';
            }

            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    private static function notification_lang_count(array $notification_lang_counts, string $group_key, string $lang_key): int
    {
        if ($group_key === '') {
            return 0;
        }

        return (int) ($notification_lang_counts[$group_key][$lang_key] ?? 0);
    }

    private static function render_recent_log(array $job): void
    {
        $log = array_reverse(array_slice($job['log'] ?? [], -20));

        if (!$log) {
            return;
        }

        echo '<p class="pwe-section-heading">Ostatni log <span style="font-weight:400;color:var(--pwe-faint);">(ostatnie 20 zdarzeń)</span></p>';

        echo '<table class="widefat">';
        echo '<thead><tr>';
        echo '<th>Czas</th>';
        echo '<th>Entry ID</th>';
        echo '<th>Powiadomienie</th>';
        echo '<th>Status</th>';
        echo '<th>Komunikat</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($log as $row) {
            $entry_display = !empty($row['entry_id']) ? esc_html($row['entry_id']) : '&mdash;';
            $status        = (string) ($row['status'] ?? '');

            echo '<tr>';
            echo '<td class="pwe-resend-muted">' . esc_html($row['time'] ?? '') . '</td>';
            echo '<td><code>' . $entry_display . '</code></td>';
            echo '<td>' . esc_html($row['notification'] ?? '') . '</td>';
            echo '<td><span class="pwe-log-status pwe-log-status--' . esc_attr($status) . '">' . esc_html($status) . '</span></td>';
            echo '<td class="pwe-resend-muted">' . esc_html($row['message'] ?? '') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
