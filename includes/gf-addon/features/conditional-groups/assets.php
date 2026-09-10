<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Conditional_Groups_Assets
{
    public static function enqueue_admin(): void
    {
        if (!class_exists('PWE_Multilang_Admin_Access')
            || !PWE_Multilang_Admin_Access::is_allowed()
            || !class_exists('GFForms')
            || !GFForms::is_gravity_page()) {
            return;
        }

        $page = method_exists('GFForms', 'get_page') ? (string) GFForms::get_page() : '';
        $is_settings = isset($_GET['page'], $_GET['view'])
            && sanitize_key((string) $_GET['page']) === 'gf_edit_forms'
            && sanitize_key((string) $_GET['view']) === 'settings';

        if ($page !== 'form_editor' && !$is_settings) {
            return;
        }

        $relative = 'includes/gf-addon/features/conditional-groups/assets/';
        $base_path = plugin_dir_path(PWE_MULTILANG_FILE) . $relative;
        $base_url = plugin_dir_url(PWE_MULTILANG_FILE) . $relative;
        $css = $base_path . 'css/conditional-groups.css';
        $js = $base_path . 'js/conditional-groups-admin.js';

        wp_enqueue_style(
            'pwe-multilang-gf-conditional-groups-admin',
            $base_url . 'css/conditional-groups.css',
            [],
            file_exists($css) ? (string) filemtime($css) : PWE_MULTILANG_VERSION
        );
        wp_enqueue_script(
            'pwe-multilang-gf-conditional-groups-admin',
            $base_url . 'js/conditional-groups-admin.js',
            ['jquery', 'gform_form_admin'],
            file_exists($js) ? (string) filemtime($js) : PWE_MULTILANG_VERSION,
            true
        );
        wp_localize_script('pwe-multilang-gf-conditional-groups-admin', 'pweConditionalGroups', [
            'property' => PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY,
            'fields'   => PWE_Multilang_GF_Conditional_Groups_Settings::admin_fields(),
            'strings'  => [
                'intro'       => 'Match %s of the following groups:',
                'match'       => 'Match',
                'rules'       => 'of the following rules:',
                'any'         => 'any',
                'all'         => 'all',
                'addRule'     => '+ Add Rule',
                'addGroup'    => '+ Add Rule Group',
                'deleteGroup' => 'Delete rule group',
                'is'          => 'is',
                'isnot'       => 'is not',
                'contains'    => 'contains',
                'notContains' => 'does NOT contain',
                'greater'     => 'greater than',
                'less'        => 'less than',
                'starts'      => 'starts with',
                'ends'        => 'ends with',
                'value'       => 'Enter a value',
            ],
        ]);
    }

    public static function enqueue_frontend(array $form): void
    {
        if (!self::form_uses_groups($form)) {
            return;
        }

        $relative = 'includes/gf-addon/features/conditional-groups/assets/js/conditional-groups-frontend.js';
        $path = plugin_dir_path(PWE_MULTILANG_FILE) . $relative;
        wp_enqueue_script(
            'pwe-multilang-gf-conditional-groups',
            plugin_dir_url(PWE_MULTILANG_FILE) . $relative,
            ['gform_conditional_logic'],
            file_exists($path) ? (string) filemtime($path) : PWE_MULTILANG_VERSION,
            true
        );
    }

    private static function form_uses_groups(array $form): bool
    {
        $property = PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY;

        foreach (($form['fields'] ?? []) as $field) {
            if (is_object($field) && (!empty($field->{$property}) || self::logic_is_compiled($field->conditionalLogic ?? null))) {
                return true;
            }

            if (is_object($field) && is_array($field->nextButton ?? null)
                && (!empty($field->nextButton[$property])
                    || self::logic_is_compiled($field->nextButton['conditionalLogic'] ?? null))) {
                return true;
            }
        }

        foreach (['button', 'notifications', 'confirmations'] as $collection) {
            $items = $collection === 'button'
                ? [is_array($form['button'] ?? null) ? $form['button'] : []]
                : (array) ($form[$collection] ?? []);

            foreach ($items as $item) {
                if (is_array($item) && (!empty($item[$property]) || self::logic_is_compiled($item['conditionalLogic'] ?? null))) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function logic_is_compiled($logic): bool
    {
        return is_array($logic) && PWE_Multilang_GF_Conditional_Groups_Engine::is_compiled($logic);
    }
}
