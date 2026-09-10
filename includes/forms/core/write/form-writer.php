<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Writer {

    public static function create(array $payload) : ?int {
        $token = PWE_Multilang_Form_Operation_Lock::acquire('form_create');

        if ($token === null) {
            return null;
        }

        try {
            return PWE_Multilang_Form_Writer_Create::handle($payload);
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }

    public static function update(
        array $existing,
        array $payload,
        array $options = []
    ) : bool {
        $token = PWE_Multilang_Form_Operation_Lock::acquire('form_update');

        if ($token === null) {
            return false;
        }

        try {
            return PWE_Multilang_Form_Writer_Update::handle($existing, $payload, $options);
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }
}
