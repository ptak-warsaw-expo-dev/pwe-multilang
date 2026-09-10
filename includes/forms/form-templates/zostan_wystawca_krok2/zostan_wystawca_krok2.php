<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Zostan_Wystawca_Krok2 {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl', 'en', 'cs', 'de', 'sk', 'lt', 'lv', 'it', 'uk', 'ro', 'et', 'fr', 'es'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Zostań wystawcą (krok2)';

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
                cssClass: "pwe-field__text--name",
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'TAX ID',
                adminLabel: 'nip',
                placeholder: 'TAX ID',
                cssClass: "pwe-field__text--nip",
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'Email',
                adminLabel: 'email',
                placeholder: 'Email',
                required: true,
                cssClass: 'form-required',
            ),

            PWE_Multilang_GF_Fields::Phone(
                label: 'Phone number',
                adminLabel: 'phone',
                placeholder: 'Phone number',
                required: false,
                defaultCountryGField: '',
            ),

            PWE_Multilang_GF_Fields::Textarea(
                label: 'Additional company information',
                adminLabel: 'company_information',
                placeholder: 'Additional company information',
                cssClass: 'pwe-field__textarea--company',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Select your exhibition space',
                adminLabel: 'area',
                placeholder: 'Select your exhibition space',
                cssClass: 'input-area pwe-field__text--area',
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
        $confirmation_messages = [
            'pl' => 'Dziękujemy za uzupełnienie danych. Do usłyszenia już wkrótce. Zespół Ptak Warsaw Expo',
            'en' => 'Thank you for completing the data. We look forward to hearing from you soon. Ptak Warsaw Expo Team',
            'cs' => 'Děkujeme za vyplnění údajů. Brzy vás budeme kontaktovat. Tým Ptak Warsaw Expo',
            'de' => 'Vielen Dank für das Ausfüllen der Daten. Wir werden uns in Kürze bei Ihnen melden. Ptak Warsaw Expo Team',
            'sk' => 'Ďakujeme za vyplnenie údajov. Čoskoro vás budeme kontaktovať. Tím Ptak Warsaw Expo',
            'lt' => 'Dėkojame, kad užpildėte duomenis. Netrukus su jumis susisieksime. Ptak Warsaw Expo komanda',
            'lv' => 'Paldies, ka aizpildījāt datus. Mēs drīzumā ar jums sazināsimies. Ptak Warsaw Expo komanda',
            'it' => 'Grazie per aver completato i dati. Ti contatteremo presto. Team Ptak Warsaw Expo',
            'uk' => 'Дякуємо за заповнення даних. Незабаром ми з вами зв’яжемося. Команда Ptak Warsaw Expo',
            'ro' => 'Vă mulțumim pentru completarea datelor. Vă vom contacta în curând. Echipa Ptak Warsaw Expo',
            'et' => 'Täname andmete täitmise eest. Võtame teiega peagi ühendust. Ptak Warsaw Expo meeskond',
            'fr' => 'Merci d’avoir rempli les données. Nous vous contacterons bientôt. L’équipe Ptak Warsaw Expo',
            'es' => 'Gracias por completar los datos. Nos pondremos en contacto con usted pronto. Equipo de Ptak Warsaw Expo',
        ];

        $confirmations = [

            ...PWE_Multilang_GF_Confirmations::Multilang_Message_Confirmations(
                $langs,
                $confirmation_messages
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
