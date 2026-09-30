<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Admin_Template_Presenter
{
    public static function statusLabel(array $template): string
    {
        if (!empty($template['is_separate'])) {
            $status = sprintf(
                'formularze osobne: %d/%d istnieje',
                (int) ($template['exists_count'] ?? 0),
                (int) ($template['targets_count'] ?? 0)
            );

            if (!empty($template['manual_modified_count'])) {
                $status .= sprintf(
                    ', %d edytowany ręcznie',
                    (int) $template['manual_modified_count']
                );
            }

            return $status;
        }

        if (empty($template['exists'])) {
            return 'brak';
        }

        if (!empty($template['managed'])) {
            return !empty($template['manual_modified'])
                ? 'PWE Multilang, edytowany ręcznie'
                : 'zarządzany przez PWE Multilang';
        }

        return 'ręczny / niezarządzany';
    }

    public static function formIdsLabel(array $template): string
    {
        $ids = [];

        foreach (($template['targets'] ?? []) as $target) {
            if (!is_array($target)) {
                continue;
            }

            $formId = (int) ($target['form_id'] ?? 0);

            if ($formId > 0) {
                $ids[] = $formId;
            }
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return 'ID: -';
        }

        return 'ID: ' . implode(', ', $ids);
    }

    public static function statusClass(array $template): string
    {
        if (empty($template['exists'])) {
            return 'is-missing';
        }

        if (!empty($template['managed']) && !empty($template['manual_modified'])) {
            return 'is-modified';
        }

        return !empty($template['managed']) ? 'is-managed' : 'is-unmanaged';
    }
}