<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Site_Group_Config
{
    public const RESOURCE_PAGES = 'pages';
    public const RESOURCE_FORMS = 'forms';

    private const SUPPORTED_GROUPS = ['gr1', 'gr2', 'gr3', 'b2c', 'b2c-new', 'week'];

    /** @var array<string, array<string, bool>> */
    private const PAGE_GROUPS = [
        'home' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'dla_odwiedzajacych' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'dla_wystawcow' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'informacje_organizacyjne_dla_wystawcow' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'kontakt' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'krok2' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'wypromuj_sie' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'katalog_wystawcow' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'rejestracja' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'zostan_wystawca' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'potwierdzenie_rejestracji_wystawcy' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
    ];

    /** @var array<string, array<string, bool>> */
    private const FORM_GROUPS = [
        'badge_generator_local' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'ceremonia_medalowa' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'napisz_do_nas' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'pw_potencjalny_wystawca_aktywacja' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'rejestracja_gosci_wystawcow' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'rejestracja' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'rejestracja_fb' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'rejestracja_wystawcow_badge' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'rejestracja_zaproszen_call_centre' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'voucher_generator' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'zostan_wystawca' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
        'zostan_wystawca_krok2' => [
            'gr1' => true, 'gr2' => true, 'gr3' => true, 'b2c' => true, 'b2c-new' => true, 'week' => true,
        ],
    ];

    /** @return string[] */
    public static function supported_groups(): array
    {
        return self::SUPPORTED_GROUPS;
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

}
