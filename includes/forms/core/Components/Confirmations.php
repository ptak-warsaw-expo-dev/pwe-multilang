<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_GF_Confirmations
{
    public static function Confirmation(
        string $name,
        string $type = 'message',
        string $message = '',
        string $url = '',
        bool $isDefault = false,
        array $conditionalLogic = []
    ): array {
        $confirmation = [
            'id' => 'pwe_' . md5($name),
            'name' => $name,
            'isDefault' => $isDefault,
            'type' => $type,
        ];

        if ($type === 'redirect') {
            $confirmation['url'] = $url;
        } else {
            $confirmation['message'] = $message;
        }

        if (!empty($conditionalLogic)) {
            $confirmation['conditionalLogic'] = $conditionalLogic;
        }

        return $confirmation;
    }

    public static function translatedPageUrl(string $baseSlug, string $lang) : string
    {
        $baseSlug = trim($baseSlug, '/');
        $lang     = strtolower($lang);

        $page = get_page_by_path($baseSlug, OBJECT, 'page');

        if (!$page) {
            return home_url('/' . $baseSlug . '/');
        }

        $translatedId = apply_filters(
            'wpml_object_id',
            $page->ID,
            'page',
            true,
            $lang
        );

        if ($translatedId) {
            return get_permalink($translatedId);
        }

        return get_permalink($page->ID);
    }

    public static function Multilang_Redirect_To_Translated_Page(
        string $name,
        string $baseSlug,
        array $langs,
        string $langField = 'lang',
        bool $isDefault = false
    ) : array {

        $confirmations = [];

        foreach ($langs as $lang) {
            $lang = strtolower($lang);

            if (in_array($lang, ['pl'], true)) {
                continue;
            }

            $confirmations[] = self::Confirmation(
                name: $name . ' - ' . strtoupper($lang),
                type: 'redirect',
                url: self::translatedPageUrl($baseSlug, $lang),
                isDefault: $isDefault,
                conditionalLogic: [
                    'actionType' => 'show',
                    'logicType'  => 'all',
                    'rules' => [
                        [
                            'field'    => $langField,
                            'operator' => 'is',
                            'value'    => $lang,
                        ],
                    ],
                ],
            );
        }

        return $confirmations;
    }

    public static function Multilang_Redirect_To_Translated_Page_With_Any_Rules(
        string $name,
        string $baseSlug,
        array $langs,
        array $anyRules,
        string $langField = 'lang',
        bool $isDefault = false
    ) : array {

        $confirmations = [];

        foreach ($langs as $lang) {
            $lang = strtolower($lang);

            if ($lang === 'pl') {
                continue;
            }

            foreach ($anyRules as $index => $rule) {
                $confirmations[] = self::Confirmation(
                    name: $name . ' - ' . sanitize_text_field($rule['value'] ?? 'rule-' . ($index + 1)) . ' - ' . strtoupper($lang),
                    type: 'redirect',
                    url: self::translatedPageUrl($baseSlug, $lang),
                    isDefault: $isDefault,
                    conditionalLogic: [
                        'actionType' => 'show',
                        'logicType'  => 'all',
                        'rules' => [
                            [
                                'field'    => $langField,
                                'operator' => 'is',
                                'value'    => $lang,
                            ],
                            $rule,
                        ],
                    ],
                );
            }
        }

        return $confirmations;
    }
}

