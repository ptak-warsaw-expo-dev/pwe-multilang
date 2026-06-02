<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Translations_Legacy
{
    public static function init(): void
    {
        add_filter('gform_pre_render', [self::class, 'apply']);
        add_filter('gform_pre_validation', [self::class, 'apply']);
        add_filter('gform_pre_submission_filter', [self::class, 'apply']);
    }

    public static function apply(array $form): array
    {

        $lang = self::getCurrentLang();

        if (!$lang || in_array($lang, ['pl', 'en'], true)) {
            return $form;
        }
        
        if (empty($form['title']) || stripos($form['title'], 'Multilang') === false) {
            return $form;
        }

        $lang = self::getCurrentLang();

        if (!$lang) {
            return $form;
        }

        $translations = self::getFieldTranslations($lang);

        if (empty($translations)) {
            return $form;
        }

        foreach ($form['fields'] as &$field) {
            $adminLabel = $field->adminLabel ?? null;

            if (!$adminLabel || empty($translations[$adminLabel])) {
                continue;
            }

            $t = $translations[$adminLabel];

            foreach (['label', 'placeholder', 'description', 'checkboxLabel'] as $property) {
                if (array_key_exists($property, $t)) {
                    $field->{$property} = $t[$property];
                }
            }
        }

        unset($field);

        if (!empty($translations['_button'])) {
            $form['button']['text'] = $translations['_button'];
        }

        return $form;
    }

    private static function getCurrentLang(): ?string
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

    private static function getFieldTranslations(string $lang): array
    {
        $file = dirname(__DIR__) . '/translations/fields.php';

        if (!is_file($file)) {
            return [];
        }

        $all = require $file;

        if (!is_array($all)) {
            return [];
        }

        $translations = [];

        foreach ($all as $adminLabel => $langs) {
            if (!is_array($langs) || !array_key_exists($lang, $langs)) {
                continue;
            }

            $translations[$adminLabel] = $langs[$lang];
        }

        return $translations;
    }
}