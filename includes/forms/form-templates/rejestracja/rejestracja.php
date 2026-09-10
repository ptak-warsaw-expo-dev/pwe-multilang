<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Rejestracja {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl', 'en', 'cs', 'de', 'sk', 'lt', 'lv', 'it', 'uk', 'ro', 'et', 'fr', 'es'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Rejestracja';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Register',
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Email(
                label: 'Email',
                adminLabel: 'email',
                placeholder: 'Email',
                cssClass: 'form-required pwe-email-validate',
            ),

            PWE_Multilang_GF_Fields::Phone(
                label: 'Phone number',
                adminLabel: 'phone',
                placeholder: 'Phone number',
                cssClass: 'form-required pwe-phone-validate',
            ),

            PWE_Multilang_GF_Fields::UTM(),

            PWE_Multilang_GF_Fields::Lang(),
            
            PWE_Multilang_GF_Fields::Text(
                label: 'country',
                adminLabel: 'country',
                cssClass: 'country pwe-field__text--country',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'location',
                adminLabel: 'location',
                cssClass: 'location pwe-field__text--location',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'patron',
                adminLabel: 'patron',
                cssClass: 'patron pwe-field__text--patron',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Consent(
                label: 'Consent to the processing of personal data',
                adminLabel: 'consent_marketing',
                checkboxLabel: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. my personal data for marketing purposes and sending messages.  <span class="show-consent">(More)</span>',
                description: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data, i.e. 1) name and surname; 2) e-mail address; 3) telephone number for the purposes of sending marketing and commercial messages related to products and services offered by Ptak Warsaw Expo sp. z o.o. by means of electronic communication or direct remote communication, including receiving commercial information, pursuant to the Act of 18 July 2002 on the provision of services by electronic means. I know that the consent is voluntary but necessary for registration. I can withdraw my consent at any time.',
                cssClass: 'pwe-field__consent--marketing',
            ),

            PWE_Multilang_GF_Fields::Captcha(),

            PWE_Multilang_GF_Fields::Text(
                label: 'Imię i nazwisko',
                adminLabel: 'name',
                visibility: 'hidden',
                cssClass: 'pwe-field__text--name',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Ulica',
                adminLabel: 'street',
                visibility: 'hidden',
                cssClass: 'pwe-field__text--street',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Numer domu',
                adminLabel: 'house',
                visibility: 'hidden',
                cssClass: 'pwe-field__text--house',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Numer lokalu',
                adminLabel: 'apartment',
                visibility: 'hidden',
                cssClass: 'pwe-field__text--apartment',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Kod pocztowy',
                adminLabel: 'post',
                visibility: 'hidden',
                cssClass: 'pwe-field__text--post',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Miasto',
                adminLabel: 'city',
                visibility: 'hidden',
                cssClass: 'pwe-field__text--city',
            ),

        ];

        // CONFIRMATIONS
        $confirmation_messages = [
            'pl' => 'Dziękujemy za rejestrację',
            'en' => 'Thank you for registering',
            'cs' => 'Děkujeme za registraci',
            'de' => 'Vielen Dank für Ihre Registrierung',
            'sk' => 'Ďakujeme za registráciu',
            'lt' => 'Dėkojame už registraciją',
            'lv' => 'Paldies par reģistrāciju',
            'it' => 'Grazie per la registrazione',
            'uk' => 'Дякуємо за реєстрацію',
            'ro' => 'Vă mulțumim pentru înregistrare',
            'et' => 'Täname registreerumise eest',
            'fr' => 'Merci pour votre inscription',
            'es' => 'Gracias por registrarte',
        ];

        $confirmations = [

            ...PWE_Multilang_GF_Confirmations::Multilang_Redirect_To_Translated_Page(
                name: 'Default Confirmation',
                baseSlug: 'krok2',
                langs: $langs,
                allRules: [
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'not_contains',
                        'value'    => 'utm_source=byli',
                    ],
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'not_contains',
                        'value'    => 'utm_source=premium',
                    ],
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'not_contains',
                        'value'    => 'utm_source=platyna',
                    ],
                ],
            ),

            ...PWE_Multilang_GF_Confirmations::Multilang_Redirect_To_Translated_Page_With_Any_Rules(
                name: 'Potwierdzenie rejestracji',
                baseSlug: 'potwierdzenie-rejestracji',
                langs: ['pl'],
                anyRules: [
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'contains',
                        'value'    => 'utm_source=byli',
                    ],
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'contains',
                        'value'    => 'utm_source=premium',
                    ],
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'contains',
                        'value'    => 'utm_source=platyna',
                    ],
                ],
            ),

            ...PWE_Multilang_GF_Confirmations::Multilang_Redirect_To_Translated_Page_With_Any_Rules(
                name: 'Potwierdzenie rejestracji',
                baseSlug: 'krok2',
                langs: array_values(array_diff($langs, ['pl'])),
                anyRules: [
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'contains',
                        'value'    => 'utm_source=byli',
                    ],
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'contains',
                        'value'    => 'utm_source=premium',
                    ]
                ],
            ),

            ...PWE_Multilang_GF_Confirmations::Multilang_Message_Confirmations_With_Any_Rules(
                langs: array_values(array_diff($langs, ['pl'])),
                messages: $confirmation_messages,
                anyRules: [
                    [
                        'field'    => 'pwe_utm',
                        'operator' => 'contains',
                        'value'    => 'utm_source=platyna',
                    ],
                ],
            ),
        ];

        $subjects = [
            'pl' => 'Dziękujemy za rejestrację na {trade_fair_name}',
            'en' => 'Thank you for registering on {trade_fair_name}',
            'cs' => 'Děkujeme za registraci na veletrhu {pwe_name_lang}',
            'de' => 'Vielen Dank für Ihre Registrierung zur Messe {pwe_name_lang}',
            'sk' => 'Ďakujeme za registráciu na veľtrh {pwe_name_lang}',
            'lt' => 'Dėkojame už registraciją į mugę {pwe_name_lang}',
            'lv' => 'Paldies par reģistrāciju izstādei {pwe_name_lang}',
            'it' => 'Grazie per esserti registrato alla fiera {pwe_name_lang}',
            'uk' => 'Дякуємо за реєстрацію на виставку {pwe_name_lang}',
            'ro' => 'Vă mulțumim că v-ați înregistrat la târgul comercial {pwe_name_lang}',
            'et' => 'Täname teid {pwe_name_lang} messile registreerumise eest',
            'fr' => 'Merci de vous être inscrit à {pwe_name_lang}',
            'es' => 'Gracias por registrarte en {pwe_name_lang}',
        ];

        $user_notification_variants = [

            [
                'name'     => 'Registration',
                'template' => 'registration.html',
                'attachQr' => true,
                'rules'    => [
                    [
                        'field'    => 'location',
                        'operator' => 'isnot',
                        'value'    => 'platyna',
                    ],
                ],
            ],

            [
                'name'     => 'Registration Platyna',
                'template' => 'registration-platyna.html',
                'attachQr' => true,
                'rules'    => [
                    [
                        'field'    => 'location',
                        'operator' => 'is',
                        'value'    => 'platyna',
                    ],
                ],
            ],

            [
                'name'     => 'Resend',
                'template' => 'resend.html',
                'isActive' => false,
                'attachQr' => true,
                'subjects' => [
                    'pl' => 'Przypominamy o targach {pwe_name_lang}',
                    'en' => 'Reminder about the trade fair {pwe_name_lang}',
                    'cs' => 'Připomínáme veletrh {pwe_name_lang}',
                    'de' => 'Wir erinnern an die Messe {pwe_name_lang}',
                    'sk' => 'Pripomíname veľtrh {pwe_name_lang}',
                    'lt' => 'Primename apie mugę {pwe_name_lang}',
                    'lv' => 'Atgādinām par izstādi {pwe_name_lang}',
                    'it' => 'Promemoria per la fiera {pwe_name_lang}',
                    'uk' => 'Нагадуємо про виставку {pwe_name_lang}',
                    'ro' => 'Vă reamintim despre târgul {pwe_name_lang}',
                    'et' => 'Tuletame meelde messi {pwe_name_lang}',
                    'fr' => 'Rappel sur le salon {pwe_name_lang}',
                    'es' => 'Recordatorio sobre la feria {pwe_name_lang}',
                ],
                'rules'    => [],
            ],

            [
                'name'     => 'Resend Platyna',
                'template' => 'resend-platyna.html',
                'attachQr' => true,
                'isActive' => false,
                'subjects' => [
                    'pl' => 'Zaproszenie VIP {pwe_name_lang}',
                    'en' => 'VIP invitation {pwe_name_lang}',
                    'cs' => 'VIP pozvánka {pwe_name_lang}',
                    'de' => 'VIP-Einladung {pwe_name_lang}',
                    'sk' => 'VIP pozvánka {pwe_name_lang}',
                    'lt' => 'VIP kvietimas {pwe_name_lang}',
                    'lv' => 'VIP ielūgums {pwe_name_lang}',
                    'it' => 'Invito VIP {pwe_name_lang}',
                    'uk' => 'VIP-запрошення {pwe_name_lang}',
                    'ro' => 'Invitație VIP {pwe_name_lang}',
                    'et' => 'VIP kutse {pwe_name_lang}',
                    'fr' => 'Invitation VIP {pwe_name_lang}',
                    'es' => 'Invitación VIP {pwe_name_lang}',
                ],
                'rules'    => [],
            ],
        ];

        // NOTIFICATIONS
        $notifications = [

            ...PWE_Multilang_GF_Notifications::Admin_Notification_Multilang(),

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
