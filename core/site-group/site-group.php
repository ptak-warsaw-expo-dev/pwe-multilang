<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/site-group-config.php';

final class PWE_Multilang_Site_Group
{
    public const LEGACY_GROUP = 'legacy';

    /** @var array<string, bool> */
    private static array $reported = [];

    public static function current(): string
    {
        if (!function_exists('shortcode_exists') || !shortcode_exists('trade_fair_group')) {
            return self::LEGACY_GROUP;
        }

        $value = do_shortcode('[trade_fair_group]');
        $value = sanitize_key(trim(wp_strip_all_tags((string) $value)));

        if (self::is_supported_group($value)) {
            return $value;
        }

        if ($value !== '' && empty(self::$reported[$value])) {
            self::$reported[$value] = true;
            do_action('pwe_multilang_unknown_site_group', $value);
        }

        return self::LEGACY_GROUP;
    }

    public static function label(): string
    {
        $group = self::current();

        return $group === self::LEGACY_GROUP
            ? 'wszystkie (brak rozpoznanej grupy)'
            : strtoupper($group);
    }

    public const RESOURCE_PAGES = PWE_Multilang_Site_Group_Config::RESOURCE_PAGES;
    public const RESOURCE_FORMS = PWE_Multilang_Site_Group_Config::RESOURCE_FORMS;

    /** @return string[] */
    public static function supported_groups(): array
    {
        return PWE_Multilang_Site_Group_Config::supported_groups();
    }

    public static function is_supported_group(string $group): bool
    {
        return in_array($group, self::supported_groups(), true);
    }

    public static function is_assigned(string $resource_type, string $resource_key, string $group): bool
    {
        $resources = self::resources($resource_type);

        return !empty($resources[$resource_key][$group]);
    }

    /** @return array<string, array<string, bool>> */
    public static function resources(string $resource_type): array
    {
        return PWE_Multilang_Site_Group_Config::resources($resource_type);
    }

    /**
     * Reports missing and stale matrix entries without changing availability.
     *
     * @param string[] $page_keys
     * @param string[] $form_slugs
     * @return string[]
     */
    public static function validate_resources(array $page_keys, array $form_slugs): array
    {
        $errors = [];
        $catalogs = [
            self::RESOURCE_PAGES => array_values(array_unique(array_map('strval', $page_keys))),
            self::RESOURCE_FORMS => array_values(array_unique(array_map('strval', $form_slugs))),
        ];

        foreach ($catalogs as $type => $keys) {
            $matrix = self::resources($type);

            foreach (array_diff($keys, array_keys($matrix)) as $key) {
                $errors[] = $type . ': brak przypisania dla zasobu „' . $key . '”.';
            }

            foreach (array_diff(array_keys($matrix), $keys) as $key) {
                $errors[] = $type . ': przypisanie wskazuje nieistniejący zasób „' . $key . '”.';
            }

            foreach ($matrix as $key => $groups) {
                foreach (self::supported_groups() as $group) {
                    if (!array_key_exists($group, $groups) || !is_bool($groups[$group])) {
                        $errors[] = $type . ': niepełne przypisanie „' . $key . '” dla ' . $group . '.';
                    }
                }
            }
        }

        return $errors;
    }

    public static function filter_pages(array $pages, ?string $group = null): array
    {
        $group = $group ?? self::current();

        if ($group === self::LEGACY_GROUP) {
            return $pages;
        }

        foreach (array_keys($pages) as $page_key) {
            if (!self::is_allowed(
                self::RESOURCE_PAGES,
                (string) $page_key,
                $group
            )) {
                unset($pages[$page_key]);
            }
        }

        return $pages;
    }

    public static function is_form_template_allowed(string $template_slug, ?string $group = null): bool
    {
        $group = $group ?? self::current();

        if ($group === self::LEGACY_GROUP) {
            return true;
        }

        return self::is_allowed(
            self::RESOURCE_FORMS,
            $template_slug,
            $group
        );
    }

    public static function is_allowed(string $resource_type, string $resource_key, string $group): bool
    {
        if (!self::is_supported_group($group)) {
            return false;
        }

        return self::is_assigned(
            $resource_type,
            $resource_key,
            $group
        );
    }
}
