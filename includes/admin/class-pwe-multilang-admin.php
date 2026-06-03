<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_head', [self::class, 'inject_styles']);
    }

    public static function inject_styles(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'pwe-multilang') === false) {
            return;
        }
        ?>
        <style>
        /* ── PWE Multilang — SaaS admin styles ── */
        #adminmenu .toplevel_page_pwe-multilang.current .wp-menu-image:before,
        #adminmenu .toplevel_page_pwe-multilang:hover .wp-menu-image:before { color: #fff !important; }
        #adminmenu .toplevel_page_pwe-multilang.current,
        #adminmenu .toplevel_page_pwe-multilang.wp-has-current-submenu { background: #2d8653 !important; }

        .pwe-wrap { max-width: 860px; margin: 24px 20px 40px 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

        /* Top bar */
        .pwe-topbar { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .pwe-topbar-icon { width: 36px; height: 36px; background: #edf7f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #2d8653; }
        .pwe-topbar-icon .dashicons { font-size: 20px; width: 20px; height: 20px; line-height: 1; margin: auto; }
        .pwe-topbar h1 { font-size: 18px !important; font-weight: 600; color: #111827 !important; margin: 0 !important; padding: 0 !important; }
        .pwe-topbar p { font-size: 13px; color: #6b7280; margin: 2px 0 0; }

        /* Segmented control */
        .pwe-seg { display: inline-flex; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 9px; padding: 3px; margin-bottom: 24px; }
        .pwe-seg a { display: inline-flex; align-items: center; gap: 6px; padding: 7px 18px; font-size: 13px; font-weight: 500; border-radius: 7px; text-decoration: none !important; color: #6b7280; transition: background .14s, color .14s; }
        .pwe-seg a .dashicons { font-size: 15px; width: 15px; height: 15px; line-height: 1; }
        .pwe-seg a:hover { color: #2d8653; }
        .pwe-seg a.active { background: #fff; color: #2d8653; border: 1px solid #d1fae5; box-shadow: 0 1px 3px rgba(0,0,0,.06); }

        /* Cards */
        .pwe-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px 28px; margin-bottom: 16px; }
        .pwe-card-title { font-size: 14px; font-weight: 600; color: #111827; margin: 0 0 6px; display: flex; align-items: center; gap: 8px; }
        .pwe-card-title .dashicons { color: #2d8653; font-size: 17px; width: 17px; height: 17px; }
        .pwe-card-desc { font-size: 13px; color: #6b7280; margin: 0 0 20px; line-height: 1.6; }
        .pwe-divider { border: none; border-top: 1px solid #f3f4f6; margin: 18px 0; }

        /* Field */
        .pwe-field { margin-bottom: 16px; }
        .pwe-field label { display: block; font-size: 11.5px; font-weight: 600; color: #9ca3af; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 6px; }
        .pwe-field input[type="number"] { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 13.5px; color: #111827; background: #fff; width: 120px; transition: border-color .15s, box-shadow .15s; }
        .pwe-field input[type="number"]:focus { outline: none; border-color: #2d8653; box-shadow: 0 0 0 3px rgba(45,134,83,.12); }
        .pwe-field .pwe-hint { font-size: 12px; color: #9ca3af; margin-top: 5px; }

        /* Button */
        .pwe-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; background: #2d8653; border: none; border-radius: 7px; color: #fff !important; font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; transition: background .14s; }
        .pwe-btn:hover { background: #256e45; }
        .pwe-btn .dashicons { font-size: 15px; width: 15px; height: 15px; line-height: 1; }

        /* Notices */
        .pwe-notice-success { display: flex; align-items: flex-start; gap: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #166534; margin-bottom: 20px; }
        .pwe-notice-error   { display: flex; align-items: flex-start; gap: 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #991b1b; margin-bottom: 20px; }
        .pwe-notice-success .dashicons,
        .pwe-notice-error   .dashicons { font-size: 17px; width: 17px; height: 17px; flex-shrink: 0; margin-top: 1px; }
        </style>
        <?php
    }

    public static function register_menu(): void
    {
        add_menu_page(
            'PWE Multilang',
            'PWE Multilang',
            'manage_options',
            'pwe-multilang',
            [self::class, 'render_page'],
            'dashicons-translation',
            4
        );

        add_submenu_page(
            'pwe-multilang',
            'General',
            'General',
            'manage_options',
            'pwe-multilang',
            [self::class, 'render_page']
        );

        add_submenu_page(
            'pwe-multilang',
            'Forms',
            'Forms',
            'manage_options',
            'pwe-multilang-forms',
            [self::class, 'render_forms_page']
        );

        add_submenu_page(
            'pwe-multilang',
            'Pages',
            'Pages',
            'manage_options',
            'pwe-multilang-pages',
            [self::class, 'render_pages_page']
        );
    }

    private static function render_header(string $active): void
    {
        $tabs = [
            'pwe-multilang'       => ['label' => 'General', 'icon' => 'dashicons-admin-settings'],
            'pwe-multilang-forms' => ['label' => 'Forms',   'icon' => 'dashicons-feedback'],
            'pwe-multilang-pages' => ['label' => 'Pages',   'icon' => 'dashicons-admin-page'],
        ];
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
                <?php foreach ($tabs as $page => $tab) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page)); ?>"
                       class="<?php echo $active === $page ? 'active' : ''; ?>">
                        <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                        <?php echo esc_html($tab['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php
    }

    private static function render_footer(): void
    {
        echo '</div><!-- /.pwe-wrap -->';
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        self::render_header('pwe-multilang');
        ?>
        <div class="pwe-card">
            <p class="pwe-card-title">
                <span class="dashicons dashicons-admin-settings"></span>
                General settings
            </p>
            <p class="pwe-card-desc">Panel administracyjny wtyczki PWE Multilang. Tutaj znajdziesz ogólne ustawienia i konfigurację wtyczki.</p>
            <hr class="pwe-divider">
            <p style="font-size:13px; color:#6b7280; margin:0;">
                Wersja: <strong style="color:#374151;">1.0.0</strong>
                &nbsp;·&nbsp;
                Status: <span style="color:#2d8653; font-weight:600;">Active</span>
            </p>
        </div>
        <?php
        self::render_footer();
    }

    public static function render_forms_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        self::render_header('pwe-multilang-forms');
        PWE_Multilang_Forms::render_admin_page();
        self::render_footer();
    }

    public static function render_pages_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        self::render_header('pwe-multilang-pages');
        PWE_Multilang_Pages::render_admin_page();
        self::render_footer();
    }
}