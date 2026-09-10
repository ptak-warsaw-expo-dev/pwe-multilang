<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Rejestracja_Gosci_Wystawcow {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl', 'en'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Rejestracja gości wystawców';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Wyślij',
            settings: ['markupVersion' => 2],
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Text(
                label: 'Gość - Imię i Nazwisko',
                labelPlacement: 'hidden_label',
                placeholder: 'Gość - Imię i Nazwisko',
                adminLabel: 'name',
                cssClass: 'pwe-field__text--name',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Firma Zapraszająca',
                labelPlacement: 'hidden_label',
                placeholder: 'Firma Zapraszająca',
                adminLabel: 'company',
                cssClass: 'pwe-field__text--company',
                required: true,
                allowsPrepopulate: true,
                inputName: 'generator',
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'E-mail osoby zapraszanej',
                labelPlacement: 'hidden_label',
                placeholder: 'E-mail osoby zapraszanej',
                adminLabel: 'email',
                required: true,
            ),

            // PWE_Multilang_GF_Fields::Text(
            //     label: 'Numer stoiska',
            //     placeholder: 'Numer stoiska',
            //     adminLabel: 'exhibitor_stand',
            //     cssClass: 'exhibitor_stand',
            //     required: true,
            //     layoutGridColumnSpan: 4,
            // ),

            // PWE_Multilang_GF_Fields::File(
            //     label: 'Dodaj logo swojej firmy',
            //     adminLabel: 'input_logo',
            //     cssClass: 'input_logo',
            //     allowedExtensions : 'jpg, png',
            //     maxFileSize: 1,
            //     required: true,
            //     layoutGridColumnSpan: 8,
            // ),

            // PWE_Multilang_GF_Fields::Text(
            //     label: 'Telefon',
            //     labelPlacement: 'hidden_label',
            //     placeholder: 'Telefon',
            //     adminLabel: 'phone',
            //     cssClass: 'phone',
            //     visibility: 'hidden',
            // ),

            // PWE_Multilang_GF_Fields::Text(
            //     label: 'Logotyp wystwacy',
            //     labelPlacement: 'hidden_label',
            //     placeholder: 'Logotyp wystwacy',
            //     adminLabel: 'exhibitor_logo',
            //     cssClass: 'exhibitor_logo',
            //     visibility: 'hidden',
            // ),

            // PWE_Multilang_GF_Fields::Text(
            //     label: 'Nazwa Wystawcy Badge',
            //     labelPlacement: 'hidden_label',
            //     placeholder: 'Nazwa Wystawcy Badge',
            //     adminLabel: 'exhibitors_name',
            //     cssClass: 'exhibitors_name',
            //     visibility: 'hidden',
            // ),

            // PWE_Multilang_GF_Fields::Text(
            //     label: 'Opis wystawcy',
            //     labelPlacement: 'hidden_label',
            //     placeholder: 'Opis wystawcy',
            //     adminLabel: 'exhibitor_desc',
            //     cssClass: 'exhibitor_desc',
            //     visibility: 'hidden',
            // ),

            // PWE_Multilang_GF_Fields::Text(
            //     label: 'patron',
            //     labelPlacement: 'hidden_label',
            //     placeholder: 'patron',
            //     adminLabel: 'patron',
            //     cssClass: 'patron pwe-field__text--patron',
            //     visibility: 'hidden',
            // ),


            // PWE_Multilang_GF_Fields::Checkbox(
            //     label: 'Brak firmy na identyfikatorze',
            //     labelPlacement: 'hidden_label',
            //     adminLabel: 'badge_name',
            //     cssClass: 'badge_name',
            //     visibility: 'hidden',
            //     choices: [
            //         [
            //             'text'  => 'Brak uwzględnienia nazwy firmy na identyfikatorze',
            //             'value' => 'yes',
            //         ],
            //     ],
            // ),

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
            'pl' => '{Firma Zapraszająca:5} zaprasza na targi i kongres {trade_fair_name}',
            'en' => '{Firma Zapraszająca:5} invites you to the fair and congress {trade_fair_name}',
        ];

        $admin_subjects = [
            'pl' => 'Test Nowa pozycja od {form_title}',
            'en' => 'Test New entry from {form_title}',
        ];

        $admin_messages = [
            'pl' => '<a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category=[trade_fair_badge]_vipgold_a6&getname={Gość - Imię i Nazwisko:1}&firma={Firma Zapraszająca:5}&qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Wygeneruj identyfikator</a>
<a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category=empty_vipgold_a6&getname={Gość - Imię i Nazwisko:1}&firma={Firma Zapraszająca:5}&qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Wygeneruj pusty identyfikator</a>
{all_fields}',
            'en' => '<a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category=[trade_fair_badge]_vipgold_a6&getname={Gość - Imię i Nazwisko:1}&firma={Firma Zapraszająca:5}&qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Generate badge</a>
<a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category=empty_vipgold_a6&getname={Gość - Imię i Nazwisko:1}&firma={Firma Zapraszająca:5}&qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Generate empty badge</a>
{all_fields}',
        ];

        $admin_notification_variants = [
            [
                'name' => 'Admin Notification',
                'toType' => 'email',
                'to' => 'vipgold@warsawexpo.eu',
                'attachQr' => false,
            ],
        ];

        $user_notification_variants = [

            // [
            //     'name'     => 'Registration Gr1',
            //     'template' => 'registration-gr1.html',
            //     'attachQr' => true,
            //     'rules'    => [
            //         [
            //             'field'    => 'patron',
            //             'operator' => 'is',
            //             'value'    => 'gr1',
            //         ],
            //     ],
            // ],

            // [
            //     'name'     => 'Registration Gr2',
            //     'template' => 'registration-gr2.html',
            //     'attachQr' => true,
            //     'rules'    => [
            //         [
            //             'field'    => 'patron',
            //             'operator' => 'is',
            //             'value'    => 'gr2',
            //         ],
            //     ],
            // ],

            // [
            //     'name'     => 'Registration Patron',
            //     'template' => 'registration-patron.html',
            //     'attachQr' => true,
            //     'rules'    => [
            //         [
            //             'field'    => 'patron',
            //             'operator' => 'is',
            //             'value'    => 'patron',
            //         ],
            //     ],
            // ],

            // [
            //     'name'     => 'Bussines Premium Pass Gr3',
            //     'template' => 'bussines-premium-pass-gr3.html',
            //     'attachQr' => true,
            //     'rules'    => [
            //         [
            //             'field'    => 'patron',
            //             'operator' => 'is',
            //             'value'    => 'gr3',
            //         ],
            //     ],
            // ],

            [
                'name'     => 'Registration Vip Gold',
                'template' => 'registration-vip-gold.html',
                'attachQr' => true,
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
            //         'pl' => 'Rejestracja gości wystawców PL',
            //         'en' => 'Rejestracja gości wystawców EN',
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
