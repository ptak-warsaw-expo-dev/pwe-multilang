<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One parser for notification names used by Resend, Tests and GF admin UI.
 */
final class PWE_Multilang_GF_Notification_Name
{
    public static function strict_language(string $name): string
    {
        if (preg_match('/\s-\s([a-z]{2})$/i', trim($name), $matches) !== 1) {
            return '';
        }

        return strtolower($matches[1]);
    }

    /**
     * Detects a language in a stable order. The notification name has priority;
     * template/message are compatibility fallbacks used by the Tests screen.
     *
     * @param string[] $languages
     */
    public static function detect_language(
        string $name,
        array $languages,
        string $template = '',
        string $message = ''
    ): string {
        $strict = self::strict_language($name);

        if ($strict !== '' && self::is_allowed($strict, $languages)) {
            return strtoupper($strict);
        }

        $sources = [$name, $template, wp_strip_all_tags($message)];

        foreach ($sources as $source) {
            foreach ($languages as $language) {
                $language = strtolower(sanitize_key((string) $language));

                if ($language === '') {
                    continue;
                }

                if (preg_match(
                    '/(^|[\s_\-\[\(\/])' . preg_quote($language, '/') . '($|[\s_\-\]\)\.\/])/i',
                    $source
                ) === 1) {
                    return strtoupper($language);
                }
            }
        }

        return '';
    }

    public static function base_title(string $name, string $language = ''): string
    {
        $name = trim($name);

        if ($language !== '') {
            $name = (string) preg_replace(
                '/\s-\s' . preg_quote(strtoupper($language), '/') . '$/i',
                '',
                $name
            );
        } else {
            $name = (string) preg_replace('/\s-\s[A-Z]{2}$/i', '', $name);
        }

        return trim($name) !== '' ? trim($name) : '__OTHER__';
    }

    private static function is_allowed(string $language, array $languages): bool
    {
        $allowed = array_map(
            static fn($item): string => strtolower(sanitize_key((string) $item)),
            $languages
        );

        return in_array(strtolower($language), $allowed, true);
    }
}

