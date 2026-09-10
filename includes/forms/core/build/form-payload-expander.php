<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Payload_Expander
{
    public static function expand(array $payload): array
    {
        $config = self::normalizeConfig($payload);

        if (!$config['enabled']) {
            return [$payload];
        }

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            is_array($payload['_template_langs'] ?? null) ? $payload['_template_langs'] : []
        );

        if (empty($langs)) {
            return [$payload];
        }

        $targets = [];
        $baseTitle = (string) ($payload['title'] ?? '');
        $separateLangs = self::resolveSeparateLangs($langs, $config);
        $coveredLangs = $separateLangs;

        foreach ($separateLangs as $lang) {
            $coveredLangs = array_merge(
                $coveredLangs,
                self::resolveRenderLangsFor($lang, $langs, $config)
            );
        }

        $groupLangs = array_values(array_diff($langs, array_values(array_unique($coveredLangs))));

        if (empty($separateLangs)) {
            return [$payload];
        }

        if (!empty($config['keep_rest']) && !empty($groupLangs)) {
            $targets[] = self::makeGroupTarget($payload, $groupLangs, $separateLangs);
        }

        foreach ($separateLangs as $lang) {
            $targets[] = self::makeLanguageTarget(
                $payload,
                $lang,
                $config['titles'][$lang] ?? null,
                $baseTitle,
                !empty($config['langs']),
                self::resolveRenderLangsFor($lang, $langs, $config)
            );
        }

        return $targets;
    }

    private static function languageTitle(string $title, string $lang, bool $compact = false): string
    {
        $lang = strtoupper($lang);

        if ($compact) {
            $replaced = preg_replace('/\bmultilang\b/i', $lang, $title, 1);

            if (is_string($replaced) && $replaced !== $title) {
                return trim($replaced);
            }
        }

        return trim($title . ' - ' . $lang);
    }

    private static function makeLanguageTarget(array $payload, string $lang, ?string $title, string $baseTitle, bool $compactTitle, array $renderLangs): array
    {
        $target = $payload;
        $target['title'] = $title
            ? self::preserveYearPrefix($title, $baseTitle)
            : self::languageTitle($baseTitle, $lang, $compactTitle);
        $target['_pwe_separate_forms'] = 1;
        $target['_pwe_separate_lang'] = $lang;
        $target['_pwe_base_title'] = $baseTitle;
        $target['fields'] = self::applyLangFieldDefault($target['fields'] ?? [], $lang);

        if (count($renderLangs) > 1) {
            $target['_pwe_group_langs'] = $renderLangs;

            foreach (['confirmations', 'notifications'] as $section) {
                if (!empty($target[$section]) && is_array($target[$section])) {
                    $target[$section] = self::filterItemsForLangGroup($target[$section], $renderLangs);
                }
            }

            // Save base separate form in its own language (e.g. EN),
            // while keeping runtime translation for the rest of render_langs.
            return PWE_Multilang_Form_Translations::applyForLang($target, $lang);
        }

        foreach (['confirmations', 'notifications'] as $section) {
            if (!empty($target[$section]) && is_array($target[$section])) {
                $target[$section] = self::filterItemsForLang($target[$section], $lang);
            }
        }

        return PWE_Multilang_Form_Translations::applyForLang($target, $lang);
    }

    private static function preserveYearPrefix(string $title, string $baseTitle): string
    {
        if (preg_match('/^\(\d{4}\)\s*/', $title)) {
            return trim($title);
        }

        if (preg_match('/^(\(\d{4}\)\s*)/', $baseTitle, $matches)) {
            return trim($matches[1] . $title);
        }

        return trim($title);
    }

    private static function makeGroupTarget(array $payload, array $groupLangs, array $excludedLangs): array
    {
        $target = $payload;
        $target['_pwe_group_langs'] = $groupLangs;
        $target['_pwe_excluded_separate_langs'] = $excludedLangs;

        foreach (['confirmations', 'notifications'] as $section) {
            if (!empty($target[$section]) && is_array($target[$section])) {
                $target[$section] = self::filterItemsForLangGroup($target[$section], $groupLangs);
            }
        }

        return $target;
    }

    private static function normalizeConfig(array $payload): array
    {
        $value = $payload['separate_forms'] ?? false;
        $config = [
            'enabled' => false,
            'langs' => [],
            'titles' => [],
            'render_langs' => [],
            'keep_rest' => false,
        ];

        if (is_bool($value)) {
            $config['enabled'] = $value;
            return $config;
        }

        if (is_string($value)) {
            $config['enabled'] = in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
            return $config;
        }

        if (!is_array($value) || empty($value)) {
            return $config;
        }

        $config['enabled'] = true;
        $config['keep_rest'] = self::boolValue($value['keep_rest'] ?? true);

        $source = $value['langs'] ?? $value['separate_langs'] ?? $value;
        $titles = is_array($value['titles'] ?? null) ? $value['titles'] : [];

        foreach ($source as $key => $langOrTitle) {
            if (is_string($key) && self::normalizeLang($key)) {
                $lang = self::normalizeLang($key);
                $title = is_string($langOrTitle) ? trim($langOrTitle) : '';
            } else {
                $lang = is_string($langOrTitle) ? self::normalizeLang($langOrTitle) : null;
                $title = '';
            }

            if (!$lang) {
                continue;
            }

            $config['langs'][] = $lang;

            if ($title !== '') {
                $config['titles'][$lang] = $title;
            }
        }

        foreach ($titles as $lang => $title) {
            $lang = is_string($lang) ? self::normalizeLang($lang) : null;
            if ($lang && is_string($title) && trim($title) !== '') {
                $config['titles'][$lang] = trim($title);
            }
        }

        if (is_array($value['render_langs'] ?? null)) {
            foreach ($value['render_langs'] as $lang => $renderLangs) {
                $lang = is_string($lang) ? self::normalizeLang($lang) : null;

                if (!$lang || !is_array($renderLangs)) {
                    continue;
                }

                $normalized = [];

                foreach ($renderLangs as $renderLang) {
                    if (!is_string($renderLang)) {
                        continue;
                    }

                    $normalizedLang = self::normalizeLang($renderLang);

                    if ($normalizedLang) {
                        $normalized[] = $normalizedLang;
                    }
                }

                if (empty($normalized)) {
                    continue;
                }

                if (!in_array($lang, $normalized, true)) {
                    array_unshift($normalized, $lang);
                }

                $config['render_langs'][$lang] = array_values(array_unique($normalized));
            }
        }

        $config['langs'] = array_values(array_unique($config['langs']));

        return $config;
    }

    private static function resolveSeparateLangs(array $langs, array $config): array
    {
        if (empty($config['langs'])) {
            return $langs;
        }

        return array_values(array_intersect($langs, $config['langs']));
    }

    private static function resolveRenderLangsFor(array|string $lang, array $langs, array $config): array
    {
        $lang = is_string($lang) ? self::normalizeLang($lang) : null;

        if (!$lang) {
            return [];
        }

        $renderLangs = $config['render_langs'][$lang] ?? [$lang];
        $renderLangs = array_values(array_intersect($renderLangs, $langs));

        if (!in_array($lang, $renderLangs, true)) {
            array_unshift($renderLangs, $lang);
        }

        return array_values(array_unique($renderLangs));
    }

    private static function boolValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return !empty($value);
    }

    private static function applyLangFieldDefault(array $fields, string $lang): array
    {
        foreach ($fields as &$field) {
            if (!is_array($field) || strtolower((string) ($field['adminLabel'] ?? '')) !== 'lang') {
                continue;
            }

            $field['defaultValue'] = $lang;
        }

        unset($field);

        return $fields;
    }

    private static function filterItemsForLang(array $items, string $lang): array
    {
        $out = [];

        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemLang = self::detectItemLang($item);

            if ($itemLang !== null && $itemLang !== $lang) {
                continue;
            }

            $item = self::removeLangConditionalRule($item, $lang);
            $out[$key] = $item;
        }

        return $out;
    }

    private static function filterItemsForLangGroup(array $items, array $langs): array
    {
        $out = [];

        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemLang = self::detectItemLang($item);

            if ($itemLang !== null && !in_array($itemLang, $langs, true)) {
                continue;
            }

            $out[$key] = $item;
        }

        return $out;
    }

    private static function detectItemLang(array $item): ?string
    {
        if (!empty($item['_pwe_lang']) && is_string($item['_pwe_lang'])) {
            return self::normalizeLang($item['_pwe_lang']);
        }

        if (!empty($item['name']) && is_string($item['name']) && preg_match('/-\s*([a-z]{2})\s*$/i', $item['name'], $matches)) {
            return strtolower($matches[1]);
        }

        $logicLang = self::detectLangFromConditionalLogic($item['conditionalLogic'] ?? []);
        if ($logicLang !== null) {
            return $logicLang;
        }

        if (!empty($item['_template']) && is_string($item['_template']) && preg_match('/-([a-z]{2})\.html$/i', $item['_template'], $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private static function detectLangFromConditionalLogic($logic): ?string
    {
        if (empty($logic['rules']) || !is_array($logic['rules'])) {
            return null;
        }

        foreach ($logic['rules'] as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (
                strtolower((string) ($rule['field'] ?? '')) === 'lang'
                && strtolower((string) ($rule['operator'] ?? '')) === 'is'
                && !empty($rule['value'])
            ) {
                return self::normalizeLang((string) $rule['value']);
            }
        }

        return null;
    }

    private static function removeLangConditionalRule(array $item, string $lang): array
    {
        if (empty($item['conditionalLogic']['rules']) || !is_array($item['conditionalLogic']['rules'])) {
            return $item;
        }

        $rules = [];

        foreach ($item['conditionalLogic']['rules'] as $rule) {
            if (!is_array($rule) || self::isLangRuleFor($rule, $lang)) {
                continue;
            }

            $rules[] = $rule;
        }

        if (empty($rules)) {
            unset($item['conditionalLogic']);
            return $item;
        }

        $item['conditionalLogic']['rules'] = $rules;
        return $item;
    }

    private static function isLangRuleFor(array $rule, string $lang): bool
    {
        return strtolower((string) ($rule['field'] ?? '')) === 'lang'
            && strtolower((string) ($rule['operator'] ?? '')) === 'is'
            && self::normalizeLang((string) ($rule['value'] ?? '')) === $lang;
    }

    private static function normalizeLang(string $lang): ?string
    {
        $lang = strtolower(trim($lang));

        return preg_match('/^[a-z]{2}$/', $lang) ? $lang : null;
    }
}
