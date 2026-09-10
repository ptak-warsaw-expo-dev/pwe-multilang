<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Finder {

    private static $forms = null;
    private static $identityIndex = null;

    public static function byIdentity(string $templateSlug, string $targetKey): ?array
    {
        if (!class_exists('GFAPI') || !method_exists('GFAPI', 'get_forms')) {
            return null;
        }

        $templateSlug = sanitize_key($templateSlug);
        $targetKey = trim($targetKey);

        if ($templateSlug === '' || $targetKey === '') {
            return null;
        }

        $identityIndex = self::getIdentityIndex();
        $bestId = (int) ($identityIndex[$templateSlug . '|' . $targetKey] ?? 0);

        if ($bestId <= 0) {
            return null;
        }

        $form = GFAPI::get_form($bestId);
        return is_array($form) ? $form : null;
    }

    public static function byTitle(string $title) : ?array {

        if (!class_exists('GFAPI') || !method_exists('GFAPI', 'get_forms')) {
            return null;
        }

        $needle = self::normalizeTitleBase($title);

        if ($needle === '') {
            return null;
        }

        $bestId = 0;
        $bestYear = 0;

        foreach (self::getForms() as $form) {
            $formTitle = $form['title'] ?? ($form->title ?? null);
            $formId = (int) ($form['id'] ?? ($form->id ?? 0));

            if (!is_string($formTitle) || $formId <= 0) {
                continue;
            }

            if (self::normalizeTitleBase($formTitle) !== $needle) {
                continue;
            }

            $year = self::extractYearPrefix($formTitle);

            if ($year > $bestYear || ($year === $bestYear && $bestId === 0)) {
                $bestYear = $year;
                $bestId = $formId;
            }
        }

        if ($bestId > 0) {
            $form = GFAPI::get_form($bestId);
            return is_array($form) ? $form : null;
        }

        return null;
    }

    public static function resetCache(): void
    {
        self::$forms = null;
        self::$identityIndex = null;
    }

    private static function normalizeTitleBase(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return '';
        }

        $withoutYear = preg_replace('/^\s*\((\d{4})\)\s*/', '', $title);

        if (!is_string($withoutYear)) {
            $withoutYear = $title;
        }

        $withoutYear = preg_replace('/\s+/u', ' ', trim($withoutYear));

        return mb_strtolower((string) $withoutYear);
    }

    private static function extractYearPrefix(string $title): int
    {
        if (preg_match('/^\s*\((\d{4})\)\s*/', $title, $matches) === 1) {
            return (int) ($matches[1] ?? 0);
        }

        return 0;
    }

    private static function getForms(): array {

        if (self::$forms !== null) {
            return self::$forms;
        }

        $forms = GFAPI::get_forms();
        self::$forms = is_array($forms) ? $forms : [];

        return self::$forms;
    }

    private static function getIdentityIndex(): array
    {
        if (self::$identityIndex !== null) {
            return self::$identityIndex;
        }

        $index = [];

        foreach (self::getForms() as $summary) {
            $formId = (int) self::formValue($summary, 'id', 0);

            if ($formId <= 0) {
                continue;
            }

            $identitySource = $summary;
            $templateSlug = sanitize_key((string) self::formValue(
                $summary,
                PWE_Multilang_Form_Identity::TEMPLATE_SLUG_KEY,
                ''
            ));
            $targetKey = trim((string) self::formValue(
                $summary,
                PWE_Multilang_Form_Identity::TARGET_KEY,
                ''
            ));

            // Some Gravity Forms versions return lightweight summaries without
            // custom form metadata. Fetch each full form once while building the
            // cached identity index so stable identity never depends on a title.
            if ($templateSlug === '' || $targetKey === '') {
                $full = GFAPI::get_form($formId);

                if (is_array($full)) {
                    $identitySource = $full;
                    $templateSlug = sanitize_key((string) self::formValue(
                        $identitySource,
                        PWE_Multilang_Form_Identity::TEMPLATE_SLUG_KEY,
                        ''
                    ));
                    $targetKey = trim((string) self::formValue(
                        $identitySource,
                        PWE_Multilang_Form_Identity::TARGET_KEY,
                        ''
                    ));
                }
            }

            if ($templateSlug === '' || $targetKey === '') {
                continue;
            }

            $key = $templateSlug . '|' . $targetKey;
            $index[$key] = max((int) ($index[$key] ?? 0), $formId);
        }

        self::$identityIndex = $index;

        return self::$identityIndex;
    }

    private static function formValue($form, string $key, $default = null)
    {
        if (is_array($form) && array_key_exists($key, $form)) {
            return $form[$key];
        }

        if (is_object($form) && isset($form->{$key})) {
            return $form->{$key};
        }

        return $default;
    }
}
