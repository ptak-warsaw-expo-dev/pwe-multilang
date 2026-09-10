<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Tests_Admin
{
    public static function render(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            wp_die('Brak uprawnień.');
        }

        if (!class_exists('GFAPI')) {
            PWE_Multilang_Admin_UI::cardOpen('pwe-card');
            PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-warning"></span> Gravity Forms wymagane');
            PWE_Multilang_Admin_UI::cardDesc('Gravity Forms nie jest aktywne. Aktywuj wtyczkę, aby korzystać z modułu Tests.');
            PWE_Multilang_Admin_UI::cardClose();
            return;
        }

        $forms = PWE_Multilang_Tests_Gravity::get_managed_forms();

        if (!empty($_GET['pwe_status'])) {
            echo '<div class="pwe-notice-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <div>' . esc_html(wp_unslash($_GET['pwe_status'])) . '</div>
            </div>';
        }

        self::render_update_sync_tools_box();
        self::render_entries_box($forms);
        self::render_notifications_box($forms);
    }

    private static function render_update_sync_tools_box(): void
    {
        $syncState = PWE_Multilang_Tests_Gravity::update_sync_state();
        $savedPluginVersion = (string) ($syncState['saved_plugin_version'] ?? '');
        $savedTemplateVersions = $syncState['template_versions'] ?? [];
        $lastResult = $syncState['last_result'] ?? [];

        if (!is_array($savedTemplateVersions)) {
            $savedTemplateVersions = [];
        }

        ksort($savedTemplateVersions);

        $currentPluginVersion = (string) ($syncState['current_plugin_version'] ?? '');

        PWE_Multilang_Admin_UI::cardOpen('pwe-card');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-update"></span> Narzędzia update-sync (Forms)');
        PWE_Multilang_Admin_UI::cardDesc('Pomocnicze akcje testowe dla automatycznej synchronizacji po zmianie wersji pluginu.');
        echo '<hr class="pwe-divider">';

        ?>
            <table class="widefat striped" style="margin-bottom:16px;">
                <colgroup>
                    <col style="width:30%">
                    <col style="width:70%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Parametr</th>
                        <th>Wartość</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Aktualna wersja pluginu (nagłówek)</td>
                        <td><div class="pwe-template-version-chip__value"><?php echo esc_html($currentPluginVersion !== '' ? $currentPluginVersion : '(brak)'); ?></div></td>
                    </tr>
                    <tr>
                        <td>Zapisana wersja pluginu (option: pwe_mlg_last_plugin_version)</td>
                        <td><div class="pwe-template-version-chip__value"><?php echo esc_html($savedPluginVersion !== '' ? $savedPluginVersion : '(brak)'); ?></div></td>
                    </tr>
                    <tr>
                        <td>Zapisane wersje template (option: pwe_mlg_form_template_versions)</td>
                        <td>
                            <?php if (empty($savedTemplateVersions)) : ?>
                                <code>(brak)</code>
                            <?php else : ?>
                                <div class="pwe-template-version-grid">
                                    <?php foreach ($savedTemplateVersions as $slug => $version) : ?>
                                        <div class="pwe-template-version-chip">
                                            <span class="pwe-template-version-chip__slug"><?php echo esc_html((string) $slug); ?></span>
                                            <span class="pwe-template-version-chip__value"><?php echo esc_html((string) $version); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (is_array($lastResult) && !empty($lastResult)) : ?>
                        <tr>
                            <td>Ostatni wynik update-sync</td>
                            <td>
                                <code>
                                    <?php
                                    $status = (string) ($lastResult['status'] ?? '-');
                                    $time = (string) ($lastResult['time'] ?? '-');
                                    echo esc_html($status . ' @ ' . $time);
                                    ?>
                                </code>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php
        ?>
            <div class="pwe-tests-submit-row">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('pwe_multilang_reset_update_sync_versions'); ?>
                    <input type="hidden" name="action" value="pwe_multilang_reset_update_sync_versions">
                    <input type="hidden" name="mode" value="clear">
                    <?php
                    PWE_Multilang_Admin_UI::button(
                        [
                            'type' => 'submit',
                            'class' => 'pwe-btn',
                        ],
                        '<span class="dashicons dashicons-trash"></span> Wyczyść zapisane wersje'
                    );
                    ?>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('pwe_multilang_reset_update_sync_versions'); ?>
                    <input type="hidden" name="action" value="pwe_multilang_reset_update_sync_versions">
                    <input type="hidden" name="mode" value="set_1_0_0">
                    <?php
                    PWE_Multilang_Admin_UI::button(
                        [
                            'type' => 'submit',
                            'class' => 'pwe-btn',
                        ],
                        '<span class="dashicons dashicons-editor-code"></span> Ustaw zapisane wersje na 1.0.0'
                    );
                    ?>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('pwe_multilang_reset_update_sync_versions'); ?>
                    <input type="hidden" name="action" value="pwe_multilang_reset_update_sync_versions">
                    <input type="hidden" name="mode" value="run_now">
                    <?php
                    PWE_Multilang_Admin_UI::button(
                        [
                            'type' => 'submit',
                            'class' => 'pwe-btn',
                        ],
                        '<span class="dashicons dashicons-controls-play"></span> Wymuś update-sync teraz'
                    );
                    ?>
                </form>
            </div>
        <?php
        PWE_Multilang_Admin_UI::cardClose();
    }

    /* ------------------------------------------------------------------ */
    /* Add test entries                                                     */
    /* ------------------------------------------------------------------ */

    private static function render_entries_box(array $forms): void
    {
        PWE_Multilang_Admin_UI::cardOpen('pwe-card');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-database-add"></span> Dodaj testowe wpisy');
        PWE_Multilang_Admin_UI::cardDesc('Automatycznie tworzy wpisy z przykładowymi danymi w wybranym formularzu. Pole języka uzupełniane jest tylko językami Multilang (z wyłączeniem PL i EN).');
        echo '<hr class="pwe-divider">';
        ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pwe_multilang_create_test_entries'); ?>
                <input type="hidden" name="action" value="pwe_multilang_create_test_entries">

                <div class="pwe-tests-form-grid">

                    <?php PWE_Multilang_Admin_UI::fieldOpen('pwe-field'); ?>
                        <label for="pwe-test-form-id">Formularz</label>
                        <select id="pwe-test-form-id" name="form_id" required>
                            <?php foreach ($forms as $form) : ?>
                                <option value="<?php echo esc_attr($form['id']); ?>">
                                    #<?php echo esc_html($form['id']); ?> &mdash; <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php PWE_Multilang_Admin_UI::fieldClose(); ?>

                    <?php PWE_Multilang_Admin_UI::fieldOpen('pwe-field'); ?>
                        <label for="pwe-test-entries-count">Liczba wpisów</label>
                        <input
                            type="number"
                            id="pwe-test-entries-count"
                            name="entries_count"
                            value="10"
                            min="1"
                            max="<?php echo esc_attr(PWE_Multilang_Tests::MAX_ENTRIES); ?>"
                            required
                        >
                        <?php PWE_Multilang_Admin_UI::fieldHint('Maks. ' . esc_html((string) PWE_Multilang_Tests::MAX_ENTRIES) . ' wpisów jednorazowo.'); ?>
                    <?php PWE_Multilang_Admin_UI::fieldClose(); ?>

                </div>

                <?php PWE_Multilang_Admin_UI::button(['type' => 'submit', 'class' => 'pwe-btn'], '<span class="dashicons dashicons-plus-alt2"></span> Dodaj testowe wpisy'); ?>
            </form>
        <?php
        PWE_Multilang_Admin_UI::cardClose();
    }

    /* ------------------------------------------------------------------ */
    /* Send test notifications                                              */
    /* ------------------------------------------------------------------ */

    private static function render_notifications_box(array $forms): void
    {
        PWE_Multilang_Admin_UI::cardOpen('pwe-card');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-email-alt"></span> Wyślij testowe powiadomienia');
        PWE_Multilang_Admin_UI::cardDesc('Powiadomienia pasujące do filtrów lub zaznaczone ręcznie zostaną wysłane na wskazany adres testowy zamiast do oryginalnych odbiorców.');
        echo '<hr class="pwe-divider">';
        ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pwe_multilang_send_test_notifications'); ?>
                <input type="hidden" name="action" value="pwe_multilang_send_test_notifications">
                <input type="hidden" name="form_filter" id="pwe-tests-form-filter-value" value="ALL">
                <input type="hidden" name="lang_filter" id="pwe-tests-lang-filter-value" value="ALL">
                <input type="hidden" name="notification_filter" id="pwe-tests-notification-filter-value" value="ALL">

                <div class="pwe-tests-topbar">
                    <?php PWE_Multilang_Admin_UI::fieldOpen('pwe-field pwe-tests-email-field'); ?>
                        <label for="pwe-test-email">Adres odbiorcy testowego</label>
                        <input
                            type="email"
                            id="pwe-test-email"
                            name="test_email"
                            class="regular-text"
                            placeholder="test@example.com"
                            required
                        >
                    <?php PWE_Multilang_Admin_UI::fieldClose(); ?>

                    <label class="pwe-check-visible">
                        <div class="pwe-resend-checkbox-row">
                            <?php PWE_Multilang_Admin_UI::checkbox(['type' => 'checkbox', 'id' => 'pwe-check-all'], 'pwe-checkbox-container', 'span'); ?>
                            <span>Zaznacz widoczne</span>
                        </div>
                    </label>
                </div>

                <div class="pwe-tests-filter-block">
                    <div class="pwe-tests-filter-grid">
                        <div class="pwe-tests-filter-col">
                            <?php PWE_Multilang_Admin_UI::fieldOpen('pwe-field'); ?>
                                <label for="pwe-tests-form-filter">Formularz</label>
                                <select id="pwe-tests-form-filter">
                                    <option value="ALL">Wszystkie formularze</option>
                                    <?php foreach ($forms as $form) : ?>
                                        <option value="<?php echo esc_attr((string) ($form['id'] ?? '')); ?>">
                                            #<?php echo esc_html((string) ($form['id'] ?? '')); ?> - <?php echo esc_html((string) ($form['title'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php PWE_Multilang_Admin_UI::fieldClose(); ?>
                        </div>

                        <div class="pwe-tests-filter-col">
                            <p class="pwe-tests-filter-label">Język</p>
                            <div class="pwe-gf-lang-tabs" id="pwe-tests-lang-tabs"></div>
                        </div>
                    </div>

                    <p class="pwe-tests-filter-label pwe-tests-filter-label--spaced">Powiadomienie</p>
                    <div class="pwe-gf-title-tabs" id="pwe-tests-title-tabs"></div>

                    <p class="pwe-gf-counter" id="pwe-tests-counter"></p>
                </div>

                <div class="pwe-tests-notification-list" id="pwe-notifications-list">
                    <?php self::render_notification_cards($forms); ?>
                </div>

                <div class="pwe-tests-submit-row">
                    <?php PWE_Multilang_Admin_UI::button(['type' => 'submit', 'class' => 'pwe-btn'], '<span class="dashicons dashicons-email-alt"></span> Wyślij testowe powiadomienia'); ?>
                </div>

            </form>
        <?php
        PWE_Multilang_Admin_UI::cardClose();
    }

    private static function render_notification_cards(array $forms): void
    {
        foreach ($forms as $form) {
            foreach (($form['notifications'] ?? []) as $notification_id => $notification) {
                $form_title        = (string) ($form['title'] ?? '');
                $notification_name = (string) ($notification['name'] ?? $notification_id);
                $template          = (string) ($notification['template'] ?? '');
                $message           = (string) ($notification['message'] ?? '');

                $detected_lang = PWE_Multilang_Tests_Notifications::detect_notification_lang($form, $notification);
                $base_title    = PWE_Multilang_Tests_Notifications::detect_base_title($notification_name, $detected_lang);
                $is_active     = !empty($notification['isActive']);
                $status_class  = $is_active ? 'is-active' : 'is-inactive';
                $status_label  = $is_active ? 'Aktywne' : 'Nieaktywne';
                $lang_label    = $detected_lang ? strtoupper($detected_lang) : 'OTHER';
                ?>
                <div class="pwe-tests-notification-card"
                     data-pwe-form-id="<?php echo esc_attr((string) ($form['id'] ?? '')); ?>"
                     data-pwe-form-title="<?php echo esc_attr($form_title); ?>"
                     data-pwe-lang="<?php echo esc_attr($detected_lang ?: 'OTHER'); ?>"
                     data-pwe-title="<?php echo esc_attr($base_title ?: '__OTHER__'); ?>">

                    <div class="pwe-tests-notification-head">
                        <?php
                        PWE_Multilang_Admin_UI::checkbox(
                            [
                                'type' => 'checkbox',
                                'class' => 'pwe-notification-checkbox',
                                'name' => 'notifications[]',
                                'value' => (string) ($form['id'] . '|' . $notification_id),
                            ],
                            'pwe-checkbox-container',
                            'label'
                        );
                        ?>

                        <div class="pwe-tests-notification-main" style="flex:1;min-width:0;">
                            <div class="pwe-tests-notification-title">
                                <?php echo esc_html($notification_name); ?>
                            </div>
                            <div class="pwe-tests-notification-meta">
                                <span>#<?php echo esc_html($form['id']); ?></span>
                                <span><?php echo esc_html($form_title); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="pwe-tests-notification-info">
                        <span class="pwe-tests-lang-badge"><?php echo esc_html($lang_label); ?></span>
                        <span class="pwe-tests-title-badge"><?php echo esc_html($base_title ?: 'Other'); ?></span>
                        <span class="pwe-tests-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                    </div>

                </div>
                <?php
            }
        }
    }
}
