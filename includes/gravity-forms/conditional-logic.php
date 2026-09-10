<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Decouples consumers from optional conditional-logic extensions.
 */
final class PWE_Multilang_GF_Conditional_Logic
{
    public static function compile($logic, string $context, array $owner = [])
    {
        return apply_filters('pwe_multilang_compile_conditional_logic', $logic, $context, $owner);
    }

    public static function evaluate($logic, array $form, array $entry): bool
    {
        if (empty($logic)) {
            return true;
        }

        if (!class_exists('GFCommon') || !method_exists('GFCommon', 'evaluate_conditional_logic')) {
            return true;
        }

        return (bool) GFCommon::evaluate_conditional_logic($logic, $form, $entry);
    }
}

