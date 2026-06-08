<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Zostan_Wystawca_krok2_Multilang {

    public static function apply(?int $forms_year) : void {

        $langs = array_keys(apply_filters('wpml_active_languages', null, [
            'skip_missing' => 0,
        ]) ?: []);

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Zostań wystawcą Multilang (krok2)';

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
                label: 'First and last name',
                adminLabel: 'name',
                placeholder: 'First and last name',
                required: true,
                cssClass: "pwe-field__text",
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'TAX ID',
                adminLabel: 'nip',
                placeholder: 'TAX ID',
                cssClass: "pwe-field__text"
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'Email',
                adminLabel: 'email',
                placeholder: 'Email',
                required: true,
                cssClass: 'pwe-field__email form-required',
            ),

            PWE_Multilang_GF_Fields::Phone(
                label: 'Phone number',
                adminLabel: 'phone',
                placeholder: 'Phone number',
                cssClass: 'pwe-field__phone form-required',
            ),

            PWE_Multilang_GF_Fields::Textarea(
                label: 'Additional company information',
                adminLabel: 'company_information',
                placeholder: 'Additional company information',
                cssClass: 'pwe-field__textarea',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Select your exhibition space',
                adminLabel: 'area',
                placeholder: 'Select your exhibition space',
                cssClass: 'pwe-field__text input-area',
            ),

            PWE_Multilang_GF_Fields::UTM(),

            PWE_Multilang_GF_Fields::Text(
                label: 'Lang',
                adminLabel: 'lang',
                cssClass: 'pwe-field__text lang',
                visibility: 'hidden',
            ),
            
            PWE_Multilang_GF_Fields::Text(
                label: 'country',
                adminLabel: 'country',
                cssClass: 'pwe-field__text country',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'patron',
                adminLabel: 'patron',
                cssClass: 'pwe-field__text patron',
                visibility: 'hidden',
            ),

            PWE_Multilang_GF_Fields::Consent(
                label: 'Consent to the processing of personal data',
                adminLabel: 'consent_marketing',
                checkboxLabel: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data for the purpose of sending messages. <span class="show-consent">(More)</span>',
                description: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data, i.e. 1) name and surname; 2) e-mail address; 3) telephone number for the purposes of sending marketing and commercial messages related to products and services offered by Ptak Warsaw Expo sp. z o.o. by means of electronic communication or direct remote communication, including receiving commercial information, pursuant to the Act of 18 July 2002 on the provision of services by electronic means. I know that the consent is voluntary but necessary for registration. I can withdraw my consent at any time.',
                cssClass: 'pwe-field__consent',
            ),

            PWE_Multilang_GF_Fields::Consent(
                label: 'Consent to the processing of personal data',
                adminLabel: 'consent_marketing_phone',
                checkboxLabel: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data for the purpose of sending messages. <span class="show-consent">(More)</span>',
                description: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data, i.e. 1) name and surname; 2) telephone number for marketing purposes related to products and services offered by Ptak Warsaw Expo sp. z o.o. by means of terminal telecommunications equipment within the meaning of article 172 of the Act of 16 July 2014 - Telecommunications law. I know that consent is voluntary, but necessary for registration. I can withdraw my consent at any time. I know that the consent is voluntary but necessary for registration. I can withdraw my consent at any time.',
                cssClass: 'pwe-field__consent',
            ),

            PWE_Multilang_GF_Fields::Captcha(),

        ];

        // CONFIRMATIONS
        $confirmation_messages = [
            'cs' => 'Děkujeme za vyplnění údajů. Brzy vás budeme kontaktovat. Tým Ptak Warsaw Expo',
            'de' => 'Vielen Dank für das Ausfüllen der Daten. Wir werden uns in Kürze bei Ihnen melden. Ptak Warsaw Expo Team',
            'sk' => 'Ďakujeme za vyplnenie údajov. Čoskoro vás budeme kontaktovať. Tím Ptak Warsaw Expo',
            'lt' => 'Dėkojame, kad užpildėte duomenis. Netrukus su jumis susisieksime. Ptak Warsaw Expo komanda',
            'lv' => 'Paldies, ka aizpildījāt datus. Mēs drīzumā ar jums sazināsimies. Ptak Warsaw Expo komanda',
            'it' => 'Grazie per aver completato i dati. Ti contatteremo presto. Team Ptak Warsaw Expo',
            'uk' => 'Дякуємо за заповнення даних. Незабаром ми з вами зв’яжемося. Команда Ptak Warsaw Expo',
            'ro' => 'Vă mulțumim pentru completarea datelor. Vă vom contacta în curând. Echipa Ptak Warsaw Expo',
            'et' => 'Täname andmete täitmise eest. Võtame teiega peagi ühendust. Ptak Warsaw Expo meeskond',
        ];

        $confirmations = [

            ...PWE_Multilang_GF_Confirmations::Multilang_Message_Confirmations(
                $langs,
                $confirmation_messages
            ),
        ];

        $subjects = [
            'cs' => 'Děkujeme za registraci jako vystavovatel na veletrhu {pwe_name_lang}',
            'de' => 'Vielen Dank für Ihre Anmeldung als Aussteller auf der {pwe_name_lang}',
            'sk' => 'Ďakujeme za registráciu ako vystavovateľ na veľtrhu {pwe_name_lang}',
            'lt' => 'Dėkojame už registraciją kaip dalyviui parodoje {pwe_name_lang}',
            'lv' => 'Paldies, ka reģistrējāties kā izstādes dalībnieks izstādē {pwe_name_lang}',
            'it' => 'Grazie per esserti registrato come espositore alla {pwe_name_lang}',
            'uk' => 'Дякуємо за реєстрацію в якості учасника виставки {pwe_name_lang}',
            'ro' => 'Vă mulțumim că v-ați înregistrat la târgul comercial {pwe_name_lang}',
            'et' => 'Täname teid {pwe_name_lang} messile registreerumise eest',
        ];

        $user_notification_variants = [
            [
                'name'     => 'Exhibitor registration confirmation - gr1',
                'template' => 'exhibitor-registration-confirmation-gr1-{lang}.html',
                'rules'    => [
                    [
                        'field'    => 'patron',
                        'operator' => 'is',
                        'value'    => 'gr1',
                    ],
                ],
            ],

            [
                'name'     => 'Exhibitor registration confirmation - gr2',
                'template' => 'exhibitor-registration-confirmation-gr2-{lang}.html',
                'rules'    => [
                    [
                        'field'    => 'patron',
                        'operator' => 'is',
                        'value'    => 'gr2',
                    ],
                ],
            ],

            [
                'name'     => 'Exhibitor registration confirmation - gr3',
                'template' => 'exhibitor-registration-confirmation-gr3-{lang}.html',
                'rules'    => [
                    [
                        'field'    => 'patron',
                        'operator' => 'is',
                        'value'    => 'gr3',
                    ],
                ],
            ],
        ];

        // NOTIFICATIONS
        $notifications = [

            ...PWE_Multilang_GF_Notifications::Admin_Notification_Multilang(
                to: '{trade_fair_lidy}',
                subject: '{pwe_name_lang} - nowa rejestracja Wystawcy WWW2'
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
