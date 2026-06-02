<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Notifications_UI
{
    public static function init(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (empty($_GET['page']) || $_GET['page'] !== 'gf_edit_forms') {
            return;
        }

        if (empty($_GET['view']) || $_GET['view'] !== 'settings') {
            return;
        }

        if (
            empty($_GET['subview']) ||
            !in_array($_GET['subview'], ['notification', 'confirmation'], true)
        ) {
            return;
        }

        $form_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if (!$form_id || !class_exists('GFAPI')) {
            return;
        }

        $form = GFAPI::get_form($form_id);

        if (empty($form['title']) || stripos($form['title'], 'Multilang') === false) {
            return;
        }

        $css_path = plugin_dir_path(PWE_MULTILANG_FILE) . 'assets/gf-filter-ui.css';
        $js_path  = plugin_dir_path(PWE_MULTILANG_FILE) . 'assets/gf-filter-ui.js';

        wp_enqueue_style(
            'pwe-multilang-gf-filter-ui',
            plugin_dir_url(PWE_MULTILANG_FILE) . 'assets/gf-filter-ui.css',
            [],
            file_exists($css_path) ? filemtime($css_path) : time()
        );

        wp_enqueue_script(
            'pwe-multilang-gf-filter-ui',
            plugin_dir_url(PWE_MULTILANG_FILE) . 'assets/gf-filter-ui.js',
            ['jquery'],
            file_exists($js_path) ? filemtime($js_path) : time(),
            true
        );
    }
}