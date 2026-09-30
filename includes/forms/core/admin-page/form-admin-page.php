<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Admin
{
    public static function render(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        $currentYear = PWE_Multilang_Year_Resolver::current();
        $savedYear = PWE_Multilang_Year_Resolver::configured();
        $templates = PWE_Multilang_Form_Core::getTemplates($savedYear);
        $result = PWE_Multilang_Form_Result_Store::pull();
        $gravityReady = class_exists('GFAPI');

        PWE_Multilang_Form_Admin_Result_Renderer::render($result);
        PWE_Multilang_Form_Admin_View::render($templates, $savedYear, $currentYear, $gravityReady);
    }

}
