<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Rejestracja_Fb {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl', 'en', 'cs', 'de', 'sk', 'lt', 'lv', 'it', 'uk', 'ro', 'et', 'fr', 'es'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Rejestracja (FB)';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Register',
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Text(
                label: 'First and last name',
                adminLabel: 'name',
                cssClass: 'pwe-field__text--name',
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'Email',
                adminLabel: 'email',
                placeholder: 'Email',
            ),

            PWE_Multilang_GF_Fields::Phone(
                label: 'Phone number',
                adminLabel: 'phone',
                placeholder: 'Phone number',
                cssClass: 'form-required',
            ),

            PWE_Multilang_GF_Fields::Lang(),

            PWE_Multilang_GF_Fields::UTM(),

            PWE_Multilang_GF_Fields::Captcha(),

        ];

        // CONFIRMATIONS
        $confirmations = [

            ...PWE_Multilang_GF_Confirmations::Multilang_Redirect_To_Translated_Page(
                name: 'Default Confirmation',
                baseSlug: 'krok2',
                langs: $langs,
            ),
            
        ];

        $subjects = [
            'pl' => 'Dziękujemy za rejestrację na {pwe_name_lang}',
            'en' => 'Thank you for registering on {pwe_name_lang}',
            'cs' => 'Děkujeme za registraci na veletrhu {pwe_name_lang}',
            'de' => 'Vielen Dank für Ihre Registrierung zur Messe {pwe_name_lang}',
            'sk' => 'Ďakujeme za registráciu na veľtrh {pwe_name_lang}',
            'lt' => 'Dėkojame už registraciją į mugę {pwe_name_lang}',
            'lv' => 'Paldies par reģistrāciju izstādei {pwe_name_lang}',
            'it' => 'Grazie per esserti registrato alla fiera {pwe_name_lang}',
            'uk' => 'Дякуємо за реєстрацію на виставку {pwe_name_lang}',
            'ro' => 'Vă mulțumim că v-ați înregistrat la târgul comercial {pwe_name_lang}',
            'et' => 'Täname teid {pwe_name_lang} messile registreerumise eest',
            'fr' => 'Merci de vous être inscrit au salon {pwe_name_lang}',
            'es' => 'Gracias por registrarte en la feria {pwe_name_lang}',
        ];

        $resend_subjects = [
            'pl' => 'Przypominamy o targach {pwe_name_lang}',
            'en' => '{pwe_name_lang} Reminder',
            'cs' => 'Připomínka veletrhu {pwe_name_lang}',
            'de' => 'Erinnerung an die Messe {pwe_name_lang}',
            'sk' => 'Pripomienka na veľtrh {pwe_name_lang}',
            'lt' => '{pwe_name_lang} parodos priminimas',
            'lv' => '{pwe_name_lang} izstādes atgādinājums',
            'it' => 'Promemoria della fiera {pwe_name_lang}',
            'uk' => 'Нагадування про виставку {pwe_name_lang}',
            'ro' => 'Memento pentru târgul {pwe_name_lang}',
            'et' => '{pwe_name_lang} messi meeldetuletus',
            'fr' => 'Rappel du salon {pwe_name_lang}',
            'es' => 'Recordatorio de la feria {pwe_name_lang}',
        ];

        $user_notification_variants = [
            [
                'name'     => 'Registration FB',
                'template' => 'registration.html',
                'rules'    => [],
            ],

            [
                'name'     => 'Resend',
                'template' => 'resend.html',
                'subjects' => $resend_subjects,
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
