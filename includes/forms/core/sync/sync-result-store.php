<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Result_Store
{
    private const TRANSIENT_PREFIX = 'pwe_mlg_forms_result_';

    public static function set(array $result): void
    {
        set_transient(self::key(), $result, 5 * MINUTE_IN_SECONDS);
    }

    public static function pull()
    {
        if (empty($_GET['pwe_mlg_forms_result'])) {
            return null;
        }

        $key = self::key();
        $result = get_transient($key);
        delete_transient($key);

        return $result;
    }

    private static function key(): string
    {
        return self::TRANSIENT_PREFIX . get_current_user_id();
    }
}
