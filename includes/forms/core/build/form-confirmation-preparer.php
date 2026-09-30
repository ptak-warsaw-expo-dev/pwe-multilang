<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Confirmation_Preparer
{
    public static function prepare(array $confirmations, array $fields): array
    {
        $confirmations = self::normalize($confirmations);
        $confirmations = PWE_Multilang_Form_Merge_Tag_Resolver::confirmations($confirmations, $fields);

        return PWE_Multilang_Form_Conditional_Logic_Resolver::confirmations($confirmations, $fields);
    }

    /**
     * Gravity Forms requires exactly one default confirmation. Prefer the
     * English confirmation as the unconditional fallback for multilingual
     * forms, then Polish when English is unavailable. If neither language is
     * present, keep the existing default or use the first confirmation.
     */
    public static function ensureSingleDefault(array $confirmations): array
    {
        if ($confirmations === []) {
            return $confirmations;
        }

        $defaultId = self::findLanguageConfirmation($confirmations, 'en');

        if ($defaultId === null) {
            $defaultId = self::findLanguageConfirmation($confirmations, 'pl');
        }

        if ($defaultId === null) {
            foreach ($confirmations as $id => $confirmation) {
                if (is_array($confirmation) && !empty($confirmation['isDefault'])) {
                    $defaultId = $id;
                    break;
                }
            }
        }

        if ($defaultId === null) {
            $defaultId = array_key_first($confirmations);
        }

        foreach ($confirmations as $id => &$confirmation) {
            if (!is_array($confirmation)) {
                continue;
            }

            $confirmation['isDefault'] = $id === $defaultId;

            if ($id === $defaultId) {
                // Conditional logic is ignored by Gravity Forms for defaults.
                unset($confirmation['conditionalLogic']);
            }
        }
        unset($confirmation);

        return $confirmations;
    }

    private static function findLanguageConfirmation(array $confirmations, string $language)
    {
        foreach ($confirmations as $id => $confirmation) {
            if (!is_array($confirmation)) {
                continue;
            }

            if (preg_match(
                '/(?:^|\s-\s)' . preg_quote($language, '/') . '$/i',
                trim((string) ($confirmation['name'] ?? ''))
            ) === 1) {
                return $id;
            }

        }

        return null;
    }

    private static function normalize(array $confirmations): array
    {
        $normalized = [];

        foreach ($confirmations as $confirmation) {
            if (empty($confirmation['id'])) {
                $confirmation['id'] = uniqid();
            }

            $normalized[$confirmation['id']] = $confirmation;
        }

        return $normalized;
    }
}
