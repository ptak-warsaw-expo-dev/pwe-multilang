<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Writer {

    public static function create(array $payload) : void {
        PWE_Multilang_Form_Writer_Create::handle($payload);
    }

    public static function update(
        array $existing,
        array $payload,
        array $options = []
    ) : void {
        PWE_Multilang_Form_Writer_Update::handle($existing, $payload, $options);
    }
}