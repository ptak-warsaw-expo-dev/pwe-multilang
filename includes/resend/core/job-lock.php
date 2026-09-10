<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Resend_Job_Lock
{
    private const TTL = 300;
    private const MAX_CAS_ATTEMPTS = 3;

    private static ?string $owned_token = null;
    private static int $ownership_depth = 0;

    public static function acquire(): ?string
    {
        if (self::$owned_token !== null) {
            if (self::refresh(self::$owned_token)) {
                self::$ownership_depth++;
                return self::$owned_token;
            }

            self::$owned_token = null;
            self::$ownership_depth = 0;
        }

        $key = self::key();

        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $token = wp_generate_uuid4();
            $value = self::value($token);

            if (add_option($key, $value, '', false)) {
                self::$owned_token = $token;
                self::$ownership_depth = 1;
                return $token;
            }

            $raw = self::read_raw($key);

            if ($raw === null) {
                continue;
            }

            $current = maybe_unserialize($raw);

            if (self::is_valid($current) && (int) $current['expires_at'] > time()) {
                return null;
            }

            // Remove an expired or malformed value only when it is still exactly
            // the value that was inspected. A concurrent refresh cannot be deleted.
            self::compare_and_delete($key, $raw);
        }

        return null;
    }

    public static function refresh(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $key = self::key();

        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $raw = self::read_raw($key);

            if ($raw === null) {
                return false;
            }

            $current = maybe_unserialize($raw);

            if (!self::is_owned_by($current, $token)) {
                return false;
            }

            $next = $current;
            $next['expires_at'] = time() + self::TTL;
            $next['revision'] = max(0, (int) ($current['revision'] ?? 0)) + 1;

            if (self::compare_and_update($key, $raw, maybe_serialize($next))) {
                return true;
            }
        }

        return false;
    }

    public static function release(string $token): void
    {
        if ($token === '') {
            return;
        }

        if (self::$owned_token !== null && hash_equals(self::$owned_token, $token)) {
            self::$ownership_depth--;

            if (self::$ownership_depth > 0) {
                return;
            }

            self::$owned_token = null;
            self::$ownership_depth = 0;
        }

        $key = self::key();

        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $raw = self::read_raw($key);

            if ($raw === null) {
                return;
            }

            $current = maybe_unserialize($raw);

            if (!self::is_owned_by($current, $token)) {
                return;
            }

            if (self::compare_and_delete($key, $raw)) {
                return;
            }
        }
    }

    private static function key(): string
    {
        return PWE_Multilang_Resend::OPTION_JOB . '_lock';
    }

    private static function value(string $token): array
    {
        return ['token' => $token, 'expires_at' => time() + self::TTL, 'revision' => 0];
    }

    private static function is_valid($value): bool
    {
        return is_array($value)
            && is_string($value['token'] ?? null)
            && $value['token'] !== ''
            && is_numeric($value['expires_at'] ?? null)
            && (int) $value['expires_at'] > 0;
    }

    private static function is_owned_by($value, string $token): bool
    {
        return self::is_valid($value)
            && hash_equals((string) $value['token'], $token);
    }

    private static function read_raw(string $key): ?string
    {
        global $wpdb;

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $key
            )
        );

        return is_string($raw) ? $raw : null;
    }

    private static function compare_and_delete(string $key, string $expected_raw): bool
    {
        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->options,
            ['option_name' => $key, 'option_value' => $expected_raw],
            ['%s', '%s']
        );

        if ($deleted === 1) {
            self::clear_option_cache($key);
            return true;
        }

        return false;
    }

    private static function compare_and_update(string $key, string $expected_raw, string $next_raw): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $wpdb->options,
            ['option_value' => $next_raw],
            ['option_name' => $key, 'option_value' => $expected_raw],
            ['%s'],
            ['%s', '%s']
        );

        if ($updated === 1) {
            self::clear_option_cache($key);
            return true;
        }

        return false;
    }

    private static function clear_option_cache(string $key): void
    {
        wp_cache_delete($key, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }
}
