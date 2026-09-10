<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Zostan_Wystawca {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl', 'en', 'cs', 'de', 'sk', 'lt', 'lv', 'it', 'uk', 'ro', 'et', 'fr', 'es'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Zostań wystawcą';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Submit',
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Text(
                label: 'First and last name',
                adminLabel: 'name',
                placeholder: 'First and last name',
                cssClass: 'pwe-field__text--name',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'TAX ID',
                adminLabel: 'nip',
                placeholder: 'TAX ID',
                cssClass: 'pwe-field__text--nip',
                visibility: 'hidden',
            ),

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

            PWE_Multilang_GF_Fields::Textarea(
                label: 'Additional company information',
                adminLabel: 'company',
                placeholder: 'Additional company information',
                cssClass: 'pwe-field__textarea--company',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Select your exhibition space',
                adminLabel: 'area',
                placeholder: 'Select your exhibition space',
                cssClass: 'input-area pwe-field__text--area',
                visibility: 'hidden',
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
                label: 'patron',
                adminLabel: 'patron',
                cssClass: 'patron pwe-field__text--patron',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Consent(
                label: 'Consent to the processing of personal data',
                adminLabel: 'consent_marketing',
                checkboxLabel: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data for the purpose of sending messages. <span class="show-consent">(More)</span>',
                description: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data, i.e. 1) name and surname; 2) e-mail address; 3) telephone number for the purposes of sending marketing and commercial messages related to products and services offered by Ptak Warsaw Expo sp. z o.o. by means of electronic communication or direct remote communication, including receiving commercial information, pursuant to the Act of 18 July 2002 on the provision of services by electronic means. I know that the consent is voluntary but necessary for registration. I can withdraw my consent at any time.',
                cssClass: 'pwe-field__consent--marketing',
            ),

            PWE_Multilang_GF_Fields::Consent(
                label: 'Consent to the processing of personal data',
                adminLabel: 'consent_marketing_phone',
                checkboxLabel: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data for the purpose of sending messages. <span class="show-consent">(More)</span>',
                description: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data, i.e. 1) name and surname; 2) telephone number for marketing purposes related to products and services offered by Ptak Warsaw Expo sp. z o.o. by means of terminal telecommunications equipment within the meaning of article 172 of the Act of 16 July 2014 - Telecommunications law. I know that consent is voluntary, but necessary for registration. I can withdraw my consent at any time. I know that the consent is voluntary but necessary for registration. I can withdraw my consent at any time.',
                cssClass: 'pwe-field__consent--marketing-phone',
            ),

            PWE_Multilang_GF_Fields::Captcha(),

        ];

        // CONFIRMATIONS
        $confirmations = [

            ...PWE_Multilang_GF_Confirmations::Multilang_Redirect_To_Translated_Page(
                name: 'Default Confirmation',
                baseSlug: 'potwierdzenie-rejestracji-wystawcy',
                langs: $langs,
            ),

        ];

        $subjects = [
            'pl' => 'Dziękujemy za rejestrację jako wystawca na {trade_fair_name}',
            'en' => 'Thank you for registering as an exhibitor at {trade_fair_name}',
            'cs' => 'Děkujeme za registraci jako vystavovatel na veletrhu {pwe_name_lang}',
            'de' => 'Vielen Dank für Ihre Anmeldung als Aussteller auf der {pwe_name_lang}',
            'sk' => 'Ďakujeme za registráciu ako vystavovateľ na veľtrhu {pwe_name_lang}',
            'lt' => 'Dėkojame už registraciją kaip dalyviui parodoje {pwe_name_lang}',
            'lv' => 'Paldies, ka reģistrējāties kā izstādes dalībnieks izstādē {pwe_name_lang}',
            'it' => 'Grazie per esserti registrato come espositore alla {pwe_name_lang}',
            'uk' => 'Дякуємо за реєстрацію в якості учасника виставки {pwe_name_lang}',
            'ro' => 'Vă mulțumim că v-ați înregistrat la târgul comercial {pwe_name_lang}',
            'et' => 'Täname teid {pwe_name_lang} messile registreerumise eest',
            'fr' => 'Merci de vous être inscrit en tant qu’exposant au salon {pwe_name_lang}',
            'es' => 'Gracias por registrarte como expositor en la feria {pwe_name_lang}',
        ];

        $user_notification_variants = [
            [
                'name'     => 'Registration Confirmation',
                'template' => 'registration-confirmation.html',
                'rules'    => [
                    [
                        'field'    => 'patron',
                        'operator' => 'isnot',
                        'value'    => 'gr2',
                    ],
                ],
            ],

            [
                'name'     => 'Registration Confirmation Gr2',
                'template' => 'registration-confirmation-gr2.html',
                'rules'    => [
                    [
                        'field'    => 'patron',
                        'operator' => 'is',
                        'value'    => 'gr2',
                    ],
                ],
            ],

        ];

        // NOTIFICATIONS
        $notifications = [

            ...PWE_Multilang_GF_Notifications::Admin_Notification_Multilang(
                to: '{trade_fair_lidy}',
                subject: '{pwe_name_lang} - nowa rejestracja Wystawcy WWW1'
            ),

            ...PWE_Multilang_GF_Notifications::Multilang_Notifications(
                $langs,
                $user_notification_variants,
                $subjects
            ),

            ...PWE_Multilang_GF_Notifications::Admin_Notification_Multilang(
                name: 'Admin Notification Potwierdzenie',
                to: '{trade_fair_lidy}',
                subject: '{pwe_name_lang} - potwierdzenie rejestracji Wystawcy WWW1',
                isActive: false
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
