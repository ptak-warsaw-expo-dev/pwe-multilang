<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_GF_Notifications {

    private static function val($v, $d) {
        return ($v === null || $v === '') ? $d : $v;
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

        bool $attachQr = false
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

        if (!empty($conditionalLogic)) {
            $notification['conditionalLogic'] = $conditionalLogic;
        }

        return $notification;
    }

    public static function Admin_Notification_Multilang(
        string $name = 'Admin Notification',
        string $to = 'odwiedzajacy@warsawexpo.eu',
        string $subject = '{trade_fair_name} - nowa rejestracja B2B',
        bool $isActive = true
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
                ),
            ];
        }

        $notifications = [];

        foreach ($active_languages as $lang_code => $lang_data) {
            if (in_array($lang_code, ['pl', 'en'], true)) {
                continue;
            }

            $lang = strtoupper($lang_code);

            $notifications[] = self::Notification(
                name: $name . ' - ' . $lang,
                to: $to,
                subject: $subject . ' - ' . $lang,
                message: '{all_fields}',
                disableAutoformat: false,
                isActive: $isActive,
                conditionalLogic: [
                    'actionType' => 'show',
                    'logicType'  => 'all',
                    'rules'      => [
                        [
                            'field'    => 'lang',
                            'operator' => 'is',
                            'value'    => $lang_code,
                        ],
                    ],
                ],
            );
        }

        return $notifications;
    }

    public static function Multilang_Notifications(
        array $langs,
        array $variants,
        array $subjects = []
    ) : array {

        $notifications = [];

        foreach ($langs as $lang) {

            if (in_array($lang, ['pl', 'en'], true)) {
                continue;
            }

            foreach ($variants as $variant) {

                if (empty($variant['name']) || empty($variant['template'])) {
                    continue;
                }

                $rules = $variant['rules'] ?? [];

                $rules[] = [
                    'field'    => 'lang',
                    'operator' => 'is',
                    'value'    => $lang,
                ];

                $subject = $subjects[$lang]
                    ?? $variant['subject']
                    ?? '{trade_fair_name}';

                $notifications[] = self::Notification(
                    name: $variant['name'] . ' - ' . strtoupper($lang),
                    toType: $variant['toType'] ?? 'field',
                    to: $variant['to'] ?? 'email',
                    subject: $subject,
                    template: str_replace('{lang}', $lang, $variant['template']),
                    isActive: $variant['isActive'] ?? true,
                    attachQr: $variant['attachQr'] ?? true,
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
}
