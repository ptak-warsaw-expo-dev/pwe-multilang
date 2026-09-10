<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Tests_Entries
{
    public const META_TEST_ENTRY = '_pwe_multilang_test_entry';

    public static function create_entries(int $form_id, int $count): array
    {
        if (!class_exists('GFAPI')) {
            return [
                'created' => 0,
                'errors'  => 1,
                'message' => 'Gravity Forms API niedostępne.',
            ];
        }

        $form = PWE_Multilang_Tests_Gravity::get_managed_form($form_id);

        if ($form === null || empty($form['fields'])) {
            return [
                'created' => 0,
                'errors'  => 1,
                'message' => 'Nie znaleziono formularza lub formularz nie ma pól.',
            ];
        }

        $count = max(1, min($count, PWE_Multilang_Tests::MAX_ENTRIES));
        $langs = PWE_Multilang_Tests_Gravity::get_active_langs();

        if (!$langs) {
            return [
                'created' => 0,
                'errors'  => 1,
                'message' => 'Brak języków dostępnych do utworzenia wpisów testowych.',
            ];
        }

        $created = 0;
        $errors  = 0;

        for ($i = 1; $i <= $count; $i++) {
            $lang = $langs[($i - 1) % count($langs)];
            $entry = self::build_entry($form, $i, $lang);

            $result = GFAPI::add_entry($entry);

            if (is_wp_error($result)) {
                $errors++;
            } else {
                gform_update_meta((int) $result, self::META_TEST_ENTRY, current_time('mysql'));
                $created++;
            }
        }

        return [
            'created' => $created,
            'errors'  => $errors,
            'message' => 'Dodano testowe pozycje: ' . $created . '. Błędy: ' . $errors . '.',
        ];
    }

    public static function build_entry(array $form, int $index, string $lang): array
    {
        $entry = [
            'form_id'    => absint($form['id']),
            'status'     => 'active',
            'source_url' => home_url('/pwe-multilang-test/?lang=' . rawurlencode($lang)),
        ];

        foreach (($form['fields'] ?? []) as $field) {
            if (empty($field->id)) {
                continue;
            }

            $field_id    = (string) $field->id;
            $admin_label = strtolower((string) ($field->adminLabel ?? ''));
            $type        = (string) ($field->type ?? '');

            if (PWE_Multilang_Tests_Gravity::is_lang_field($admin_label, $field)) {
                $entry[$field_id] = $lang;
                continue;
            }

            if ($admin_label === 'email' || $admin_label === 'pwe_email' || $type === 'email') {
                $email = 'pwe-test+' . $index . '-' . $lang . '@example.com';
                $entry[$field_id] = (string) apply_filters(
                    'pwe_multilang_test_entry_email',
                    $email,
                    $index,
                    $lang,
                    $form
                );
                continue;
            }

            if ($admin_label === 'phone' || $admin_label === 'pwe_phone' || $type === 'phone') {
                $entry[$field_id] = '+48123123' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);
                continue;
            }

            if ($admin_label === 'patron') {
                $entry[$field_id] = PWE_Multilang_Tests_Gravity::get_choice_value($field, $index, ['gr2', 'gr1', 'patron', 'media']);
                continue;
            }

            if ($admin_label === 'location') {
                $entry[$field_id] = PWE_Multilang_Tests_Gravity::get_choice_value($field, $index, ['test', 'default', 'warszawa', 'platyna']);
                continue;
            }

            if ($type === 'name') {
                $entry[$field_id . '.3'] = 'Test';
                $entry[$field_id . '.6'] = 'User ' . $index;
                continue;
            }

            if ($type === 'address') {
                $entry[$field_id . '.1'] = 'Testowa 1';
                $entry[$field_id . '.3'] = 'Warszawa';
                $entry[$field_id . '.4'] = 'Mazowieckie';
                $entry[$field_id . '.5'] = '00-001';
                $entry[$field_id . '.6'] = 'Polska';
                continue;
            }

            if ($type === 'checkbox') {
                self::fill_checkbox_field($entry, $field, $index);
                continue;
            }

            if (in_array($type, ['select', 'radio', 'multiselect'], true)) {
                $entry[$field_id] = PWE_Multilang_Tests_Gravity::get_choice_value($field, $index);
                continue;
            }

            if ($type === 'number') {
                $entry[$field_id] = (string) $index;
                continue;
            }

            if ($type === 'date') {
                $entry[$field_id] = wp_date('Y-m-d');
                continue;
            }

            if ($type === 'time') {
                $entry[$field_id] = '12:00';
                continue;
            }

            if ($type === 'website') {
                $entry[$field_id] = home_url('/');
                continue;
            }

            if ($type === 'textarea') {
                $entry[$field_id] = 'Testowa treść wpisu #' . $index . ' / lang: ' . $lang;
                continue;
            }

            if (in_array($type, ['text', 'hidden'], true)) {
                $entry[$field_id] = self::guess_text_value($admin_label, $index, $lang);
                continue;
            }

            if (!empty($field->choices)) {
                $entry[$field_id] = PWE_Multilang_Tests_Gravity::get_choice_value($field, $index);
            }
        }

        return $entry;
    }

    private static function guess_text_value(string $admin_label, int $index, string $lang): string
    {
        if (str_contains($admin_label, 'company') || str_contains($admin_label, 'firma')) {
            return 'Test Company ' . $index;
        }

        if (str_contains($admin_label, 'name') || str_contains($admin_label, 'imie') || str_contains($admin_label, 'imię')) {
            return 'Test User ' . $index;
        }

        if (str_contains($admin_label, 'country')) {
            return strtoupper($lang);
        }

        if (str_contains($admin_label, 'nip')) {
            return '525000' . str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        }

        if (str_contains($admin_label, 'utm')) {
            return 'utm_source=test-' . $lang;
        }

        if (str_contains($admin_label, 'lang') || str_contains($admin_label, 'language')) {
            return $lang;
        }

        return 'Test ' . $index;
    }

    private static function fill_checkbox_field(array &$entry, object $field, int $index): void
    {
        $choices = $field->choices ?? [];

        if (empty($choices) || !is_array($choices)) {
            return;
        }

        $field_id = (string) $field->id;
        $choice   = $choices[($index - 1) % count($choices)];
        $input_id = null;

        if (!empty($field->inputs) && is_array($field->inputs)) {
            $input = $field->inputs[($index - 1) % count($field->inputs)];
            $input_id = (string) ($input['id'] ?? '');
        }

        if (!$input_id) {
            $input_id = $field_id . '.1';
        }

        $entry[$input_id] = (string) ($choice['value'] ?? $choice['text'] ?? 'test');
    }

    public static function build_entry_for_notification(array $form, string $email, array $notification = []): ?array
    {
        if (!class_exists('GFAPI')) {
            return null;
        }

        $lang  = self::resolve_notification_lang($form, $notification);

        $entry_data = self::build_entry($form, time(), $lang);

        foreach (($form['fields'] ?? []) as $field) {
            $admin_label = strtolower((string) ($field->adminLabel ?? ''));
            $type        = (string) ($field->type ?? '');

            if ($admin_label === 'email' || $admin_label === 'pwe_email' || $type === 'email') {
                $entry_data[(string) $field->id] = $email;
            }
        }

        $entry_id = GFAPI::add_entry($entry_data);

        if (is_wp_error($entry_id)) {
            return null;
        }

        gform_update_meta((int) $entry_id, self::META_TEST_ENTRY, current_time('mysql'));

        $entry = GFAPI::get_entry($entry_id);

        if (is_wp_error($entry)) {
            GFAPI::delete_entry((int) $entry_id);
            return null;
        }

        return $entry;
    }

    private static function resolve_notification_lang(array $form, array $notification): string
    {
        $detected = PWE_Multilang_Tests_Notifications::detect_notification_lang($form, $notification);

        if ($detected !== '') {
            return strtolower($detected);
        }

        $langs = PWE_Multilang_Tests_Gravity::get_active_langs();

        return strtolower((string) ($langs[0] ?? 'en'));
    }
}
