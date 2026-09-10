<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Voucher_Generator {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Voucher Generator';

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
                label: 'Nazwa Firmy',
                adminLabel: 'company',
                cssClass: 'company pwe-field__text--company',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Język Zaproszenia',
                adminLabel: 'language',
                cssClass: 'language pwe-field__text--language',
            ),

        ];

        // CONFIRMATIONS
        $confirmations = [

        ];

        // NOTIFICATIONS
        $notifications = [

            PWE_Multilang_GF_Notifications::Notification(
                name: 'Admin Notification - PL',
                toType: 'text',
                to: '{admin_email}',
                subject: 'Nowa pozycja od {form_title}',
                message: '{all_fields}',
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
