<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_GF_Notifications {

    private static function val($v, $d) {
        return ($v === null || $v === '') ? $d : $v;
    }

    private static function language_rules(string $lang, array $langs): array {
        if (strtolower($lang) !== 'en') {
            return [[
                'field'    => 'lang',
                'operator' => 'is',
                'value'    => $lang,
            ]];
        }

        $rules = [];

        foreach ($langs as $supported_lang) {
            $supported_lang = strtolower(trim((string) $supported_lang));

            if ($supported_lang === '' || $supported_lang === 'en') {
                continue;
            }

            $rules[] = [
                'field'    => 'lang',
                'operator' => 'isnot',
                'value'    => $supported_lang,
            ];
        }

        return $rules;
    }

    public static function Notification(
        string $name,
        string $event = 'form_submission',
        string $service = 'wordpress',

        string $toType = 'email',          // email | field
        ?string $to = null,                // email lub adminLabel pola

        ?string $subject = null,
        ?string $message = null,           // inline HTML / tekst
        ?string $template = null,          // plik HTML

        ?string $from = '{trade_fair_rejestracja}',
        ?string $fromName = '{trade_fair_name}',

        bool $isActive = true,
        bool $disableAutoformat = true,

        $conditionalLogic = null,

        bool $attachQr = false,
        ?string $notificationKey = null
    ) : array {

        $notification = [
            'id'        => null,
            'isActive'  => $isActive,
            'name'      => $name,
            'service'   => $service,
            'event'     => $event,

            'toType'    => $toType,
            'to'        => self::val($to, ''),
            'toField'   => $toType === 'field' ? $to : '',
            'toEmail'   => $toType === 'email' ? $to : '',

            'subject'   => self::val($subject, ''),
            'message'   => self::val($message, ''),

            'from'      => $from,
            'fromName'  => $fromName,
            'replyTo'   => '',
            'cc'        => '',
            'bcc'       => '',

            'disableAutoformat' => $disableAutoformat,
            'enableAttachments' => false,
            'messageFormat' => 'html',

            'attachQr' => $attachQr,

            // ⬇️ info dla PWE_Multilang_Form_Writer
            '_template' => $template,
        ];

        $notificationKey = self::normalize_notification_key($notificationKey);

        if ($notificationKey !== '') {
            $notification['pwe_notification_key'] = $notificationKey;
        }

        if (!empty($conditionalLogic)) {
            $notification['conditionalLogic'] = $conditionalLogic;
        }

        return $notification;
    }

    public static function Admin_Notification_Multilang(
        string $name = 'Admin Notification',
        string $to = 'odwiedzajacy@warsawexpo.eu',
        string $subject = '{trade_fair_name} - nowa rejestracja B2B',
        bool $isActive = true,
        string $notificationKey = 'admin-notification'
    ) : array {

        $active_languages = apply_filters('wpml_active_languages', null, [
            'skip_missing' => 0,
        ]);

        if (empty($active_languages) || !is_array($active_languages)) {
            return [
                self::Notification(
                    name: $name,
                    to: $to,
                    subject: $subject,
                    message: '{all_fields}',
                    disableAutoformat: false,
                    notificationKey: $notificationKey . '__all',
                ),
            ];
        }

        $notifications = [];

        $active_lang_codes = array_keys($active_languages);

        foreach ($active_languages as $lang_code => $lang_data) {
            $lang = strtoupper($lang_code);

            $notifications[] = self::Notification(
                name: $name . ' - ' . $lang,
                to: $to,
                subject: $subject . ' - ' . $lang,
                message: '{all_fields}',
                disableAutoformat: false,
                isActive: $isActive,
                notificationKey: $notificationKey . '__' . strtolower($lang),
                conditionalLogic: [
                    'actionType' => 'show',
                    'logicType'  => 'all',
                    'rules'      => self::language_rules($lang_code, $active_lang_codes),
                ],
            );
        }

        return $notifications;
    }

    public static function Multilang_Notifications(
        array $langs,
        array $variants,
        array $subjects = [],
        array $messages = [],
        array $fromNames = []
    ) : array {

        $notifications = [];

        foreach ($langs as $lang) {

            foreach ($variants as $variant) {

                if (empty($variant['name'])) {
                    continue;
                }

                $rules = $variant['rules'] ?? [];

                $rules = array_merge($rules, self::language_rules($lang, $langs));

                $variant_subjects = $variant['subjects'] ?? [];
                $variant_messages = $variant['messages'] ?? [];
                $variant_from_names = $variant['fromNames'] ?? [];

                $subject = $variant_subjects[$lang]
                    ?? $variant['subject']
                    ?? $subjects[$lang]
                    ?? '{trade_fair_name}';

                $message = $variant_messages[$lang]
                    ?? $variant['message']
                    ?? $messages[$lang]
                    ?? '';

                $template = !empty($variant['template'])
                    ? str_replace('{lang}', $lang, $variant['template'])
                    : null;

                if (!$template && $message === '') {
                    continue;
                }

                $fromName = $variant_from_names[$lang]
                    ?? $variant['fromName']
                    ?? $fromNames[$lang]
                    ?? '{trade_fair_name}';

                $notifications[] = self::Notification(
                    name: $variant['name'] . ' - ' . strtoupper($lang),
                    toType: $variant['toType'] ?? 'field',
                    to: $variant['to'] ?? 'email',
                    subject: $subject,
                    message: $message,
                    template: $template,
                    fromName: $fromName,
                    isActive: $variant['isActive'] ?? true,
                    attachQr: $variant['attachQr'] ?? (bool) $template,
                    notificationKey: self::variant_notification_key($variant) . '__' . strtolower($lang),
                    conditionalLogic: [
                        'actionType' => 'show',
                        'logicType'  => 'all',
                        'rules'      => $rules,
                    ],
                );
            }
        }

        return $notifications;
    }

    private static function variant_notification_key(array $variant): string
    {
        $key = self::normalize_notification_key($variant['key'] ?? null);

        if ($key !== '') {
            return $key;
        }

        // Compatibility fallback for custom callers. Built-in templates use
        // explicit immutable keys so changing a display name is safe.
        return sanitize_title((string) ($variant['name'] ?? 'notification'));
    }

    private static function normalize_notification_key($key): string
    {
        return is_string($key) ? sanitize_key($key) : '';
    }
}
