<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Writer_Create {

    public static function handle(array $payload) : ?int {

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

        PWE_Multilang_Form_Manual_Modified::run(static function () use ($formId, $payload): void {
            PWE_Multilang_Form_QR::processAfterSave(
                (int) $formId,
                $payload
            );
        });

        PWE_Multilang_Form_Finder::resetCache();

        return (int) $formId;
    }
}
