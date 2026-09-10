<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Addon_Admin
{
    public static function render(): void
    {
        PWE_Multilang_Admin_UI::cardOpen('pwe-card');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-admin-plugins"></span> GF Addon');
        PWE_Multilang_Admin_UI::cardDesc('Customowe rozszerzenia Gravity Forms. Ich konfiguracja i logika znajdują się wyłącznie w kodzie.');
        echo '<hr class="pwe-divider">';

        $features = PWE_Multilang_GF_Addon_Features::active();

        if (!$features) {
            echo '<p class="pwe-empty-text">Brak aktywnych funkcji rozszerzających.</p>';
            PWE_Multilang_Admin_UI::cardClose();
            return;
        }

        echo '<div class="pwe-modules-grid">';
        foreach ($features as $feature) {
            PWE_Multilang_Admin_UI::fieldOpen('pwe-field pwe-gf-addon-feature');
            echo '<div class="pwe-module-switch-row">';
            echo '<div class="pwe-module-switch-copy">';
            echo '<p class="pwe-module-switch-title">' . esc_html($feature['label']) . '</p>';
            echo '<p class="pwe-hint">' . esc_html($feature['description']) . '</p>';
            echo '</div>';
            PWE_Multilang_Admin_UI::status('span', 'ok', 'dashicons-yes-alt', ' Aktywna');
            echo '</div>';
            PWE_Multilang_Admin_UI::fieldClose();
        }
        echo '</div>';

        PWE_Multilang_Admin_UI::cardClose();
    }
}
