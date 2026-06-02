<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Writer_Update {

    public static function handle(
        array $existing,
        array $payload,
        array $options = []
    ) : void {

        $options = array_replace([
            'confirmations' => 'replace',
            'notifications' => 'merge_by_name',
        ], $options);

        $existing = PWE_Multilang_Form_Writer_Helper::prepareExistingForUpdate(
            $existing,
            $payload,
            $options
        );

        $result = GFAPI::update_form($existing);

        if (is_wp_error($result)) {
            PWE_Multilang_Form_Writer_Helper::logError(
                'Nie udało się zaktualizować formularza',
                [
                    'id'    => $existing['id'] ?? null,
                    'title' => $existing['title'] ?? null,
                    'error' => $result->get_error_message(),
                ]
            );

            return;
        }

        PWE_Multilang_Form_Writer_Helper::processQrAfterSave(
            (int) $existing['id'],
            $payload,
            $existing['title'] ?? ''
        );
    }
}