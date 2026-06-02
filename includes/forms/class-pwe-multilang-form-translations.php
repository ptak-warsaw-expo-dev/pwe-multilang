<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Translations
{
    public static function init(): void
    {
        add_filter('gform_pre_render', [self::class, 'apply']);
        add_filter('gform_pre_validation', [self::class, 'apply']);
        add_filter('gform_pre_submission_filter', [self::class, 'apply']);
    }

    public static function apply(array $form): array
    {
        if (empty($form['title']) || stripos($form['title'], 'Multilang') === false) {
            return $form;
        }

        $lang = self::get_current_lang();

        if (!$lang || in_array($lang, ['pl', 'en'], true)) {
            return $form;
        }

        $translations = self::get_field_translations($lang);

        if (empty($translations)) {
            return $form;
        }

        foreach ($form['fields'] as &$field) {
            $admin_label = $field->adminLabel ?? null;

            if (!$admin_label || empty($translations[$admin_label])) {
                continue;
            }

            foreach (['label', 'placeholder', 'description', 'checkboxLabel'] as $property) {
                if (array_key_exists($property, $translations[$admin_label])) {
                    $field->{$property} = $translations[$admin_label][$property];
                }
            }
        }

        unset($field);

        $current_button = $form['button']['text'] ?? '';

        if (
            $current_button
            && isset($translations['_button'][$current_button][$lang])
            && is_string($translations['_button'][$current_button][$lang])
        ) {
            $form['button']['type'] = 'text';
            $form['button']['text'] = $translations['_button'][$current_button][$lang];
        }

        return $form;
    }

    private static function get_current_lang(): ?string
    {
        $lang = apply_filters('wpml_current_language', null);

        if (!empty($lang)) {
            return $lang;
        }

        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }

        return null;
    }

    private static function get_field_translations(string $lang): array
    {
        $file = PWE_MULTILANG_PATH . 'includes/forms/translations/fields.php';

        if (!is_file($file)) {
            return [];
        }

        $all = require $file;

        if (!is_array($all)) {
            return [];
        }

        $translations = [];

        foreach ($all as $admin_label => $langs) {

            // specjalna obsługa przycisków
            if ($admin_label === '_button') {
                $translations['_button'] = $langs;
                continue;
            }

            if (!is_array($langs) || !array_key_exists($lang, $langs)) {
                continue;
            }

            $translations[$admin_label] = $langs[$lang];
        }

        return $translations;
}
}
