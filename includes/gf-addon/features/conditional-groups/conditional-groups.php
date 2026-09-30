<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibility facade for the Conditional Groups feature.
 */
final class PWE_Multilang_GF_Conditional_Groups
{
    private static bool $active = false;
    private static bool $evaluating_groups = false;
    private static bool $resolving_current_lead = false;

    public static function init(): void
    {
        if (self::$active) {
            return;
        }

        self::$active = true;
        add_filter('gform_is_valid_conditional_logic_operator', [self::class, 'validate_operator'], 20, 2);
        add_filter('gform_rule_pre_evaluation', [self::class, 'prepare_rule'], 20, 5);
        add_filter('gform_is_value_match', [self::class, 'match_value'], 20, 6);
        add_filter('pwe_multilang_compile_conditional_logic', [self::class, 'compile_for_consumer'], 10, 3);

        foreach ([
            'gform_pre_render',
            'gform_pre_validation',
            'gform_pre_submission_filter',
            'gform_pre_process',
            'gform_admin_pre_render',
        ] as $hook) {
            add_filter($hook, [self::class, 'prepare_form'], 20);
        }

        add_filter('gform_form_pre_process_async_task', [self::class, 'prepare_async_form'], 20, 2);
        add_filter('gform_addon_pre_process_feeds', [self::class, 'prepare_feeds'], 20, 3);
        add_filter('gform_notification_settings_fields', [self::class, 'notification_settings'], 20, 3);
        add_filter('gform_pre_notification_save', [self::class, 'save_notification'], 20, 2);
        add_filter('gform_confirmation_settings_fields', [self::class, 'confirmation_settings'], 20, 3);
        add_filter('gform_pre_confirmation_save', [self::class, 'save_confirmation'], 20, 2);
        add_filter('gform_addon_feed_settings_fields', [self::class, 'feed_settings'], 20, 2);
        add_filter('gform_noconflict_scripts', [self::class, 'allow_noconflict_script']);
        add_filter('gform_noconflict_styles', [self::class, 'allow_noconflict_style']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets'], 30);
        add_action('gform_enqueue_scripts', [self::class, 'enqueue_frontend_asset'], 30);
    }

    public static function is_active(): bool
    {
        return self::$active;
    }

    public static function validate_operator(bool $is_valid, string $operator): bool
    {
        return in_array($operator, [
            PWE_Multilang_GF_Conditional_Groups_Engine::GROUP_OPERATOR,
            PWE_Multilang_GF_Conditional_Groups_Engine::DEPENDENCY_OPERATOR,
        ], true) ? true : $is_valid;
    }

    public static function prepare_rule(
        array $rule,
        array $form,
        array $logic,
        $field_values,
        $entry
    ): array {
        if (($rule['operator'] ?? '') !== PWE_Multilang_GF_Conditional_Groups_Engine::GROUP_OPERATOR
            || self::$evaluating_groups) {
            return $rule;
        }

        $entry = self::entry_for_evaluation($entry);
        self::$evaluating_groups = true;

        try {
            $rule['pweResult'] = self::evaluate_groups(
                is_array($rule['pweGroups'] ?? null) ? $rule['pweGroups'] : [],
                $form,
                $entry
            );
        } finally {
            self::$evaluating_groups = false;
        }

        return $rule;
    }

    public static function match_value(
        bool $is_match,
        $field_value,
        $target_value,
        string $operation,
        $source_field,
        $rule
    ): bool {
        if ($operation === PWE_Multilang_GF_Conditional_Groups_Engine::DEPENDENCY_OPERATOR) {
            return true;
        }

        if ($operation === PWE_Multilang_GF_Conditional_Groups_Engine::GROUP_OPERATOR) {
            return !empty($rule['pweResult']);
        }

        return $is_match;
    }

    public static function prepare_form($form)
    {
        return PWE_Multilang_GF_Conditional_Groups_Form_Preparer::prepare($form);
    }

    public static function prepare_async_form($form, $entry)
    {
        return self::prepare_form($form);
    }

    public static function prepare_feeds(array $feeds, array $entry, array $form): array
    {
        return PWE_Multilang_GF_Conditional_Groups_Form_Preparer::prepare_feeds($feeds);
    }

    public static function notification_settings(array $fields, array $notification, array $form): array
    {
        return PWE_Multilang_GF_Conditional_Groups_Settings::add_hidden(
            $fields,
            $notification[PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY] ?? []
        );
    }

    public static function confirmation_settings(array $fields, array $confirmation, array $form): array
    {
        return PWE_Multilang_GF_Conditional_Groups_Settings::add_hidden(
            $fields,
            $confirmation[PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY] ?? []
        );
    }

    public static function feed_settings(array $fields, $addon): array
    {
        return PWE_Multilang_GF_Conditional_Groups_Settings::add_feed_hidden($fields);
    }

    public static function save_notification(array $notification, array $form): array
    {
        return PWE_Multilang_GF_Conditional_Groups_Settings::save($notification);
    }

    public static function save_confirmation(array $confirmation, array $form): array
    {
        return PWE_Multilang_GF_Conditional_Groups_Settings::save($confirmation);
    }

    public static function enqueue_admin_assets(): void
    {
        PWE_Multilang_GF_Conditional_Groups_Assets::enqueue_admin();
    }

    public static function enqueue_frontend_asset(array $form): void
    {
        PWE_Multilang_GF_Conditional_Groups_Assets::enqueue_frontend($form);
    }

    public static function allow_noconflict_script(array $scripts): array
    {
        $scripts[] = 'pwe-multilang-gf-conditional-groups-admin';

        return array_values(array_unique($scripts));
    }

    public static function allow_noconflict_style(array $styles): array
    {
        $styles[] = 'pwe-multilang-gf-conditional-groups-admin';

        return array_values(array_unique($styles));
    }

    public static function compile_logic($logic, $definition)
    {
        return PWE_Multilang_GF_Conditional_Groups_Engine::compile($logic, $definition);
    }

    public static function compile_for_consumer($logic, string $context, array $owner)
    {
        return self::compile_logic(
            $logic,
            $owner[PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY] ?? []
        );
    }

    public static function evaluate_groups(array $definition, array $form, array $entry): bool
    {
        return PWE_Multilang_GF_Conditional_Groups_Engine::evaluate($definition, $form, $entry);
    }

    /**
     * Gravity Forms can call the rule filter before an entry has been saved.
     * Its documented fallback is GFFormsModel::get_current_lead(), which may
     * itself evaluate conditional logic. Temporarily detaching this callback
     * prevents that lookup from recursively entering the grouped evaluator.
     */
    private static function entry_for_evaluation($entry): array
    {
        if (is_array($entry) && $entry) {
            return $entry;
        }

        if (self::$resolving_current_lead
            || !class_exists('GFFormsModel')
            || !method_exists('GFFormsModel', 'get_current_lead')) {
            return [];
        }

        self::$resolving_current_lead = true;
        remove_filter('gform_rule_pre_evaluation', [self::class, 'prepare_rule'], 20);

        try {
            $current_lead = GFFormsModel::get_current_lead();
        } catch (Throwable $error) {
            $current_lead = [];
        } finally {
            add_filter('gform_rule_pre_evaluation', [self::class, 'prepare_rule'], 20, 5);
            self::$resolving_current_lead = false;
        }

        return is_array($current_lead) ? $current_lead : [];
    }
}
