<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Log_Service
{
    public static function error(string $message, array $context = []): void
    {
        if (class_exists('PWE_Multilang_Form_Log')) {
            PWE_Multilang_Form_Log::error('FORM: ' . $message, $context);
        }
    }

    public static function warn(string $message, array $context = []): void
    {
        if (class_exists('PWE_Multilang_Form_Log')) {
            PWE_Multilang_Form_Log::warn('FORM: ' . $message, $context);
        }
    }
}
