<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Renderer
{
    public static function render_sync_panel(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        $result = null;
        $event_year = self::get_event_year_from_request();

        if (
            isset($_POST['pwe_wpml_sync_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pwe_wpml_sync_nonce'])), 'pwe_wpml_sync_pages')
        ) {
            $result = self::run_sync($event_year);
        }

        echo '<p><strong>PL i EN są pomijane.</strong> EN jest używany jako wzorzec.</p>';
        self::render_languages_overview();

        if ($result) {
            echo '<div class="notice notice-info"><p><strong>Wynik synchronizacji:</strong></p></div>';
            echo '<pre class="pwe-debug-output">';
            echo esc_html(print_r($result, true));
            echo '</pre>';
        }

        echo '<form method="post">';
        wp_nonce_field('pwe_wpml_sync_pages', 'pwe_wpml_sync_nonce');
        self::render_event_year_field($event_year);
        submit_button('Utwórz brakujące tłumaczenia');
        echo '</form>';
    }

    private static function run_sync(int $event_year): array
    {
        $uses_lock = class_exists('PWE_Multilang_Form_Operation_Lock');
        $token = $uses_lock
            ? PWE_Multilang_Form_Operation_Lock::acquire('page_sync_request')
            : '';

        if ($uses_lock && $token === null) {
            return [
                'status' => 'locked',
                'message' => 'Inna operacja generowania stron lub formularzy jest w toku.',
                'errors' => ['Nie uruchomiono równoległej operacji generowania.'],
            ];
        }

        try {
            $result = PWE_Multilang_Page_Creator::create_missing_wpml_pages_from_json($event_year);

            try {
                PWE_Multilang_Year_Resolver::save($event_year);

                $form_templates = array_values(array_unique((array) ($result['form_templates'] ?? [])));

                if ($form_templates && class_exists('GFAPI') && class_exists('PWE_Multilang_Form_Core')) {
                    $forms_sync_result = PWE_Multilang_Form_Core::sync(
                        scope: 'forms',
                        templateSlugs: $form_templates,
                        formsYear: $event_year,
                        addOnly: false,
                        overwriteModified: [],
                        allowCreate: false
                    );

                    $result['forms_sync_result'] = $forms_sync_result;
                    $updated_forms = 0;

                    foreach ((array) ($forms_sync_result['items'] ?? []) as $item) {
                        if (is_array($item) && !empty($item['updated'])) {
                            $updated_forms++;
                        }
                    }

                    $forms_status = (string) ($forms_sync_result['status'] ?? 'failed');
                    $result['forms_resynced'] = $updated_forms > 0;

                    if ($updated_forms > 0 && $forms_status === 'partial') {
                        $result['forms_resynced_message'] = 'Zaktualizowano część istniejących formularzy; sprawdź szczegóły synchronizacji.';
                    } elseif ($updated_forms > 0) {
                        $result['forms_resynced_message'] = 'Zaktualizowano tylko istniejące formularze użyte na utworzonych stronach.';
                    } elseif ($forms_status === 'locked') {
                        $result['forms_resynced_message'] = 'Pominięto aktualizację formularzy — inna operacja jest w toku.';
                    } else {
                        $result['forms_resynced_message'] = 'Nie zaktualizowano formularzy; sprawdź wynik synchronizacji.';
                    }
                } elseif (!$form_templates) {
                    $result['forms_resynced'] = false;
                    $result['forms_resynced_message'] = 'Nie znaleziono formularzy do synchronizacji na utworzonych stronach.';
                } else {
                    $result['forms_resynced'] = false;
                    $result['forms_resynced_message'] = 'Pominięto aktualizację formularzy — brak GFAPI albo PWE_Multilang_Form_Core.';
                }
            } catch (Throwable $forms_error) {
                $result['forms_resynced'] = false;
                $result['forms_resynced_message'] = 'Pominięto aktualizację formularzy — ' . $forms_error->getMessage();
                $result['errors'] = array_values(array_merge(
                    (array) ($result['errors'] ?? []),
                    ['Synchronizacja formularzy — ' . $forms_error->getMessage()]
                ));
            }

            return $result;
        } catch (Throwable $e) {
            return [
                'status'  => 'critical_error',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];
        } finally {
            if ($uses_lock && is_string($token)) {
                PWE_Multilang_Form_Operation_Lock::release($token);
            }
        }
    }

    private static function get_event_year_from_request(): int
    {
        if (!isset($_POST['pwe_multilang_event_year'])) {
            return PWE_Multilang_Year_Resolver::configured();
        }

        return PWE_Multilang_Year_Resolver::normalise(
            wp_unslash($_POST['pwe_multilang_event_year'])
        );
    }

    private static function render_event_year_field(int $event_year): void
    {
        echo '<div class="pwe-multilang-field pwe-multilang-field--event-year">';
        echo '<label for="pwe_multilang_event_year" class="pwe-multilang-field__label">Rocznik formularzy</label>';

        echo '<div class="pwe-multilang-field__control">';
        echo '<input type="number" min="2000" max="2100" step="1" id="pwe_multilang_event_year" name="pwe_multilang_event_year" value="' . esc_attr((string) $event_year) . '" class="small-text">';

        echo '<p class="description pwe-multilang-field__description">';
        echo 'Ten rok zostanie użyty przy pobieraniu nazw formularzy z template’ów i podmianie ich w shortcode’ach PWE.';
        echo '</p>';

        echo '</div>';
        echo '</div>';
    }

    private static function render_languages_overview(): void
    {
        try {
            $all_pages = PWE_Multilang_Page_Json_Repository::get_all_pages();
            $pages = PWE_Multilang_Page_Json_Repository::get_pages_for_current_group();
        } catch (Throwable $e) {
            echo '<div class="notice notice-warning inline"><p>Nie można odczytać listy języków — ' . esc_html($e->getMessage()) . '</p></div>';
            return;
        }

        $skip_languages = PWE_Multilang_Page_Config::get_skipped_languages();
        $json_language_codes = PWE_Multilang_Page_Language_Helper::get_json_language_codes($all_pages);
        $active_languages = PWE_Multilang_Page_Language_Helper::get_active_languages();
        $available_for_sync = PWE_Multilang_Page_Language_Helper::get_allowed_language_codes($pages);

        echo '<div class="pwe-language-overview">';

        echo '<div class="pwe-language-panel">';
        echo '<p class="pwe-language-panel__title">Języki dostępne w pliku JSON</p>';
        self::render_language_badges($json_language_codes, $active_languages, $skip_languages, false);
        echo '</div>';

        echo '<div class="pwe-language-panel">';
        echo '<p class="pwe-language-panel__title">Języki aktywne w WPML — zostaną użyte do tłumaczeń</p>';
        self::render_language_badges($available_for_sync, $active_languages, $skip_languages, true);
        echo '</div>';

        echo '</div>';
    }

    private static function render_language_badges(array $language_codes, array $active_languages, array $skip_languages = [], bool $sync_mode = false): void
    {
        if (empty($language_codes)) {
            echo '<p class="pwe-empty-text"><em>Brak.</em></p>';
            return;
        }

        echo '<div class="pwe-language-badges">';

        foreach ($language_codes as $lang_code) {
            $is_skipped = in_array($lang_code, $skip_languages, true);
            $is_active_wpml = isset($active_languages[$lang_code]);
            $label = PWE_Multilang_Page_Language_Helper::get_language_label($lang_code, $active_languages);
            $flag_url = PWE_Multilang_Page_Language_Helper::get_flag_url($lang_code, $active_languages);

            $flag = $flag_url
                ? '<img src="' . esc_url($flag_url) . '" alt="" class="pwe-language-badge__flag">'
                : '';

            if ($is_skipped) {
                $badge_class = 'is-skipped';
                $name_class  = 'is-skipped';
            } elseif (!$is_active_wpml) {
                $badge_class = 'is-inactive';
                $name_class  = 'is-inactive';
            } elseif ($sync_mode) {
                $badge_class = 'is-sync';
                $name_class  = 'is-sync';
            } else {
                $badge_class = '';
                $name_class  = '';
            }

            echo '<span class="pwe-language-badge ' . esc_attr($badge_class) . '">';
            echo $flag;
            echo '<strong class="pwe-language-badge__code">' . esc_html(strtoupper((string) $lang_code)) . '</strong>';
            echo '<span class="pwe-language-badge__divider"></span>';
            echo '<span class="pwe-language-badge__name ' . esc_attr($name_class) . '">' . esc_html($label) . '</span>';
            echo '</span>';
        }

        echo '</div>';
    }

}
