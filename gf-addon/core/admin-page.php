<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Addon_Admin
{
    public static function render(): void
    {
        $features = PWE_Multilang_GF_Addon_Features::active();
        PWE_Multilang_Admin_UI::heroOpen('pwe-multilang-hero', 'gf_addon');
        PWE_Multilang_Admin_UI::cardTitle('Rozszerzenia Gravity Forms');
        PWE_Multilang_Admin_UI::cardDesc('Customowe rozszerzenia Gravity Forms. Ich konfiguracja i logika znajdują się wyłącznie w kodzie.');
        PWE_Multilang_Admin_UI::status('div', 'info', 'dashicons-groups', ' Aktywna grupa: <strong>' . esc_html(PWE_Multilang_Site_Group::label()) . '</strong>');
        PWE_Multilang_Admin_UI::heroClose();
        ?>
        <div class="pwe-gf-addon">
            <?php if (!$features) : ?>
                <div class="pwe-multilang-card"><p class="pwe-empty-text">Brak aktywnych funkcji rozszerzających.</p></div>
            <?php else : ?>
                <div class="pwe-gf-addon-grid">
                    <?php foreach ($features as $key => $feature) :
                        $icon = $key === 'gf_notifications_ui' ? 'dashicons-bell' : ($key === 'does_not_contain' ? 'dashicons-admin-settings' : 'dashicons-admin-plugins');
                        ?>
                        <div class="pwe-gf-addon-feature">
                            <div class="pwe-gf-addon-feature-header">
                                <span class="pwe-gf-addon-icon"><span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span></span>
                                <div class="pwe-gf-addon-feature-copy">
                                    <h4><?php echo esc_html($feature['label']); ?></h4>
                                    <p><?php echo esc_html($feature['description']); ?></p>
                                </div>
                                <?php PWE_Multilang_Admin_UI::status('span', 'ok', 'dashicons-yes-alt', ' Aktywna'); ?>
                            </div>
                            <div class="pwe-gf-addon-feature-footer">
                                <span class="pwe-gf-addon-code" aria-hidden="true"><span class="dashicons dashicons-editor-code"></span></span>
                                <div><strong>Zarządzane w kodzie</strong><p>Konfiguracja i logika znajdują się w kodzie wtyczki.</p></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="pwe-gf-addon-summary">
                    <span class="pwe-gf-addon-summary-icon dashicons dashicons-yes" aria-hidden="true"></span>
                    <div><strong>Rozszerzenia są aktywne.</strong><p>Aktywne rozszerzenia Gravity Forms: <?php echo esc_html((string) count($features)); ?>. Konfiguracja jest zarządzana w kodzie wtyczki.</p></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
