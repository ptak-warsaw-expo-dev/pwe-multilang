<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Admin_Test {

    public static function render() : void {

        if (!current_user_can('manage_options')) {
            return;
        }

        $message = '';

        if (
            isset($_POST['pwe_multilang_generate_test_entries']) &&
            check_admin_referer('pwe_multilang_generate_test_entries_action')
        ) {
            $form_id = isset($_POST['pwe_test_form_id']) ? absint($_POST['pwe_test_form_id']) : 0;

            if ($form_id > 0) {
                $message = self::generate_test_entries($form_id);
            } else {
                $message = 'Nie podano ID formularza.';
            }
        }

        ?>
        <div class="wrap">
            <h1>PWE Multilang — Test</h1>

            <?php if (!empty($message)) : ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('pwe_multilang_generate_test_entries_action'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pwe_test_form_id">ID formularza GF</label>
                        </th>
                        <td>
                            <input
                                type="number"
                                name="pwe_test_form_id"
                                id="pwe_test_form_id"
                                value="70"
                                min="1"
                                required
                            >
                            <p class="description">
                                Podaj ID formularza Multilang, do którego mają zostać dodane wpisy testowe.
                            </p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button
                        type="submit"
                        name="pwe_multilang_generate_test_entries"
                        value="1"
                        class="button button-primary"
                    >
                        Generuj wpisy testowe
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    private static function generate_test_entries(int $form_id) : string {

        if (!class_exists('GFAPI')) {
            return 'Gravity Forms API niedostępne.';
        }

        $form = GFAPI::get_form($form_id);

        if (is_wp_error($form) || empty($form['fields'])) {
            return 'Nie znaleziono formularza lub formularz nie ma pól.';
        }

        $langs = apply_filters('wpml_active_languages', null, [
            'skip_missing' => 0,
        ]);

        if (empty($langs) || !is_array($langs)) {
            $langs = [
                'pl' => [],
                'en' => [],
                'de' => [],
                'cs' => [],
            ];
        }

        $field_ids = [];

        foreach ($form['fields'] as $field) {
            $admin_label = $field->adminLabel ?? '';
            $label       = $field->label ?? '';

            if (!empty($admin_label)) {
                $field_ids[$admin_label] = (string) $field->id;
            }

            if (!empty($label)) {
                $field_ids[$label] = (string) $field->id;
            }
        }

        $created = 0;
        $errors = 0;

        foreach ($langs as $lang_code => $lang_data) {

            $entry = [
                'form_id' => $form_id,
            ];

            self::set_entry_value($entry, $field_ids, ['pwe_lang', 'lang', 'language'], $lang_code);
            self::set_entry_value($entry, $field_ids, ['pwe_email', 'email'], 'test_' . $lang_code . '@example.com');
            self::set_entry_value($entry, $field_ids, ['pwe_name', 'name'], 'Test ' . strtoupper($lang_code));
            self::set_entry_value($entry, $field_ids, ['pwe_phone', 'phone'], '+48123123123');
            self::set_entry_value($entry, $field_ids, ['pwe_company', 'company'], 'Firma testowa ' . strtoupper($lang_code));
            self::set_entry_value($entry, $field_ids, ['pwe_country', 'country'], strtoupper($lang_code));
            self::set_entry_value($entry, $field_ids, ['pwe_utm', 'utm'], 'utm_source=test-' . $lang_code);

            $result = GFAPI::add_entry($entry);

            if (is_wp_error($result)) {
                $errors++;
            } else {
                $created++;
            }
        }

        return 'Utworzono wpisów testowych: ' . $created . '. Błędy: ' . $errors . '.';
    }

    private static function set_entry_value(array &$entry, array $field_ids, array $keys, string $value) : void {

        foreach ($keys as $key) {
            if (!empty($field_ids[$key])) {
                $entry[$field_ids[$key]] = $value;
                return;
            }
        }
    }
}