<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Replace_Content_Ajax
{
    public const NONCE_ACTION = 'pwe_multilang_replace_content';
    public const START_ACTION = 'pwe_replace_content_start';
    public const STEP_ACTION = 'pwe_replace_content_step';

    private const JOB_TTL = 15 * MINUTE_IN_SECONDS;
    private const FINAL_JOB_TTL = 30 * MINUTE_IN_SECONDS;

    public static function init(): void
    {
        add_action('wp_ajax_' . self::START_ACTION, [self::class, 'start']);
        add_action('wp_ajax_' . self::STEP_ACTION, [self::class, 'step']);
    }

    public static function start(): void
    {
        self::authorize();

        $map = PWE_Multilang_Replace_Content_Config::get_map();
        $plan = PWE_Multilang_Replace_Content_Service::build_plan($map);
        $prepared = PWE_Multilang_Replace_Content_Service::prepare_job($plan);
        $tasks = array_values((array) ($prepared['tasks'] ?? []));
        $result = (array) ($prepared['result'] ?? PWE_Multilang_Replace_Content_Service::empty_result());
        $total = count($tasks);

        if ($total === 0) {
            wp_send_json_success([
                'complete' => true,
                'processed' => 0,
                'total' => 0,
                'percent' => 100,
                'result' => $result,
            ]);
        }

        $token = wp_generate_password(20, false, false);
        $job = [
            'version' => 2,
            'tasks' => $tasks,
            'total' => $total,
            'cursor' => 0,
            'processed' => 0,
            'status' => 'running',
            'inflight' => null,
            'last_page' => null,
            'result' => $result,
        ];

        if (!set_transient(self::job_key($token), $job, self::JOB_TTL)) {
            wp_send_json_error(['message' => 'Nie udało się zapisać kolejki operacji.'], 500);
        }

        wp_send_json_success([
            'complete' => false,
            'job' => $token,
            'processed' => 0,
            'total' => $total,
            'percent' => 0,
            'result' => $result,
        ]);
    }

    public static function step(): void
    {
        self::authorize();

        $token = isset($_POST['job'])
            ? sanitize_key(wp_unslash($_POST['job']))
            : '';
        $client_processed = isset($_POST['processed'])
            ? absint(wp_unslash($_POST['processed']))
            : null;

        if ($token === '') {
            wp_send_json_error(['message' => 'Brak identyfikatora operacji.'], 400);
        }

        $job_key = self::job_key($token);
        $job = get_transient($job_key);

        if (!self::is_valid_job($job)) {
            wp_send_json_error(['message' => 'Operacja wygasła albo została już zakończona.'], 410);
        }

        if (($job['status'] ?? '') === 'complete') {
            wp_send_json_success(self::response_from_job($job, $token));
        }

        $lock_owner = self::acquire_lock($token);

        if ($lock_owner === null) {
            wp_send_json_error(
                self::error_response_data(
                    'Inny etap tej operacji jest jeszcze przetwarzany. Spróbuj ponownie.',
                    $token,
                    $job,
                    true
                ),
                409
            );
        }

        $response = null;
        $error_message = '';
        $error_status = 500;

        try {
            // Re-read after acquiring the lock. Another request may have moved
            // the cursor between the initial read and lock acquisition.
            $job = get_transient($job_key);

            if (!self::is_valid_job($job)) {
                $error_message = 'Operacja wygasła albo została już zakończona.';
                $error_status = 410;
            } elseif (($job['status'] ?? '') === 'complete') {
                $response = self::response_from_job($job, $token);
            } else {
                $response = self::process_job_step(
                    $job_key,
                    $token,
                    $job,
                    $client_processed,
                    $error_message
                );
            }
        } catch (Throwable $e) {
            $error_message = 'Nie udało się wykonać etapu operacji: ' . $e->getMessage();
        }

        self::release_lock($token, $lock_owner);

        if ($error_message !== '') {
            $stored_job = get_transient($job_key);

            wp_send_json_error(
                self::error_response_data(
                    $error_message,
                    $token,
                    self::is_valid_job($stored_job) ? $stored_job : $job,
                    $error_status >= 500
                ),
                $error_status
            );
        }

        wp_send_json_success(is_array($response) ? $response : []);
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>|null
     */
    private static function process_job_step(
        string $job_key,
        string $token,
        array $job,
        ?int $client_processed,
        string &$error_message
    ): ?array {
        $tasks = array_values((array) $job['tasks']);
        $cursor = max(0, (int) ($job['cursor'] ?? 0));
        $total = count($tasks);

        // A previous response may have been lost after its cursor was safely
        // stored. Replay that result before mutating the next page.
        if ($client_processed !== null && (int) ($job['processed'] ?? 0) > $client_processed) {
            return self::response_from_job($job, $token);
        }

        if ($cursor >= $total) {
            $job['status'] = 'complete';
            $job['processed'] = $total;
            $job['cursor'] = $total;
            $job['inflight'] = null;

            if (!set_transient($job_key, $job, self::FINAL_JOB_TTL)) {
                $error_message = 'Nie udało się zapisać końcowego wyniku operacji.';
                return null;
            }

            return self::response_from_job($job, $token);
        }

        $task = (array) $tasks[$cursor];
        $inflight = is_array($job['inflight'] ?? null) ? $job['inflight'] : null;
        $recovering = $inflight && (int) ($inflight['cursor'] ?? -1) === $cursor;

        if (!$recovering) {
            $job['status'] = 'processing';
            $job['inflight'] = [
                'cursor' => $cursor,
                'started_at' => time(),
            ];

            // Persist intent before mutating the page. If PHP or the transport
            // fails afterwards, the same cursor can be safely recovered.
            if (!set_transient($job_key, $job, self::JOB_TTL)) {
                $error_message = 'Nie udało się zapisać stanu operacji przed aktualizacją strony.';
                return null;
            }
        }

        try {
            $page_result = $recovering
                ? PWE_Multilang_Replace_Content_Service::recover_page_result($task)
                : PWE_Multilang_Replace_Content_Service::process_page(
                    (int) ($task['post_id'] ?? 0),
                    (string) ($task['shortcode'] ?? '')
                );
        } catch (Throwable $e) {
            $page_result = [
                'status' => 'failed',
                'post_id' => (int) ($task['post_id'] ?? 0),
                'message' => $e->getMessage(),
            ];
        }

        if (!isset($job['result']) || !is_array($job['result'])) {
            $job['result'] = PWE_Multilang_Replace_Content_Service::empty_result();
        }

        PWE_Multilang_Replace_Content_Service::add_page_result($job['result'], $page_result);

        $job['cursor'] = $cursor + 1;
        $job['processed'] = $cursor + 1;
        $job['inflight'] = null;
        $job['last_page'] = [
            'post_id' => (int) ($task['post_id'] ?? 0),
            'title' => (string) ($task['title'] ?? ''),
            'language' => (string) ($task['language'] ?? ''),
            'status' => (string) ($page_result['status'] ?? 'failed'),
            'message' => (string) ($page_result['message'] ?? ''),
        ];
        $job['status'] = $job['cursor'] >= $total ? 'complete' : 'running';
        $ttl = $job['status'] === 'complete' ? self::FINAL_JOB_TTL : self::JOB_TTL;

        if (!set_transient($job_key, $job, $ttl)) {
            // The previously persisted inflight marker remains recoverable.
            $error_message = 'Strona została przetworzona, ale nie udało się zapisać postępu operacji. Ponów etap.';
            return null;
        }

        return self::response_from_job($job, $token);
    }

    /**
     * @param mixed $job
     */
    private static function is_valid_job($job): bool
    {
        return is_array($job)
            && (int) ($job['version'] ?? 0) === 2
            && isset($job['tasks'])
            && is_array($job['tasks'])
            && isset($job['result'])
            && is_array($job['result']);
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private static function response_from_job(array $job, string $token): array
    {
        $total = max(0, (int) ($job['total'] ?? count((array) ($job['tasks'] ?? []))));
        $processed = min($total, max(0, (int) ($job['processed'] ?? 0)));
        $complete = ($job['status'] ?? '') === 'complete' || ($total > 0 && $processed >= $total);
        $percent = $complete
            ? 100
            : ($total > 0 ? min(99, (int) round(($processed / $total) * 100)) : 100);

        $response = [
            'complete' => $complete,
            'job' => $token,
            'processed' => $processed,
            'total' => $total,
            'percent' => $percent,
            'result' => (array) ($job['result'] ?? PWE_Multilang_Replace_Content_Service::empty_result()),
        ];

        if (is_array($job['last_page'] ?? null)) {
            $response['page'] = $job['last_page'];
        }

        return $response;
    }

    /**
     * Returns enough persisted state for the browser to retry the same token
     * instead of starting a second job after a recoverable transport/storage
     * failure.
     *
     * @param mixed $job
     * @return array<string, mixed>
     */
    private static function error_response_data(
        string $message,
        string $token,
        $job,
        bool $retryable
    ): array {
        $data = self::is_valid_job($job)
            ? self::response_from_job($job, $token)
            : ['job' => $token];

        $data['message'] = $message;
        $data['retryable'] = $retryable;

        return $data;
    }

    private static function authorize(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            wp_send_json_error(['message' => 'Brak uprawnień do wykonania operacji.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }

    private static function job_key(string $token): string
    {
        return 'pwe_rc_' . get_current_user_id() . '_' . sanitize_key($token);
    }

    private static function lock_key(string $token): string
    {
        return 'pwe_rc_lock_' . get_current_user_id() . '_' . sanitize_key($token);
    }

    private static function acquire_lock(string $token): ?string
    {
        global $wpdb;

        $lock_name = self::lock_key($token);
        $acquired = $wpdb->get_var(
            $wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock_name)
        );

        return (string) $acquired === '1' ? $lock_name : null;
    }

    private static function release_lock(string $token, string $lock_name): void
    {
        global $wpdb;

        if (!hash_equals(self::lock_key($token), $lock_name)) {
            return;
        }

        $wpdb->get_var(
            $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name)
        );
    }
}
