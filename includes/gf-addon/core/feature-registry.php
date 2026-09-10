<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Addon_Features
{
    /** @var array<string, array{label: string, description: string, callback: callable, active: bool}> */
    private static array $features = [];
    private static array $booted = [];

    public static function register(
        string $key,
        string $label,
        string $description,
        callable $callback,
        bool $active = true
    ): void {
        self::$features[$key] = compact('label', 'description', 'callback', 'active');
    }

    /** @return array<string, array{label: string, description: string, callback: callable, active: bool}> */
    public static function active(): array
    {
        return array_filter(self::$features, static fn(array $feature): bool => $feature['active']);
    }

    public static function boot(): void
    {
        foreach (self::active() as $key => $feature) {
            if (!empty(self::$booted[$key])) {
                continue;
            }

            call_user_func($feature['callback']);
            self::$booted[$key] = true;
        }
    }
}
