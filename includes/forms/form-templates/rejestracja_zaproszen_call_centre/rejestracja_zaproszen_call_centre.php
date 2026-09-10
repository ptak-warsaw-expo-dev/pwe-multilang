<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Rejestracja_Zaproszen_Call_Centre {

    public static function apply(?int $forms_year) : void {

        $template_langs = ['pl'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        // Tytuł formularza (punkt odniesienia)
        $title = '(' . $forms_year . ') Rejestracja Zaproszeń - call centre PL/EN';

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
                labelPlacement: 'hidden_label',
                placeholder: 'First name and last name',
                adminLabel: 'name',
                cssClass: 'pwe-field__text--name',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'Email',
                labelPlacement: 'hidden_label',
                adminLabel: 'email',
                placeholder: 'Email',
                cssClass: 'pwe-email-validate',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'Phone',
                labelPlacement: 'hidden_label',
                placeholder: 'Phone number',
                cssClass: 'pwe-field__text--phone pwe-phone-validate',
                adminLabel: 'phone',
            ),

            PWE_Multilang_GF_Fields::Text(
                label: 'ID',
                labelPlacement: 'hidden_label',
                placeholder: 'ID - Only CallCentre',
                adminLabel: 'id',
                cssClass: 'pwe-field__text--id',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Dropdown(
                label: 'ID Platyna',
                adminLabel: 'id_platyna',
                cssClass: 'pwe-field__dropdown--id-platyna',
            ),

            PWE_Multilang_GF_Fields::Dropdown(
                label: 'Kanał wysyłki',
                placeholder: 'Wybierz kanał wysyłki zaproszenia',
                adminLabel: 'ccchannel',
                cssClass: 'pwe-field__dropdown--ccchannel',
                required: true,
            ),

            PWE_Multilang_GF_Fields::Consent(
                label: 'Consent to the processing of personal data',
                adminLabel: 'consent_marketing',
                checkboxLabel: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data for the purpose of sending messages. <span class="show-consent">(More)</span>',
                description: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data, i.e. 1) name and surname; 2) e-mail address; 3) telephone number for the purposes of sending marketing and commercial messages related to products and services offered by Ptak Warsaw Expo sp. z o.o. by means of electronic communication or direct remote communication, including receiving commercial information, pursuant to the Act of 18 July 2002 on the provision of services by electronic means. I know that the consent is voluntary but necessary for registration. I can withdraw my consent at any time.',
                cssClass: 'pwe-field__consent--marketing',
            ),

            PWE_Multilang_GF_Fields::Consent(
                label: 'Zgoda na przetwarzanie danych osobowych',
                adminLabel: 'consent_marketing_phone',
                checkboxLabel: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data for marketing purposes. <span class="show-consent">(More)</span>',
                description: 'I agree to the processing by PTAK WARSAW EXPO sp. z o.o. of my personal data, i.e. 1) name and surname; 2) telephone number for marketing purposes related to products and services offered by Ptak Warsaw Expo sp. z o.o. by means of terminal telecommunications equipment within the meaning of article 172 of the Act of 16 July 2014 - Telecommunications law. I know that consent is voluntary, but necessary for registration. I can withdraw my consent at any time. I know that the consent is voluntary but necessary for registration. I can withdraw my consent at any time.',
                cssClass: 'pwe-field__consent--marketing-phone',
            ),

            PWE_Multilang_GF_Fields::Captcha(),

            PWE_Multilang_GF_Fields::Text(
                label: 'Aktywacja',
                labelPlacement: 'hidden_label',
                placeholder: 'Aktywacja',
                adminLabel: 'activation',
                cssClass: 'pwe-field__text--activation',
                visibility: 'hidden',
            ),

        ];

        // CONFIRMATIONS
        $confirmations = [

            PWE_Multilang_GF_Confirmations::Confirmation(
                name: 'Default Confirmation',
                message: 'Rejestracja przebiegła pomyślnie,
                Proszę kontynuować',
            ),

        ];

        // NOTIFICATIONS
        $notifications = [

            PWE_Multilang_GF_Notifications::Notification(
                name: 'Registration Platyna - EN',
                toType: 'field',
                to: 'email',
                subject: 'VIP Invitation to the {trade_fair_name_eng}',
                template: 'registration-platyna-en.html',
                attachQr: true,
                conditionalLogic: [
                    'actionType' => 'show',
                    'logicType'  => 'all',
                    'rules'      => [
                        [
                            'field'    => 'ccchannel',
                            'operator' => 'is',
                            'value'    => 'Platyna EN',
                        ],
                    ],
                ],
            ),

            PWE_Multilang_GF_Notifications::Notification(
                name: 'Registration Platyna - PL',
                toType: 'field',
                to: 'email',
                subject: 'Zaproszenie VIP na targi {trade_fair_name}',
                template: 'registration-platyna-pl.html',
                attachQr: true,
                conditionalLogic: [
                    'actionType' => 'show',
                    'logicType'  => 'all',
                    'rules'      => [
                        [
                            'field'    => 'ccchannel',
                            'operator' => 'is',
                            'value'    => 'Platyna',
                        ],
                    ],
                ],
            ),

            PWE_Multilang_GF_Notifications::Notification(
                name: 'Registration CC - EN',
                toType: 'field',
                to: 'email',
                subject: 'Thank you for the registration at {trade_fair_name}',
                template: 'registration-cc-en.html',
                attachQr: true,
                conditionalLogic: [
                    'actionType' => 'show',
                    'logicType'  => 'any',
                    'rules'      => [
                        [
                            'field'    => 'ccchannel',
                            'operator' => 'is',
                            'value'    => 'SPECJALSI B ENG',
                        ],
                        [
                            'field'    => 'ccchannel',
                            'operator' => 'is',
                            'value'    => 'CC 1 SREBRNE ODWIEDZAJĄCY ENG',
                        ],
                        [
                            'field'    => 'ccchannel',
                            'operator' => 'is',
                            'value'    => 'SPECJALSI A ENG',
                        ],
                    ],
                ],
            ),

            PWE_Multilang_GF_Notifications::Notification(
                name: 'Registration CC - PL',
                toType: 'field',
                to: 'email',
                subject: 'Dziękujemy za rejestrację na {trade_fair_name}',
                template: 'registration-cc-pl.html',
                attachQr: true,
                conditionalLogic: [
                    'actionType' => 'show',
                    'logicType'  => 'any',
                    'rules'      => [
                        [
                            'field'    => 'ccchannel',
                            'operator' => 'is',
                            'value'    => 'SPECJALSI B PL',
                        ],
                        [
                            'field'    => 'ccchannel',
                            'operator' => 'is',
                            'value'    => 'CC 1 SREBRNE ODWIEDZAJĄCY',
                        ],
                        [
                            'field'    => 'ccchannel',
                            'operator' => 'is',
                            'value'    => 'SPECJALSI A PL',
                        ],
                    ],
                ],
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
