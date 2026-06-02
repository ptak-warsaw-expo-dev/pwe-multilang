<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/core/PageConfig.php';
require_once __DIR__ . '/core/PageJsonRepository.php';
require_once __DIR__ . '/core/PageSlug.php';
require_once __DIR__ . '/core/PageFinder.php';
require_once __DIR__ . '/core/PageMetaCopier.php';
require_once __DIR__ . '/core/PageContentTransformer.php';
require_once __DIR__ . '/core/PageLanguageHelper.php';
require_once __DIR__ . '/core/PageCreator.php';
require_once __DIR__ . '/core/PageRenderer.php';

if (!class_exists('PWE_Multilang_Pages')) {
    final class PWE_Multilang_Pages
    {
        public static function render_admin_page(): void
        {
            echo '<div class="pwe-card">';
            echo '<p class="pwe-card-title"><span class="dashicons dashicons-admin-page"></span>Pages</p>';
            echo '<p class="pwe-card-desc">Tworzenie brakujących stron/tłumaczeń WPML na podstawie pliku <code>website-translation.json</code>.</p>';
            echo '<hr class="pwe-divider">';
            PWE_Multilang_Page_Renderer::render_sync_panel();
            echo '</div>';
        }
    }
}
