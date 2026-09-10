<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Writer_Update {

    public static function handle(
        array $existing,
        array $payload,
        array $options = []
    ) : bool {

        $options = array_replace([
            'confirmations' => 'replace',
            'notifications' => 'merge_by_name',
        ], $options);

        $diffLog = PWE_Multilang_Form_Diff::getUpdateLog($existing, $payload);

        if (!empty(array_filter($diffLog))) {
            PWE_Multilang_Form_Log::warn('FORM: Różnice względem template', [
                'form' => $existing['title'] ?? '',
                'diff' => $diffLog,
            ]);
        }

        $existing = PWE_Multilang_Form_Preparer::prepareExistingForUpdate(
            $existing,
            $payload,
            $options
        );
        $existing['pwe_multilang_managed'] = 1;
        $existing[PWE_Multilang_Form_Core::MANUAL_MODIFIED_FLAG] = 0;

        $result = PWE_Multilang_Form_Manual_Modified::run(static function () use ($existing) {
            $result = GFAPI::update_form($existing);
            return $result;
        });

        if (is_wp_error($result) || $result === false) {
            PWE_Multilang_Form_Log_Service::error(
                'Nie udało się zaktualizować formularza',
                [
                    'id'    => $existing['id'] ?? null,
                    'title' => $existing['title'] ?? null,
                    'error' => is_wp_error($result) ? $result->get_error_message() : 'GFAPI::update_form zwróciło false',
                ]
            );

            return false;
        }

        PWE_Multilang_Form_Manual_Modified::run(static function () use ($existing): void {
            PWE_Multilang_Form_QR::processShortcodesOnly(
                (int) $existing['id'],
                $existing['title'] ?? ''
            );
        });

        PWE_Multilang_Form_Finder::resetCache();

        return true;
    }
}
