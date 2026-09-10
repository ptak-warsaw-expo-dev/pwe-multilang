<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_GF_Conditional_Groups_Form_Preparer
{
    public static function prepare($form)
    {
        if (!is_array($form)) {
            return $form;
        }

        $property = PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY;

        foreach (($form['fields'] ?? []) as $field) {
            if (!is_object($field)) {
                continue;
            }

            $field->conditionalLogic = PWE_Multilang_GF_Conditional_Groups_Engine::compile(
                $field->conditionalLogic ?? null,
                $field->{$property} ?? []
            );

            if (isset($field->nextButton) && is_array($field->nextButton)) {
                $field->nextButton['conditionalLogic'] = PWE_Multilang_GF_Conditional_Groups_Engine::compile(
                    $field->nextButton['conditionalLogic'] ?? null,
                    $field->nextButton[$property] ?? []
                );
            }
        }

        if (isset($form['button']) && is_array($form['button'])) {
            $form['button']['conditionalLogic'] = PWE_Multilang_GF_Conditional_Groups_Engine::compile(
                $form['button']['conditionalLogic'] ?? null,
                $form['button'][$property] ?? []
            );
        }

        foreach (['notifications', 'confirmations'] as $collection) {
            if (!is_array($form[$collection] ?? null)) {
                continue;
            }

            foreach ($form[$collection] as &$item) {
                if (is_array($item)) {
                    $item['conditionalLogic'] = PWE_Multilang_GF_Conditional_Groups_Engine::compile(
                        $item['conditionalLogic'] ?? null,
                        $item[$property] ?? []
                    );
                }
            }
            unset($item);
        }

        return $form;
    }

    public static function prepare_feeds(array $feeds): array
    {
        $property = PWE_Multilang_GF_Conditional_Groups_Definition::PROPERTY;

        foreach ($feeds as &$feed) {
            if (!is_array($feed['meta'] ?? null)) {
                continue;
            }

            $definition = PWE_Multilang_GF_Conditional_Groups_Definition::normalise(
                $feed['meta'][$property] ?? []
            );

            if (empty($definition['groups'])) {
                continue;
            }

            foreach ($feed['meta'] as &$setting) {
                if (is_array($setting) && array_key_exists('conditionalLogic', $setting)) {
                    $setting['conditionalLogic'] = PWE_Multilang_GF_Conditional_Groups_Engine::compile(
                        $setting['conditionalLogic'],
                        $definition
                    );
                    break;
                }
            }
            unset($setting);
        }
        unset($feed);

        return $feeds;
    }
}

