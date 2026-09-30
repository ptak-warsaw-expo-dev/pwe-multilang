<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Content_Transformer
{
    /**
     * Ordered rules preserve the exact transformation order used previously.
     */
    private const FORM_RULES = [
        [
            'tag' => 'pwe_registration',
            'match_attribute' => 'registration_type',
            'match_value' => 'PWERegistrationVisitors',
            'bindings' => [
                'registration_form_id' => 'rejestracja',
            ],
        ],
        [
            'tag' => 'pwe_registration',
            'match_attribute' => 'registration_type',
            'match_value' => 'PWERegistrationExhibitors',
            'bindings' => [
                'registration_form_id' => 'zostan_wystawca',
            ],
        ],
        [
            'tag' => 'pwelement',
            'match_attribute' => 'pwe_element',
            'match_value' => 'PWElementStepTwoExhibitor',
            'bindings' => [
                'registration_form_step2_exhibitor' => 'zostan_wystawca',
                'registration_form_step2_exhibitor_www2' => 'zostan_wystawca_krok2',
            ],
        ],
        [
            'tag' => 'pwelement',
            'match_attribute' => 'pwe_element',
            'match_value' => 'PWElementPotwierdzenieRejestracji',
            'bindings' => [
                'reg_form_name_pr' => 'rejestracja',
            ],
        ],
    ];

    public static function transform_for_language(string $content, string $lang_code, int $event_year, array &$form_template_slugs = []): string
    {
        if (in_array($lang_code, PWE_Multilang_Page_Config::get_skipped_languages(), true)) {
            return $content;
        }

        if ($event_year <= 0) {
            $event_year = PWE_Multilang_Year_Resolver::current();
        }

        foreach (self::FORM_RULES as $rule) {
            $content = self::apply_form_rule($content, $rule, $event_year, $form_template_slugs);
        }

        return $content;
    }

    private static function apply_form_rule(
        string $content,
        array $rule,
        int $event_year,
        array &$form_template_slugs
    ): string
    {
        $tag = (string) ($rule['tag'] ?? '');
        $match_attribute = (string) ($rule['match_attribute'] ?? '');
        $match_value = (string) ($rule['match_value'] ?? '');
        $bindings = is_array($rule['bindings'] ?? null) ? $rule['bindings'] : [];

        if ($tag === '' || $match_attribute === '' || $match_value === '' || empty($bindings)) {
            return $content;
        }

        $pattern = '/\[' . preg_quote($tag, '/') . '\b[^\]]*\]/i';

        return (string) preg_replace_callback($pattern, static function (array $matches) use (
            $match_attribute,
            $match_value,
            $bindings,
            $event_year,
            &$form_template_slugs
        ): string {
            $shortcode = $matches[0];

            if (!self::has_attribute_value($shortcode, $match_attribute, $match_value)) {
                return $shortcode;
            }

            foreach ($bindings as $attribute => $template_slug) {
                $shortcode = self::set_template_form_attribute(
                    $shortcode,
                    (string) $attribute,
                    (string) $template_slug,
                    $event_year,
                    $form_template_slugs
                );
            }

            return $shortcode;
        }, $content);
    }

    private static function set_template_form_attribute(
        string $shortcode,
        string $attribute,
        string $template_slug,
        int $event_year,
        array &$form_template_slugs
    ): string {
        $form_template_slugs[] = $template_slug;

        if (!class_exists('PWE_Multilang_Form_Core')) {
            return $shortcode;
        }

        $title = PWE_Multilang_Form_Core::getTemplateTitle($template_slug, $event_year);

        if ($title === null || $title === '') {
            return $shortcode;
        }

        return self::set_attribute_value($shortcode, $attribute, $title);
    }

    private static function has_attribute_value(string $shortcode, string $attribute, string $expected_value): bool
    {
        $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*([\"\'])(.*?)\1/i';

        if (!preg_match($pattern, $shortcode, $matches)) {
            return false;
        }

        return html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8') === $expected_value;
    }

    private static function set_attribute_value(string $shortcode, string $attribute, string $new_value): string
    {
        $escaped_value = esc_attr($new_value);
        $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*([\"\'])(.*?)\1/i';

        if (preg_match($pattern, $shortcode)) {
            return (string) preg_replace_callback($pattern, static function (array $matches) use ($attribute, $escaped_value): string {
                return $attribute . '=' . $matches[1] . $escaped_value . $matches[1];
            }, $shortcode, 1);
        }

        return rtrim($shortcode, ']') . ' ' . $attribute . '="' . $escaped_value . '"]';
    }
}
