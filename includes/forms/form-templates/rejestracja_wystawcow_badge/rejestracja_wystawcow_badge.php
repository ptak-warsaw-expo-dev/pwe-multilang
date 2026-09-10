<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Rejestracja_Wystawcow_Badge {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl', 'en'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Rejestracja wystawców (badge)';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Wyślij',
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Text(
                label: 'IMIĘ I NAZWISKO (PRACOWNIKA)',
                labelPlacement: 'hidden_label',
                placeholder: 'IMIĘ I NAZWISKO (PRACOWNIKA)',
                adminLabel: 'name',
                cssClass: 'pwe-field__text--name',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'FIRMA ZAPRASZAJĄCA',
                labelPlacement: 'hidden_label',
                placeholder: 'FIRMA ZAPRASZAJĄCA',
                adminLabel: 'company',
                cssClass: 'pwe-field__text--company',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'E-MAIL OSOBY ZAPRASZANEJ',
                labelPlacement: 'hidden_label',
                placeholder: 'E-MAIL OSOBY ZAPRASZANEJ',
                adminLabel: 'email',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Lang(),

            PWE_Multilang_GF_Fields::Captcha(),

        ];

        // CONFIRMATIONS
        $confirmations = [

            PWE_Multilang_GF_Confirmations::Confirmation(
                name: 'Default Confirmation',
                type: 'redirect',
                url: '{embed_url}',
            ),

        ];

        $subjects = [
            'pl' => '{company:5} zaprasza na targi i kongres {trade_fair_name}',
            'en' => '{company:5} invites to the trade fair and congress {trade_fair_name}',
        ];

        $admin_subjects = [
            'pl' => 'Nowa pozycja od {form_title}',
            'en' => 'New entry from {form_title}',
        ];

        $admin_messages = [
            'pl' => '<a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category=[trade_fair_badge]_wystawca_a6&getname={name:1}&firma={company:5}&qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Wygeneruj identyfikator</a>
<a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category=empty_wystawca_a6&getname={name:1}&firma={company:5}&qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Wygeneruj pusty identyfikator</a>
{all_fields}',
            'en' => '<a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category=[trade_fair_badge]_wystawca_a6&getname={name:1}&firma={company:5}&qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Generate badge</a>
<a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category=empty_wystawca_a6&getname={name:1}&firma={company:5}&qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Generate empty badge</a>
{all_fields}',
        ];

        $admin_notification_variants = [
            [
                'name' => 'Admin Notification',
                'toType' => 'email',
                'to' => 'identyfikatorywystawcow@warsawexpo.eu',
                'attachQr' => false,
            ],
        ];

        $user_notification_variants = [

            [
                'name'     => 'Registration Invites',
                'template' => 'registration-invites.html',
                'attachQr' => false,
                'rules'    => [],
            ],

        ];

        // NOTIFICATIONS
        $notifications = [

            ...PWE_Multilang_GF_Notifications::Multilang_Notifications(
                $langs,
                $admin_notification_variants,
                $admin_subjects,
                $admin_messages
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
            //         'pl' => 'Rejestracja wystawców (badge) PL',
            //         'en' => 'Rejestracja wystawców (badge) EN',
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
