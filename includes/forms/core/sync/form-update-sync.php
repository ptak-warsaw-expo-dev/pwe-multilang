<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Update_Sync
{
    private const OPTION_LAST_PLUGIN_VERSION = 'pwe_mlg_last_plugin_version';
    private const OPTION_TEMPLATE_VERSIONS = 'pwe_mlg_form_template_versions';
    private const OPTION_LAST_RESULT = 'pwe_mlg_form_update_sync_last_result';
    private const OPTION_RETRY_AFTER = 'pwe_mlg_form_update_sync_retry_after';
    private const RETRY_DELAY = 300;

    public static function init(): void
    {
        add_action('init', [self::class, 'maybe_sync_after_plugin_update'], 99);
    }

    public static function maybe_sync_after_plugin_update(): void
    {
        if (self::is_auto_sync_blocked_for_tests_tools() || !class_exists('GFAPI')) {
            return;
        }

        $currentVersion = self::get_current_plugin_version();

        if ($currentVersion === '' || (string) get_option(self::OPTION_LAST_PLUGIN_VERSION, '') === $currentVersion) {
            return;
        }

        if ((int) get_option(self::OPTION_RETRY_AFTER, 0) > time()) {
            return;
        }

        self::run($currentVersion, false);
    }

    public static function force_run_now(): void
    {
        if (!class_exists('GFAPI')) {
            return;
        }

        $currentVersion = self::get_current_plugin_version();

        if ($currentVersion !== '') {
            self::run($currentVersion, true);
        }
    }

    /** Stable diagnostics API used by the Tests module. */
    public static function get_state(): array
    {
        $templateVersions = get_option(self::OPTION_TEMPLATE_VERSIONS, []);

        return [
            'current_plugin_version' => self::get_current_plugin_version(),
            'saved_plugin_version' => (string) get_option(self::OPTION_LAST_PLUGIN_VERSION, ''),
            'template_versions' => is_array($templateVersions) ? $templateVersions : [],
            'last_result' => get_option(self::OPTION_LAST_RESULT, []),
            'retry_after' => (int) get_option(self::OPTION_RETRY_AFTER, 0),
            'locked' => PWE_Multilang_Form_Operation_Lock::isLocked(),
        ];
    }

    public static function clear_state(): void
    {
        delete_option(self::OPTION_LAST_PLUGIN_VERSION);
        delete_option(self::OPTION_TEMPLATE_VERSIONS);
        delete_option(self::OPTION_LAST_RESULT);
        delete_option(self::OPTION_RETRY_AFTER);
    }

    public static function seed_versions(string $version = '1.0.0'): array
    {
        $version = trim($version) !== '' ? trim($version) : '1.0.0';
        $versions = [];

        foreach (PWE_Multilang_Form_Core::getTemplates(self::get_forms_year()) as $slug => $template) {
            unset($template);
            $versions[(string) $slug] = $version;
        }

        ksort($versions);
        update_option(self::OPTION_TEMPLATE_VERSIONS, $versions, false);

        return $versions;
    }

    private static function run(string $currentVersion, bool $ignoreRetry): void
    {
        $token = PWE_Multilang_Form_Operation_Lock::acquire('plugin_update_sync');

        if ($token === null) {
            return;
        }

        try {
            if (!$ignoreRetry && (string) get_option(self::OPTION_LAST_PLUGIN_VERSION, '') === $currentVersion) {
                return;
            }

            self::sync_changed_templates(
                $currentVersion,
                (string) get_option(self::OPTION_LAST_PLUGIN_VERSION, '')
            );
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }

    private static function sync_changed_templates(string $currentPluginVersion, string $lastPluginVersion): void
    {
        $formsYear = self::get_forms_year();
        $templates = PWE_Multilang_Form_Core::getTemplates($formsYear);
        $currentVersions = self::collect_template_versions($templates);
        $savedVersions = get_option(self::OPTION_TEMPLATE_VERSIONS, []);
        $savedVersions = is_array($savedVersions) ? $savedVersions : [];
        $external = class_exists('PWE_Multilang_Form_External_Patches')
            ? PWE_Multilang_Form_External_Patches::applyRegistered(false, 'plugin_update_sync')
            : ['items' => [], 'errors' => []];
        $externalFailed = self::has_errors($external);

        if (!self::refresh_operation_lock()) {
            return;
        }

        if (empty($savedVersions)) {
            update_option(self::OPTION_TEMPLATE_VERSIONS, $currentVersions, false);
            self::complete_or_retry(
                !$externalFailed,
                $currentPluginVersion,
                $lastPluginVersion,
                [
                    'status' => $externalFailed ? 'partial' : 'baseline',
                    'message' => 'Zapisano bazowe wersje template formularzy bez automatycznej synchronizacji.',
                    'changed_templates' => [],
                    'external_patches' => $external,
                ]
            );
            return;
        }

        $changedSlugs = self::get_changed_slugs($currentVersions, $savedVersions);

        if (empty($changedSlugs)) {
            update_option(self::OPTION_TEMPLATE_VERSIONS, $currentVersions, false);
            self::complete_or_retry(
                !$externalFailed,
                $currentPluginVersion,
                $lastPluginVersion,
                [
                    'status' => $externalFailed ? 'partial' : 'no_changes',
                    'message' => 'Nie znaleziono template formularzy z podbita template_version.',
                    'changed_templates' => [],
                    'external_patches' => $external,
                ]
            );
            return;
        }

        $result = PWE_Multilang_Form_Core::sync('forms', $changedSlugs, $formsYear, false, []);
        $successful = PWE_Multilang_Form_Sync_Result::successfulTemplateSlugs($result, $changedSlugs);

        if (!self::refresh_operation_lock()) {
            return;
        }

        foreach ($successful as $slug) {
            if (array_key_exists($slug, $currentVersions)) {
                $savedVersions[$slug] = $currentVersions[$slug];
            }
        }

        update_option(self::OPTION_TEMPLATE_VERSIONS, $savedVersions, false);
        $complete = count($successful) === count($changedSlugs) && !$externalFailed;

        if ($complete) {
            update_option(self::OPTION_TEMPLATE_VERSIONS, $currentVersions, false);
        }

        self::complete_or_retry(
            $complete,
            $currentPluginVersion,
            $lastPluginVersion,
            [
                'status' => $complete ? 'synced' : 'partial',
                'message' => $complete
                    ? 'Zsynchronizowano formularze ze zmienionymi template_version.'
                    : 'Nie wszystkie formularze zostaly zsynchronizowane; operacja zostanie ponowiona.',
                'changed_templates' => $changedSlugs,
                'successful_templates' => $successful,
                'result' => $result,
                'external_patches' => $external,
            ]
        );
    }

    private static function complete_or_retry(
        bool $complete,
        string $currentVersion,
        string $lastVersion,
        array $result
    ): void {
        if (!self::refresh_operation_lock()) {
            return;
        }

        if ($complete) {
            update_option(self::OPTION_LAST_PLUGIN_VERSION, $currentVersion, false);
            delete_option(self::OPTION_RETRY_AFTER);
        } else {
            update_option(self::OPTION_RETRY_AFTER, time() + self::RETRY_DELAY, false);
        }

        update_option(self::OPTION_LAST_RESULT, array_merge($result, [
            'plugin_version_before' => $lastVersion,
            'plugin_version_after' => $currentVersion,
            'retry_after' => $complete ? 0 : time() + self::RETRY_DELAY,
            'time' => current_time('mysql'),
        ]), false);
    }

    private static function refresh_operation_lock(): bool
    {
        return !PWE_Multilang_Form_Operation_Lock::ownedByCurrentRequest()
            || PWE_Multilang_Form_Operation_Lock::refreshOwned();
    }

    private static function collect_template_versions(array $templates): array
    {
        $versions = [];

        foreach ($templates as $slug => $template) {
            $payload = $template['payload'] ?? [];
            $version = is_array($payload) ? (string) ($payload['template_version'] ?? '') : '';

            if ($version === '') {
                PWE_Multilang_Form_Log_Service::warn(
                    'Template formularza nie ma ustawionego template_version',
                    ['template' => $slug]
                );
                continue;
            }

            $versions[$slug] = $version;
        }

        ksort($versions);
        return $versions;
    }

    private static function get_changed_slugs(array $currentVersions, array $savedVersions): array
    {
        $changed = [];

        foreach ($currentVersions as $slug => $version) {
            if ((string) ($savedVersions[$slug] ?? '') !== $version) {
                $changed[] = $slug;
            }
        }

        return $changed;
    }

    private static function has_errors(array $result): bool
    {
        if (!empty($result['errors'])) {
            return true;
        }

        foreach ($result['items'] ?? [] as $item) {
            if (
                is_array($item)
                && (!empty($item['errors']) || in_array((string) ($item['status'] ?? ''), ['failed', 'pending'], true))
            ) {
                return true;
            }
        }

        return false;
    }

    private static function get_forms_year(): int
    {
        return PWE_Multilang_Year_Resolver::configured();
    }

    private static function get_current_plugin_version(): string
    {
        if (!defined('PWE_MULTILANG_FILE') || !function_exists('get_file_data')) {
            return '';
        }

        $data = get_file_data(PWE_MULTILANG_FILE, ['Version' => 'Version'], 'plugin');
        return (string) ($data['Version'] ?? '');
    }

    private static function is_auto_sync_blocked_for_tests_tools(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $requestAction = sanitize_key((string) ($_REQUEST['action'] ?? ''));

        if ($requestAction === 'pwe_multilang_reset_update_sync_versions') {
            return true;
        }

        return sanitize_key((string) ($_GET['page'] ?? '')) === 'pwe-multilang-tests';
    }
}
