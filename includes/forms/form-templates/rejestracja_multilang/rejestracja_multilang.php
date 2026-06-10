<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Rejestracja_Multilang {

    public static function apply(?int $forms_year) : void {

        $langs = array_keys(apply_filters('wpml_active_languages', null, [
            'skip_missing' => 0,
        ]) ?: []);

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Rejestracja Multilang';

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
                cssClass: 'form-required',
            ),

            PWE_Multilang_GF_Fields::Phone(
                label: 'Phone number',
                adminLabel: 'phone',
                placeholder: 'Phone number',
                cssClass: 'form-required',
            ),

            PWE_Multilang_GF_Fields::UTM(),


            PWE_Multilang_GF_Fields::Text(
                label: 'Lang',
                adminLabel: 'lang',
                cssClass: 'lang',
                visibility: 'hidden',
            ),
            
            PWE_Multilang_GF_Fields::Text(
                label: 'country',
                adminLabel: 'country',
                cssClass: 'country',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'location',
                adminLabel: 'location',
                cssClass: 'location',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'patron',
                adminLabel: 'patron',
                cssClass: 'patron',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Consent(
                label: 'Consent to the processing of personal data',
                adminLabel: 'consent_marketing',
                checkboxLabel: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. my personal data for marketing purposes and sending messages.  <span class="show-consent">(More)</span>',
                description: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data, i.e. 1) name and surname; 2) e-mail address; 3) telephone number for the purposes of sending marketing and commercial messages related to products and services offered by Ptak Warsaw Expo sp. z o.o. by means of electronic communication or direct remote communication, including receiving commercial information, pursuant to the Act of 18 July 2002 on the provision of services by electronic means. I know that the consent is voluntary but necessary for registration. I can withdraw my consent at any time.',
            ),

            PWE_Multilang_GF_Fields::Captcha(),

        ];

        // CONFIRMATIONS
        $confirmations = [

            ...PWE_Multilang_GF_Confirmations::Multilang_Redirect_To_Translated_Page(
                name: 'Default Confirmation',
                baseSlug: 'krok2',
                langs: $langs,
            ),

            ...PWE_Multilang_GF_Confirmations::Multilang_Redirect_To_Translated_Page_With_Any_Rules(
                name: 'Potwierdzenie rejestracji',
                baseSlug: 'potwierdzenie-rejestracji',
                langs: $langs,
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
                        'field'    => 'location',
                        'operator' => 'is',
                        'value'    => 'wydarzenia',
                    ],
                ],
            ),
        ];

        $subjects = [
            'cs' => 'Děkujeme za registraci na veletrhu {pwe_name_lang}',
            'de' => 'Vielen Dank für Ihre Registrierung zur Messe {pwe_name_lang}',
            'sk' => 'Ďakujeme za registráciu na veľtrh {pwe_name_lang}',
            'lt' => 'Dėkojame už registraciją į mugę {pwe_name_lang}',
            'lv' => 'Paldies par reģistrāciju izstādei {pwe_name_lang}',
            'it' => 'Grazie per esserti registrato alla fiera {pwe_name_lang}',
            'uk' => 'Дякуємо за реєстрацію на виставку {pwe_name_lang}',
            'ro' => 'Vă mulțumim că v-ați înregistrat la târgul comercial {pwe_name_lang}',
            'et' => 'Täname teid {pwe_name_lang} messile registreerumise eest',
        ];

        $user_notification_variants = [
            // [
            //     'name'     => 'Thank you for registering for the Fair - (gr1)',
            //     'template' => 'thank-you-for-registering-for-the-fair-gr1-{lang}.html',
            //     'attachQr' => true,
            //     'rules'    => [
            //         [
            //             'field'    => 'patron',
            //             'operator' => 'is',
            //             'value'    => 'gr1',
            //         ],
            //     ],
            // ],

            [
                'name'     => 'Thank you for registering for the Fair',
                'template' => 'thank-you-for-registering-for-the-fair-gr2-{lang}.html',
                'attachQr' => true,
                'rules'    => [
                    // [
                    //     'field'    => 'patron',
                    //     'operator' => 'is',
                    //     'value'    => 'gr2',
                    // ],
                    [
                        'field'    => 'location',
                        'operator' => 'isnot',
                        'value'    => 'platyna',
                    ],
                ],
            ],

            [
                'name'     => 'Thank you for registering for the Fair - platyna',
                'template' => 'thank-you-for-registering-for-the-fair-gr2-platyna-{lang}.html',
                'attachQr' => true,
                'rules'    => [
                    // [
                    //     'field'    => 'patron',
                    //     'operator' => 'is',
                    //     'value'    => 'gr2',
                    // ],
                    [
                        'field'    => 'location',
                        'operator' => 'is',
                        'value'    => 'platyna',
                    ],
                ],
            ],

            // [
            //     'name'     => 'Thank you for registering for the Fair - (gr3)',
            //     'template' => 'thank-you-for-registering-for-the-fair-gr3-{lang}.html',
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
                'name'     => 'Resend',
                'template' => 'resend-{lang}.html',
                'isActive' => false,
                'attachQr' => true,
                'rules'    => [],
            ],

            [
                'name'     => 'Platyna RESEND - edytowana kopia [(platyna) - Aktywacja]',
                'template' => 'platyna-resend-edytowana-kopia-platyna-aktywacja-{lang}.html',
                'attachQr' => true,
                'isActive' => false,
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
        ]);

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
