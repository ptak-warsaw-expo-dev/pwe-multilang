<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Badge_Generator_Local {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Badge generator(local)';

        // Sprawdź, czy formularz już istnieje
        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        // META + SETTINGS
        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Zatwierdź',
        );

        // POLA
        $fields = [

            PWE_Multilang_GF_Fields::Text(
                label: 'Imię i nazwisko',
                labelPlacement: 'hidden_label',
                placeholder: 'Imię i nazwisko',
                adminLabel: 'name',
                cssClass: 'pwe-field__text--name',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Firma',
                labelPlacement: 'hidden_label',
                placeholder: 'Firma',
                adminLabel: 'company',
                cssClass: 'pwe-field__text--company',
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'Email',
                labelPlacement: 'hidden_label',
                placeholder: 'Email',
                adminLabel: 'email',
                required: false,
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Tel',
                labelPlacement: 'hidden_label',
                placeholder: 'Tel',
                adminLabel: 'phone',
                cssClass: 'pwe-field__text--phone',
            ),

            PWE_Multilang_GF_Fields::Radio(
                label: 'Wybierz:',
                adminLabel: 'category',
                cssClass: 'pwe-field__radio--category',
                required: true,
                choices: [
                    ['text' => '_gosc_a6', 'value' => '_gosc_a6'],
                    ['text' => '_vipgold_a6', 'value' => '_vipgold_a6'],
                    ['text' => '_wystawca_a6', 'value' => '_wystawca_a6'],
                    ['text' => '_empty_wystawca_a6', 'value' => '_empty_wystawca_a6'],
                    ['text' => '_media_a6', 'value' => '_media_a6'],
                    ['text' => '_obsluga_a6', 'value' => '_obsluga_a6'],
                    ['text' => '_prelegent_a6', 'value' => '_prelegent_a6'],
                    ['text' => '_konferencja_a6', 'value' => '_konferencja_a6'],
                ],
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Uwagi',
                labelPlacement: 'hidden_label',
                placeholder: 'Uwagi',
                adminLabel: 'notes',
                cssClass: 'pwe-field__text--notes',
            ),

        ];

        // CONFIRMATIONS
        $confirmations = [

            PWE_Multilang_GF_Confirmations::Confirmation(
                name: 'Default Confirmation',
                message: '<a style="background-color: #4caf50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://[trade_fair_domainadress]/identyfikatory/">Powrót do generatora</a><a style="background-color: #f44336; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;" href="https://warsawexpo.eu/assets/badge/local/loading.html?category={Wybierz::3}&amp;getname={Imię i Nazwisko:1}&amp;firma={Firma:2}&amp;qrcode={pwe_qr_url_encoded}" target="_blank" rel="noopener">Wygeneruj badge</a>',
                isDefault: true,
                disableAutoformat: true,
            ),
            
        ];

        // NOTIFICATIONS
        $notifications = [

            PWE_Multilang_GF_Notifications::Notification(
                name: 'Notification Badge (local)',
                toType: 'email',
                to: 'rejestracja@warsawexpo.eu, badge@warsawexpo.eu',
                subject: 'Drukarka {trade_fair_name} - Badge {Wybierz::3} {Imię i Nazwisko:1}',
                message: 'Imię i Nazwisko:<strong> {Imię i Nazwisko:1}</strong> <br>
                        Firma: <strong>{Firma:2}</strong><br>
                        Category: {Wybierz::3}<br>
                        Mail: {Email:6} <br>
                        Tel: {Tel:5} <br>
                        Uwagi: {Uwagi:4} <br>
                        <a href="https://warsawexpo.eu/assets/badge/local/loading.html?category={Wybierz::3}&getname={Imię i Nazwisko:1}&firma={Firma:2}&qrcode={pwe_qr_url_encoded}">Pobierz badge</a>'
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
