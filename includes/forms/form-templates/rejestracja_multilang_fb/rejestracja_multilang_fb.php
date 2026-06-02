<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Rejestracja_Fb_Multilang {

    public static function apply(?int $forms_year) : void {

        $langs = array_keys(apply_filters('wpml_active_languages', null, [
            'skip_missing' => 0,
        ]) ?: []);

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Rejestracja Multilang (FB)';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Zarejestruj się',
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Text(
                label: 'First and last name',
                adminLabel: 'name',
                cssClass: 'First and last name',
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

            PWE_Multilang_GF_Fields::Text(
                label: 'Lang',
                adminLabel: 'lang',
                cssClass: 'lang',
                visibility: 'hidden',
            ),

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
            [
                'name'     => 'Thank you for registering for the Fair',
                'template' => 'thank-you-for-registering-for-the-fair-{lang}.html',
                'rules'    => [],
            ],

            [
                'name'     => 'Resend',
                'template' => 'resend-{lang}.html',
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
