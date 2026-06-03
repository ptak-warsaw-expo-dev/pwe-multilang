<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Global_Settings {

    public static function init(): void
    {
        add_action('gform_loaded', function () {
            self::enable_honeypot_on_all();
        }, 20);
    }

    public static function enable_honeypot_on_all(): void
    {
        if (!class_exists('GFAPI')) {
            return;
        }

        foreach (GFAPI::get_forms() as $f) {
            $form = GFAPI::get_form($f['id']);

            if (!$form || !is_array($form)) {
                continue;
            }

            // if (!empty($form['enableHoneypot'])) {
            //     continue;
            // }

            $form['enableHoneypot'] = true;

            $res = GFAPI::update_form($form);

            if (is_wp_error($res)) {
                PWE_Multilang_Form_Log::error('HONEYPOT SAVE FAIL', ['id' => $form['id']]);
            } else {
                PWE_Multilang_Form_Log::warn('HONEYPOT SAVED', ['id' => $form['id']]);
            }
        }
    }
}

