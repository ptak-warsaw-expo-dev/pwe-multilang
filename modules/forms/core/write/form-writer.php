<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Writer {

    public static function create(array $payload, array &$warnings = [], array &$errors = []) : ?int {
        $token = PWE_Multilang_Form_Operation_Lock::acquire('form_create');

        if ($token === null) {
            return null;
        }

        try {
            return self::write_new($payload, $warnings, $errors);
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }

    public static function update(
        array $existing,
        array $payload,
        array $options = [],
        array &$warnings = [],
        array &$errors = []
    ) : bool {
        $token = PWE_Multilang_Form_Operation_Lock::acquire('form_update');

        if ($token === null) {
            return false;
        }

        try {
            return self::write_existing($existing, $payload, $options, $warnings);
        } catch (\RuntimeException $error) {
            $errors[] = $error->getMessage();
            return false;
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }

    private static function write_new(array $payload, array &$warnings, array &$errors) : ?int {

        $payload = PWE_Multilang_Form_Preparer::preparePayloadForCreate($payload);
        $payload['pwe_multilang_managed'] = 1;
        $payload[PWE_Multilang_Form_Core::MANUAL_MODIFIED_FLAG] = 0;

        $formId = PWE_Multilang_Form_Manual_Modified::run(static function () use ($payload) {
            $formId = GFAPI::add_form($payload);
            return $formId;
        });

        if (is_wp_error($formId) || (int) $formId <= 0) {
            PWE_Multilang_Form_Log_Service::error(
                'Nie udało się utworzyć formularza',
                [
                    'title' => $payload['title'] ?? null,
                    'error' => is_wp_error($formId) ? $formId->get_error_message() : 'GFAPI::add_form zwróciło pusty identyfikator',
                ]
            );

            return null;
        }

        try {
            PWE_Multilang_Form_Manual_Modified::run(static function () use ($formId, $payload, &$warnings): void {
                PWE_Multilang_Form_QR::processAfterSave(
                    (int) $formId,
                    $payload,
                    '',
                    $warnings
                );
            });
        } catch (\RuntimeException $error) {
            $errors[] = $error->getMessage();
        }

        PWE_Multilang_Form_Finder::resetCache();

        return (int) $formId;
    }

    private static function write_existing(
        array $existing,
        array $payload,
        array $options,
        array &$warnings
    ) : bool {

        $options = array_replace([
            'confirmations' => 'replace',
            'notifications' => 'merge',
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

        PWE_Multilang_Form_Manual_Modified::run(static function () use ($existing, $payload, &$warnings): void {
            PWE_Multilang_Form_QR::processAfterUpdate(
                (int) $existing['id'],
                $payload,
                $existing['title'] ?? '',
                $warnings
            );
        });

        PWE_Multilang_Form_Finder::resetCache();

        return true;
    }
}
