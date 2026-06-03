<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Writer_Helper {

    public static function preparePayloadForCreate(array $payload) : array {

        if (!empty($payload['fields'])) {
            $payload['fields'] = self::prepareFields($payload['fields']);
            $payload['nextFieldId'] = count($payload['fields']) + 1;
        }

        if (!empty($payload['confirmations'])) {
            $payload['confirmations'] = self::prepareConfirmations(
                $payload['confirmations'],
                $payload['fields'] ?? []
            );
        }

        if (!empty($payload['notifications'])) {
            $payload['notifications'] = self::prepareNotifications(
                $payload['notifications'],
                $payload['fields'] ?? [],
                $payload['_formDir'] ?? ''
            );
        }

        return $payload;
    }

    public static function prepareExistingForUpdate(
        array $existing,
        array $payload,
        array $options
    ) : array {

        unset($payload['qr']);

        if (isset($payload['description'])) {
            $existing['description'] = $payload['description'];
        }

        if (!empty($payload['fields'])) {
            $existing['fields'] = self::prepareFields(
                $payload['fields'],
                $existing['fields'] ?? []
            );
        }

        if (!empty($payload['confirmations'])) {
            $confirmations = self::prepareConfirmations(
                $payload['confirmations'],
                $existing['fields'] ?? []
            );

            $existing['confirmations'] = self::applyMerge(
                $existing['confirmations'] ?? [],
                $confirmations,
                $options['confirmations']
            );
        }

        if (!empty($payload['notifications'])) {
            $notifications = self::prepareNotifications(
                $payload['notifications'],
                $existing['fields'] ?? [],
                $payload['_formDir'] ?? ''
            );

            $existing['notifications'] = self::applyMerge(
                $existing['notifications'] ?? [],
                $notifications,
                $options['notifications']
            );
        }

        return $existing;
    }

    private static function prepareFields(
        array $fields,
        array $existingFields = []
    ) : array {

        $fields = self::assignFieldIds($fields, $existingFields);
        $fields = self::resolveConditionalLogic($fields);

        return self::normalizeFields($fields);
    }

    private static function prepareConfirmations(
        array $confirmations,
        array $fields
    ) : array {

        $confirmations = self::normalizeConfirmations($confirmations);

        $confirmations = self::resolveConfirmationMergeTags($confirmations, $fields);

        return self::resolveConfirmationLogic($confirmations, $fields);
    }

    private static function prepareNotifications(
        array $notifications,
        array $fields,
        string $formDir = ''
    ) : array {

        $notifications = self::normalizeNotifications($notifications);

        $notifications = self::hydrateNotificationTemplates(
            $notifications,
            $formDir
        );

        $notifications = self::replaceLangShortcodes($notifications);

        $notifications = self::resolveNotificationLogic(
            $notifications,
            $fields
        );

        $notifications = self::resolveNotificationRecipients(
            $notifications,
            $fields
        );

        return self::prepareQrAttachmentFlags($notifications);
    }

    private static function prepareQrAttachmentFlags(
        array $notifications
    ) : array {

        foreach ($notifications as &$notification) {
            if (empty($notification['attachQr'])) {
                unset($notification['attachQr']);
                continue;
            }

            $notification['pwe_attach_qr_image'] = 1;

            unset($notification['attachQr']);
        }

        unset($notification);

        return $notifications;
    }

    public static function processQrAfterSave(
        int $formId,
        array $payload,
        string $fallbackTitle = ''
    ) : void {

        if (empty($payload['qr']['enabled'])) {
            return;
        }

        $title = $payload['title'] ?? $fallbackTitle;
        $slug = self::makeQrNameSlug($title);

        if ($slug === '') {
            return;
        }

        $qrFeedId = self::addQrFeed(
            $formId,
            $payload['qr']['config'] ?? [],
            $slug
        );

        if (!$qrFeedId) {
            return;
        }

        $form = GFAPI::get_form($formId);

        if (empty($form) || !is_array($form)) {
            return;
        }

        foreach (['confirmations', 'notifications'] as $section) {
            if (!empty($form[$section])) {
                self::replaceQrCodeId($form[$section], $slug);
            }
        }

        GFAPI::update_form($form);
    }

    private static function assignFieldIds(
        array $fields,
        array $existingFields = []
    ) : array {

        $existingMap = self::indexExistingFields($existingFields);
        $maxId = self::getMaxFieldId($existingFields);

        foreach ($fields as &$field) {
            if (!is_array($field)) {
                continue;
            }

            $adminLabel = $field['adminLabel'] ?? null;

            if ($adminLabel && isset($existingMap[$adminLabel])) {
                $field['id'] = $existingMap[$adminLabel];
                continue;
            }

            $maxId++;
            $field['id'] = $maxId;
        }

        unset($field);

        return $fields;
    }

    private static function normalizeFields(array $fields) : array {

        $out = [];

        foreach ($fields as $field) {
            if (empty($field['type'])) {
                self::logWarn('Pominięto pole bez typu', [
                    'field' => $field,
                ]);

                continue;
            }

            if (class_exists('GF_Fields') && method_exists('GF_Fields', 'create')) {
                $out[] = GF_Fields::create($field);
                continue;
            }

            $out[] = $field;
        }

        return $out;
    }

    private static function indexExistingFields(array $fields) : array {

        $map = [];

        foreach ($fields as $field) {
            if (is_object($field) && !empty($field->adminLabel)) {
                $map[$field->adminLabel] = (int) $field->id;
            }
        }

        return $map;
    }

    private static function getMaxFieldId(array $fields) : int {

        $maxId = 0;

        foreach ($fields as $field) {
            if (is_object($field) && isset($field->id)) {
                $maxId = max($maxId, (int) $field->id);
            }
        }

        return $maxId;
    }

    private static function resolveConditionalLogic(array $fields) : array {

        $map = [];

        foreach ($fields as $field) {
            if (
                is_array($field)
                && !empty($field['adminLabel'])
                && !empty($field['id'])
            ) {
                $map[$field['adminLabel']] = $field['id'];
            }
        }

        foreach ($fields as &$field) {
            if (empty($field['conditionalLogic']['rules'])) {
                continue;
            }

            foreach ($field['conditionalLogic']['rules'] as &$rule) {
                if (!empty($rule['fieldId'])) {
                    continue;
                }

                if (!empty($rule['field']) && isset($map[$rule['field']])) {
                    $rule['fieldId'] = $map[$rule['field']];
                    unset($rule['field']);
                }
            }

            unset($rule);
        }

        unset($field);

        return $fields;
    }

    private static function normalizeNotifications(array $notifications) : array {

        $out = [];

        foreach ($notifications as $notification) {
            if (empty($notification['id'])) {
                $notification['id'] = md5($notification['name'] ?? uniqid('', true));
            }

            $out[$notification['id']] = $notification;
        }

        return $out;
    }

    private static function normalizeConfirmations(array $confirmations) : array {

        $out = [];

        foreach ($confirmations as $confirmation) {
            if (empty($confirmation['id'])) {
                $confirmation['id'] = uniqid();
            }

            $out[$confirmation['id']] = $confirmation;
        }

        return $out;
    }

    private static function resolveNotificationLogic(
        array $notifications,
        array $fields
    ) : array {

        $map = self::getFieldMapByAdminLabel($fields);

        foreach ($notifications as &$notification) {
            if (empty($notification['conditionalLogic']['rules'])) {
                continue;
            }

            foreach ($notification['conditionalLogic']['rules'] as &$rule) {
                if (!empty($rule['fieldId'])) {
                    continue;
                }

                if (!empty($rule['field']) && isset($map[$rule['field']])) {
                    $rule['fieldId'] = $map[$rule['field']];
                    unset($rule['field']);
                }
            }

            unset($rule);

            $notification['notification_conditional_logic'] = '1';
            $notification['notification_conditional_logic_object'] = $notification['conditionalLogic'];
        }

        unset($notification);

        return $notifications;
    }

    private static function resolveNotificationRecipients(
        array $notifications,
        array $fields
    ) : array {

        $map = self::getFieldMapByAdminLabel($fields);

        foreach ($notifications as &$notification) {
            if (($notification['toType'] ?? '') !== 'field') {
                continue;
            }

            $to = $notification['to'] ?? $notification['toField'] ?? '';

            if ($to === '') {
                continue;
            }

            if (isset($map[$to])) {
                $fieldId = (string) $map[$to];
            } elseif (is_numeric($to)) {
                $fieldId = (string) $to;
            } else {
                continue;
            }

            $notification['toType']  = 'field';
            $notification['to']      = $fieldId;
            $notification['toField'] = $fieldId;
            $notification['toEmail'] = '';
        }

        unset($notification);

        return $notifications;
    }

    private static function resolveConfirmationLogic(
        array $confirmations,
        array $fields
    ) : array {

        $map = self::getFieldMapByAdminLabel($fields);

        foreach ($confirmations as &$confirmation) {
            if (empty($confirmation['conditionalLogic']['rules'])) {
                continue;
            }

            foreach ($confirmation['conditionalLogic']['rules'] as &$rule) {
                if (!empty($rule['fieldId'])) {
                    continue;
                }

                if (!empty($rule['field']) && isset($map[$rule['field']])) {
                    $rule['fieldId'] = $map[$rule['field']];
                    unset($rule['field']);
                }
            }

            unset($rule);
        }

        unset($confirmation);

        return $confirmations;
    }

    private static function resolveConfirmationMergeTags(
        array $confirmations,
        array $fields
    ) : array {

        $map = self::getFieldMapByAdminLabel($fields);

        foreach ($confirmations as &$confirmation) {
            foreach (['url', 'message', 'queryString'] as $key) {
                if (empty($confirmation[$key]) || !is_string($confirmation[$key])) {
                    continue;
                }

                $confirmation[$key] = preg_replace_callback(
                    '/\{UTM:([a-zA-Z0-9_\-]+)\}/',
                    static function ($matches) use ($map) {
                        return isset($map[$matches[1]])
                            ? '{UTM:' . $map[$matches[1]] . '}'
                            : $matches[0];
                    },
                    $confirmation[$key]
                );
            }
        }

        unset($confirmation);

        return $confirmations;
    }

    private static function hydrateNotificationTemplates(
        array $notifications,
        string $formDir
    ) : array {

        foreach ($notifications as &$notification) {
            if (empty($notification['_template'])) {
                continue;
            }

            $template = ltrim($notification['_template'], '/');
            $lang = self::getLangFromTemplateName($template);

            if ($lang) {
                $notification['_pwe_lang'] = $lang;
            }

            $templateBase = preg_replace('/-[a-z]{2}\.html$/', '', $template);
            $templateDir  = rtrim($formDir, '/') . '/notifications/' . $templateBase;

            $customPath  = $templateDir . '/' . $template;
            $defaultPath = $templateDir . '/' . $templateBase . '.html';

            if (is_file($customPath)) {
                $notification['message'] = file_get_contents($customPath);
            } elseif (is_file($defaultPath)) {
                $notification['message'] = file_get_contents($defaultPath);

                if ($lang) {
                    $notification['message'] = self::applyNotificationTranslations(
                        $notification['message'],
                        $lang,
                        $templateBase,
                        $formDir
                    );
                }
            }

            unset($notification['_template']);
        }

        unset($notification);

        return $notifications;
    }
    
    private static function getLangFromTemplateName(string $template) : ?string {

        if (preg_match('/-([a-z]{2})\.html$/', $template, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    private static function applyNotificationTranslations(
        string $html,
        string $lang,
        string $templateBase,
        string $formDir
    ) : string {

        $translations = self::getNotificationTranslations(
            $lang,
            $templateBase,
            $formDir
        );

        if (empty($translations)) {
            return $html;
        }

        foreach ($translations as $key => $value) {
            $html = str_replace(
                '{{' . $key . '}}',
                $value,
                $html
            );
        }

        return $html;
    }

    private static function getNotificationTranslations(
        string $lang,
        string $templateBase,
        string $formDir
    ) : array {

        $file = rtrim($formDir, '/') . '/notifications/' . $templateBase . '/translations.php';

        if (!is_file($file)) {
            return [];
        }

        $translations = include $file;

        if (!is_array($translations)) {
            return [];
        }

        return $translations[$lang] ?? [];
    }

    private static function addQrFeed(
        int $formId,
        array $config = [],
        string $slug = ''
    ) : ?int {

        if (!class_exists('GFAPI')) {
            return null;
        }

        $feeds = GFAPI::get_feeds();

        foreach ($feeds as $feed) {
            if (
                (int) ($feed['form_id'] ?? 0) === $formId
                && ($feed['addon_slug'] ?? null) === 'qr-code'
            ) {
                return (int) ($feed['id'] ?? 0);
            }
        }

        $badge = do_shortcode('[trade_fair_badge]');
        $prefix = strtoupper(substr($badge, 0, 4));

        $defaults = [
            'feedName' => $slug ?: 'qr_' . $formId,
            'qrcodeLabel' => '',
            'qrcodeSize' => '200',
            'qrcodeFields' => [
                [
                    'key' => 'gf_custom',
                    'custom_key' => $prefix . $formId,
                    'value' => 'id',
                ],
                [
                    'key' => 'gf_custom',
                    'custom_key' => 'rnd' . mt_rand(10000, 99999),
                    'value' => 'id',
                ],
            ],
        ];

        $feed = array_replace_recursive($defaults, $config);
        $feedId = GFAPI::add_feed($formId, $feed, 'pwe_qr');

        if (is_wp_error($feedId)) {
            self::logError('QR feed add failed', [
                'formId' => $formId,
                'error'  => $feedId->get_error_message(),
            ]);

            return null;
        }

        return (int) $feedId;
    }

    private static function replaceQrCodeId(
        array &$items,
        string $slug
    ) : void {

        foreach ($items as &$item) {
            foreach ($item as $key => $value) {
                if (
                    $key === 'id'
                    || !is_string($value)
                    || strpos($value, '[pwe_qr_img]') === false
                ) {
                    continue;
                }

                $item[$key] = str_replace(
                    '[pwe_qr_img]',
                    '[pwe_qr_img name="' . esc_attr($slug) . '"]',
                    $value
                );
            }
        }

        unset($item);
    }

    private static function replaceLangShortcodes(array $notifications) : array
    {
        foreach ($notifications as &$notification) {
            $lang = self::getLangFromNotification($notification);

            if (!$lang) {
                continue;
            }

            foreach ($notification as $key => $value) {
                if (
                    $key === 'id'
                    || !is_string($value)
                    || (
                        strpos($value, '[pwe_name_lang]') === false
                        && strpos($value, '{pwe_name_lang}') === false
                        && strpos($value, 'https://[trade_fair_domainadress]/lang/?utm') === false
                    )
                ) {
                    continue;
                }

                $notification[$key] = str_replace(
                    [
                        '[pwe_name_lang]',
                        '{pwe_name_lang}',
                        'https://[trade_fair_domainadress]/lang/?utm',
                    ],
                    [
                        '[pwe_name_' . esc_attr($lang) . ']',
                        '{pwe_name_' . esc_attr($lang) . '}',
                        'https://[trade_fair_domainadress]/' . esc_attr($lang) . '/?utm',
                    ],
                    $value
                );
            }
        }

        unset($notification);

        return $notifications;
    }

    private static function getLangFromNotification(array $notification) : ?string
    {
        if (!empty($notification['_pwe_lang'])) {
            return strtolower($notification['_pwe_lang']);
        }

        if (!empty($notification['name'])) {
            if (preg_match('/-\s*([A-Z]{2})(?:\s-|$)/', $notification['name'], $m)) {
                return strtolower($m[1]);
            }

            if (preg_match('/-([a-z]{2})$/', $notification['name'], $m)) {
                return strtolower($m[1]);
            }
        }

        if (!empty($notification['conditionalLogic']['rules'])) {
            foreach ($notification['conditionalLogic']['rules'] as $rule) {
                if (($rule['field'] ?? '') === 'lang' && !empty($rule['value'])) {
                    return strtolower($rule['value']);
                }
            }
        }

        return null;
    }

    private static function attachQrToNotifications(
        array &$notifications
    ) : void {

        foreach ($notifications as &$notification) {
            if (empty($notification['attachQr'])) {
                continue;
            }

            $notification['pwe_attach_qr_image'] = 1;

            unset($notification['attachQr']);
        }

        unset($notification);
    }

    private static function makeQrNameSlug(string $title) : string {

        $slug = mb_strtolower($title);

        $slug = preg_replace('/\(\d{4}\)/', '', $slug);
        $slug = trim($slug);
        $slug = preg_replace('/\s+/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);

        return trim($slug, '_');
    }

    private static function applyMerge(
        array $existing,
        array $incoming,
        string $mode
    ) : array {

        if ($mode === 'replace') {
            return $incoming;
        }

        $index = [];

        foreach ($existing as $id => $item) {
            if (!empty($item['name'])) {
                $index[mb_strtolower($item['name'])] = $id;
            }
        }

        foreach ($incoming as $newId => $item) {
            $name = $item['name'] ?? null;

            if (!$name) {
                $existing[$newId] = $item;
                continue;
            }

            $key = mb_strtolower($name);

            if (isset($index[$key])) {
                $existing[$index[$key]] = array_replace_recursive(
                    $existing[$index[$key]],
                    $item
                );
            } else {
                $existing[$newId] = $item;
            }
        }

        return $existing;
    }

    private static function getFieldMapByAdminLabel(array $fields) : array {

        $map = [];

        foreach ($fields as $field) {
            if (is_object($field) && !empty($field->adminLabel)) {
                $map[$field->adminLabel] = (string) $field->id;
            }
        }

        return $map;
    }

    public static function logError(
        string $message,
        array $context = []
    ) : void {

        if (class_exists('PWE_Multilang_Form_Log')) {
            PWE_Multilang_Form_Log::error('FORM: ' . $message, $context);
        }
    }

    private static function logWarn(
        string $message,
        array $context = []
    ) : void {

        if (class_exists('PWE_Multilang_Form_Log')) {
            PWE_Multilang_Form_Log::warn('FORM: ' . $message, $context);
        }
    }

    public static function processQrShortcodesOnly(
        int $formId,
        string $fallbackTitle = ''
    ) : void {

        $form = GFAPI::get_form($formId);

        if (empty($form) || !is_array($form)) {
            return;
        }

        $title = $form['title'] ?? $fallbackTitle;
        $slug = self::makeQrNameSlug($title);

        if ($slug === '') {
            return;
        }

        foreach (['confirmations', 'notifications'] as $section) {
            if (empty($form[$section])) {
                continue;
            }

            self::replaceQrCodeId($form[$section], $slug);
        }

        GFAPI::update_form($form);
    }
}