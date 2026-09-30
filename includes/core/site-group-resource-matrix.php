<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Site_Group_Resource_Matrix
{
    public const RESOURCE_PAGES = 'pages';
    public const RESOURCE_FORMS = 'forms';

    private const SUPPORTED_GROUPS = ['gr1', 'gr2', 'gr3', 'b2c', 'b2c-new', 'week'];

    /** @var array<string, array<string, bool>> */
    private const PAGE_GROUPS = [
        'home' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'dla_odwiedzajacych' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'dla_wystawcow' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'informacje_organizacyjne_dla_wystawcow' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'kontakt' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'krok2' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => false, 'b2c-new' => false, 'week' => false,
        ],
        'wypromuj_sie' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'katalog_wystawcow' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'rejestracja' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => false, 'b2c-new' => false, 'week' => false,
        ],
        'zostan_wystawca' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'potwierdzenie_rejestracji_wystawcy' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
    ];

    /** @var array<string, array<string, bool>> */
    private const FORM_GROUPS = [
        'badge_generator_local' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'ceremonia_medalowa' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'napisz_do_nas' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'pw_potencjalny_wystawca_aktywacja' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'rejestracja_gosci_wystawcow' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'rejestracja' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => false, 'b2c-new' => false, 'week' => false,
        ],
        'rejestracja_fb' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => false, 'b2c-new' => false, 'week' => false,
        ],
        'rejestracja_wystawcow_badge' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'rejestracja_zaproszen_call_centre' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'voucher_generator' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'zostan_wystawca' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
        'zostan_wystawca_krok2' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => false,
        ],
    ];

    /** @return string[] */
    public static function supported_groups(): array
    {
        return self::SUPPORTED_GROUPS;
    }

    public static function is_supported_group(string $group): bool
    {
        return in_array($group, self::SUPPORTED_GROUPS, true);
    }

    public static function is_assigned(string $resource_type, string $resource_key, string $group): bool
    {
        $resources = self::resources($resource_type);

        return !empty($resources[$resource_key][$group]);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function resources(string $resource_type): array
    {
        if ($resource_type === self::RESOURCE_PAGES) {
            return self::PAGE_GROUPS;
        }

        if ($resource_type === self::RESOURCE_FORMS) {
            return self::FORM_GROUPS;
        }

        return [];
    }

    /**
     * Reports missing and stale matrix entries without changing availability.
     *
     * @param string[] $page_keys
     * @param string[] $form_slugs
     * @return string[]
     */
    public static function validate(array $page_keys, array $form_slugs): array
    {
        $errors = [];
        $catalogs = [
            self::RESOURCE_PAGES => array_values(array_unique(array_map('strval', $page_keys))),
            self::RESOURCE_FORMS => array_values(array_unique(array_map('strval', $form_slugs))),
        ];

        foreach ($catalogs as $type => $keys) {
            $matrix = self::resources($type);

            foreach (array_diff($keys, array_keys($matrix)) as $key) {
                $errors[] = $type . ': brak przypisania dla zasobu „' . $key . '”.';
            }

            foreach (array_diff(array_keys($matrix), $keys) as $key) {
                $errors[] = $type . ': przypisanie wskazuje nieistniejący zasób „' . $key . '”.';
            }

            foreach ($matrix as $key => $groups) {
                foreach (self::SUPPORTED_GROUPS as $group) {
                    if (!array_key_exists($group, $groups) || !is_bool($groups[$group])) {
                        $errors[] = $type . ': niepełne przypisanie „' . $key . '” dla ' . $group . '.';
                    }
                }
            }
        }

        return $errors;
    }
}
