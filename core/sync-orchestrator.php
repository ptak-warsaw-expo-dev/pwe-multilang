<?php

declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

final class PWE_Multilang_Sync_Orchestrator
{
    public const COMPLETED_TRANSIENT = 'pwe_multilang_sync_completed';

    private static bool $running = false;

    public static function init(): void
    {
        add_action('wpml_update_active_languages', [self::class, 'on_languages_changed']);
        add_action('wp', [self::class, 'maybe_sync'], 100);
        add_action('admin_init', [self::class, 'maybe_sync'], 100);
    }

    public static function on_languages_changed($before = null): void
    {
        self::sync();
    }

    public static function maybe_sync(): void
    {
        self::sync();
    }

    /** Manual sync reconciles every stage regardless of completion or CAP changes. */
    public static function reset_and_sync(): array
    {
        return self::sync([], true);
    }

    private static function remember(array $report): void
    {
        set_transient(self::COMPLETED_TRANSIENT, [
            'completed_at' => time(),
            'report' => $report,
        ], 0);
    }

    public static function sync(array $overwriteModified = [], bool $force = false): array
    {
        $report = ['status' => 'pending', 'languages' => ['desired' => [], 'before' => [], 'activated' => [],
            'deactivated' => [], 'already_active' => [], 'invalid' => [], 'errors' => [], 'after' => []],
            'pages' => ['status' => 'skipped'], 'forms' => ['status' => 'skipped'], 'errors' => []];
        if (!$force && get_transient(self::COMPLETED_TRANSIENT) !== false) {
            $report['status'] = 'skipped';
            $report['reason'] = 'completed_transient';
            return $report;
        }
        if (self::$running || PWE_Multilang_Form_Operation_Lock::ownedByCurrentRequest()) {
            $report['status'] = 'locked';
            return $report;
        }
        self::$running = true;
        $token = null;
        try {
            $token = PWE_Multilang_Form_Operation_Lock::acquire('pwe_multilang_sync');
            if ($token === null) {
                $report['status'] = 'locked';
                return $report;
            }
            // Another request may have completed before this request acquired the lock.
            if (!$force && get_transient(self::COMPLETED_TRANSIENT) !== false) {
                $report['status'] = 'skipped';
                $report['reason'] = 'completed_transient';
                return $report;
            }
            // Clear the old result only after acquiring the operation lock.
            if ($force) {
                delete_transient(self::COMPLETED_TRANSIENT);
            }
            $source = PWE_Multilang_Additional_Languages::from_shortcode();
            if ($source === []) {
                $report['status'] = 'failed';
                $report['reason'] = 'shortcode_empty_or_invalid';
                $report['errors'][] = 'Shortcode [pwe_available_languages] jest niedostępny albo zwrócił pustą lub niepoprawną listę języków. Nie zmieniono języków, stron ani formularzy.';
                return $report;
            }
            if (!$force && !PWE_Multilang_Additional_Languages::shortcode_changed($source)) {
                $report['status'] = 'skipped';
                $report['reason'] = 'languages_unchanged';
                return $report;
            }
            PWE_Multilang_Additional_Languages::save($source);
            $desired = PWE_Multilang_WPML_Language_Manager::desired_languages($source);
            $report['languages']['desired'] = $desired;
            $before = PWE_Multilang_WPML_Language_Manager::active_languages();
            $codes = array_keys($before);
            $report['languages']['before'] = $codes;
            $report['languages']['after'] = $codes;
            $report['languages']['already_active'] = array_values(array_intersect($desired, $codes));
            if (!PWE_Multilang_Module_Registry::is_enabled('pages') || !PWE_Multilang_Module_Registry::is_enabled('forms')) {
                throw new RuntimeException('Pełna synchronizacja wymaga włączonych modułów Pages i Forms.');
            }
            if (!class_exists('PWE_Multilang_Page_Creator')) {
                require_once PWE_MULTILANG_PATH . 'modules/pages/pages-module.php';
            }
            if (!class_exists('PWE_Multilang_Forms')) {
                require_once PWE_MULTILANG_PATH . 'modules/forms/forms-module.php';
            }
            PWE_Multilang_Forms::load_dependencies();
            if (!class_exists('GFAPI')) { throw new RuntimeException('Gravity Forms jest niedostępne.'); }

            $report['languages'] = PWE_Multilang_WPML_Language_Manager::sync_active_languages($desired);
            if ($report['languages']['errors']) {
                $report['status'] = 'failed';
                return $report;
            }
            if (!PWE_Multilang_Form_Operation_Lock::refreshOwned()) { throw new RuntimeException('Utracono blokadę synchronizacji.'); }
            try {
                $report['pages'] = PWE_Multilang_Page_Creator::create_missing_wpml_pages_from_json();
            } catch (Throwable $error) {
                $report['pages'] = ['status' => 'critical_error', 'errors' => [$error->getMessage()]];
            }
            if (
                ($report['pages']['status'] ?? '') !== 'success'
                || !empty($report['pages']['errors'])
                || !empty($report['pages']['skipped_no_en_source'])
            ) {
                $report['status'] = 'failed';
                return $report;
            }
            if (!PWE_Multilang_Form_Operation_Lock::refreshOwned()) { throw new RuntimeException('Utracono blokadę synchronizacji.'); }
            try {
                $report['forms'] = PWE_Multilang_Form_Core::syncAll($overwriteModified);
            } catch (Throwable $error) {
                $report['forms'] = ['status' => 'failed', 'errors' => [$error->getMessage()]];
            }
            $report['status'] = ($report['forms']['status'] ?? '') === 'success'
                ? (empty($report['languages']['invalid']) ? 'success' : 'partial') : 'partial';
            if ($report['status'] === 'success') {
                update_option(PWE_Multilang_Additional_Languages::SHORTCODE_OPTION_KEY, $source, false);
                self::remember($report);
            }
        } catch (Throwable $error) {
            $report['status'] = 'failed';
            $report['errors'][] = $error->getMessage();
        } finally {
            try {
                PWE_Multilang_Sync_Mail_Report::send($report);
            } catch (Throwable $error) {
                error_log('PWE Multilang: błąd raportu e-mail: ' . $error->getMessage());
            } finally {
                if ($token !== null) { PWE_Multilang_Form_Operation_Lock::release($token); }
                self::$running = false;
            }
        }
        return $report;
    }
}
