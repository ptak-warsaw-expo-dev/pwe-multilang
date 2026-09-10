<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/pages/core/page-slug.php';
require_once dirname(__DIR__) . '/pages/core/wpml-gateway.php';
require_once dirname(__DIR__) . '/pages/core/page-locator.php';
require_once dirname(__DIR__) . '/pages/core/page-meta-adapter.php';
require_once __DIR__ . '/core/replace-content-config.php';
require_once __DIR__ . '/core/replace-content-service.php';
require_once __DIR__ . '/core/ajax-actions.php';
require_once __DIR__ . '/core/admin-page.php';

final class PWE_Multilang_Replace_Content
{
    public static function init(): void
    {
        PWE_Multilang_Replace_Content_Ajax::init();
    }

    public static function render_admin_page(): void
    {
        PWE_Multilang_Admin_UI::cardOpen('pwe-card');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-update"></span>Replace content');
        PWE_Multilang_Admin_UI::cardDesc(
            'Podmiana treści wskazanych stron oraz wszystkich połączonych tłumaczeń WPML na shortcode. '
            . 'Operacja ustawia również <code>Header = none</code> oraz <code>Show title = Off</code> w ustawieniach strony.'
        );
        echo '<hr class="pwe-divider">';
        PWE_Multilang_Replace_Content_Admin_Page::render();
        PWE_Multilang_Admin_UI::cardClose();
    }
}
