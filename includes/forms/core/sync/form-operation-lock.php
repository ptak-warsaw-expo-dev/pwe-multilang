<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One site-wide, token-owned and request-reentrant lock for every form write.
 */
final class PWE_Multilang_Form_Operation_Lock
{
    public const OPTION_KEY = 'pwe_mlg_form_update_sync_lock';

    private const TTL = 600;
    private const MAX_CAS_ATTEMPTS = 3;

    private static ?string $ownedToken = null;
    private static int $depth = 0;

    public static function acquire(string $context = 'form_sync'): ?string
    {
        if (self::$ownedToken !== null) {
            if (self::refresh(self::$ownedToken)) {
                self::$depth++;
                return self::$ownedToken;
            }

            return null;
        }

        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $token = self::makeToken();
            $lock = self::value($token, $context);

            if (add_option(self::OPTION_KEY, $lock, '', false)) {
                self::$ownedToken = $token;
                self::$depth = 1;
                return $token;
            }

            $raw = self::readRaw();

            if ($raw === null) {
                continue;
            }

            if (self::isActive(maybe_unserialize($raw))) {
                return null;
            }

            self::compareAndDelete($raw);
        }

        return null;
    }

    public static function refresh(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $raw = self::readRaw();

            if ($raw === null) {
                return false;
            }

            $current = maybe_unserialize($raw);

            if (!self::isOwnedBy($current, $token)) {
                return false;
            }

            $next = $current;
            $next['expires_at'] = time() + self::TTL;
            $next['revision'] = max(0, (int) ($current['revision'] ?? 0)) + 1;

            if (self::compareAndUpdate($raw, maybe_serialize($next))) {
                return true;
            }
        }

        return false;
    }

    public static function refreshOwned(): bool
    {
        return self::$ownedToken !== null && self::refresh(self::$ownedToken);
    }

    public static function release(?string $token): void
    {
        if ($token === null || self::$ownedToken === null || !hash_equals(self::$ownedToken, $token)) {
            return;
        }

        self::$depth = max(0, self::$depth - 1);

        if (self::$depth > 0) {
            return;
        }

        self::$ownedToken = null;
        self::$depth = 0;

        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $raw = self::readRaw();

            if ($raw === null) {
                return;
            }

            if (!self::isOwnedBy(maybe_unserialize($raw), $token)) {
                return;
            }

            if (self::compareAndDelete($raw)) {
                return;
            }
        }
    }

    public static function isLocked(): bool
    {
        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $raw = self::readRaw();

            if ($raw === null) {
                return false;
            }

            if (self::isActive(maybe_unserialize($raw))) {
                return true;
            }

            if (!self::compareAndDelete($raw)) {
                continue;
            }
        }

        return self::readRaw() !== null;
    }

    public static function ownedByCurrentRequest(): bool
    {
        return self::$ownedToken !== null;
    }

    private static function value(string $token, string $context): array
    {
        return [
            'token' => $token,
            'time' => time(),
            'expires_at' => time() + self::TTL,
            'revision' => 0,
            'context' => sanitize_key($context),
        ];
    }

    private static function isActive($lock): bool
    {
        if (is_numeric($lock)) {
            return ((int) $lock + self::TTL) >= time();
        }

        if (!is_array($lock)) {
            return false;
        }

        if (is_numeric($lock['expires_at'] ?? null)) {
            return (int) $lock['expires_at'] >= time();
        }

        return is_numeric($lock['time'] ?? null)
            && ((int) $lock['time'] + self::TTL) >= time();
    }

    private static function isOwnedBy($lock, string $token): bool
    {
        return is_array($lock)
            && is_string($lock['token'] ?? null)
            && $lock['token'] !== ''
            && hash_equals($lock['token'], $token);
    }

    private static function readRaw(): ?string
    {
        global $wpdb;

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                self::OPTION_KEY
            )
        );

        return is_string($raw) ? $raw : null;
    }

    private static function compareAndDelete(string $expectedRaw): bool
    {
        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->options,
            ['option_name' => self::OPTION_KEY, 'option_value' => $expectedRaw],
            ['%s', '%s']
        );

        if ($deleted !== 1) {
            return false;
        }

        self::clearOptionCache();
        return true;
    }

    private static function compareAndUpdate(string $expectedRaw, string $nextRaw): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $wpdb->options,
            ['option_value' => $nextRaw],
            ['option_name' => self::OPTION_KEY, 'option_value' => $expectedRaw],
            ['%s'],
            ['%s', '%s']
        );

        if ($updated !== 1) {
            return false;
        }

        self::clearOptionCache();
        return true;
    }

    private static function clearOptionCache(): void
    {
        wp_cache_delete(self::OPTION_KEY, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }

    private static function makeToken(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        return uniqid('pwe_mlg_', true);
    }
}
