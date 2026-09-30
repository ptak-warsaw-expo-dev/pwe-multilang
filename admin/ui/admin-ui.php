<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Admin_UI
{
    public static function moduleIcon(string $module): string
    {
        $icons = [
            '' => '<path d="M4 3v7m0 4v7M12 3v3m0 4v11M20 3v11m0 4v3M1 10h6M9 6h6M17 14h6"/>',
            'forms' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 8h18M7 12h2m3 0h5M7 16h2m3 0h5"/>',
            'pages' => '<path d="M14 2H8a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 2v6h6M2 6v14a2 2 0 0 0 2 2h12"/>',
            'tests' => '<rect x="5" y="4" width="14" height="18" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m9 13 2 2 4-4"/>',
            'gf_addon' => '<path d="M8 3v5m8-5v5M6 8h12v3a6 6 0 0 1-12 0V8ZM12 17v4"/>',
            'replace_content' => '<path d="M20 7H4m0 0 4-4M4 7l4 4M4 17h16m0 0-4-4m4 4-4 4"/>',
            'resend' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'warning' => '<path d="m12 3 10 18H2L12 3ZM12 9v5m0 3v1"/>',
            'sync' => '<path d="M20 7a9 9 0 0 0-15-2L2 8m0-5v5h5M4 17a9 9 0 0 0 15 2l3-3m0 5v-5h-5"/>',
            'entries' => '<ellipse cx="10" cy="5" rx="7" ry="3"/><path d="M3 5v12c0 4 14 4 14 0V5M3 11c0 4 14 4 14 0M20 15v6m-3-3h6"/>',
            'log' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h1m3 0h6M7 12h1m3 0h6M7 17h1m3 0h6"/>',
        ];

        if (!isset($icons[$module])) {
            return '';
        }

        return '<svg class="pwe-module-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $icons[$module] . '</svg>';
    }

    public static function heroOpen(string $class = 'pwe-multilang-hero', string $module = ''): void
    {
        $themes = [
            '' => ['label' => 'General settings', 'secondary' => 'gf_addon'],
            'forms' => ['label' => 'Forms', 'secondary' => 'sync'],
            'pages' => ['label' => 'Pages', 'secondary' => 'translation'],
            'tests' => ['label' => 'Tests', 'secondary' => 'resend'],
            'gf_addon' => ['label' => 'GF Addon', 'secondary' => 'forms'],
        ];
        $theme = $themes[$module] ?? $themes[''];

        echo '<div class="' . esc_attr($class . ' pwe-gf-addon-hero') . '">';
        echo '<div class="pwe-gf-addon-art pwe-multilang-hero-art" aria-hidden="true">';
        echo '<svg class="pwe-gf-addon-arrows" viewBox="0 0 260 170" fill="none"><path d="M123 31C148 5 176 17 190 44m-13-6 13 6 1-14M139 139c-25 24-53 13-65-9m-1 13 1-13 13 4" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        echo '<span class="pwe-gf-addon-art-tile pwe-gf-addon-art-tile--forms">' . self::moduleIcon($module) . '</span>';
        echo '<span class="pwe-gf-addon-art-tile pwe-gf-addon-art-tile--addon">';
        if ($theme['secondary'] === 'translation') {
            echo '<span class="dashicons dashicons-translation"></span>';
        } else {
            echo self::moduleIcon($theme['secondary']);
        }
        echo '</span></div>';
        echo '<div class="pwe-gf-addon-hero-copy">';
        echo '<div class="pwe-gf-addon-eyebrow"><span class="pwe-gf-addon-icon">' . self::moduleIcon($module) . '</span>' . esc_html($theme['label']) . '</div>';
    }

    public static function heroClose(): void
    {
        echo '</div></div>';
    }
    public static function cardOpen(string $class = 'pwe-multilang-card'): void
    {
        echo '<div class="' . esc_attr($class) . '">';
    }

    public static function cardClose(): void
    {
        echo '</div>';
    }

    public static function cardTitle(string $contentHtml): void
    {
        echo '<h3 class="pwe-multilang-card-title">' . $contentHtml . '</h3>';
    }

    public static function cardDesc(string $contentHtml): void
    {
        echo '<p class="pwe-multilang-card-desc">' . $contentHtml . '</p>';
    }

    public static function notice(string $class, string $iconClass, string $contentHtml): void
    {
        echo '<div class="' . esc_attr($class) . '">';
        echo '<span class="dashicons ' . esc_attr($iconClass) . '"></span>';
        echo '<div>' . $contentHtml . '</div>';
        echo '</div>';
    }

    public static function status(string $tag, string $variant, string $iconClass, string $contentHtml): void
    {
        $tag = in_array($tag, ['p', 'span', 'div'], true) ? $tag : 'span';
        echo '<' . $tag . ' class="' . esc_attr('pwe-status pwe-status--' . $variant) . '">';
        echo '<span class="dashicons ' . esc_attr($iconClass) . '"></span>';
        echo $contentHtml;
        echo '</' . $tag . '>';
    }

    /**
     * @param array<string, string> $inputAttributes
     */
    public static function checkbox(array $inputAttributes, string $containerClass = 'pwe-checkbox-container', string $wrapperTag = 'label'): void
    {
        $wrapperTag = in_array($wrapperTag, ['label', 'span'], true) ? $wrapperTag : 'label';
        echo '<' . $wrapperTag . ' class="' . esc_attr($containerClass) . '">';
        echo '<input' . self::attributes($inputAttributes) . '>';
        echo '<span class="pwe-checkmark"></span>';
        echo '</' . $wrapperTag . '>';
    }

    /**
     * @param array<string, string> $inputAttributes
     */
    public static function switch(array $inputAttributes, string $wrapperClass = 'pwe-switch'): void
    {
        echo '<label class="' . esc_attr($wrapperClass) . '">';
        echo '<input' . self::attributes($inputAttributes) . '>';
        echo '<span class="pwe-switch-slider"></span>';
        echo '</label>';
    }

    public static function fieldOpen(string $class = 'pwe-field'): void
    {
        echo '<div class="' . esc_attr($class) . '">';
    }

    public static function fieldClose(): void
    {
        echo '</div>';
    }

    public static function fieldHint(string $contentHtml): void
    {
        echo '<p class="pwe-hint">' . $contentHtml . '</p>';
    }

    /**
     * @param array<string, string> $attributes
     */
    public static function button(array $attributes, string $innerHtml): void
    {
        echo '<button' . self::attributes($attributes) . '>' . $innerHtml . '</button>';
    }

    /**
     * @param array<string, string> $attributes
     */
    public static function attributes(array $attributes): string
    {
        $chunks = [];

        foreach ($attributes as $name => $value) {
            $chunks[] = sprintf(' %s="%s"', esc_attr((string) $name), esc_attr((string) $value));
        }

        return implode('', $chunks);
    }
}
