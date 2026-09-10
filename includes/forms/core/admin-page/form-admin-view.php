<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Admin_View
{
    private const NONCE_ACTION = 'pwe_mlg_generate_forms_action';
    private const NONCE_NAME = 'pwe_mlg_forms_nonce';

    public static function render(array $templates, int $savedYear, int $currentYear, bool $gravityReady): void
    {
        PWE_Multilang_Admin_UI::cardOpen('pwe-card');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-feedback"></span> Generator formularzy');
        PWE_Multilang_Admin_UI::status('div', 'info', 'dashicons-groups', ' Aktywna grupa: <strong>' . esc_html(PWE_Multilang_Site_Group::label()) . '</strong>');
        PWE_Multilang_Admin_UI::cardDesc('Synchronizuje formularze Gravity Forms na podstawie template\'ów generatora PWE Multilang. Wybierz template\'y i zakres operacji.');

        self::renderGravityStatus($gravityReady);

        echo '<hr class="pwe-divider">';

        ?>
            <form method="post" id="pwe-forms-sync-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="pwe_mlg_do_generate" value="1">
    
                <div class="pwe-form-settings">
                    <?php self::renderYearField($savedYear, $currentYear, $gravityReady); ?>
                    <?php self::renderAddOnlyField(); ?>
                </div>
                <?php self::renderTemplateActions(); ?>
                <?php PWE_Multilang_Form_Admin_Template_Renderer::render($templates, $gravityReady); ?>
                <?php do_action('pwe_mlg_forms_after_template_list', $gravityReady); ?>

                <div class="pwe-form-sync-actions">
                    <?php foreach (self::syncScopes() as $scope => $data) : ?>
                        <?php self::renderScopeButton($scope, $data['label'], $data['description'], $gravityReady); ?>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php
        PWE_Multilang_Admin_UI::cardClose();
    }

    private static function renderGravityStatus(bool $gravityReady): void
    {
        if (!$gravityReady) {
            PWE_Multilang_Admin_UI::status('div', 'error', 'dashicons-warning', ' Gravity Forms nie jest aktywne. Aktywuj wtyczkę, aby korzystać z generatora.');
            return;
        }

        PWE_Multilang_Admin_UI::status('div', 'ok', 'dashicons-yes-alt', ' Gravity Forms jest aktywne i gotowe do użycia.');
    }

    private static function renderYearField(int $savedYear, int $currentYear, bool $gravityReady): void
    {
        PWE_Multilang_Admin_UI::fieldOpen('pwe-field');
        ?>
            <label for="pwe_mlg_forms_year">Rok formularzy</label>
            <input
                type="number"
                id="pwe_mlg_forms_year"
                name="pwe_mlg_forms_year"
                value="<?php echo esc_attr((string) $savedYear); ?>"
                min="2000"
                max="2100"
                <?php disabled(!$gravityReady); ?>
            >
            <?php PWE_Multilang_Admin_UI::fieldHint('Np. ' . esc_html((string) $currentYear) . ' - formularze otrzymają rok w nazwie.'); ?>
        <?php
        PWE_Multilang_Admin_UI::fieldClose();
    }

    private static function renderAddOnlyField(): void
    {
        PWE_Multilang_Admin_UI::fieldOpen('pwe-field pwe-form-safe-mode');
        ?>
            <label>
                <?php
                PWE_Multilang_Admin_UI::checkbox(
                    [
                        'type' => 'checkbox',
                        'name' => 'pwe_mlg_add_only',
                        'value' => '1',
                    ],
                    'pwe-checkbox-container',
                    'span'
                );
                ?>
                <span>Tylko dodaj brakujące - nie aktualizuj istniejących</span>
            </label>
            <?php PWE_Multilang_Admin_UI::fieldHint('Ten tryb działa dla Forms, Fields, Notifications i Confirmations.'); ?>
        <?php
        PWE_Multilang_Admin_UI::fieldClose();
    }

    private static function renderTemplateActions(): void
    {
        ?>
        <div class="pwe-form-template-actions">
            <button type="button" class="button" data-pwe-select="all">Zaznacz wszystkie</button>
            <button type="button" class="button" data-pwe-select="missing">Zaznacz brakujące</button>
            <button type="button" class="button" data-pwe-select="existing">Zaznacz istniejące</button>
            <button type="button" class="button" data-pwe-select="none">Odznacz wszystkie</button>
        </div>
        <?php
    }

    private static function renderScopeButton(string $scope, string $label, string $description, bool $gravityReady): void
    {
        $attributes = [
            'type' => 'submit',
            'name' => 'pwe_mlg_sync_scope',
            'value' => $scope,
            'class' => 'pwe-btn pwe-form-sync-button',
        ];

        if (!$gravityReady) {
            $attributes['disabled'] = 'disabled';
        }

        PWE_Multilang_Admin_UI::button(
            $attributes,
            '<span class="dashicons dashicons-update"></span><span>' . esc_html($label) . '</span><small>' . esc_html($description) . '</small>'
        );
    }

    private static function syncScopes(): array
    {
        return [
            'forms' => [
                'label' => 'Forms',
                'description' => 'cały formularz',
            ],
            'fields' => [
                'label' => 'Fields',
                'description' => 'pola i ich ustawienia',
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'powiadomienia GF',
            ],
            'confirmations' => [
                'label' => 'Confirmations',
                'description' => 'potwierdzenia GF',
            ],
            'feed' => [
                'label' => 'Feed',
                'description' => 'wszystkie feedy QR zachowuje rnd',
            ],
        ];
    }
}
