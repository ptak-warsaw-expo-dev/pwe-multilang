<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Actions
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'handle_generate']);
    }

    public static function handle_generate(): void
    {
        if (empty($_POST['pwe_mlg_do_generate']) || !PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        if (
            empty($_POST['pwe_mlg_forms_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['pwe_mlg_forms_nonce'])),
                'pwe_mlg_generate_forms_action'
            )
        ) {
            return;
        }

        if (isset($_POST['pwe_mlg_sync_scope']) && $_POST['pwe_mlg_sync_scope'] === 'feed') {
            self::handle_feed();
            return;
        }

        $year = !empty($_POST['pwe_mlg_forms_year'])
            ? PWE_Multilang_Year_Resolver::normalise(absint($_POST['pwe_mlg_forms_year']))
            : PWE_Multilang_Year_Resolver::configured();
        PWE_Multilang_Year_Resolver::save($year);

        $scope = self::get_scope();
        $selected = self::get_selected_templates();
        $overwriteModified = self::get_overwrite_modified_templates();
        $addOnly = !empty($_POST['pwe_mlg_add_only']);
        $token = PWE_Multilang_Form_Operation_Lock::acquire('manual_sync');

        if ($token === null) {
            $result = PWE_Multilang_Form_Sync_Result::locked($scope, $addOnly);
        } else {
            try {
                $result = !class_exists('GFAPI')
                    ? PWE_Multilang_Form_Sync_Result::finalize(array_merge(
                        PWE_Multilang_Form_Sync_Result::create($scope, $addOnly),
                        ['errors' => ['Gravity Forms / GFAPI nie jest dostępne.']]
                    ))
                    : PWE_Multilang_Form_Core::sync(
                        $scope,
                        $selected,
                        $year,
                        $addOnly,
                        $overwriteModified
                    );

                if (class_exists('PWE_Multilang_Form_External_Patches')) {
                    $patchResult = PWE_Multilang_Form_External_Patches::applyRegistered($addOnly, 'manual_sync');
                    $result['items'] = array_merge($result['items'] ?? [], $patchResult['items'] ?? []);
                    $result['errors'] = array_merge($result['errors'] ?? [], $patchResult['errors'] ?? []);
                }
            } finally {
                PWE_Multilang_Form_Operation_Lock::release($token);
            }
        }

        PWE_Multilang_Form_Result_Store::set($result);
        wp_redirect(add_query_arg(
            'pwe_mlg_forms_result',
            '1',
            admin_url('admin.php?page=pwe-multilang-forms')
        ));
        exit;
    }

    private static function handle_feed(): void
    {
        $token = PWE_Multilang_Form_Operation_Lock::acquire('manual_qr_feed_update');
        $result = PWE_Multilang_Form_Sync_Result::create('feed', false);

        if ($token === null) {
            $result = PWE_Multilang_Form_Sync_Result::locked('feed', false);
        } else {
            try {
                $result = PWE_Multilang_Form_QR::updateAllFeedPrefixes();
            } catch (\Throwable $error) {
                $result['errors'][] = $error->getMessage();
                $result = PWE_Multilang_Form_Sync_Result::finalize($result);
            } finally {
                PWE_Multilang_Form_Operation_Lock::release($token);
            }
        }

        PWE_Multilang_Form_Result_Store::set($result);
        wp_safe_redirect(add_query_arg(
            'pwe_mlg_forms_result',
            '1',
            admin_url('admin.php?page=pwe-multilang-forms')
        ));
        exit;
    }

    private static function get_scope(): string
    {
        $scope = !empty($_POST['pwe_mlg_sync_scope'])
            ? sanitize_key(wp_unslash($_POST['pwe_mlg_sync_scope']))
            : 'forms';

        return in_array($scope, ['forms', 'fields', 'notifications', 'confirmations'], true)
            ? $scope
            : 'forms';
    }

    private static function get_selected_templates(): array
    {
        if (empty($_POST['pwe_mlg_templates']) || !is_array($_POST['pwe_mlg_templates'])) {
            return [];
        }

        $selected = [];

        foreach (wp_unslash($_POST['pwe_mlg_templates']) as $template) {
            $selected[] = sanitize_key($template);
        }

        return array_values(array_unique($selected));
    }

    private static function get_overwrite_modified_templates(): array
    {
        if (empty($_POST['pwe_mlg_overwrite_modified']) || !is_array($_POST['pwe_mlg_overwrite_modified'])) {
            return [];
        }

        $selected = [];

        foreach (wp_unslash($_POST['pwe_mlg_overwrite_modified']) as $template => $value) {
            if (!empty($value)) {
                $selected[] = sanitize_key($template);
            }
        }

        return array_values(array_unique($selected));
    }
}
