<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Ceremonia_Medalowa {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl', 'en'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Ceremonia Medalowa';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Zatwierdź',
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Email(
                label: 'Adres e-mail',
                adminLabel: 'email',
                placeholder: 'Twój adres e-mail',
                required: true,
            ),

            // PWE_Multilang_GF_Fields::Checkbox(
            //     label: 'W jakiej kategorii konkursowej chcesz zgłosić swój udział?',
            //     labelPlacement: 'top_label',
            //     adminLabel: 'categories',
            //     required: true,
            //     choices: [
            //         ['text' => 'Premiera Targowa - Dla produktów lub usług prezentowanych po raz pierwszy – innowacyjne rozwiązania, które debiutują na rynku właśnie podczas targów.', 'value' => 'Premiera Targowa - Dla produktów lub usług prezentowanych po raz pierwszy – innowacyjne rozwiązania, które debiutują na rynku właśnie podczas targów.'],
            //         ['text' => 'Innowacyjność - Dla przełomowych technologii i koncepcji, które mają potencjał zmienić oblicze branży lub znacząco ją usprawnić.', 'value' => 'Innowacyjność - Dla przełomowych technologii i koncepcji, które mają potencjał zmienić oblicze branży lub znacząco ją usprawnić.'],
            //         ['text' => 'Ekspozycja Targowa - Za kreatywną, estetyczną i przyciągającą uwagę aranżację stoiska – wyróżniające się wizualnie wystąpienie targowe.', 'value' => 'Ekspozycja Targowa - Za kreatywną, estetyczną i przyciągającą uwagę aranżację stoiska – wyróżniające się wizualnie wystąpienie targowe.'],
            //         ['text' => 'Produkt Targowy - Za wyjątkowy, inspirujący i przełomowy produkt zaprezentowany podczas wydarzenia – prawdziwy „highlight” targów.', 'value' => 'Produkt Targowy - Za wyjątkowy, inspirujący i przełomowy produkt zaprezentowany podczas wydarzenia – prawdziwy „highlight” targów.'],
            //     ],
            // ),

            PWE_Multilang_GF_Fields::Text(
                label: 'W jakiej kategorii konkursowej chcesz zgłosić swój udział?',
                adminLabel: 'categories',
                placeholder: 'Twoja odpowiedź',
                required: true,
                visibility: 'hidden',
                cssClass: 'pwe-categories-cap pwe-field__text--categories',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Podaj nazwę produktu lub usługi, którą zgłaszasz do konkursu medalowego:',
                adminLabel: 'nazwa_produktu',
                placeholder: 'Twoja odpowiedź',
                cssClass: 'pwe-field__text--product-name',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Opisz krótko swój produkt lub usługę:',
                adminLabel: 'opis_produktu',
                placeholder: 'Twoja odpowiedź',
                cssClass: 'pwe-field__text--product-description',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Dlaczego Twój produkt/usługa zasługuje na medal?',
                adminLabel: 'dlaczego_produktu',
                placeholder: 'Twoja odpowiedź',
                cssClass: 'pwe-field__text--product-justification',
                required: true,
            ),


            PWE_Multilang_GF_Fields::File(
                label: 'Załącz zdjęcie produktu lub stoiska:',
                adminLabel: 'zdjecie_produktu',
                allowedExtensions: 'jpg, png, pdf, webp',
                maxFileSize: '1',
                required: true,
                cssClass: 'pwe-field__file--product-photo',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Imię i nazwisko osoby zgłaszającej:',
                adminLabel: 'imie_i_nazwisko',
                placeholder: 'Twoja odpowiedź',
                cssClass: 'pwe-field__text--name',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Nazwa Firmy:',
                adminLabel: 'nazwa_firmy',
                placeholder: 'Twoja odpowiedź',
                cssClass: 'pwe-field__text--company',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Phone(
                label: 'Telefon kontaktowy',
                adminLabel: 'phone',
                placeholder: 'Telefon kontaktowy',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'Adres e-mail osoby odpowiedzialnej za zgłoszenie:',
                adminLabel: 'email_osoby_zglaszajacej',
                placeholder: 'Twoja odpowiedź',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Numer stoiska (opcjonalnie):',
                adminLabel: 'stand_number',
                placeholder: 'Twoja odpowiedź',
                cssClass: 'pwe-field__text--stand',
            ),

            PWE_Multilang_GF_Fields::Radio(
                label: 'Zapoznałem(-am) się z regulaminem konkursu medalowego i akceptuję jego warunki.',
                labelPlacement: 'top_label',
                adminLabel: 'akceptacja_regulaminu',
                choices: [
                    ['text' => 'Tak, akceptuję regulamin.', 'value' => 'Tak, akceptuję regulamin.'],
                ],
                required: true,
                cssClass: 'pwe-field__radio--regulamin',
            ),

            PWE_Multilang_GF_Fields::Lang(),
            
        ];

        // CONFIRMATIONS
        $confirmation_messages = [
            'pl' => 'Dziękujemy za przesłanie zgłoszenia do konkursu medalowego. Skontaktujemy się z Tobą wkrótce.',
            'en' => 'Thank you for submitting your entry to the medal competition. We will contact you shortly.',
        ];

        $confirmations = [
            ...PWE_Multilang_GF_Confirmations::Multilang_Message_Confirmations(
                $langs,
                $confirmation_messages
            ),
        ];

        // NOTIFICATIONS
        $notifications = [

            ...PWE_Multilang_GF_Notifications::Multilang_Notifications(
                $langs,
                [
                    [
                        'name' => 'Admin Notification',
                        'toType' => 'email',
                        'to' => '{trade_fair_contact_medal_ceremony_email}',
                        'subject' => 'Zgłoszenie do ceremonii medalowej {trade_fair_name}',
                        'message' => '{trade_fair_name}
{all_fields}',
                        'disableAutoformat' => true,
                    ],
                ]
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
            //     'titles' => [
            //         'pl' => '(' . $forms_year . ') Ceremonia medalowa',
            //         'en' => '(' . $forms_year . ') Ceremonia medalowa (EN)',
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
