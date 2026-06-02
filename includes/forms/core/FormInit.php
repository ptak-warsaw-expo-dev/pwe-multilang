<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Init {

    public static function init() : void {

        add_action('gform_loaded', function () {
            if (!class_exists('GFAPI')) {
                return;
            }

            PWE_Multilang_Form_Core::run();
        }, 20);
    }
}
