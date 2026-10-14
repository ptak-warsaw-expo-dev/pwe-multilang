<?php

declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

/** Email reporting only; never changes the synchronization result. */
final class PWE_Multilang_Sync_Mail_Report
{
    /** Shared recipients for sites using this plugin. Empty means disabled. */
    private const RECIPIENTS = ['piotr.krupniewski@warsawexpo.eu', 'anton.melnychuk@warsawexpo.eu', 'oliwia.ptasinska@warsawexpo.eu'];
    private const REPEAT_DELAY = 86400;
    private const DELIVERY_RETRY_DELAY = 900;

    public static function send(array $report): void
    {
        $status = (string) ($report['status'] ?? '');
        if (!in_array($status, ['success', 'partial', 'failed'], true)) {
            return;
        }

        $recipients = array_values(array_unique(array_filter(
            (array) apply_filters('pwe_multilang_sync_report_recipients', self::RECIPIENTS),
            static fn($email): bool => is_string($email) && (bool) is_email($email)
        )));
        if ($recipients === []) {
            return;
        }

        $problems = self::problems($report);
        $desired = (array) ($report['languages']['desired'] ?? []);
        sort($desired);
        $signatureProblems = $problems;
        sort($signatureProblems);
        // Ignore counts and before/after language deltas: retries may already
        // have completed those writes while still failing on the same item.
        $key = 'pwe_mlg_sync_mail_' . md5(serialize([$recipients, $status, $desired, $signatureProblems]));
        if ($status !== 'success' && get_transient($key) !== false) {
            return;
        }

        // Also throttle failed delivery attempts. The orchestrator still holds
        // its operation lock here, preventing parallel duplicate reports.
        set_transient($key, 1, self::DELIVERY_RETRY_DELAY);
        $site = (string) home_url('/');
        $subject = '[PWE Multilang] ' . self::status($status) . ' — ' . $site;
        $subject = str_replace(["\r", "\n"], '', $subject);
        $sent = wp_mail($recipients, $subject, self::body($report, $problems), [
            'Content-Type: text/plain; charset=UTF-8',
        ]);
        if ($sent) {
            set_transient($key, 1, self::REPEAT_DELAY);
        } else {
            error_log('PWE Multilang: wp_mail nie przyjął raportu synchronizacji dla ' . $site);
        }
    }

    private static function body(array $report, array $problems): string
    {
        $languages = $report['languages'] ?? [];
        $pages = $report['pages'] ?? [];
        $forms = $report['forms'] ?? [];
        $lines = [
            'Raport synchronizacji PWE Multilang',
            'Strona: ' . home_url('/'),
            'Data: ' . current_time('mysql') . ' (' . wp_timezone_string() . ')',
            'Wynik: ' . self::status((string) $report['status']),
            '',
            'Języki docelowe: ' . self::codes($languages['desired'] ?? []),
            'Języki przed operacją: ' . self::codes($languages['before'] ?? []),
            'Dodane / aktywowane: ' . self::codes($languages['activated'] ?? []),
            'Wyłączone: ' . self::codes($languages['deactivated'] ?? []),
            'Języki po operacji: ' . self::codes($languages['after'] ?? []),
            '',
            'Strony: ' . self::status((string) ($pages['status'] ?? 'skipped')),
            'Utworzone / dokończone: ' . count($pages['created'] ?? []),
            'Pominięte istniejące: ' . count($pages['skipped_existing'] ?? []),
            '',
            'Formularze: ' . self::status((string) ($forms['status'] ?? 'skipped')),
        ];
        foreach (($forms['items'] ?? []) as $item) {
            $lines[] = '- #' . (int) ($item['form_id'] ?? 0) . ' ' . ($item['title'] ?? '')
                . ': ' . self::status((string) ($item['status'] ?? 'pending'));
        }
        $lines[] = '';
        $lines[] = 'Błędy / ostrzeżenia / powody pominięcia:';
        foreach ($problems as $problem) {
            $lines[] = '- ' . $problem;
        }
        if ($problems === []) {
            $lines[] = 'Brak zgłoszonych błędów i ostrzeżeń.';
        }
        if (($report['status'] ?? '') !== 'success') {
            $lines[] = '';
            $lines[] = 'Synchronizacja nie zakończyła się w pełni. Wcześniejsze zmiany mogły zostać zapisane.';
            $lines[] = 'Powtórzenie tego samego wyniku jest raportowane najwyżej raz na 24 godziny.';
        }
        return implode("\n", $lines);
    }

    private static function problems(array $report): array
    {
        $problems = [];
        foreach (['errors' => 'Synchronizacja'] as $key => $label) {
            foreach (($report[$key] ?? []) as $message) {
                $problems[] = $label . ': ' . $message;
            }
        }
        foreach (['languages' => 'Języki', 'pages' => 'Strony', 'forms' => 'Formularze'] as $key => $label) {
            foreach (($report[$key]['errors'] ?? []) as $message) {
                $problems[] = $label . ': ' . $message;
            }
            if (!empty($report[$key]['message'])) {
                $problems[] = $label . ': ' . $report[$key]['message'];
            }
        }
        if (!empty($report['languages']['invalid'])) {
            $problems[] = 'Języki niedostępne w katalogu WPML: ' . self::codes($report['languages']['invalid']);
        }
        foreach (['skipped_no_en_source', 'skipped_invalid_data'] as $key) {
            foreach (($report['pages'][$key] ?? []) as $message) {
                $problems[] = 'Pominięta strona: ' . $message;
            }
        }
        foreach (($report['forms']['items'] ?? []) as $item) {
            $prefix = 'Formularz #' . (int) ($item['form_id'] ?? 0) . ' ' . ($item['title'] ?? '') . ': ';
            foreach (['errors', 'warnings'] as $key) {
                foreach (($item[$key] ?? []) as $message) {
                    $problems[] = $prefix . (is_scalar($message) ? (string) $message : wp_json_encode($message));
                }
            }
            if (($item['status'] ?? '') === 'skipped') {
                foreach (($item['details'] ?? $item['skipped'] ?? []) as $message) {
                    $problems[] = $prefix . $message;
                }
            }
        }
        return array_values(array_unique($problems));
    }

    private static function codes(array $codes): string
    {
        return $codes === [] ? 'brak' : implode(', ', $codes);
    }

    private static function status(string $status): string
    {
        return [
            'success' => 'Sukces',
            'partial' => 'Częściowe wykonanie',
            'failed' => 'Błąd',
            'critical_error' => 'Błąd krytyczny',
            'error' => 'Błąd',
            'skipped' => 'Pominięto',
            'locked' => 'Zablokowane przez inną operację',
            'pending' => 'Niezakończone',
        ][$status] ?? $status;
    }
}
