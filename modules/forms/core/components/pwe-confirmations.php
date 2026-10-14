<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_GF_Confirmations
{
    private static function language_rules(string $lang, array $langs, string $langField): array
    {
        return PWE_Multilang_Form_Language_Rules::build($lang, $langs, $langField);
    }

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
        $lang = strtolower(trim($lang));
        $previous = PWE_Multilang_Language_Catalog::current_wpml(false);

        try {
            do_action('wpml_switch_language', 'pl');
            // get_page_by_path() alone does not constrain the WPML language.
            $pages = get_posts([
                'post_type' => 'page',
                'post_status' => 'publish',
                'pagename' => $baseSlug,
                'posts_per_page' => 1,
                'lang' => 'pl',
                'suppress_filters' => false,
            ]);
            $page = $pages[0] ?? null;
            if ($baseSlug === '' || !$page instanceof WP_Post) {
                throw new RuntimeException('Brak opublikowanej strony źródłowej PL dla potwierdzenia: ' . $baseSlug);
            }

            $translatedId = (int) apply_filters('wpml_object_id', $page->ID, 'page', false, $lang);
            $target = $translatedId > 0 ? get_post($translatedId) : null;
            if (!$target instanceof WP_Post || $target->post_type !== 'page' || $target->post_status !== 'publish') {
                $target = $page;
            }
            do_action('wpml_switch_language', (int) $target->ID === (int) $page->ID ? 'pl' : $lang);
            $url = get_permalink($target->ID);
            if (!is_string($url) || $url === '') {
                throw new RuntimeException('Nie można odczytać adresu strony potwierdzenia: ' . $baseSlug);
            }
            return $url;
        } finally {
            do_action('wpml_switch_language', $previous);
        }
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
                    'rules' => array_merge(
                        self::language_rules($lang, $langs, $langField),
                        $allRules
                    ),
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
                        'rules' => array_merge(
                            self::language_rules($lang, $langs, $langField),
                            [$rule]
                        ),
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
                    'rules' => self::language_rules($lang, $langs, $langField),
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
                        'rules' => array_merge(
                            self::language_rules($lang, $langs, $langField),
                            [$rule]
                        ),
                    ],
                );
            }
        }

        return $confirmations;
    }
}

