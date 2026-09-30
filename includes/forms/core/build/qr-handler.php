<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_QR
{
    /** Update existing prefixes and create missing feeds required by templates. */
    public static function updateAllFeedPrefixes(array $selectedTemplates = []): array
    {
        $result = PWE_Multilang_Form_Sync_Result::create('feed', false);
        if (!class_exists('GFAPI')) {
            $result['errors'][] = 'Gravity Forms / GFAPI nie jest dostępne.';
            return PWE_Multilang_Form_Sync_Result::finalize($result);
        }
        try {
            $prefix = self::getFeedPrefix();
            $forms = GFAPI::get_forms(null, false);
            if (is_wp_error($forms) || !is_array($forms)) {
                throw new \RuntimeException(is_wp_error($forms) ? $forms->get_error_message() : 'Nie udało się pobrać formularzy.');
            }
            $existingForms = [];
            foreach ($forms as $form) {
                if (is_array($form) && !empty($form['id']) && empty($form['is_trash'])) {
                    $existingForms[(int) $form['id']] = $form;
                }
            }
            $templates = PWE_Multilang_Form_Template_Registry::getTemplates(null, $existingForms);
            $selectedFormIds = [];
            if ($selectedTemplates !== []) {
                $templates = array_filter($templates, static function (array $template) use ($selectedTemplates): bool {
                    return in_array((string) ($template['slug'] ?? ''), $selectedTemplates, true);
                });
                foreach ($templates as $template) {
                    foreach ($template['targets'] ?? [] as $target) {
                        $formId = (int) ($target['form_id'] ?? 0);
                        if ($formId > 0) {
                            $selectedFormIds[$formId] = true;
                        }
                    }
                }
            }
        } catch (\RuntimeException $error) {
            $result['errors'][] = $error->getMessage();
            return PWE_Multilang_Form_Sync_Result::finalize($result);
        }
        $feeds = GFAPI::get_feeds();
        if (is_wp_error($feeds) && $feeds->get_error_code() === 'not_found') {
            $feeds = [];
        }
        if (is_wp_error($feeds) || !is_array($feeds)) {
            $result['errors'][] = is_wp_error($feeds) ? $feeds->get_error_message() : 'Nie udało się pobrać feedów.';
            return PWE_Multilang_Form_Sync_Result::finalize($result);
        }

        foreach ($feeds as $feed) {
            if (!in_array($feed['addon_slug'] ?? '', ['pwe_qr', 'qr-code'], true)) {
                continue;
            }
            $formId = (int) ($feed['form_id'] ?? 0);
            if (!isset($existingForms[$formId])) {
                continue;
            }
            if ($selectedTemplates !== [] && !isset($selectedFormIds[$formId])) {
                continue;
            }
            if (!PWE_Multilang_Form_Operation_Lock::refreshOwned()) {
                $result['errors'][] = 'Utracono blokadę operacji. Przerwano aktualizację feedów.';
                return PWE_Multilang_Form_Sync_Result::finalize($result);
            }
            $formId = (int) ($feed['form_id'] ?? 0);
            $feedId = (int) ($feed['id'] ?? 0);
            $item = PWE_Multilang_Form_Sync_Result::item(
                ['name' => 'Feed QR #' . $feedId],
                ['form_id' => $formId],
                'feed'
            );
            try {
                $form = $formId > 0 ? GFAPI::get_form($formId) : false;
                if (!is_array($form)) {
                    continue;
                }
                if ($feedId <= 0) {
                    throw new \RuntimeException('Nieprawidłowe ID feedu QR.');
                }
                if (empty($form['pwe_multilang_managed']) || !empty($form['is_trash'])) {
                    continue;
                }
                $item['title'] = (string) ($form['title'] ?? '');
                $meta = $feed['meta'] ?? null;
                if (!is_array($meta)
                    || !isset($meta['qrcodeFields'][0])
                    || !is_array($meta['qrcodeFields'][0])
                ) {
                    throw new \RuntimeException('Feed nie ma pierwszego pola qrcodeFields. Nie zmieniono ustawień.');
                }
                $newPrefix = $prefix . str_pad((string) $formId, 3, '0', STR_PAD_LEFT);
                if (($meta['qrcodeFields'][0]['custom_key'] ?? '') === $newPrefix) {
                    $item['details'][] = 'Prefiks jest już poprawny: ' . $newPrefix;
                } else {
                    $meta['qrcodeFields'][0]['custom_key'] = $newPrefix;
                    $updated = GFAPI::update_feed($feedId, $meta, $formId);
                    if (is_wp_error($updated) || $updated === false) {
                        throw new \RuntimeException(is_wp_error($updated) ? $updated->get_error_message() : 'Nie udało się zapisać feedu.');
                    }
                    $item['updated'][] = 'feed:' . $feedId;
                    $item['details'][] = 'Ustawiono prefiks ' . $newPrefix . '; pozostałe ustawienia zachowano.';
                }
                $item['status'] = 'success';
            } catch (\Throwable $error) {
                $item['status'] = 'failed';
                $item['errors'][] = $error->getMessage();
            }
            $result['items'][] = $item;
        }

        self::createMissingTemplateFeeds($result, $templates, $existingForms);

        if (empty($result['items']) && empty($result['errors'])) {
            $item = PWE_Multilang_Form_Sync_Result::item(['name' => 'Feedy QR'], [], 'feed');
            $item['status'] = 'skipped';
            $item['skipped'][] = 'Brak feedów QR.';
            $item['details'][] = 'Nie znaleziono feedów QR do obsłużenia w wybranym zakresie formularzy Multilang poza koszem.';
            $result['items'][] = $item;
        }
        return PWE_Multilang_Form_Sync_Result::finalize($result);
    }

    private static function createMissingTemplateFeeds(array &$result, array $templates, array $existingForms): void
    {
        foreach ($templates as $template) {
            foreach ($template['targets'] ?? [] as $target) {
                $payload = $target['payload'] ?? [];
                $formId = (int) ($target['form_id'] ?? 0);
                if (!isset($existingForms[$formId]) || empty($payload['qr']['enabled'])) {
                    continue;
                }

                if (!PWE_Multilang_Form_Operation_Lock::refreshOwned()) {
                    $result['errors'][] = 'Utracono blokadę operacji. Przerwano tworzenie feedów.';
                    return;
                }

                $item = PWE_Multilang_Form_Sync_Result::item($template, $target, 'feed');
                try {
                    $form = GFAPI::get_form($formId);
                    if (!is_array($form) || empty($form['pwe_multilang_managed']) || !empty($form['is_trash'])) {
                        continue;
                    }
                    $feeds = GFAPI::get_feeds(null, $formId, null, null);
                    if (is_wp_error($feeds) && $feeds->get_error_code() === 'not_found') {
                        $feeds = [];
                    }
                    if (is_wp_error($feeds) || !is_array($feeds)) {
                        throw new \RuntimeException(is_wp_error($feeds) ? $feeds->get_error_message() : 'Nie udało się pobrać feedów.');
                    }
                    foreach ($feeds as $feed) {
                        if (in_array($feed['addon_slug'] ?? '', ['pwe_qr', 'qr-code'], true)) {
                            continue 2;
                        }
                    }

                    $slug = self::makeNameSlug((string) ($payload['title'] ?? $form['title'] ?? ''));
                    $feed = self::addFeed($formId, $payload['qr']['config'] ?? [], $slug);
                    if (empty($feed['id'])) {
                        throw new \RuntimeException('Nie udało się utworzyć brakującego feedu QR.');
                    }
                    $item['created'][] = 'feed:' . $feed['id'];
                    self::replaceShortcodes($formId, (string) $feed['name'], $form);
                    $item['details'][] = 'Utworzono brakujący feed QR zgodnie z szablonem formularza.';
                    $item['status'] = 'success';
                } catch (\Throwable $error) {
                    $item['status'] = 'failed';
                    $item['errors'][] = $error->getMessage();
                }
                $result['items'][] = $item;
            }
        }
    }

    public static function processAfterSave(int $formId, array $payload, string $fallbackTitle = ''): void
    {
        if (empty($payload['qr']['enabled'])) {
            return;
        }

        $title = $payload['title'] ?? $fallbackTitle;
        $slug = self::makeNameSlug($title);

        if ($slug === '') {
            return;
        }

        $qrFeed = self::addFeed($formId, $payload['qr']['config'] ?? [], $slug);

        if (empty($qrFeed['id'])) {
            return;
        }

        self::replaceShortcodes($formId, (string) ($qrFeed['name'] ?? $slug));
    }

    public static function processShortcodesOnly(int $formId, string $fallbackTitle = ''): void
    {
        $form = GFAPI::get_form($formId);

        if (empty($form) || !is_array($form)) {
            return;
        }

        $slug = self::makeNameSlug($form['title'] ?? $fallbackTitle);

        if ($slug === '') {
            return;
        }

        self::replaceShortcodes($formId, self::getExistingFeedName($formId, $slug), $form);
    }

    private static function replaceShortcodes(int $formId, string $slug, ?array $form = null): void
    {
        $form = $form ?: GFAPI::get_form($formId);

        if (empty($form) || !is_array($form)) {
            return;
        }

        foreach (['confirmations', 'notifications'] as $section) {
            if (!empty($form[$section])) {
                self::replaceCodeId($form[$section], $slug);
            }
        }

        GFAPI::update_form($form);
    }

    private static function addFeed(int $formId, array $config = [], string $slug = ''): ?array
    {
        if (!class_exists('GFAPI')) {
            return null;
        }

        $existingFeed = self::findQrFeed($formId, ['pwe_qr']);

        if ($existingFeed !== null) {
            return [
                'id' => (int) ($existingFeed['id'] ?? 0),
                'name' => self::feedName($existingFeed, $slug),
            ];
        }

        try {
            $prefix = self::getFeedPrefix();
        } catch (\RuntimeException $error) {
            PWE_Multilang_Form_Log_Service::error('QR feed add failed', [
                'formId' => $formId,
                'error' => $error->getMessage(),
            ]);
            return null;
        }
        $defaults = [
            'feedName' => $slug ?: 'qr_' . $formId,
            'qrcodeLabel' => '',
            'qrcodeSize' => '200',
            'qrcodeFields' => [
                [
                    'key' => 'gf_custom',
                    'custom_key' => $prefix . str_pad((string) $formId, 3, '0', STR_PAD_LEFT),
                    'value' => 'id',
                ],
                [
                    'key' => 'gf_custom',
                    'custom_key' => 'rnd' . mt_rand(10000, 99999),
                    'value' => 'id',
                ],
            ],
        ];

        $legacyFeed = self::findQrFeed($formId, ['qr-code']);

        if ($legacyFeed !== null) {
            $qrData = self::extractQrDataFromFeed($legacyFeed);


            if ($qrData['rnd'] !== '') {
                $defaults['qrcodeFields'][1]['custom_key'] = $qrData['rnd'];
            }
        }

        $settings = array_replace_recursive($defaults, $config);
        $settings['feedName'] = $slug ?: 'qr_' . $formId;
        $settings['qrcodeFields'][0]['custom_key'] = $prefix . str_pad((string) $formId, 3, '0', STR_PAD_LEFT);

        $feedId = GFAPI::add_feed($formId, $settings, 'pwe_qr');

        if (is_wp_error($feedId)) {
            PWE_Multilang_Form_Log_Service::error('QR feed add failed', [
                'formId' => $formId,
                'error' => $feedId->get_error_message(),
            ]);

            return null;
        }

        return [
            'id' => (int) $feedId,
            'name' => (string) $settings['feedName'],
        ];
    }

    private static function getFeedPrefix(): string
    {
        if (!shortcode_exists('trade_fair_feed_prefix')) {
            throw new \RuntimeException('Shortcode [trade_fair_feed_prefix] nie jest zarejestrowany. Nie zmieniono feedów.');
        }
        $badge = trim((string) do_shortcode('[trade_fair_feed_prefix]'));
        if ($badge === '' || $badge === '[trade_fair_feed_prefix]' || strip_tags($badge) !== $badge) {
            throw new \RuntimeException('Shortcode [trade_fair_feed_prefix] musi zwrócić niepusty tekst prefiksu. Nie zmieniono feedów.');
        }
        return strtoupper(substr($badge, 0, 4));
    }

    private static function getExistingFeedName(int $formId, string $fallback): string
    {
        if (!class_exists('GFAPI')) {
            return $fallback;
        }

        $feed = self::findQrFeed($formId, ['pwe_qr']);

        if ($feed !== null) {
            return self::feedName($feed, $fallback);
        }

        $legacyFeed = self::findQrFeed($formId, ['qr-code']);

        if ($legacyFeed !== null) {
            return self::feedName($legacyFeed, $fallback);
        }

        return $fallback;
    }

    private static function findQrFeed(int $formId, array $addonSlugs): ?array
    {
        $feeds = GFAPI::get_feeds(null, $formId, null, null);
        if (is_wp_error($feeds)) {
            if ($feeds->get_error_code() === 'not_found') {
                return null;
            }
            throw new \RuntimeException($feeds->get_error_message());
        }
        if (!is_array($feeds)) {
            throw new \RuntimeException('Nie udało się pobrać feedów.');
        }

        foreach ($feeds as $feed) {
            if (
                (int) ($feed['form_id'] ?? 0) === $formId
                && in_array((string) ($feed['addon_slug'] ?? ''), $addonSlugs, true)
            ) {
                return $feed;
            }
        }

        return null;
    }

    private static function extractQrDataFromFeed(array $feed): array
    {
        $meta = is_array($feed['meta'] ?? null) ? $feed['meta'] : [];
        $fields = is_array($meta['qrcodeFields'] ?? null) ? $meta['qrcodeFields'] : [];
        $primaryCustomKey = '';
        $rnd = '';

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fieldCustomKey = (string) ($field['custom_key'] ?? '');

            if ($fieldCustomKey === '') {
                continue;
            }

            if ($rnd === '' && strpos($fieldCustomKey, 'rnd') === 0) {
                $rnd = $fieldCustomKey;
                continue;
            }

            if ($primaryCustomKey === '') {
                $primaryCustomKey = $fieldCustomKey;
            }
        }

        return [
            'custom_key' => $primaryCustomKey,
            'rnd' => $rnd,
        ];
    }

    private static function feedName(array $feed, string $fallback): string
    {
        $meta = is_array($feed['meta'] ?? null) ? $feed['meta'] : [];
        $name = (string) ($meta['feedName'] ?? $feed['feedName'] ?? '');

        return $name !== '' ? $name : $fallback;
    }

    private static function replaceCodeId(array &$items, string $slug): void
    {
        foreach ($items as &$item) {
            foreach ($item as $key => $value) {
                if ($key === 'id' || !is_string($value) || strpos($value, 'pwe_qr_') === false) {
                    continue;
                }

                $item[$key] = self::addNameToBareQrShortcodes($value, $slug);
            }
        }

        unset($item);
    }

    private static function addNameToBareQrShortcodes(string $value, string $slug): string
    {
        $name = esc_attr($slug);
        $replacements = [
            '[pwe_qr_img]' => '[pwe_qr_img name="' . $name . '"]',
            '[pwe_qr_url]' => '[pwe_qr_url name="' . $name . '"]',
            '[pwe_qr_url_encoded]' => '[pwe_qr_url_encoded name="' . $name . '"]',
            '{pwe_qr_url}' => '{pwe_qr_url name=' . $name . '}',
            '{pwe_qr_url_encoded}' => '{pwe_qr_url_encoded name=' . $name . '}',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }

    private static function makeNameSlug(string $title): string
    {
        $title = self::stripYearFromTitle($title);
        $title = trim((string) $title);

        if ($title === '') {
            return '';
        }

        if (function_exists('sanitize_title')) {
            $slug = sanitize_title($title);
            $slug = str_replace('-', '_', $slug);
        } else {
            $slug = mb_strtolower($title);
            $slug = preg_replace('/[^a-z0-9]+/i', '_', $slug);
        }

        $slug = mb_strtolower((string) $slug);
        $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug);
        $slug = preg_replace('/_+/', '_', (string) $slug);
        $slug = self::normalizeLangSuffix((string) $slug);

        return trim((string) $slug, '_');
    }

    private static function stripYearFromTitle(string $title): string
    {
        $title = trim($title);

        $title = preg_replace_callback(
            '/^\s*\((\d{4})([^)]*)\)\s*/',
            static function (array $matches): string {
                $rest = trim((string) ($matches[2] ?? ''));

                return $rest !== '' ? $rest . ' ' : '';
            },
            $title
        );

        $title = preg_replace('/\b\d{4}\b/', '', (string) $title);
        $title = preg_replace('/\s+/', ' ', (string) $title);

        return trim((string) $title);
    }

    private static function normalizeLangSuffix(string $slug): string
    {
        $slug = trim($slug, '_');

        // Keep dual-language markers as-is (e.g. ..._pl_en).
        if (preg_match('/_(pl|en)_(pl|en)$/', $slug) === 1) {
            return $slug;
        }

        if (preg_match('/^(.*)_(pl|en)_(.+)$/', $slug, $matches) !== 1) {
            return $slug;
        }

        $prefix = trim((string) ($matches[1] ?? ''), '_');
        $lang = (string) ($matches[2] ?? '');
        $suffix = trim((string) ($matches[3] ?? ''), '_');

        if ($prefix === '' || $suffix === '') {
            return $slug;
        }

        return $prefix . '_' . $suffix . '_' . $lang;
    }
}
