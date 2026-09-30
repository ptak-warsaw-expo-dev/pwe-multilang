<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/core/feature-registry.php';
require_once __DIR__ . '/core/admin-page.php';
require_once __DIR__ . '/features/notifications-ui/notifications-ui.php';
require_once __DIR__ . '/features/does-not-contain/does-not-contain.php';
require_once __DIR__ . '/features/conditional-groups/definition.php';
require_once __DIR__ . '/features/conditional-groups/logic-engine.php';
require_once __DIR__ . '/features/conditional-groups/form-preparer.php';
require_once __DIR__ . '/features/conditional-groups/settings.php';
require_once __DIR__ . '/features/conditional-groups/assets.php';
require_once __DIR__ . '/features/conditional-groups/conditional-groups.php';

final class PWE_Multilang_GF_Addon
{
    private static bool $registered = false;

    public static function init(): void
    {
        self::register_features();
        PWE_Multilang_GF_Addon_Features::boot();
    }

    public static function render_admin_page(): void
    {
        self::register_features();
        PWE_Multilang_GF_Addon_Admin::render();
    }

    private static function register_features(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PWE_Multilang_GF_Addon_Features::register(
            'gf_notifications_ui',
            'GF notifications UI',
            'Ulepszenia interfejsu na ekranach powiadomień i potwierdzeń Gravity Forms.',
            [PWE_Multilang_GF_Notifications_UI::class, 'init']
        );

        PWE_Multilang_GF_Addon_Features::register(
            'does_not_contain',
            'Conditional logic: does NOT contain',
            'Dodaje operator „does NOT contain” do logiki warunkowej Gravity Forms.',
            [PWE_Multilang_GF_Does_Not_Contain::class, 'init']
        );

        // PWE_Multilang_GF_Addon_Features::register(
        //     'conditional_groups',
        //     'Conditional logic: rule groups',
        //     'Dodaje wiele grup reguł z logiką Any/All wewnątrz grup i pomiędzy grupami.',
        //     [PWE_Multilang_GF_Conditional_Groups::class, 'init']
        // );

        /**
         * Pozwala rejestrować kolejne rozszerzenia bez dodawania ustawień w panelu.
         * Callback akcji otrzymuje nazwę klasy rejestru.
         */
        do_action('pwe_multilang_gf_addon_register_features', PWE_Multilang_GF_Addon_Features::class);
    }
}
