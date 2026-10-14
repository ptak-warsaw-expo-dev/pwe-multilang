<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Module_Registry
{
    private const OPTION_KEY = 'pwe_multilang_modules';

    /**
     * The order below is the order used on the General settings screen.
     * Loading and boot order are kept separately to preserve the existing hooks.
     *
     * @var array<string, array<string, mixed>>
     */
    private const MODULES = [
        'forms' => [
            'default'          => true,
            'label'            => 'Forms',
            'description'      => 'Generator i synchronizacja formularzy.',
            'page_slug'        => 'pwe-multilang-forms',
            'page_icon'        => 'dashicons-feedback',
            'page_callback'    => 'render_forms_page',
            'render_class'     => 'PWE_Multilang_Forms',
            'file'             => 'modules/forms/forms-module.php',
            'boot'             => ['PWE_Multilang_Forms', 'init'],
            'admin_only'       => true,
            'load_priority'    => 20,
            'boot_priority'    => 50,
        ],
        'form_translations' => [
            'default'          => true,
            'label'            => 'Form translations',
            'description'      => 'Tłumaczenia pól formularzy po stronie frontendu.',
            'page_slug'        => '',
            'page_icon'        => '',
            'page_callback'    => '',
            'render_class'     => '',
            'file'             => 'modules/form-translations/form-translations-module.php',
            'boot'             => ['PWE_Multilang_Form_Translations', 'init'],
            'admin_only'       => false,
            'load_priority'    => 30,
            'boot_priority'    => 60,
        ],
        'notification_translations' => [
            'default' => true,
            'label' => 'Tłumaczenia powiadomień',
            'description' => 'Biblioteka tłumaczeń powiadomień MJML zapisywana w plikach JSON.',
            'page_slug' => 'pwe-multilang-notification-translations',
            'page_icon' => 'dashicons-email-alt',
            'page_callback' => 'render_notification_translations_page',
            'render_class' => 'PWE_Multilang_Notification_Translations',
            'file' => 'modules/notification-translations/notification-translations-module.php',
            'boot' => ['PWE_Multilang_Notification_Translations', 'init'],
            'admin_only' => true,
            'load_priority' => 35,
            'boot_priority' => 65,
        ],
        'pages' => [
            'default'          => true,
            'label'            => 'Pages',
            'description'      => 'Generowanie stron i tłumaczeń WPML.',
            'page_slug'        => 'pwe-multilang-pages',
            'page_icon'        => 'dashicons-admin-page',
            'page_callback'    => 'render_pages_page',
            'render_class'     => 'PWE_Multilang_Pages',
            'file'             => 'modules/pages/pages-module.php',
            'boot'             => null,
            'admin_only'       => true,
            'load_priority'    => 40,
            'boot_priority'    => 0,
        ],
        'tools' => [
            'default'          => true,
            'label'            => 'Tools',
            'description'      => 'Narzędzia wpisów testowych i testowych powiadomień.',
            'page_slug'        => 'pwe-multilang-tools',
            'page_icon'        => 'dashicons-clipboard',
            'page_callback'    => 'render_tools_page',
            'render_class'     => 'PWE_Multilang_Tools',
            'file'             => 'modules/tools/tools-module.php',
            'boot'             => ['PWE_Multilang_Tools', 'init'],
            'admin_only'       => true,
            'load_priority'    => 71,
            'boot_priority'    => 11,
        ],
        'gf_addon' => [
            'default'          => true,
            'label'            => 'GF Addon',
            'description'      => 'Customowe rozszerzenia Gravity Forms zarządzane w kodzie.',
            'page_slug'        => 'pwe-multilang-gf-addon',
            'page_icon'        => 'dashicons-admin-plugins',
            'page_callback'    => 'render_gf_addon_page',
            'render_class'     => 'PWE_Multilang_GF_Addon',
            'file'             => 'modules/gf-addon/gf-addon-module.php',
            'boot'             => ['PWE_Multilang_GF_Addon', 'init'],
            'admin_only'       => false,
            'load_priority'    => 10,
            'boot_priority'    => 40,
        ],
    ];

    /** @var array<string, bool>|null */
    private static ?array $settings = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function modules(): array
    {
        return self::MODULES;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function module(string $key): ?array
    {
        return self::MODULES[$key] ?? null;
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::MODULES as $key => $module) {
            $defaults[$key] = !empty($module['default']);
        }

        return $defaults;
    }

    /**
     * @return array<string, bool>
     */
    public static function settings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $saved = get_option(self::OPTION_KEY, []);
        $saved = is_array($saved) ? $saved : [];
        $settings = self::defaults();

        foreach (array_keys($settings) as $module) {
            if (array_key_exists($module, $saved)) {
                $settings[$module] = (bool) $saved[$module];
            }
        }

        self::$settings = $settings;

        return self::$settings;
    }

    public static function is_enabled(string $module): bool
    {
        $settings = self::settings();

        return $settings[$module] ?? false;
    }

    public static function save_settings(array $incoming): void
    {
        $normalized = [];

        foreach (array_keys(self::defaults()) as $module) {
            $normalized[$module] = !empty($incoming[$module]);
        }

        update_option(self::OPTION_KEY, $normalized, false);
        self::$settings = $normalized;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function admin_pages(): array
    {
        $pages = [[
            'module'   => '',
            'slug'     => 'pwe-multilang',
            'label'    => 'General',
            'icon'     => 'dashicons-admin-settings',
            'callback' => 'render_page',
        ]];

        foreach (self::MODULES as $key => $module) {
            if (empty($module['page_slug'])) {
                continue;
            }

            $pages[] = [
                'module'   => $key,
                'slug'     => (string) $module['page_slug'],
                'label'    => (string) $module['label'],
                'icon'     => (string) $module['page_icon'],
                'callback' => (string) $module['page_callback'],
            ];
        }

        return $pages;
    }

    public static function load_modules(bool $is_admin): void
    {
        $modules = self::ordered_by('load_priority');

        foreach ($modules as $key => $module) {
            if (!empty($module['admin_only']) && !$is_admin) {
                continue;
            }

            if (!$is_admin && !self::is_enabled((string) $key)) {
                continue;
            }

            $file = self::resolve_module_file($module);

            if ($file !== '') {
                require_once $file;
            }
        }
    }

    public static function boot_modules(bool $is_admin): void
    {
        $modules = self::ordered_by('boot_priority');

        foreach ($modules as $key => $module) {
            if (!self::is_enabled($key)) {
                continue;
            }

            if (!empty($module['admin_only']) && !$is_admin) {
                continue;
            }

            $callback = $module['boot'] ?? null;

            if (is_callable($callback)) {
                call_user_func($callback);
            }
        }
    }

    private static function resolve_module_file(array $module): string
    {
        $relative = (string) ($module['file'] ?? '');

        if ($relative === '') {
            return '';
        }

        return PWE_MULTILANG_PATH . $relative;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function ordered_by(string $field): array
    {
        $modules = self::MODULES;

        uasort($modules, static function (array $left, array $right) use ($field): int {
            return ((int) ($left[$field] ?? 0)) <=> ((int) ($right[$field] ?? 0));
        });

        return $modules;
    }
}
