<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_GF_Meta_Settings
{
    public static function build(string $title, string $buttonText = 'Submit', array $settings = []): array
    {
        return array_merge([
            'title' => $title,
            'description' => '',
            'labelPlacement' => 'top_label',
            'descriptionPlacement' => 'below',
            'button' => [
                'type' => 'text',
                'text' => $buttonText,
            ],
            'is_active' => true,
            'enableHoneypot' => true,
            'markupVersion' => 1,
        ], $settings);
    }
}
