<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/store.php';
final class PWE_Multilang_Notification_Translations
{
    public static function init(): void
    {
        add_action('wp_ajax_pwe_notification_translations_save', [self::class, 'save']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
    }

    public static function assets(): void
    {
        if (($_GET['page'] ?? '') !== 'pwe-multilang-notification-translations' || !PWE_Multilang_Admin_Access::is_allowed()) { return; }
        $base = 'modules/notification-translations/assets/';
        wp_enqueue_style('pwe-notification-translations', PWE_MULTILANG_URL . $base . 'editor.css', ['pwe-multilang-admin'], (string) filemtime(__DIR__ . '/assets/editor.css'));
        wp_enqueue_script('pwe-notification-translations', PWE_MULTILANG_URL . $base . 'editor.js', [], (string) filemtime(__DIR__ . '/assets/editor.js'), true);
    }

    public static function save(): void
    {
        if (!PWE_Multilang_Admin_Access::is_allowed() || !PWE_Multilang_Module_Registry::is_enabled('notification_translations')) {
            wp_send_json_error(['message' => 'Brak uprawnień.'], 403);
        }
        check_ajax_referer('pwe_notification_translations', 'nonce');
        try {
            $payload = json_decode(wp_unslash($_POST['payload'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) { throw new RuntimeException('Nieprawidłowe dane.'); }
            wp_send_json_success(PWE_Multilang_Notification_Translation_Store::save($payload));
        } catch (Throwable $error) {
            wp_send_json_error(['message' => $error->getMessage()], 400);
        }
    }

    public static function render_admin_page(): void
    {
        PWE_Multilang_Admin_UI::heroOpen('pwe-multilang-hero', 'notification_translations');
        PWE_Multilang_Admin_UI::cardTitle('Tłumaczenia powiadomień MJML');
        PWE_Multilang_Admin_UI::cardDesc('Biblioteka wspólnych zmiennych. Edycja wpisu zmienia wszystkie jego wystąpienia. Zapis dotyczy plików; istniejące formularze wymagają późniejszej synchronizacji w module Forms.');
        PWE_Multilang_Admin_UI::heroClose();
        try { $state = PWE_Multilang_Notification_Translation_Store::scan(); }
        catch (Throwable $error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error->getMessage()) . '</p></div>';
            return;
        }
        $state['nonce'] = wp_create_nonce('pwe_notification_translations');
        $state['url'] = admin_url('admin-ajax.php');
        ?>
        <div id="pwe-nt" class="pwe-multilang-card">
            <div class="pwe-nt-editor-header">
                <div><h3>Biblioteka tłumaczeń</h3><p>Edytuj wspólne zmienne i ich wersje językowe.</p></div>
                <div class="pwe-nt-header-actions">
                    <button type="button" class="button" id="pwe-nt-export">Eksportuj całą bibliotekę</button>
                    <button type="button" class="pwe-btn" id="pwe-nt-save">Zapisz zmiany</button>
                </div>
            </div>
            <p id="pwe-nt-status" role="status" aria-live="polite"></p>
            <div class="pwe-nt-management">
                <details class="pwe-nt-tool-panel">
                    <summary><span>Porządkuj zmienne</span><span class="pwe-nt-tool-caption">Konflikty nazw</span></summary>
                    <div class="pwe-nt-tool-body">
                        <p>Nadaj różnym treściom osobne nazwy.</p>
                        <div class="pwe-nt-cleanup-actions">
                            <button type="button" class="button" id="pwe-nt-separate">Nadaj osobne nazwy konfliktom</button>
                        </div>
                    </div>
                </details>
            </div>
            <div class="pwe-nt-table-topline">
                <div id="pwe-nt-summary" aria-live="polite"></div>
                <span class="pwe-nt-table-hint">Wybierz powiadomienie, aby dodać zmienną w ostatnim wierszu</span>
            </div>
            <div class="pwe-nt-table-section">
                <div class="pwe-nt-toolbar pwe-nt-filters">
                    <label>Powiadomienie <select id="pwe-nt-notification"><option value="">Cała biblioteka</option></select></label>
                    <label class="pwe-nt-search-field">Szukaj zmiennej lub treści <input id="pwe-nt-search" type="search" placeholder="np. Dziękujemy za reje…"></label>
                    <label class="pwe-nt-conflict-filter"><input id="pwe-nt-conflicts" type="checkbox"> Tylko konflikty nazw</label>
                </div>
                <div class="pwe-nt-table-container"><table id="pwe-nt-table" aria-label="Biblioteka tłumaczeń powiadomień"></table></div>
            </div>
            <p class="pwe-nt-footer-hint">Zmiany wspólnej zmiennej obejmują wszystkie jej wystąpienia. Nową linię wpisz jako <code>\n</code>. Zmienna <code>{{lang}}</code> jest ustawiana automatycznie.</p>
        </div>
        <script type="application/json" id="pwe-nt-data"><?php echo wp_json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <?php
    }
}
