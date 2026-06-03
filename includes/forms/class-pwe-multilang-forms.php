<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Forms
{
    public static function init(): void
    {
        self::load_dependencies();
        add_action('admin_init', [self::class, 'handle_generate_action']);
    }

    private static function load_dependencies(): void
    {
        $files = [
            'includes/forms/core/Log.php',
            'includes/forms/core/GlobalSettings.php',
            'includes/forms/core/FormFinder.php',
            'includes/forms/core/Components/Fields.php',
            'includes/forms/core/Components/MetaSettings.php',
            'includes/forms/core/Components/Confirmations.php',
            'includes/forms/core/Components/Notifications.php',
            'includes/forms/core/Components/Form.php',
            'includes/forms/core/FormWriterHelper.php',
            'includes/forms/core/FormWriterCreate.php',
            'includes/forms/core/FormWriterUpdate.php',
            'includes/forms/core/FormWriter.php',
            'includes/forms/core/FormCore.php',
        ];

        foreach ($files as $file) {
            require_once PWE_MULTILANG_PATH . $file;
        }
    }

    public static function handle_generate_action(): void
    {
        if (empty($_POST['pwe_mlg_do_generate'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (empty($_POST['pwe_mlg_forms_nonce']) || !wp_verify_nonce($_POST['pwe_mlg_forms_nonce'], 'pwe_mlg_generate_forms_action')) {
            return;
        }

        $year = !empty($_POST['pwe_mlg_forms_year']) ? absint($_POST['pwe_mlg_forms_year']) : (int) date('Y');

        update_option('pwe_general_options', [
            'pwe_create_forms_year' => $year,
        ]);

        if (class_exists('GFAPI')) {
            PWE_Multilang_Form_Core::run();

            wp_redirect(add_query_arg('pwe_mlg_forms_success', (string) $year, admin_url('admin.php?page=pwe-multilang-forms')));
            exit;
        }

        wp_redirect(add_query_arg('pwe_mlg_forms_error', 'gravity_forms_missing', admin_url('admin.php?page=pwe-multilang-forms')));
        exit;
    }


    public static function render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="pwe-card">
            <p class="pwe-card-title">
                <span class="dashicons dashicons-feedback"></span>
                Forms generator
            </p>
            <p class="pwe-card-desc">
                Generator formularzy Gravity Forms dla formularzy Multilang.
                Wybierz rok i kliknij przycisk, aby wygenerować komplet formularzy.
            </p>

            <?php if (isset($_GET['pwe_mlg_forms_success'])) : ?>
                <div class="pwe-notice-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span>Formularze zostały wygenerowane dla roku
                        <strong><?php echo esc_html((string) absint($_GET['pwe_mlg_forms_success'])); ?></strong>.
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['pwe_mlg_forms_error']) && $_GET['pwe_mlg_forms_error'] === 'gravity_forms_missing') : ?>
                <div class="pwe-notice-error">
                    <span class="dashicons dashicons-warning"></span>
                    <span>Nie można wygenerować formularzy: <strong>Gravity Forms</strong> nie jest aktywne.</span>
                </div>
            <?php endif; ?>

            <hr class="pwe-divider">

            <form method="post">
                <?php wp_nonce_field('pwe_mlg_generate_forms_action', 'pwe_mlg_forms_nonce'); ?>

                <div class="pwe-field">
                    <label for="pwe_mlg_forms_year">Rok formularzy</label>
                    <input
                        type="number"
                        id="pwe_mlg_forms_year"
                        name="pwe_mlg_forms_year"
                        value="<?php echo esc_attr(date('Y')); ?>"
                        min="2000"
                        max="2100"
                    >
                    <p class="pwe-hint">np. <?php echo esc_html(date('Y')); ?></p>
                </div>

                <button type="submit" name="pwe_mlg_do_generate" value="1" class="pwe-btn">
                    <span class="dashicons dashicons-update"></span>
                    Generate forms
                </button>
            </form>
        </div>
        <?php
    }
}
