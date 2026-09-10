<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Admin_Access
{
    private const REQUIRED_ROLES = ['administrator', 'web-dev'];

    public static function is_allowed(): bool
    {
        $user = wp_get_current_user();

        if (!$user || !$user->exists()) {
            return false;
        }

        $roles = array_map('sanitize_key', (array) $user->roles);

        foreach (self::REQUIRED_ROLES as $required_role) {
            if (!in_array($required_role, $roles, true)) {
                return false;
            }
        }

        return true;
    }
}
