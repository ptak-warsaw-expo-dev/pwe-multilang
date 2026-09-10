<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Admin
{
    private const MODULES_NONCE_ACTION = 'pwe_multilang_save_modules';
    private const MODULES_NONCE_NAME = 'pwe_multilang_modules_nonce';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'pwe-multilang') === false) {
            return;
        }

        $css_path = PWE_MULTILANG_PATH . 'assets/admin.css';
        $core_js_path = PWE_MULTILANG_PATH . 'assets/js/admin-core.js';

        wp_enqueue_style(
            'pwe-multilang-admin',
            PWE_MULTILANG_URL . 'assets/admin.css',
            [],
            self::asset_version($css_path)
        );

        wp_enqueue_script(
            'pwe-multilang-admin',
            PWE_MULTILANG_URL . 'assets/js/admin-core.js',
            ['jquery'],
            self::asset_version($core_js_path),
            true
        );

        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        $scripts = [
            'pwe-multilang-forms'           => 'forms.js',
            'pwe-multilang-tests'           => 'tests.js',
            'pwe-multilang-resend'          => 'resend.js',
            'pwe-multilang-replace-content' => 'replace-content.js',
        ];

        if (isset($scripts[$page])) {
            $filename = $scripts[$page];
            $path = PWE_MULTILANG_PATH . 'assets/js/' . $filename;
            wp_enqueue_script(
                'pwe-multilang-admin-' . sanitize_key(pathinfo($filename, PATHINFO_FILENAME)),
                PWE_MULTILANG_URL . 'assets/js/' . $filename,
                ['jquery', 'pwe-multilang-admin'],
                self::asset_version($path),
                true
            );
        }
    }

    public static function register_menu(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        $pages = PWE_Multilang_Module_Registry::admin_pages();
        $general = $pages[0];

        add_menu_page(
            'PWE Multilang',
            'PWE Multilang',
            'read',
            $general['slug'],
            [self::class, $general['callback']],
            'dashicons-translation',
            4
        );

        foreach ($pages as $page) {
            add_submenu_page(
                $general['slug'],
                $page['label'],
                $page['label'],
                'read',
                $page['slug'],
                [self::class, $page['callback']]
            );
        }
    }

    public static function render_page(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        self::handle_modules_settings_submit();
        self::render_page_wrapper('pwe-multilang', [self::class, 'render_general_content']);
    }

    public static function render_forms_page(): void
    {
        self::render_module_page('forms');
    }

    public static function render_pages_page(): void
    {
        self::render_module_page('pages');
    }

    public static function render_tests_page(): void
    {
        self::render_module_page('tests');
    }

    public static function render_replace_content_page(): void
    {
        self::render_module_page('replace_content');
    }

    public static function render_resend_page(): void
    {
        self::render_module_page('resend');
    }

    public static function render_gf_addon_page(): void
    {
        self::render_module_page('gf_addon');
    }

    private static function render_header(string $active): void
    {
        $modules = PWE_Multilang_Module_Registry::settings();
        $pages = PWE_Multilang_Module_Registry::admin_pages();
        ?>
        <div class="pwe-wrap">
            <div class="pwe-topbar">
                <div class="pwe-topbar-icon">
                    <span class="dashicons dashicons-translation"></span>
                </div>
                <div>
                    <h1>PWE Multilang</h1>
                    <p>Plugin management</p>
                </div>
            </div>

            <nav class="pwe-seg">
                <?php foreach ($pages as $page) :
                    $module_key = $page['module'];
                    $is_disabled = $module_key !== '' && empty($modules[$module_key]);
                    $classes = [];

                    if ($active === $page['slug']) {
                        $classes[] = 'active';
                    }

                    if ($is_disabled) {
                        $classes[] = 'disabled';
                    }
                    ?>
                    <a href="<?php echo $is_disabled ? '#' : esc_url(admin_url('admin.php?page=' . $page['slug'])); ?>"
                       class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                       <?php if ($is_disabled) : ?>
                           aria-disabled="true"
                           title="Moduł jest wyłączony w General settings"
                       <?php endif; ?>>
                        <span class="dashicons <?php echo esc_attr($page['icon']); ?>"></span>
                        <?php echo esc_html($page['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php
    }

    private static function render_footer(): void
    {
        echo '</div><!-- /.pwe-wrap -->';
    }

    private static function render_page_wrapper(string $active, callable $content): void
    {
        self::render_header($active);
        call_user_func($content);
        self::render_footer();
    }

    private static function render_general_content(): void
    {
        PWE_Multilang_Admin_UI::cardOpen('pwe-card');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-admin-settings"></span> General settings');
        PWE_Multilang_Admin_UI::cardDesc('Panel administracyjny wtyczki PWE Multilang. Tutaj znajdziesz ogólne ustawienia i konfigurację wtyczki.');

        $matrix_errors = self::site_group_matrix_errors();

        if ($matrix_errors !== []) {
            PWE_Multilang_Admin_UI::notice(
                'pwe-notice-error',
                'dashicons-warning',
                '<strong>Niespójne przypisania grup serwisów:</strong><br>'
                    . implode('<br>', array_map('esc_html', $matrix_errors))
            );
        }

        echo '<hr class="pwe-divider">';
        echo '<p class="pwe-meta-line">';
        echo 'Wersja: <strong>' . esc_html(PWE_MULTILANG_VERSION) . '</strong>';
        echo '&nbsp;&middot;&nbsp;';
        echo 'Status: <span class="pwe-state-ok">Active</span>';
        echo '</p>';

        if (!empty($_GET['pwe_modules_saved'])) {
            PWE_Multilang_Admin_UI::notice('pwe-notice-success pwe-toast-success', 'dashicons-yes-alt', 'Ustawienia modułów zostały zapisane.');
        }

        echo '<hr class="pwe-divider">';
        echo '<p class="pwe-section-heading">Moduły</p>';

        echo '<form method="post">';
        wp_nonce_field(self::MODULES_NONCE_ACTION, self::MODULES_NONCE_NAME);
        echo '<input type="hidden" name="pwe_modules_submit" value="1">';

        echo '<div class="pwe-modules-grid">';

        foreach (PWE_Multilang_Module_Registry::modules() as $key => $module) {
            self::render_module_switch(
                $key,
                (string) $module['label'],
                (string) $module['description']
            );
        }

        echo '</div>';

        echo '<div class="pwe-modules-actions">';
        PWE_Multilang_Admin_UI::button(['type' => 'submit', 'class' => 'pwe-btn'], '<span class="dashicons dashicons-saved"></span> Zapisz ustawienia modułów');
        echo '</div>';
        echo '</form>';

        PWE_Multilang_Admin_UI::cardClose();
    }

    private static function render_module_page(string $module_key): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        $module = PWE_Multilang_Module_Registry::module($module_key);

        if ($module === null || empty($module['page_slug'])) {
            return;
        }

        self::render_page_wrapper((string) $module['page_slug'], static function () use ($module_key, $module): void {
            $label = (string) $module['label'];

            if (!PWE_Multilang_Module_Registry::is_enabled($module_key)) {
                PWE_Multilang_Admin_UI::notice(
                    'pwe-notice-warning',
                    'dashicons-lock',
                    'Moduł ' . esc_html($label) . ' jest wyłączony w General settings.'
                );
                return;
            }

            $render_class = (string) ($module['render_class'] ?? '');
            $callback = [$render_class, 'render_admin_page'];

            if ($render_class !== '' && is_callable($callback)) {
                call_user_func($callback);
                return;
            }

            PWE_Multilang_Admin_UI::notice(
                'pwe-notice-error',
                'dashicons-warning',
                'Klasa <code>' . esc_html($render_class) . '</code> nie jest dostępna.'
            );
        });
    }

    private static function handle_modules_settings_submit(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        if (empty($_POST['pwe_modules_submit'])) {
            return;
        }

        check_admin_referer(self::MODULES_NONCE_ACTION, self::MODULES_NONCE_NAME);

        $incoming = isset($_POST['pwe_modules']) && is_array($_POST['pwe_modules'])
            ? wp_unslash($_POST['pwe_modules'])
            : [];

        PWE_Multilang_Module_Registry::save_settings($incoming);

        wp_safe_redirect(admin_url('admin.php?page=pwe-multilang&pwe_modules_saved=1'));
        exit;
    }

    private static function render_module_switch(string $key, string $label, string $description): void
    {
        $attributes = [
            'type'  => 'checkbox',
            'name'  => 'pwe_modules[' . $key . ']',
            'value' => '1',
        ];

        if (PWE_Multilang_Module_Registry::is_enabled($key)) {
            $attributes['checked'] = 'checked';
        }

        PWE_Multilang_Admin_UI::fieldOpen('pwe-field pwe-form-safe-mode pwe-module-switch-field');
        ?>
            <div class="pwe-module-switch-row">
                <div class="pwe-module-switch-copy">
                    <p class="pwe-module-switch-title"><?php echo esc_html($label); ?></p>
                    <p class="pwe-hint"><?php echo esc_html($description); ?></p>
                </div>
                <?php PWE_Multilang_Admin_UI::switch($attributes, 'pwe-switch'); ?>
            </div>
        <?php
        PWE_Multilang_Admin_UI::fieldClose();
    }

    private static function asset_version(string $path): string
    {
        return file_exists($path) ? (string) filemtime($path) : PWE_MULTILANG_VERSION;
    }

    /** @return string[] */
    private static function site_group_matrix_errors(): array
    {
        $json_path = PWE_MULTILANG_PATH . 'website-translation.json';
        $json = is_file($json_path) ? file_get_contents($json_path) : false;
        $pages = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($pages)) {
            return ['Nie można odczytać mapy stron website-translation.json.'];
        }

        $form_slugs = [];
        $form_root = PWE_MULTILANG_PATH . 'includes/forms/form-templates';

        foreach (glob($form_root . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $form_slugs[] = basename($directory);
        }

        return PWE_Multilang_Site_Group::validate_resources(
            array_keys($pages),
            $form_slugs
        );
    }
}
