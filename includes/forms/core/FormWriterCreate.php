<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Writer_Create {

    public static function handle(array $payload) : void {

        $payload = PWE_Multilang_Form_Writer_Helper::preparePayloadForCreate($payload);

        $formId = GFAPI::add_form($payload);

        if (is_wp_error($formId)) {
            PWE_Multilang_Form_Writer_Helper::logError(
                'Nie udało się utworzyć formularza',
                [
                    'title' => $payload['title'] ?? null,
                    'error' => $formId->get_error_message(),
                ]
            );

            return;
        }

        PWE_Multilang_Form_Writer_Helper::processQrAfterSave(
            (int) $formId,
            $payload
        );
    }
}