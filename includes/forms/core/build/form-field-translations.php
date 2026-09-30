<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Translations
{
    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        add_filter('gform_pre_render', [self::class, 'apply']);
        add_filter('gform_pre_validation', [self::class, 'apply']);
        add_filter('gform_pre_submission_filter', [self::class, 'apply']);
    }

    public static function apply(array $form): array
    {
        $lang = self::get_current_lang();

        if (!$lang) {
            return $form;
        }

        $lang = strtolower($lang);

        $group_langs = $form['_pwe_group_langs'] ?? [];

        if (!is_array($group_langs) || empty($group_langs)) {
            $group_langs = self::get_available_translation_langs($form);
        }

        $group_langs = array_map(
            static fn($value): string => strtolower((string) $value),
            $group_langs
        );

        if (!in_array($lang, $group_langs, true)) {
            return $form;
        }

        return self::applyForLang($form, $lang);
    }

    public static function applyForLang(array $formOrPayload, string $lang): array
    {
        $lang = strtolower($lang);

        $translations = self::get_field_translations(
            $formOrPayload,
            $lang
        );

        if (empty($translations)) {
            return $formOrPayload;
        }

        if (empty($formOrPayload['fields']) || !is_array($formOrPayload['fields'])) {
            $formOrPayload['fields'] = [];
        }

        foreach ($formOrPayload['fields'] as &$field) {
            $admin_label = self::get_field_property($field, 'adminLabel');

            if (!$admin_label || empty($translations[$admin_label])) {
                continue;
            }

            foreach (['label', 'placeholder', 'description', 'checkboxLabel'] as $property) {
                if (array_key_exists($property, $translations[$admin_label])) {
                    self::set_field_property($field, $property, $translations[$admin_label][$property]);
                }
            }

            if (!empty($translations[$admin_label]['choices']) && is_array($translations[$admin_label]['choices'])) {
                self::translate_field_choices($field, $translations[$admin_label]['choices']);
            }
        }

        unset($field);

        $button_text = $translations['_button']['text'] ?? null;

        if (is_string($button_text) && $button_text !== '') {
            if (
                empty($formOrPayload['button'])
                || !is_array($formOrPayload['button'])
            ) {
                $formOrPayload['button'] = [];
            }

            $formOrPayload['button']['type'] = 'text';
            $formOrPayload['button']['text'] = $button_text;
        }

        return $formOrPayload;
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

    private static function get_field_translations(
        array $formOrPayload,
        string $lang
    ): array {
        $all = self::get_all_field_translations($formOrPayload);

        if (!is_array($all)) {
            return [];
        }

        $translations = $all[strtolower($lang)] ?? [];

        return is_array($translations)
            ? $translations
            : [];
    }

    private static function get_available_translation_langs(array $formOrPayload): array
    {
        $all = self::get_all_field_translations($formOrPayload);

        if (empty($all) || !is_array($all)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn($lang): string => strtolower((string) $lang),
                array_keys($all)
            ),
            static fn(string $lang): bool => preg_match('/^[a-z]{2}$/', $lang) === 1
        ));
    }

    private static function get_all_field_translations(array $formOrPayload): array
    {
        $template_dir = self::get_template_dir($formOrPayload);

        if ($template_dir === null) {
            return [];
        }

        $file = PWE_MULTILANG_PATH
            . 'includes/forms/form-templates/'
            . $template_dir
            . '/field-translations.php';

        if (!is_file($file)) {
            return [];
        }

        $all = require $file;

        return is_array($all)
            ? $all
            : [];
    }

    private static function get_template_dir(
        array $formOrPayload
    ): ?string {
        $template_slug = sanitize_key((string) ($formOrPayload['pwe_multilang_template_slug'] ?? ''));

        if ($template_slug !== '') {
            return $template_slug;
        }

        $form_dir = $formOrPayload['_formDir'] ?? null;

        if (!is_string($form_dir) || trim($form_dir) === '') {
            return null;
        }

        $form_dir = str_replace('\\', '/', rtrim($form_dir, '/\\'));
        $template_dir = basename($form_dir);

        if (
            $template_dir === ''
            || !preg_match('/^[a-z0-9_-]+$/', $template_dir)
        ) {
            return null;
        }

        return $template_dir;
    }

    private static function get_field_property($field, string $property)
    {
        if (is_object($field) && isset($field->{$property})) {
            return $field->{$property};
        }

        if (is_array($field) && array_key_exists($property, $field)) {
            return $field[$property];
        }

        return null;
    }

    private static function set_field_property(&$field, string $property, $value): void
    {
        if (is_object($field)) {
            $field->{$property} = $value;
            return;
        }

        if (is_array($field)) {
            $field[$property] = $value;
        }
    }

    private static function translate_field_choices(&$field, array $translations): void
    {
        $choices = self::get_field_property($field, 'choices');

        if (empty($choices) || !is_array($choices)) {
            return;
        }

        foreach ($choices as $index => &$choice) {
            if (!is_array($choice)) {
                continue;
            }

            $value = (string) ($choice['value'] ?? '');
            $text = (string) ($choice['text'] ?? '');
            $translation = null;

            if ($value !== '' && array_key_exists($value, $translations)) {
                $translation = $translations[$value];
            } elseif ($text !== '' && array_key_exists($text, $translations)) {
                $translation = $translations[$text];
            } elseif (array_key_exists($index, $translations)) {
                $translation = $translations[$index];
            }

            if (!is_string($translation) || $translation === '') {
                continue;
            }

            $choice['text'] = $translation;
        }

        unset($choice);

        self::set_field_property($field, 'choices', $choices);
        self::sync_checkbox_input_labels($field, $choices);
    }

    private static function sync_checkbox_input_labels(&$field, array $choices): void
    {
        $inputs = self::get_field_property($field, 'inputs');

        if (empty($inputs) || !is_array($inputs)) {
            return;
        }

        foreach ($inputs as $index => &$input) {
            if (!is_array($input) || empty($choices[$index]['text'])) {
                continue;
            }

            $input['label'] = $choices[$index]['text'];
        }

        unset($input);

        self::set_field_property($field, 'inputs', $inputs);
    }
}
