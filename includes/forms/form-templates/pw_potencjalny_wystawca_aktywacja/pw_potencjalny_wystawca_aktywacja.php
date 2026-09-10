<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Potencjalny_Wystawca_Aktywacja {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl', 'en'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Potencjalny wystawca - aktywacja';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Loading...',
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Text(
                label: 'Imię i nazwisko',
                adminLabel: 'name',
                cssClass: 'vip-name pwe-field__text--name',
                visibility: 'hidden',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'Email',
                adminLabel: 'email',
                cssClass: 'vip-email form-required',
                visibility: 'hidden',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Telefon',
                adminLabel: 'phone',
                cssClass: 'vip-phone pwe-field__text--phone',
                visibility: 'hidden',
                required: false,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Firma',
                adminLabel: 'company',
                cssClass: 'vip-company pwe-field__text--company',
                visibility: 'hidden',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Kanał wysyłki',
                adminLabel: 'channel',
                cssClass: 'vip-channel pwe-field__text--channel',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Badge',
                adminLabel: 'badge',
                cssClass: 'vip-badge pwe-field__text--badge',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'ID',
                adminLabel: 'id',
                cssClass: 'vip-id pwe-field__text--id',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'IDName',
                adminLabel: 'idname',
                cssClass: 'vip-id-name pwe-field__text--id-name',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'IDEmail',
                adminLabel: 'idemail',
                cssClass: 'vip-id-email pwe-field__text--id-email',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'IDPhone',
                adminLabel: 'idphone',
                cssClass: 'vip-id-phone pwe-field__text--id-phone',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Lang(),

        ];

        // CONFIRMATIONS
        $confirmations = [

            PWE_Multilang_GF_Confirmations::Confirmation(
                name: 'Default Confirmation',
                message: '',
                isDefault: true,
            ),
            
        ];

        $subjects = [
            'pl' => 'Potwierdzenie aktywacji rejestracji - {trade_fair_name}',
            'en' => 'Confirmation of activation of registration - {trade_fair_name}',
        ];

        $user_notification_variants = [

            [
                'name'     => 'Registration Activation',
                'template' => 'registration-activation.html',
                'attachQr' => true,
                'rules'    => [],
            ],

        ];

        // NOTIFICATIONS
        $notifications = [

            ...PWE_Multilang_GF_Notifications::Multilang_Notifications(
                $langs,
                [
                    [
                        'name' => 'Admin Notification',
                        'toType' => 'field',
                        'to' => 'idemail',
                        'fromName' => 'Aktywacja zaproszenia (PW) Potencjalny wystawca',
                        'subject' => 'Potwierdzenie aktywacji zaproszenia (PW) Potencjalny wystawca',
                        'message' => 'Pan/Pani {name:1} z firmy: {company:11}, aktywował/a zaproszenie (PW). <br>
<a href="https://warsawexpo.eu/assets/badge/local/loading.html?category=[trade_fair_badge]_vip_a6&getname={name:1}&firma={company:11}&qrcode={pwe_qr_url_encoded}">Link do "badge"</a> <br>
{all_fields}',
                    ],
                ]
            ),

            ...PWE_Multilang_GF_Notifications::Multilang_Notifications(
                $langs,
                $user_notification_variants,
                $subjects
            ),
        ];

        // PAYLOAD GF
        $payload = array_merge($metaSettings, [
            'title'         => $title,
            'description'   => '',
            'fields'        => $fields,
            'confirmations' => $confirmations,
            'notifications' => $notifications,
            '_formDir'      => __DIR__,
            'qr'            => [
                'enabled' => true,
            ],
            // 'separate_forms' => [
            //     'langs' => ['pl', 'en'],
            //     'titles' => [
            //         'pl' => '(' . $forms_year . ' PW) Potencjalny wystawca - aktywacja',
            //         'en' => '(' . $forms_year . ' PW) Potencjalny wystawca EN - aktywacja',
            //     ],
            //     'keep_rest' => false,
            // ],
            // '_template_langs' => $langs,
            'enableHoneypot' => true,
            'template_version' => '1.0.0',
        ]);

        if (!empty($GLOBALS['pwe_mlg_capture_template_payload'])) {
            $GLOBALS['pwe_mlg_captured_template_payload'] = $payload;
            return;
        }

        if ($existing) {
            PWE_Multilang_Form_Writer::update(
                $existing,
                $payload,
                [
                    'confirmations' => 'replace',
                    'notifications' => 'replace',
                ]
            );

            return;
        }

        PWE_Multilang_Form_Writer::create($payload);
    }
}
