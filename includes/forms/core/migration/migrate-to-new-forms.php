<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Migrate_To_New_Forms
{
    private const ACTION_FIELD = 'pwe_mlg_do_migrate_to_new_forms';
    private const NONCE_FIELD = 'pwe_mlg_forms_nonce';
    private const NONCE_ACTION = 'pwe_mlg_generate_forms_action';
    private const OLD_SUFFIX = ' - OLD';
    private static array $archiveJournal = [];

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'handle']);
        add_action('pwe_mlg_forms_after_template_list', [self::class, 'renderAction']);
    }

    public static function renderAction(bool $gravityReady): void
    {
        ?>
        <div class="pwe-form-migration-action">
            <div class="pwe-form-migration-copy">
                <strong>Migracja do nowych formularzy</strong>
                <span>Formularze z listy legacy dostana suffix <code>- OLD</code> i zostana dezaktywowane, a potem zaznaczone template'y zostana wgrane jako nowy pakiet formularzy.</span>
            </div>
            <?php
            $attributes = [
                'type' => 'submit',
                'name' => self::ACTION_FIELD,
                'value' => '1',
                'class' => 'pwe-btn pwe-form-migration-button',
                'onclick' => "return confirm('Migracja zmieni tytuly zaznaczonych starych formularzy na - OLD i utworzy nowe formularze z template. Kontynuowac?');",
            ];

            if (!$gravityReady) {
                $attributes['disabled'] = 'disabled';
            }

            PWE_Multilang_Admin_UI::button(
                $attributes,
                '<span class="dashicons dashicons-migrate"></span><span>Migruj do nowych formularzy</span>'
            );
            ?>
        </div>
        <?php
    }

    public static function handle(): void
    {
        if (empty($_POST[self::ACTION_FIELD]) || !PWE_Multilang_Admin_Access::is_allowed()) {
            return;
        }

        if (
            empty($_POST[self::NONCE_FIELD]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            return;
        }

        $year = self::getYear();
        self::saveYear($year);

        if (!class_exists('GFAPI')) {
            self::finish([
                'scope' => 'migration_to_new_forms',
                'add_only' => false,
                'items' => [],
                'errors' => ['Gravity Forms / GFAPI nie jest dostepne.'],
            ]);
        }

        $selected = self::getSelectedTemplates();
        $availableTemplates = array_keys(PWE_Multilang_Form_Core::getTemplates($year));
        $selected = array_values(array_intersect($selected, $availableTemplates));
        $token = PWE_Multilang_Form_Operation_Lock::acquire('migration_to_new_forms');

        if ($token === null) {
            self::finish(PWE_Multilang_Form_Sync_Result::locked('migration_to_new_forms', false));
        }

        try {
            $migrationResult = self::archiveLegacyForms($selected);
            self::resetFormFinderCache();

            $syncResult = PWE_Multilang_Form_Core::sync(
                'forms',
                $selected,
                $year,
                false,
                $selected
            );

            $rolledBack = self::shouldRollbackArchive($syncResult);

            if ($rolledBack) {
                $migrationResult = self::mergeResults($migrationResult, self::rollbackArchive());
                self::resetFormFinderCache();
            }

            if (!$rolledBack && class_exists('PWE_Multilang_Form_External_Patches')) {
                $patchResult = PWE_Multilang_Form_External_Patches::applyRegistered(false, 'migration_to_new_forms');
                $syncResult = self::mergeResults($syncResult, $patchResult);
            }

            $result = self::mergeResults($migrationResult, $syncResult);
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }

        self::finish($result);
    }

    private static function archiveLegacyForms(array $selected): array
    {
        self::$archiveJournal = [];
        $result = [
            'scope' => 'migration_to_new_forms',
            'add_only' => false,
            'items' => [],
            'errors' => [],
        ];

        if (empty($selected)) {
            $result['errors'][] = 'Nie wybrano zadnego templateu do migracji.';
            return $result;
        }

        $result['items'][] = self::archiveConfiguredLegacyForms($selected);

        return $result;
    }

    private static function archiveConfiguredLegacyForms(array $selected): array
    {
        $item = [
            'template' => 'migration-to-new-forms',
            'title' => 'Legacy forms archive',
            'form_id' => 0,
            'action' => 'migration_archive_old',
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
            'details' => [],
            'diff' => [],
        ];

        $forms = self::findConfiguredLegacyForms($selected);

        if (empty($forms)) {
            $item['skipped'][] = 'archive:legacy-list';
            $item['details'][] = 'nie znaleziono formularzy pasujacych do listy legacy';
            return $item;
        }

        foreach ($forms as $form) {
            if (!PWE_Multilang_Form_Operation_Lock::refreshOwned()) {
                $item['errors'][] = 'Przerwano archiwizację, ponieważ utracono blokadę operacji.';
                break;
            }

            $oldTitle = (string) ($form['title'] ?? '');

            if (PWE_Multilang_Form_Core::isManaged($form)) {
                $item['skipped'][] = 'archive:' . $oldTitle;
                $item['details'][] = 'pominieto formularz zarzadzany przez PWE Multilang: ' . $oldTitle;
                continue;
            }

            if (self::hasOldSuffix($oldTitle)) {
                $item['skipped'][] = 'archive:' . $oldTitle;
                $item['details'][] = 'formularz juz ma suffix OLD: ' . $oldTitle;
                continue;
            }

            $originalIsActive = $form['is_active'] ?? '1';
            $form['title'] = $oldTitle . self::OLD_SUFFIX;
            $form['is_active'] = '0';
            $update = self::updateForm($form);

            if (is_wp_error($update) || $update === false) {
                $item['errors'][] = $oldTitle . ': ' . (
                    is_wp_error($update) ? $update->get_error_message() : 'GFAPI::update_form zwrocilo false'
                );
                continue;
            }

            $item['form_id'] = (int) $form['id'];
            $item['updated'][] = 'archive:' . $oldTitle;
            $item['details'][] = 'zmieniono tytul starego formularza: ' . $oldTitle . ' -> ' . $form['title'] . ' (dezaktywowano)';
            self::$archiveJournal[] = [
                'form' => array_replace($form, ['title' => $oldTitle, 'is_active' => $originalIsActive]),
                'archived_title' => $form['title'],
            ];
        }

        return $item;
    }

    private static function shouldRollbackArchive(array $syncResult): bool
    {
        if (empty(self::$archiveJournal)) {
            return false;
        }

        foreach ($syncResult['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!empty($item['created']) || !empty($item['updated'])) {
                return false;
            }
        }

        return !empty($syncResult['errors']) || !empty($syncResult['items']);
    }

    private static function rollbackArchive(): array
    {
        $result = [
            'scope' => 'migration_to_new_forms',
            'add_only' => false,
            'items' => [],
            'errors' => [],
        ];
        $item = [
            'template' => 'migration-to-new-forms',
            'title' => 'Legacy forms rollback',
            'form_id' => 0,
            'action' => 'migration_rollback_old',
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
            'details' => [],
            'diff' => [],
        ];

        foreach (array_reverse(self::$archiveJournal) as $entry) {
            if (!PWE_Multilang_Form_Operation_Lock::refreshOwned()) {
                $item['errors'][] = 'Przerwano rollback, ponieważ utracono blokadę operacji.';
                break;
            }

            $form = is_array($entry['form'] ?? null) ? $entry['form'] : [];

            if (empty($form['id']) || empty($form['title'])) {
                continue;
            }

            $update = self::updateForm($form);

            if (is_wp_error($update) || $update === false) {
                $item['errors'][] = (string) ($entry['archived_title'] ?? $form['title']);
                continue;
            }

            $item['form_id'] = (int) $form['id'];
            $item['updated'][] = 'rollback:' . $form['title'];
            $item['details'][] = 'przywrocono tytul starego formularza: ' . $form['title'];
        }

        self::$archiveJournal = [];
        $result['items'][] = $item;
        return $result;
    }

    private static function findConfiguredLegacyForms(array $selected): array
    {
        if (!class_exists('GFAPI') || !method_exists('GFAPI', 'get_forms')) {
            return [];
        }

        $matches = [];

        foreach (GFAPI::get_forms() as $summary) {
            $formId = (int) ($summary['id'] ?? ($summary->id ?? 0));
            $formTitle = (string) ($summary['title'] ?? ($summary->title ?? ''));

            if ($formId <= 0 || $formTitle === '' || self::hasOldSuffix($formTitle)) {
                continue;
            }

            if (!self::isConfiguredLegacyTitle($formTitle, $selected)) {
                continue;
            }

            $form = GFAPI::get_form($formId);

            if (!empty($form) && is_array($form)) {
                $matches[$formId] = $form;
            }
        }

        return array_values($matches);
    }

    private static function isConfiguredLegacyTitle(string $title, array $selected): bool
    {
        $normalized = self::normalizeConfiguredLegacyTitle($title);

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::configuredLegacyTitles($selected), true);
    }

    private static function normalizeConfiguredLegacyTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return '';
        }

        $title = preg_replace('/\s+-\s+OLD\s*$/i', '', $title);
        $title = preg_replace('/^\s*\(\d{4}\s+PW\)\s*/i', '(YEAR PW) ', (string) $title);
        $title = preg_replace('/^\s*\(\d{4}\)\s*/i', '(YEAR) ', (string) $title);
        $title = preg_replace('/\s+/u', ' ', trim((string) $title));

        if (function_exists('remove_accents')) {
            $title = remove_accents((string) $title);
        }

        return mb_strtolower((string) $title);
    }

    private static function configuredLegacyTitles(array $selected): array
    {
        $rawTitles = [];
        $map = self::configuredLegacyTitleMap();

        foreach ($selected as $slug) {
            if (!empty($map[$slug]) && is_array($map[$slug])) {
                $rawTitles = array_merge($rawTitles, $map[$slug]);
            }
        }

        return array_values(array_unique(array_map(
            [self::class, 'normalizeConfiguredLegacyTitle'],
            $rawTitles
        )));
    }

    private static function configuredLegacyTitleMap(): array
    {
        return [
            'pw_potencjalny_wystawca_aktywacja' => [
                '(YEAR PW) Potencjalny wystawca - aktywacja',
                '(YEAR PW) Potencjalny wystawca EN - aktywacja',
            ],
            'badge_generator_local' => [
                '(YEAR) Badge generator(local)',
            ],
            'ceremonia_medalowa' => [
                '(YEAR) Ceremonia medalowa',
                '(YEAR) Ceremonia medalowa (EN)',
            ],
            'rejestracja' => [
                '(YEAR) Rejestracja EN',
                '(YEAR) Rejestracja Multilang',
                '(YEAR) Rejestracja PL',
            ],
            'rejestracja_fb' => [
                '(YEAR) Rejestracja EN (FB)',
                '(YEAR) Rejestracja Multilang (FB)',
                '(YEAR) Rejestracja PL (FB)',
            ],
            'rejestracja_gosci_wystawcow' => [
                '(YEAR) Rejestracja gosci wystawcow EN',
                '(YEAR) Rejestracja gosci wystawcow PL',
            ],
            'rejestracja_wystawcow_badge' => [
                '(YEAR) Rejestracja wystawcow (badge) EN',
                '(YEAR) Rejestracja wystawcow (badge) PL',
            ],
            'rejestracja_zaproszen_call_centre' => [
                '(YEAR) Rejestracja Zaproszen - call centre PL/EN',
            ],
            'voucher_generator' => [
                '(YEAR) Voucher Generator',
            ],
            'zostan_wystawca' => [
                '(YEAR) Zostan wystawca EN',
                '(YEAR) Zostan wystawca Multilang',
                '(YEAR) Zostan wystawca PL',
            ],
            'zostan_wystawca_krok2' => [
                '(YEAR) Zostan wystawca EN (krok2)',
                '(YEAR) Zostan wystawca Multilang (krok2)',
                '(YEAR) Zostan wystawca PL (krok2)',
            ],
            'napisz_do_nas' => [
                'Napisz do nas',
                'Write to us',
            ],
        ];
    }

    private static function updateForm(array $form)
    {
        if (class_exists('PWE_Multilang_Form_Manual_Modified')) {
            PWE_Multilang_Form_Manual_Modified::beginGeneratorSave();
        }

        try {
            return GFAPI::update_form($form);
        } finally {
            if (class_exists('PWE_Multilang_Form_Manual_Modified')) {
                PWE_Multilang_Form_Manual_Modified::endGeneratorSave();
            }
        }
    }

    private static function mergeResults(array $migrationResult, array $syncResult): array
    {
        return [
            'scope' => 'migration_to_new_forms',
            'add_only' => false,
            'items' => array_merge(
                $migrationResult['items'] ?? [],
                $syncResult['items'] ?? []
            ),
            'errors' => array_merge(
                $migrationResult['errors'] ?? [],
                $syncResult['errors'] ?? []
            ),
        ];
    }

    private static function finish(array $result): void
    {
        PWE_Multilang_Form_Result_Store::set($result);

        wp_redirect(add_query_arg(
            'pwe_mlg_forms_result',
            '1',
            admin_url('admin.php?page=pwe-multilang-forms')
        ));
        exit;
    }

    private static function getSelectedTemplates(): array
    {
        $selected = [];

        if (empty($_POST['pwe_mlg_templates']) || !is_array($_POST['pwe_mlg_templates'])) {
            return $selected;
        }

        foreach (wp_unslash($_POST['pwe_mlg_templates']) as $template) {
            $selected[] = sanitize_key($template);
        }

        return array_values(array_unique($selected));
    }

    private static function getYear(): int
    {
        return !empty($_POST['pwe_mlg_forms_year'])
            ? PWE_Multilang_Year_Resolver::normalise(absint($_POST['pwe_mlg_forms_year']))
            : PWE_Multilang_Year_Resolver::configured();
    }

    private static function saveYear(int $year): void
    {
        PWE_Multilang_Year_Resolver::save($year);
    }

    private static function resetFormFinderCache(): void
    {
        if (method_exists('PWE_Multilang_Form_Finder', 'resetCache')) {
            PWE_Multilang_Form_Finder::resetCache();
        }
    }

    private static function hasOldSuffix(string $title): bool
    {
        return preg_match('/\s-\sOLD$/i', trim($title)) === 1;
    }
}
