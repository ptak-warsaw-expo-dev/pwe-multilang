<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Log
{
    const ERROR = 'ERROR';
    const WARN  = 'WARN';

    public static function error(string $message, array $context = []): void
    {
        self::write(self::ERROR, $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        self::write(self::WARN, $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );

        $path = self::getPath();

        if (@file_put_contents($path, $line, FILE_APPEND) === false) {
            error_log("FG_LOG FAIL {$path}: {$line}");
        }
    }

    private static function getPath(): string
    {
        $uploadDir = wp_upload_dir();
        $logDir = $uploadDir['basedir'] . '/pwe-element/mailing';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        return $logDir . '/mailing.log';
    }

}

