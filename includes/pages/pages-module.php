<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/forms/core/sync/form-operation-lock.php';
require_once __DIR__ . '/core/page-config.php';
require_once __DIR__ . '/core/page-json-repository.php';
require_once __DIR__ . '/core/page-slug.php';
require_once __DIR__ . '/core/wpml-gateway.php';
require_once __DIR__ . '/core/page-locator.php';
require_once __DIR__ . '/core/page-meta-adapter.php';
require_once __DIR__ . '/core/page-meta-copier.php';
require_once __DIR__ . '/core/page-content-transformer.php';
require_once __DIR__ . '/core/page-language-helper.php';
require_once __DIR__ . '/core/page-creator.php';
require_once __DIR__ . '/core/admin-page.php';

if (!class_exists('PWE_Multilang_Pages')) {
    final class PWE_Multilang_Pages
    {
        public static function render_admin_page(): void
        {
            PWE_Multilang_Admin_UI::cardOpen('pwe-card');
            PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-admin-page"></span>Pages');
            PWE_Multilang_Admin_UI::status('div', 'info', 'dashicons-groups', ' Aktywna grupa: <strong>' . esc_html(PWE_Multilang_Site_Group::label()) . '</strong>');
            PWE_Multilang_Admin_UI::cardDesc('Tworzenie brakujących stron/tłumaczeń WPML na podstawie pliku <code>website-translation.json</code>.');
            echo '<hr class="pwe-divider">';
            PWE_Multilang_Page_Renderer::render_sync_panel();
            PWE_Multilang_Admin_UI::cardClose();
        }
    }
}
