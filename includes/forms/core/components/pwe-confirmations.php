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
        array $conditionalLogic = [],
        bool $disableAutoformat = false,
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
            $confirmation['disableAutoformat'] = $disableAutoformat;
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
        bool $isDefault = false,
        array $allRules = []
    ) : array {

        $confirmations = [];

        foreach ($langs as $lang) {
            $lang = strtolower($lang);

            $confirmations[] = self::Confirmation(
                name: $name . ' - ' . strtoupper($lang),
                type: 'redirect',
                url: self::translatedPageUrl($baseSlug, $lang),
                isDefault: $isDefault,
                conditionalLogic: [
                    'actionType' => 'show',
                    'logicType'  => 'all',
                    'rules' => array_merge([
                        [
                            'field'    => $langField,
                            'operator' => 'is',
                            'value'    => $lang,
                        ],
                    ], $allRules),
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

    public static function Multilang_Message_Confirmations(
        array $langs,
        array $messages,
        string $langField = 'lang',
        bool $isDefault = false
    ): array {
        $confirmations = [];

        foreach ($langs as $lang) {
            $lang = strtolower($lang);

            if (empty($messages[$lang])) {
                continue;
            }

            $confirmations[] = self::Confirmation(
                name: $isDefault ? 'Default Confirmation' : 'Confirmation - ' . strtoupper($lang),
                type: 'message',
                message: $messages[$lang],
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

    public static function Multilang_Message_Confirmations_With_Any_Rules(
        array $langs,
        array $messages,
        array $anyRules,
        string $langField = 'lang',
        bool $isDefault = false
    ): array {
        $confirmations = [];

        foreach ($langs as $lang) {
            $lang = strtolower($lang);

            if (empty($messages[$lang])) {
                continue;
            }

            foreach ($anyRules as $index => $rule) {
                $confirmations[] = self::Confirmation(
                    name: 'Confirmation - ' . sanitize_text_field($rule['value'] ?? 'rule-' . ($index + 1)) . ' - ' . strtoupper($lang),
                    type: 'message',
                    message: $messages[$lang],
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

