<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Admin_UI
{
    public static function cardOpen(string $class = 'pwe-card'): void
    {
        echo '<div class="' . esc_attr($class) . '">';
    }

    public static function cardClose(): void
    {
        echo '</div>';
    }

    public static function cardTitle(string $contentHtml): void
    {
        echo '<p class="pwe-card-title">' . $contentHtml . '</p>';
    }

    public static function cardDesc(string $contentHtml): void
    {
        echo '<p class="pwe-card-desc">' . $contentHtml . '</p>';
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
