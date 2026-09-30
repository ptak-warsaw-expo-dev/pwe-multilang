<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Manual_Modified
{
    private static int $generatorSaveDepth = 0;

    public static function init(): void
    {
        add_action('gform_after_save_form', [self::class, 'mark_after_manual_save'], 10, 2);
    }

    public static function beginGeneratorSave(): void
    {
        self::$generatorSaveDepth++;
        $GLOBALS['pwe_mlg_form_generator_running'] = true;
    }

    public static function endGeneratorSave(): void
    {
        self::$generatorSaveDepth = max(0, self::$generatorSaveDepth - 1);

        if (self::$generatorSaveDepth === 0) {
            unset($GLOBALS['pwe_mlg_form_generator_running']);
            return;
        }

        $GLOBALS['pwe_mlg_form_generator_running'] = true;
    }

    public static function isGeneratorRunning(): bool
    {
        return self::$generatorSaveDepth > 0 || !empty($GLOBALS['pwe_mlg_form_generator_running']);
    }

    public static function run(callable $callback)
    {
        self::beginGeneratorSave();

        try {
            return $callback();
        } finally {
            self::endGeneratorSave();
        }
    }

    public static function mark_after_manual_save($form, $is_new = false): void
    {
        if (self::isGeneratorRunning()) {
            return;
        }

        if (
            class_exists('PWE_Multilang_Form_Lock') &&
            PWE_Multilang_Form_Lock::was_save_blocked()
        ) {
            return;
        }

        $form = is_array($form) ? $form : (array) $form;

        if (empty($form['id']) || empty($form[PWE_Multilang_Form_Core::MANAGED_FLAG])) {
            return;
        }

        $form[PWE_Multilang_Form_Core::MANUAL_MODIFIED_FLAG] = 1;

        self::beginGeneratorSave();

        try {
            GFAPI::update_form($form);
        } finally {
            self::endGeneratorSave();
        }
    }
}
