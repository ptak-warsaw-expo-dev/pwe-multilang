<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/core/site-group-resource-matrix.php';
require_once __DIR__ . '/core/site-group-resolver.php';
require_once __DIR__ . '/core/site-group-resource-filter.php';

/**
 * Backwards-compatible facade used by Pages and Forms.
 */
final class PWE_Multilang_Site_Group
{
    public static function current(): string
    {
        return PWE_Multilang_Site_Group_Resolver::current();
    }

    public static function label(): string
    {
        return PWE_Multilang_Site_Group_Resolver::label();
    }

    public static function filter_pages(array $pages): array
    {
        return PWE_Multilang_Site_Group_Resource_Filter::filter_pages(
            $pages,
            self::current()
        );
    }

    public static function is_form_template_allowed(string $template_slug): bool
    {
        return PWE_Multilang_Site_Group_Resource_Filter::is_form_template_allowed(
            $template_slug,
            self::current()
        );
    }

    /** @return string[] */
    public static function validate_resources(array $page_keys, array $form_slugs): array
    {
        return PWE_Multilang_Site_Group_Resource_Matrix::validate($page_keys, $form_slugs);
    }
}
