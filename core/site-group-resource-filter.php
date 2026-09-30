<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Site_Group_Resource_Filter
{
    public static function filter_pages(array $pages, string $group): array
    {
        if ($group === PWE_Multilang_Site_Group_Resolver::LEGACY_GROUP) {
            return $pages;
        }

        foreach (array_keys($pages) as $page_key) {
            if (!self::is_allowed(
                PWE_Multilang_Site_Group_Resource_Matrix::RESOURCE_PAGES,
                (string) $page_key,
                $group
            )) {
                unset($pages[$page_key]);
            }
        }

        return $pages;
    }

    public static function is_form_template_allowed(string $template_slug, string $group): bool
    {
        if ($group === PWE_Multilang_Site_Group_Resolver::LEGACY_GROUP) {
            return true;
        }

        return self::is_allowed(
            PWE_Multilang_Site_Group_Resource_Matrix::RESOURCE_FORMS,
            $template_slug,
            $group
        );
    }

    public static function is_allowed(string $resource_type, string $resource_key, string $group): bool
    {
        if (!PWE_Multilang_Site_Group_Resource_Matrix::is_supported_group($group)) {
            return false;
        }

        return PWE_Multilang_Site_Group_Resource_Matrix::is_assigned(
            $resource_type,
            $resource_key,
            $group
        );
    }
}
