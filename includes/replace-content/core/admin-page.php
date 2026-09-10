<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Replace_Content_Admin_Page
{
    private const NONCE_NAME = 'pwe_replace_content_nonce';

    public static function render(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        $map = PWE_Multilang_Replace_Content_Config::get_map();
        $result = null;

        // Zwykły POST pozostaje jako fallback, gdy JavaScript jest wyłączony.
        if (self::is_submit()) {
            check_admin_referer(
                PWE_Multilang_Replace_Content_Ajax::NONCE_ACTION,
                self::NONCE_NAME
            );
            $result = PWE_Multilang_Replace_Content_Service::execute(
                PWE_Multilang_Replace_Content_Service::build_plan($map)
            );
        }

        $plan = PWE_Multilang_Replace_Content_Service::build_plan($map);
        $active_languages = class_exists('PWE_Multilang_Page_Language_Helper')
            ? PWE_Multilang_Page_Language_Helper::get_active_languages()
            : [];

        echo '<div id="pwe-replace-result" aria-live="polite">';
        self::render_result($result);
        echo '</div>';

        if (empty($map)) {
            PWE_Multilang_Admin_UI::notice(
                'pwe-notice-warning',
                'dashicons-warning',
                'Mapa podmian jest pusta. Dodaj reguły w pliku <code>includes/replace-content/replace-content-map.php</code>.'
            );
            return;
        }

        $summary = self::get_summary($plan);

        echo '<div class="pwe-replace-summary">';
        echo '<span><strong>' . esc_html((string) count($plan)) . '</strong> reguł</span>';
        echo '<span><strong>' . esc_html((string) $summary['pages']) . '</strong> wersji językowych</span>';
        echo '<span><strong id="pwe-replace-change-count">' . esc_html((string) $summary['changes']) . '</strong> stron wymaga zmiany</span>';
        echo '</div>';

        echo '<div class="pwe-replace-list">';

        foreach ($plan as $item) {
            self::render_plan_item($item, $active_languages);
        }

        echo '</div>';
        echo '<hr class="pwe-divider">';

        self::render_progress();

        echo '<form method="post" id="pwe-replace-content-form" class="pwe-replace-action"';
        echo ' data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '"';
        echo ' data-start-action="' . esc_attr(PWE_Multilang_Replace_Content_Ajax::START_ACTION) . '"';
        echo ' data-step-action="' . esc_attr(PWE_Multilang_Replace_Content_Ajax::STEP_ACTION) . '"';
        echo ' data-confirm="' . esc_attr('Czy na pewno chcesz zastąpić treść wskazanych stron?') . '">';
        wp_nonce_field(PWE_Multilang_Replace_Content_Ajax::NONCE_ACTION, self::NONCE_NAME);
        echo '<input type="hidden" name="pwe_replace_content_submit" value="1">';

        $button_attributes = [
            'type' => 'submit',
            'class' => 'pwe-btn',
            'id' => 'pwe-replace-content-submit',
        ];

        if ($summary['pages'] === 0) {
            $button_attributes['disabled'] = 'disabled';
        }

        PWE_Multilang_Admin_UI::button(
            $button_attributes,
            '<span class="dashicons dashicons-update"></span><span class="pwe-replace-button-label">Zastąp treść stron</span>'
        );
        echo '<p class="pwe-hint">Treść zostanie zastąpiona dokładnie wskazanym shortcode’em. Header zostanie ustawiony na <code>none</code>, a Show title na <code>off</code>.</p>';
        echo '</form>';
    }

    private static function is_submit(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            && !empty($_POST['pwe_replace_content_submit']);
    }

    /**
     * @param array<string, mixed>|null $result
     */
    private static function render_result(?array $result): void
    {
        if ($result === null) {
            return;
        }

        $updated = (int) ($result['updated'] ?? 0);
        $unchanged = (int) ($result['unchanged'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        $notice_class = $failed > 0 ? 'pwe-notice-error' : 'pwe-notice-success pwe-toast-success';
        $icon = $failed > 0 ? 'dashicons-warning' : 'dashicons-yes-alt';
        $message = sprintf(
            'Operacja zakończona. Zmieniono: <strong>%d</strong>, bez zmian: <strong>%d</strong>, błędy: <strong>%d</strong>.',
            $updated,
            $unchanged,
            $failed
        );

        if (!empty($result['errors']) && is_array($result['errors'])) {
            $message .= '<ul>';

            foreach ($result['errors'] as $error) {
                $message .= '<li>' . esc_html((string) $error) . '</li>';
            }

            $message .= '</ul>';
        }

        PWE_Multilang_Admin_UI::notice($notice_class, $icon, $message);
    }

    private static function render_progress(): void
    {
        echo '<div id="pwe-replace-progress" class="pwe-replace-progress" hidden aria-live="polite">';
        echo '<div class="pwe-replace-progress__header">';
        echo '<strong id="pwe-replace-progress-title">Przygotowywanie operacji…</strong>';
        echo '<span id="pwe-replace-progress-percent">0%</span>';
        echo '</div>';
        echo '<div id="pwe-replace-progress-track" class="pwe-replace-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">';
        echo '<span id="pwe-replace-progress-bar" class="pwe-replace-progress__bar" style="width:0%"></span>';
        echo '</div>';
        echo '<p id="pwe-replace-progress-detail" class="pwe-replace-progress__detail">Łączenie z WordPressem…</p>';
        echo '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     * @return array{pages: int, changes: int}
     */
    private static function get_summary(array $plan): array
    {
        $pages = 0;
        $changes = 0;
        $seen_pages = [];

        foreach ($plan as $item) {
            foreach ((array) ($item['translations'] ?? []) as $translation) {
                $post_id = (int) ($translation['id'] ?? 0);

                if ($post_id <= 0 || isset($seen_pages[$post_id])) {
                    continue;
                }

                $seen_pages[$post_id] = true;
                $pages++;

                if (!empty($translation['will_change'])) {
                    $changes++;
                }
            }
        }

        return compact('pages', 'changes');
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $active_languages
     */
    private static function render_plan_item(array $item, array $active_languages): void
    {
        $translations = (array) ($item['translations'] ?? []);
        $has_error = !empty($item['error']);

        echo '<details class="pwe-replace-item' . ($has_error ? ' has-error' : '') . '"' . ($has_error ? ' open' : '') . '>';
        echo '<summary class="pwe-replace-item__header">';
        echo '<div>';
        echo '<span class="pwe-replace-item__label">Adres z mapy</span>';
        echo '<code>' . esc_html((string) ($item['url'] ?? '')) . '</code>';
        echo '</div>';
        echo '<span class="pwe-replace-arrow dashicons dashicons-arrow-right-alt"></span>';
        echo '<div>';
        echo '<span class="pwe-replace-item__label">Docelowa treść</span>';
        echo '<code>' . esc_html((string) ($item['shortcode'] ?? '')) . '</code>';
        echo '</div>';
        echo '<span class="pwe-replace-count">' . esc_html((string) count($translations)) . ' wersji</span>';
        echo '<span class="pwe-replace-chevron dashicons dashicons-arrow-down-alt2"></span>';
        echo '</summary>';

        if ($has_error) {
            echo '<div class="pwe-replace-missing"><span class="dashicons dashicons-warning"></span>';
            echo esc_html((string) $item['error']);
            echo '</div>';
            echo '</details>';
            return;
        }

        echo '<div class="pwe-replace-translations">';

        foreach ($translations as $translation) {
            self::render_translation($translation, $active_languages);
        }

        echo '</div>';
        echo '</details>';
    }

    /**
     * @param array<string, mixed> $translation
     * @param array<string, mixed> $active_languages
     */
    private static function render_translation(array $translation, array $active_languages): void
    {
        $will_change = !empty($translation['will_change']);
        $edit_url = (string) ($translation['edit_url'] ?? '');
        $permalink = (string) ($translation['permalink'] ?? '');
        $language_code = strtolower((string) ($translation['language'] ?? ''));
        $has_valid_language = (bool) preg_match('/^[a-z0-9_-]+$/', $language_code);
        $language_label = $has_valid_language && class_exists('PWE_Multilang_Page_Language_Helper')
            ? PWE_Multilang_Page_Language_Helper::get_language_label($language_code, $active_languages)
            : '';
        $flag_url = $has_valid_language && class_exists('PWE_Multilang_Page_Language_Helper')
            ? PWE_Multilang_Page_Language_Helper::get_flag_url($language_code, $active_languages)
            : '';
        $change_labels = [];

        if (!empty($translation['needs_content'])) {
            $change_labels[] = 'treść';
        }

        if (!empty($translation['needs_header'])) {
            $change_labels[] = 'header';
        }

        if (!empty($translation['needs_title'])) {
            $change_labels[] = 'tytuł';
        }

        $status_label = $will_change
            ? 'Do zmiany: ' . implode(' + ', $change_labels)
            : 'Zgodna';

        echo '<div class="pwe-replace-translation" data-post-id="' . esc_attr((string) ($translation['id'] ?? 0)) . '">';
        echo '<span class="pwe-replace-lang" title="' . esc_attr($language_label) . '">';

        if ($flag_url !== '') {
            echo '<img src="' . esc_url($flag_url) . '" alt="" class="pwe-language-badge__flag">';
        }

        echo '<strong>' . esc_html($has_valid_language ? strtoupper($language_code) : '—') . '</strong>';
        echo '</span>';
        echo '<div class="pwe-replace-page">';
        echo '<strong>' . esc_html((string) ($translation['title'] ?? '')) . '</strong>';
        echo '<span>ID ' . esc_html((string) ($translation['id'] ?? 0));
        echo ' · ' . esc_html((string) ($translation['post_status'] ?? '')) . '</span>';
        echo '</div>';
        echo '<div class="pwe-replace-url">';

        if ($permalink !== '') {
            echo '<a href="' . esc_url($permalink) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr($permalink) . '">Podgląd</a>';
        }

        echo '</div>';
        echo '<div class="pwe-replace-links">';

        if ($edit_url !== '') {
            echo '<a href="' . esc_url($edit_url) . '">Edytuj</a>';
        }

        echo '</div>';
        echo '<span class="pwe-replace-status ' . ($will_change ? 'is-pending' : 'is-ready') . '">';
        echo '<span class="dashicons ' . ($will_change ? 'dashicons-edit' : 'dashicons-yes-alt') . '"></span>';
        echo '<span class="pwe-replace-status-label">' . esc_html($status_label) . '</span>';
        echo '</span>';
        echo '</div>';
    }
}
