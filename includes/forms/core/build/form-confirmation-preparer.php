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
