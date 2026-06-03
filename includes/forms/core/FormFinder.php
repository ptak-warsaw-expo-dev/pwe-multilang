<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Finder {

    /**
     * Znajdź formularz po dokładnym tytule
     */
    public static function byTitle(string $title) : ?array {

        if (!class_exists('GFAPI') || !method_exists('GFAPI', 'get_forms')) {
            return null;
        }

        foreach (GFAPI::get_forms() as $form) {
            $formTitle = $form['title'] ?? ($form->title ?? null);

            if ($formTitle === $title) {
                return GFAPI::get_form($form['id']);
            }
        }

        return null;
    }

    /**
     * Znajdź formularze zawierające frazę w tytule
     * (np. do notyfikacji)
     */
    public static function byTitleContains(string $needle) : array {

        if (!class_exists('GFAPI') || !method_exists('GFAPI', 'get_forms')) {
            return [];
        }

        $found = [];

        foreach (GFAPI::get_forms() as $form) {
            $title = $form['title'] ?? ($form->title ?? null);

            if ($title && stripos($title, $needle) !== false) {
                $found[] = GFAPI::get_form($form['id']);
            }
        }

        return $found;
    }
}
