<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Does_Not_Contain
{
    private const OPERATOR = 'not_contains';
    private static bool $active = false;

    public static function init(): void
    {
        if (self::$active) {
            return;
        }

        self::$active = true;
        add_filter('gform_is_valid_conditional_logic_operator', [self::class, 'validate_operator'], 10, 2);
        add_filter('gform_is_value_match', [self::class, 'match_value'], 10, 6);
        add_filter('gform_noconflict_scripts', [self::class, 'allow_noconflict_script']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_editor_asset'], 20);
        add_action('gform_enqueue_scripts', [self::class, 'enqueue_frontend_asset'], 20);
    }

    public static function validate_operator(bool $is_valid, string $operator): bool
    {
        return $operator === self::OPERATOR ? true : $is_valid;
    }

    public static function match_value(bool $is_match, $field_value, $target_value, string $operation, $source_field, $rule): bool
    {
        return $operation === self::OPERATOR
            ? stripos((string) $field_value, (string) $target_value) === false
            : $is_match;
    }

    public static function enqueue_editor_asset(): void
    {
        if (!class_exists('PWE_Multilang_Admin_Access')
            || !PWE_Multilang_Admin_Access::is_allowed()
            || !class_exists('GFForms')
            || !GFForms::is_gravity_page()) {
            return;
        }

        self::enqueue_script('gform_form_admin');
    }

    public static function enqueue_frontend_asset(array $form): void
    {
        if (!self::contains_operator($form)) {
            return;
        }

        self::enqueue_script('gform_conditional_logic');
    }

    public static function allow_noconflict_script(array $scripts): array
    {
        $scripts[] = 'pwe-multilang-gf-does-not-contain';

        return array_values(array_unique($scripts));
    }

    private static function enqueue_script(string $dependency): void
    {
        $relative = 'includes/gf-addon/features/does-not-contain/assets/js/does-not-contain.js';
        $path = plugin_dir_path(PWE_MULTILANG_FILE) . $relative;

        wp_enqueue_script(
            'pwe-multilang-gf-does-not-contain',
            plugin_dir_url(PWE_MULTILANG_FILE) . $relative,
            [$dependency],
            file_exists($path) ? (string) filemtime($path) : PWE_MULTILANG_VERSION,
            true
        );
    }

    private static function contains_operator($value): bool
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return false;
        }

        if (($value['operator'] ?? null) === self::OPERATOR) {
            return true;
        }

        foreach ($value as $item) {
            if ((is_array($item) || is_object($item)) && self::contains_operator($item)) {
                return true;
            }
        }

        return false;
    }
}
