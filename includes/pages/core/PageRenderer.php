<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Renderer
{
    public static function render_sync_panel(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $result = null;
        $event_year = self::get_event_year_from_request();

        if (
            isset($_POST['pwe_wpml_sync_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pwe_wpml_sync_nonce'])), 'pwe_wpml_sync_pages')
        ) {
            try {
                $result = PWE_Multilang_Page_Creator::create_missing_wpml_pages_from_json($event_year);
            } catch (Throwable $e) {
                $result = [
                    'status'  => 'critical_error',
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ];
            }
        }

        echo '<p><strong>PL i EN są pomijane.</strong> EN jest używany jako wzorzec.</p>';
        self::render_languages_overview();

        if ($result) {
            echo '<div class="notice notice-info"><p><strong>Wynik synchronizacji:</strong></p></div>';
            echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:600px;overflow:auto;">';
            echo esc_html(print_r($result, true));
            echo '</pre>';
        }

        echo '<form method="post">';
        wp_nonce_field('pwe_wpml_sync_pages', 'pwe_wpml_sync_nonce');
        self::render_event_year_field($event_year);
        submit_button('Utwórz brakujące tłumaczenia');
        echo '</form>';
    }

    private static function get_event_year_from_request(): int
    {
        $default_year = (int) gmdate('Y');

        if (!isset($_POST['pwe_multilang_event_year'])) {
            return $default_year;
        }

        $event_year = absint(wp_unslash($_POST['pwe_multilang_event_year']));

        if ($event_year < 2000 || $event_year > 2100) {
            return $default_year;
        }

        return $event_year;
    }

    private static function render_event_year_field(int $event_year): void
    {
        echo '
        <style>
        .pwe-multilang-field {
            margin: 20px 0;
            max-width: 760px;
        }

        .pwe-multilang-field__label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .pwe-multilang-field__control {
            display: flex;
            gap: 10px;
        }

        .pwe-multilang-field__control input.small-text {
            width: 60px;
            min-height: 30px;
        }

        .pwe-multilang-field__description {
            margin-top: 8px;
        }
        </style>';

        echo '<div class="pwe-multilang-field pwe-multilang-field--event-year">';
        echo '<label for="pwe_multilang_event_year" class="pwe-multilang-field__label">Rocznik formularzy</label>';

        echo '<div class="pwe-multilang-field__control">';
        echo '<input type="number" min="2000" max="2100" step="1" id="pwe_multilang_event_year" name="pwe_multilang_event_year" value="' . esc_attr((string) $event_year) . '" class="small-text">';

        echo '<p class="description pwe-multilang-field__description">';
        echo 'Ten rok zostanie użyty przy podmianie formularzy w shortcode’ach PWE, np. <code>(' . esc_html((string) $event_year) . ') Rejestracja Multilang</code>.';
        echo '</p>';

        echo '</div>';
        echo '</div>';
    }

    private static function render_languages_overview(): void
    {
        try {
            $pages = PWE_Multilang_Page_Json_Repository::get_pages();
        } catch (Throwable $e) {
            echo '<div class="notice notice-warning inline"><p>Nie można odczytać listy języków — ' . esc_html($e->getMessage()) . '</p></div>';
            return;
        }

        $skip_languages = PWE_Multilang_Page_Config::get_skipped_languages();
        $json_language_codes = PWE_Multilang_Page_Language_Helper::get_json_language_codes($pages);
        $active_languages = PWE_Multilang_Page_Language_Helper::get_active_languages();
        $available_for_sync = PWE_Multilang_Page_Language_Helper::get_allowed_language_codes($pages);

        echo '<div style="margin: 12px 0 18px;">';

        echo '<div style="padding: 14px 18px 16px; background: var(--color-background-primary, #fff); border: 0.5px solid #ccd0d4; border-radius: 8px; margin-bottom: 8px;">';
        echo '<p style="margin: 0 0 10px; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: #646970;">Języki dostępne w pliku JSON</p>';
        self::render_language_badges($json_language_codes, $active_languages, $skip_languages, false);
        echo '</div>';

        echo '<div style="padding: 14px 18px 16px; background: var(--color-background-primary, #fff); border: 0.5px solid #ccd0d4; border-radius: 8px;">';
        echo '<p style="margin: 0 0 10px; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: #646970;">Języki aktywne w WPML — zostaną użyte do tłumaczeń</p>';
        self::render_language_badges($available_for_sync, $active_languages, $skip_languages, true);
        echo '</div>';

        echo '</div>';
    }

    private static function render_language_badges(array $language_codes, array $active_languages, array $skip_languages = [], bool $sync_mode = false): void
    {
        if (empty($language_codes)) {
            echo '<p style="margin:0;font-size:13px;color:#646970;"><em>Brak.</em></p>';
            return;
        }

        echo '<div style="display:flex;flex-wrap:wrap;gap:6px;">';

        foreach ($language_codes as $lang_code) {
            $is_skipped = in_array($lang_code, $skip_languages, true);
            $is_active_wpml = isset($active_languages[$lang_code]);
            $label = PWE_Multilang_Page_Language_Helper::get_language_label($lang_code, $active_languages);
            $flag_url = PWE_Multilang_Page_Language_Helper::get_flag_url($lang_code, $active_languages);

            $flag = $flag_url
                ? '<img src="' . esc_url($flag_url) . '" alt="" style="width:16px;height:auto;border-radius:2px;flex-shrink:0;">'
                : '';

            if ($is_skipped) {
                $badge_style = 'border-color:#f0b849;background:#fef9ec;';
                $name_style  = 'color:#996800;';
            } elseif (!$is_active_wpml) {
                $badge_style = 'border-color:#ccd0d4;background:#f6f7f7;opacity:0.65;';
                $name_style  = 'color:#646970;';
            } elseif ($sync_mode) {
                $badge_style = 'border-color:#68de7c;background:#edfaef;';
                $name_style  = 'color:#0a6b24;';
            } else {
                $badge_style = 'border-color:#ccd0d4;background:#f6f7f7;';
                $name_style  = 'color:#50575e;';
            }

            echo '<span style="flex:1;max-width:120px;display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:6px;border:0.5px solid #ccd0d4;font-size:13px;line-height:1.4;' . esc_attr($badge_style) . '">';
            echo $flag;
            echo '<strong style="font-size:12px;font-weight:500;color:#1d2327;">' . esc_html(strtoupper((string) $lang_code)) . '</strong>';
            echo '<span style="width:1px;height:12px;background:#ccd0d4;margin:0 2px;align-self:center;"></span>';
            echo '<span style="' . esc_attr($name_style) . '">' . esc_html($label) . '</span>';
            echo '</span>';
        }

        echo '</div>';
    }
}
