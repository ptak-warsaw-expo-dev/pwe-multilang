<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Notifications_UI
{
    private static bool $active = false;

    public static function init(): void
    {
        if (self::$active) {
            return;
        }

        self::$active = true;
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_filter('gform_noconflict_scripts', [self::class, 'allow_noconflict_script']);
        add_filter('gform_noconflict_styles', [self::class, 'allow_noconflict_style']);
    }

    public static function enqueue_assets(): void
    {
        if (!class_exists('PWE_Multilang_Admin_Access')
            || !PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        if (empty($_GET['page']) || $_GET['page'] !== 'gf_edit_forms') {
            return;
        }

        if (empty($_GET['view']) || $_GET['view'] !== 'settings') {
            return;
        }

        if (empty($_GET['subview']) || !in_array($_GET['subview'], ['notification', 'confirmation'], true)) {
            return;
        }

        $form_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if (!$form_id || !class_exists('GFAPI')) {
            return;
        }

        $asset_path = 'includes/gf-addon/features/notifications-ui/assets/';
        $asset_url  = plugin_dir_url(PWE_MULTILANG_FILE) . $asset_path;
        $css_path   = plugin_dir_path(PWE_MULTILANG_FILE) . $asset_path . 'css/notifications-ui.css';
        $js_path    = plugin_dir_path(PWE_MULTILANG_FILE) . $asset_path . 'js/notifications-ui.js';

        wp_enqueue_style(
            'pwe-multilang-gf-notifications-ui',
            $asset_url . 'css/notifications-ui.css',
            [],
            file_exists($css_path) ? filemtime($css_path) : time()
        );

        wp_enqueue_script(
            'pwe-multilang-gf-notifications-ui',
            $asset_url . 'js/notifications-ui.js',
            ['jquery'],
            file_exists($js_path) ? filemtime($js_path) : time(),
            true
        );
    }

    public static function allow_noconflict_script(array $scripts): array
    {
        $scripts[] = 'pwe-multilang-gf-notifications-ui';

        return array_values(array_unique($scripts));
    }

    public static function allow_noconflict_style(array $styles): array
    {
        $styles[] = 'pwe-multilang-gf-notifications-ui';

        return array_values(array_unique($styles));
    }
}
