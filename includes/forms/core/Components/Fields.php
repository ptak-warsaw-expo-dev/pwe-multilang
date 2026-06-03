<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_GF_Fields {

    /* ---------- Consent ---------- */
    public static function Consent(
        ?string $label = null,
        ?string $adminLabel = null,
        bool $required = true,
        string $visibility = 'visible',
        string $checkboxLabel = '',
        string $description = '',
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        $conditionalLogic = null,
    ) : array
    {
        $base = [
            'id'              => null,
            'type'            => 'consent',
            'inputType'       => 'consent',
            'label'           => $label,
            'adminLabel'      => $adminLabel ?? $label,
            'inputName'       => null,
            'labelPlacement'  => 'hidden_label',
            'isRequired'      => $required,
            'visibility'      => $visibility,

            // STRINGI – zgodnie z GF_Field_Consent
            'checkboxLabel'   => $checkboxLabel,
            'description'     => $description,

            // WYMAGANE STRUKTURY
            'inputs' => [
                [ 'id' => null . '.1', 'label' => 'Zgoda', 'name' => '' ],
                [ 'id' => null . '.2', 'label' => 'Tekst', 'name' => '', 'isHidden' => true ],
                [ 'id' => null . '.3', 'label' => 'Opis', 'name' => '', 'isHidden' => true ],
            ],

            'choices' => [
                [
                    'text'       => 'Zaznaczone',
                    'value'      => '1',
                    'isSelected' => false,
                    'price'      => '',
                ],
            ],

            'layoutGridColumnSpan' => $layoutGridColumnSpan,
            'cssClass' => trim('pwe-field__consent ' . ($cssClass ?? '')),

            'conditionalLogic' => $conditionalLogic,
        ];

        return $base;
    }

    /* ---------- Text ---------- */
    public static function Text(
        ?string $label = null,
        ?string $adminLabel = null,
        ?string $placeholder = '',
        ?string $labelPlacement = 'top_label',
        bool $required = false,
        string $visibility = 'visible',
        string $size = 'large',
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        $conditionalLogic = null,
    ) : array {

        $base = [
            'id'            => null,
            'type'          => 'text',
            'label'         => $label,
            'adminLabel'    => $adminLabel ?? $label,
            'placeholder'   => $placeholder,
            'labelPlacement' => $labelPlacement,
            'defaultValue'  => '',
            'isRequired'    => $required,
            'visibility'    => $visibility,
            'size'          => $size,
            'maxLength'     => null,
            'layoutGridColumnSpan' => $layoutGridColumnSpan,
            'cssClass'      => trim('pwe-field__text ' . ($cssClass ?? '')),
            'conditionalLogic' => $conditionalLogic,
        ];

        return $base;
    }

    /* ---------- Email ---------- */
    public static function Email(
        ?string $label = null,
        ?string $adminLabel = null,
        ?string $placeholder = '',
        ?string $labelPlacement = 'top_label',
        bool $required = true,
        string $visibility = 'visible',
        string $size = 'large',
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        $conditionalLogic = null,
    ) : array {

        $base = [
            'id'            => null,
            'type'          => 'email',
            'label'         => $label,
            'adminLabel'    => $adminLabel ?? $label,
            'inputName'     => null,
            'placeholder'   => $placeholder,
            'labelPlacement' => $labelPlacement,
            'defaultValue'  => '',
            'isRequired'    => $required,
            'visibility'    => $visibility,
            'size'          => $size,
            'emailConfirmEnabled' => false,
            'layoutGridColumnSpan' => $layoutGridColumnSpan,
            'cssClass'      => trim('pwe-field__email ' . ($cssClass ?? '')),
            'conditionalLogic' => $conditionalLogic,
        ];

        return $base;
    }

    /* ---------- Phone ---------- */
    public static function Phone(
        ?string $label = null,
        ?string $adminLabel = null,
        ?string $placeholder = '',
        ?string $labelPlacement = 'top_label',
        bool $required = true,
        string $visibility = 'visible',
        string $size = 'large',
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        string $phoneFormat = 'international',
        string $defaultCountryGField = 'DEF',
        $conditionalLogic = null,
    ): array {
        $base = [
            'id'            => null,
            'type'          => 'phone',
            'label'         => $label,
            'adminLabel'    => $adminLabel ?? $label,
            'inputName'     => null,
            'placeholder'   => $placeholder,
            'labelPlacement' => $labelPlacement,
            'defaultValue'  => '',
            'isRequired'    => $required,
            'visibility'    => $visibility,
            'size'          => $size,
            'phoneFormat'   => $phoneFormat,
            'defaultCountryGField' => $defaultCountryGField,
            'smartPhoneFieldGField' => empty($defaultCountryGField) ? false : true,
            'layoutGridColumnSpan' => $layoutGridColumnSpan,
            'cssClass'      => trim('pwe-field__phone ' . ($cssClass ?? '')),
            'conditionalLogic' => $conditionalLogic,
        ];

        return $base;
    }

    /* ---------- HIDDEN ---------- */
    public static function Hidden(
        ?string $label = null,
        ?string $adminLabel = null,
    ) : array {

        $base = [
            'id'           => null,
            'type'         => 'hidden',
            'label'        => $label,
            'adminLabel'   => $adminLabel ?? $label,
            'defaultValue' => '',
            'isRequired'   => false,
            'visibility'   => 'visible',
            'cssClass'      => 'pwe-field__hidden',
            'conditionalLogic' => [],
            'inputs'       => null,
        ];

        return $base;
    }

    /* ---------- TEXTAREA ---------- */
    public static function Textarea(
        ?string $label = null,
        ?string $adminLabel = null,
        ?string $placeholder = '',
        bool $required = false,
        string $size = 'medium',
        string $visibility = 'visible',
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        $conditionalLogic = null,
    ) : array {

        $base = [
            'id'                => null,
            'type'              => 'textarea',
            'label'             => $label,
            'adminLabel'        => $adminLabel ?? $label,
            'isRequired'        => $required,
            'size'              => $size,
            'placeholder'       => $placeholder,
            'description'       => '',
            'labelPlacement'    => 'top_label',
            'descriptionPlacement' => 'below',
            'subLabelPlacement' => 'below',
            'visibility'        => $visibility,
            'layoutGridColumnSpan' => 12,
            'cssClass'          => trim('pwe-field__textarea ' . ($cssClass ?? '')),
            'conditionalLogic'  => $conditionalLogic,
            'inputs'            => null,
        ];

        return $base;
    }

    /* ---------- RATING (RADIO) ---------- */
    public static function Radio(
        ?string $label = null,
        ?string $adminLabel = null,
        bool $required = true,
        string $visibility = 'visible',
        ?string $labelPlacement = 'hidden_label',
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        array $choices = [],
        $conditionalLogic = null,
    ) : array {

        $base = [
            'id'           => null,
            'type'         => 'radio',
            'label'        => $label,
            'adminLabel'   => $adminLabel ?? $label,
            'isRequired'   => $required,
            'size'         => 'large',
            'layoutGridColumnSpan' => $layoutGridColumnSpan,
            'cssClass'     => trim('rating ' . ($cssClass ?? '')),
            'labelPlacement' => $labelPlacement,
            'enableChoiceValue' => true,
            'choices'      => $choices,
            'visibility'   => $visibility,
            'conditionalLogic' => $conditionalLogic,
            'inputs'       => null,
        ];

        return $base;
    }

    /* ---------- SELECT ---------- */
    public static function Select(
        ?string $label = null,
        ?string $adminLabel = null,
        array $choices = [],
        ?string $placeholder = '',
        ?string $labelPlacement = 'top_label',
        bool $required = false,
        string $visibility = 'visible',
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        $conditionalLogic = null,
    ) : array {

        return [
            'id'             => null,
            'type'           => 'select',
            'label'          => $label,
            'adminLabel'     => $adminLabel ?? $label,
            'placeholder'    => $placeholder,
            'labelPlacement' => $labelPlacement,
            'isRequired'     => $required,
            'visibility'     => $visibility,
            'size'           => 'large',
            'enableChoiceValue' => true,
            'choices'        => $choices,
            'layoutGridColumnSpan' => $layoutGridColumnSpan,
            'cssClass'       => trim('pwe-field__select ' . ($cssClass ?? '')),
            'conditionalLogic' => $conditionalLogic,
            'inputs'         => null,
        ];
    }

    /* ---------- CHECKBOX ---------- */
    public static function Checkbox(
        ?string $label = null,
        ?string $adminLabel = null,
        array $choices = [],
        bool $required = false,
        string $visibility = 'visible',
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        $conditionalLogic = null,
    ) : array {

        $inputs = [];
        $i = 1;

        foreach ($choices as $choice) {
            $inputs[] = [
                'id'    => null . '.' . $i,
                'label' => $choice['text'] ?? '',
                'name'  => '',
            ];
            $i++;
        }

        return [
            'id'              => null,
            'type'            => 'checkbox',
            'label'           => $label,
            'adminLabel'      => $adminLabel ?? $label,
            'isRequired'      => $required,
            'visibility'      => $visibility,
            'labelPlacement'  => 'top_label',
            'enableChoiceValue' => true,

            'choices'         => $choices,
            'inputs'          => $inputs,

            'layoutGridColumnSpan' => $layoutGridColumnSpan,
            'cssClass'        => trim('pwe-field__checkbox ' . ($cssClass ?? '')),
            'conditionalLogic'=> $conditionalLogic,
        ];
    }

    /* ---------- FILE UPLOAD ---------- */
    public static function File(
        ?string $label = null,
        ?string $adminLabel = null,
        bool $required = false,
        string $visibility = 'visible',
        ?string $allowedExtensions = 'pdf,jpg,jpeg,png,doc,docx',
        int $maxFileSize = 10, // MB
        bool $multipleFiles = false,
        int $layoutGridColumnSpan = 12,
        ?string $cssClass = null,
        $conditionalLogic = null,
    ) : array {

        return [
            'id'            => null,
            'type'          => 'fileupload',
            'label'         => $label,
            'adminLabel'    => $adminLabel ?? $label,
            'isRequired'    => $required,
            'visibility'    => $visibility,
            'labelPlacement'=> 'top_label',

            'allowedExtensions' => $allowedExtensions,
            'maxFileSize'       => $maxFileSize,
            'multipleFiles'     => $multipleFiles,

            'layoutGridColumnSpan' => $layoutGridColumnSpan,
            'cssClass'      => trim('pwe-field__file ' . ($cssClass ?? '')),
            'conditionalLogic' => $conditionalLogic,
            'inputs'        => null,
        ];
    }

    /* ---------- Text ---------- */
    public static function Captcha() : array {

        $base = [
            'id'              => null,
            'type'            => 'captcha',
            'label'           => 'CAPTCHA',
            'labelPlacement'  => 'hidden_label',
        ];

        return $base;
    }

    /* ---------- Text ---------- */
    public static function UTM() : array {

        $base = [
            'id'            => null,
            'type'          => 'text',
            'label'         => 'UTM',
            'adminLabel'    => 'pwe_utm',
            'placeholder'   => '',
            'defaultValue'  => '',
            'isRequired'    => false,
            'visibility'    => 'hidden',
            'size'          => 'large',
            'cssClass'      => 'utm-class',
        ];

        return $base;
    }
}
