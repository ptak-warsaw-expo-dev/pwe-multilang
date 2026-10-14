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

        if (
            isset($_POST['pwe_wpml_sync_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pwe_wpml_sync_nonce'])), 'pwe_wpml_sync_pages')
        ) {
            $result = self::run_sync();
        }

        PWE_Multilang_Admin_UI::notice('pwe-pages-notice', 'dashicons-yes-alt', 'PL i EN są pomijane. EN jest używany jako wzorzec.');
        self::render_languages_overview();

        if ($result) {
            echo '<div class="notice notice-info"><p><strong>Wynik synchronizacji:</strong></p></div>';
            echo '<pre class="pwe-debug-output">';
            echo esc_html(print_r($result, true));
            echo '</pre>';
        }

        echo '<form method="post" class="pwe-pages-form">';
        wp_nonce_field('pwe_wpml_sync_pages', 'pwe_wpml_sync_nonce');
        PWE_Multilang_Admin_UI::button(['type' => 'submit', 'name' => 'submit', 'id' => 'submit', 'class' => 'pwe-btn pwe-pages-submit'], '<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span> PWE Multilang Sync');
        echo '</form>';
    }

    private static function run_sync(): array
    {
        return PWE_Multilang_Sync_Orchestrator::reset_and_sync();
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
        $active_languages = PWE_Multilang_Language_Catalog::active_wpml_details();
        $available_for_sync = PWE_Multilang_Page_Language_Helper::get_allowed_language_codes($pages);

        echo '<div class="pwe-language-overview">';

        self::render_language_panel('Języki dostępne w pliku JSON', 'dashicons-media-text', $json_language_codes, $active_languages, $skip_languages, false);
        self::render_language_panel('Języki aktywne w WPML — zostaną użyte do tłumaczeń', 'dashicons-admin-site-alt3', $available_for_sync, $active_languages, $skip_languages, true);
        echo '</div>';
    }

    private static function render_language_panel(string $title, string $icon, array $language_codes, array $active_languages, array $skip_languages, bool $sync_mode): void
    {
        PWE_Multilang_Admin_UI::cardOpen('pwe-language-panel');
        echo '<h4 class="pwe-multilang-language-panel__title"><span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span>' . esc_html($title) . '</h4>';
        self::render_language_badges($language_codes, $active_languages, $skip_languages, $sync_mode);
        PWE_Multilang_Admin_UI::cardClose();
    }
    private static function render_language_badges(array $language_codes, array $active_languages, array $skip_languages = [], bool $sync_mode = false): void
    {
        if (empty($language_codes)) {
            echo '<p class="pwe-empty-text"><em>Brak.</em></p>';
            return;
        }

        $primary_languages = array_values(array_intersect(['pl', 'en'], $language_codes));
        $other_languages = array_values(array_diff($language_codes, ['pl', 'en']));
        $rows = $primary_languages ? [$primary_languages] : [];

        if ($other_languages) {
            $rows = array_merge($rows, array_chunk($other_languages, (int) ceil(count($other_languages) / 2)));
        }

        echo '<div class="pwe-language-badge-rows">';
        foreach ($rows as $row) {
            echo '<div class="pwe-language-badges">';

            foreach ($row as $lang_code) {
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

        echo '</div>';
    }

}
