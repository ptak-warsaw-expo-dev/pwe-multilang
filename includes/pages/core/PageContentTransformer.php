<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Content_Transformer
{
    public static function transform_for_language(string $content, string $lang_code, int $event_year): string
    {
        if (in_array($lang_code, PWE_Multilang_Page_Config::get_skipped_languages(), true)) {
            return $content;
        }

        if ($event_year <= 0) {
            $event_year = (int) gmdate('Y');
        }

        $content = self::replace_registration_shortcode_forms($content, $event_year);
        $content = self::replace_step_two_exhibitor_forms($content, $event_year);
        $content = self::replace_registration_confirmation_forms($content, $event_year);

        return $content;
    }

    private static function replace_registration_shortcode_forms(string $content, int $event_year): string
    {
        return (string) preg_replace_callback('/\[pwe_registration\b[^\]]*\]/i', static function (array $matches) use ($event_year): string {
            $shortcode = $matches[0];

            if (self::has_attribute_value($shortcode, 'registration_type', 'PWERegistrationVisitors')) {
                return self::set_attribute_value($shortcode, 'registration_form_id', '(' . $event_year . ') Rejestracja Multilang');
            }

            if (self::has_attribute_value($shortcode, 'registration_type', 'PWERegistrationExhibitors')) {
                return self::set_attribute_value($shortcode, 'registration_form_id', '(' . $event_year . ') Zostań wystawcą Multilang');
            }

            return $shortcode;
        }, $content);
    }

    private static function replace_step_two_exhibitor_forms(string $content, int $event_year): string
    {
        return (string) preg_replace_callback('/\[pwelement\b[^\]]*\]/i', static function (array $matches) use ($event_year): string {
            $shortcode = $matches[0];

            if (!self::has_attribute_value($shortcode, 'pwe_element', 'PWElementStepTwoExhibitor')) {
                return $shortcode;
            }

            $shortcode = self::set_attribute_value($shortcode, 'registration_form_step2_exhibitor', '(' . $event_year . ') Zostań wystawcą Multilang');
            $shortcode = self::set_attribute_value($shortcode, 'registration_form_step2_exhibitor_www2', '(' . $event_year . ') Zostań wystawcą Multilang (krok2)');

            return $shortcode;
        }, $content);
    }

    private static function replace_registration_confirmation_forms(string $content, int $event_year): string
    {
        return (string) preg_replace_callback('/\[pwelement\b[^\]]*\]/i', static function (array $matches) use ($event_year): string {
            $shortcode = $matches[0];

            if (!self::has_attribute_value($shortcode, 'pwe_element', 'PWElementPotwierdzenieRejestracji')) {
                return $shortcode;
            }

            return self::set_attribute_value($shortcode, 'reg_form_name_pr', '(' . $event_year . ') Rejestracja Multilang');
        }, $content);
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
