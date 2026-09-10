<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_Form_Template_Napisz_Do_Nas
{
    public static function apply(?int $forms_year): void
    {
        $template_langs = ['pl', 'en', 'cs', 'de', 'sk', 'lt', 'lv', 'it', 'uk', 'ro', 'et'];

        $langs = PWE_Multilang_Form_Template_Languages::resolve(
            $template_langs
        );

        $title = 'Napisz do nas';

        $existing = PWE_Multilang_Form_Finder::byTitle($title);

        $metaSettings = PWE_Multilang_GF_Meta_Settings::build(
            $title,
            buttonText: 'Wyślij wiadomość',
        );

        $fields = [

            PWE_Multilang_GF_Fields::Text(
                label: 'Imię i Nazwisko',
                labelPlacement: 'hidden_label',
                adminLabel: 'name',
                placeholder: 'Imię i Nazwisko',
                required: true,
                cssClass: 'pwe-field__text--name',
            ),

            PWE_Multilang_GF_Fields::Email(
                label: 'Email',
                labelPlacement: 'hidden_label',
                adminLabel: 'email',
                placeholder: 'Email',
                required: true,
                cssClass: 'pwe-email-validate'
            ),

            PWE_Multilang_GF_Fields::Phone(
                label: 'Numer telefonu',
                labelPlacement: 'hidden_label',
                adminLabel: 'phone',
                placeholder: 'Numer telefonu',
                cssClass: 'pwe-phone-validate',
            ),

            PWE_Multilang_GF_Fields::Radio(
                label: 'Dział:',
                adminLabel: 'department',
                choices: [
                    ['text' => 'Wystawców', 'value' => 'Wystawców'],
                    ['text' => 'Odwiedzających', 'value' => 'Odwiedzających'],
                    ['text' => 'Dział Techniczny', 'value' => 'Dział Techniczny'],
                    ['text' => 'Marketingu', 'value' => 'Marketingu'],
                ],
                cssClass: 'gf_list_2col pwe-field__radio--department',
            ),

            PWE_Multilang_GF_Fields::Textarea(
                label: 'W czym możemy Ci pomóc?',
                labelPlacement: 'hidden_label',
                adminLabel: 'message',
                placeholder: 'W czym możemy Ci pomóc?',
                required: true,
                cssClass: 'pwe-field__textarea--message',
            ),

            PWE_Multilang_GF_Fields::UTM(),

            PWE_Multilang_GF_Fields::Lang(),

            PWE_Multilang_GF_Fields::Captcha(),
        ];

        $titles = [
            'pl' => 'Napisz do nas',
            'en' => 'Write to us',
            'cs' => 'Napište nám',
            'de' => 'Schreiben Sie uns',
            'sk' => 'Napíšte nám',
            'lt' => 'Parašykite mums',
            'lv' => 'Rakstiet mums',
            'it' => 'Scrivici',
            'uk' => 'Напишіть нам',
            'ro' => 'Scrie-ne',
            'et' => 'Kirjutage meile',
        ];

        $confirmation_messages = [
            'pl' => '<span style="color: #333399;"><strong>Dziękujemy za wypełnienie formularza kontaktowego. Już niebawem skontaktujemy się z Państwem.</strong></span>',
            'en' => '<span style="color: #333399;"><strong>Thank you for filling out the contact form. We will contact you soon.</strong></span>',
            'cs' => '<span style="color: #333399;"><strong>Děkujeme za vyplnění kontaktního formuláře. Brzy vás budeme kontaktovat.</strong></span>',
            'de' => '<span style="color: #333399;"><strong>Vielen Dank für das Ausfüllen des Kontaktformulars. Wir werden Sie in Kürze kontaktieren.</strong></span>',
            'sk' => '<span style="color: #333399;"><strong>Ďakujeme za vyplnenie kontaktného formulára. Čoskoro vás budeme kontaktovať.</strong></span>',
            'lt' => '<span style="color: #333399;"><strong>Dėkojame, kad užpildėte kontaktinę formą. Netrukus su jumis susisieksime.</strong></span>',
            'lv' => '<span style="color: #333399;"><strong>Paldies, ka aizpildījāt kontaktformu. Drīzumā ar jums sazināsimies.</strong></span>',
            'it' => '<span style="color: #333399;"><strong>Grazie per aver compilato il modulo di contatto. Ti contatteremo presto.</strong></span>',
            'uk' => '<span style="color: #333399;"><strong>Дякуємо за заповнення контактної форми. Ми зв’яжемося з вами найближчим часом.</strong></span>',
            'ro' => '<span style="color: #333399;"><strong>Vă mulțumim pentru completarea formularului de contact. Vă vom contacta în curând.</strong></span>',
            'et' => '<span style="color: #333399;"><strong>Täname kontaktivormi täitmise eest. Võtame teiega peagi ühendust.</strong></span>',
        ];

        $confirmations = [
            ...PWE_Multilang_GF_Confirmations::Multilang_Message_Confirmations(
                $langs,
                $confirmation_messages,
                'lang',
            ),
        ];

        $subjects = [
            'pl' => 'Formularz kontaktowy ze strony {trade_fair_name}',
            'en' => 'Contact form from {trade_fair_name}',
        ];

        $messages = [
            'pl' => "Ktoś wypełnił formularz kontaktowy na stronie {trade_fair_name}\n\n{all_fields}",
            'en' => "Someone filled out the contact form on {trade_fair_name} in language: {lang:7}\n\n{all_fields}",
        ];

        $fromNames = [
            'pl' => 'Kontakt {trade_fair_name}',
            'en' => 'Contact {trade_fair_name}',
        ];

        $admin_notification_variants = [
            [
                'name' => 'Admin Notification Kontakt',
                'toType' => 'email',
                'to'   => '{trade_fair_contact}',
                'rules' => [
                    [
                        'field'    => 'department',
                        'operator' => 'isnot',
                        'value'    => 'Dział Techniczny',
                    ],
                ],
            ],
            [
                'name' => 'Admin Notification Kontakt Tech',
                'toType' => 'email',
                'to'   => '{trade_fair_contact_tech}',
                'rules' => [
                    [
                        'field'    => 'department',
                        'operator' => 'is',
                        'value'    => 'Dział Techniczny',
                    ],
                ],
            ],
        ];

        $notifications = [];

        foreach ($admin_notification_variants as $variant) {
            foreach (['pl', 'en'] as $notification_lang) {
                $rules = $variant['rules'];
                $rules[] = [
                    'field'    => 'lang',
                    'operator' => $notification_lang === 'pl' ? 'is' : 'isnot',
                    'value'    => 'pl',
                ];

                $notifications[] = PWE_Multilang_GF_Notifications::Notification(
                    name: $variant['name'] . ' - ' . ($notification_lang === 'en' ? 'Abroad' : 'PL'),
                    toType: $variant['toType'],
                    to: $variant['to'],
                    subject: $subjects[$notification_lang],
                    message: $messages[$notification_lang],
                    fromName: $fromNames[$notification_lang],
                    conditionalLogic: [
                        'actionType' => 'show',
                        'logicType'  => 'all',
                        'rules'      => $rules,
                    ],
                );
            }
        }

        $payload = array_merge($metaSettings, [
            'title'         => $title,
            'description'   => '',
            'fields'        => $fields,
            'confirmations' => $confirmations,
            'notifications' => $notifications,
            '_formDir'      => __DIR__,
            'qr'            => [
                'enabled' => false,
            ],
            // 'separate_forms' => [
            //     'langs' => ['pl', 'en'],
            //     'render_langs' => [
            //         'en' => ['cs', 'de', 'sk', 'lt', 'lv', 'it', 'uk', 'ro', 'et'],
            //     ],
            //     'titles' => [
            //         'pl' => $titles['pl'],
            //         'en' => $titles['en'],
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
