<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibility adapter for the legacy template classes.
 *
 * Template classes still expose apply() and can save a form when called
 * directly. During discovery we switch them into their existing capture mode
 * and restore every previous global value, even when a template throws.
 */
final class PWE_Multilang_Form_Template_Capture
{
    private const CAPTURE_FLAG = 'pwe_mlg_capture_template_payload';
    private const PAYLOAD_KEY = 'pwe_mlg_captured_template_payload';

    public static function capture(string $class, int $formsYear): array
    {
        if (!method_exists($class, 'apply')) {
            return [];
        }

        $hadCaptureFlag = array_key_exists(self::CAPTURE_FLAG, $GLOBALS);
        $previousCaptureFlag = $GLOBALS[self::CAPTURE_FLAG] ?? null;
        $hadPayload = array_key_exists(self::PAYLOAD_KEY, $GLOBALS);
        $previousPayload = $GLOBALS[self::PAYLOAD_KEY] ?? null;

        $GLOBALS[self::CAPTURE_FLAG] = true;
        $GLOBALS[self::PAYLOAD_KEY] = [];

        try {
            $class::apply($formsYear);

            $payload = $GLOBALS[self::PAYLOAD_KEY] ?? [];

            return is_array($payload) ? $payload : [];
        } finally {
            if ($hadCaptureFlag) {
                $GLOBALS[self::CAPTURE_FLAG] = $previousCaptureFlag;
            } else {
                unset($GLOBALS[self::CAPTURE_FLAG]);
            }

            if ($hadPayload) {
                $GLOBALS[self::PAYLOAD_KEY] = $previousPayload;
            } else {
                unset($GLOBALS[self::PAYLOAD_KEY]);
            }
        }
    }
}
