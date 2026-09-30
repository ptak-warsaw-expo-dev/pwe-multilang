<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Admin_Template_Renderer
{
    public static function render(array $templates, bool $gravityReady): void
    {
        ?>
        <div class="pwe-form-template-list">
            <?php foreach ($templates as $template) : ?>
                <?php self::renderRow(is_array($template) ? $template : [], $gravityReady); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function renderRow(array $template, bool $gravityReady): void
    {
        $slug = (string) ($template['slug'] ?? '');
        $name = (string) ($template['name'] ?? '');
        $title = (string) ($template['title'] ?? '');
        $exists = !empty($template['exists']);
        $modified = !empty($template['manual_modified']);
        $status = PWE_Multilang_Form_Admin_Template_Presenter::statusLabel($template);
        $statusClass = PWE_Multilang_Form_Admin_Template_Presenter::statusClass($template);
        ?>
        <div
            class="pwe-form-template-row"
            data-pwe-exists="<?php echo esc_attr($exists ? '1' : '0'); ?>"
            data-pwe-manual-modified="<?php echo esc_attr($modified ? '1' : '0'); ?>"
        >
            <?php
            $attributes = [
                'type' => 'checkbox',
                'name' => 'pwe_mlg_templates[]',
                'value' => $slug,
            ];

            if (!$gravityReady || $slug === '') {
                $attributes['disabled'] = 'disabled';
            }

            PWE_Multilang_Admin_UI::checkbox($attributes, 'pwe-checkbox-container', 'label');
            ?>

            <span class="pwe-form-template-main">
                <strong><?php echo esc_html($name); ?></strong>
                <span><?php echo esc_html($title); ?></span>
            </span>

            <span class="pwe-form-template-meta">
                <span class="pwe-form-template-id">
                    <?php echo esc_html(PWE_Multilang_Form_Admin_Template_Presenter::formIdsLabel($template)); ?>
                </span>
                <span class="pwe-form-template-status <?php echo esc_attr($statusClass); ?>">
                    <?php echo esc_html($status); ?>
                </span>
            </span>

            <?php if ($modified) : ?>
                <?php self::renderOverwriteConfirmation($slug); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function renderOverwriteConfirmation(string $slug): void
    {
        ?>
        <span class="pwe-form-template-overwrite">
            <?php
            PWE_Multilang_Admin_UI::checkbox(
                [
                    'type' => 'checkbox',
                    'name' => 'pwe_mlg_overwrite_modified[' . $slug . ']',
                    'value' => '1',
                    'disabled' => 'disabled',
                ],
                'pwe-checkbox-container pwe-checkbox-container--orange',
                'label'
            );
            ?>

            <span>Rozumiem, że formularz był edytowany ręcznie i chcę go nadpisać generatorem.</span>
        </span>
        <?php
    }
}