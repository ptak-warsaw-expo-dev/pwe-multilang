<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Sync_Result
{
    public static function create(string $scope, bool $addOnly): array
    {
        return [
            'scope' => $scope,
            'add_only' => $addOnly,
            'status' => 'pending',
            'items' => [],
            'errors' => [],
        ];
    }

    public static function item(array $template, array $target, string $scope): array
    {
        $slug = (string) ($template['slug'] ?? $template['name'] ?? '');

        $item = [
            'template' => (string) ($template['name'] ?? $slug),
            'template_slug' => $slug,
            'title' => (string) ($target['title'] ?? ''),
            'form_id' => (int) ($target['form_id'] ?? 0),
            'action' => $scope,
            'status' => 'pending',
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
            'details' => [],
            'diff' => [],
        ];

        if (!empty($target['lang'])) {
            $item['lang'] = $target['lang'];
        }

        if (!empty($target['target_key'])) {
            $item['target_key'] = $target['target_key'];
        }

        return $item;
    }

    public static function locked(string $scope, bool $addOnly): array
    {
        $result = self::create($scope, $addOnly);
        $result['status'] = 'locked';
        $result['errors'][] = 'Inna synchronizacja formularzy jest już w toku. Spróbuj ponownie za chwilę.';

        return $result;
    }

    public static function finalize(array $result): array
    {
        $hasSuccess = false;
        $hasFailure = !empty($result['errors']);
        $hasSkipped = false;

        foreach (($result['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $status = (string) ($item['status'] ?? '');

            if ($status === 'success') {
                $hasSuccess = true;
            } elseif ($status === 'skipped') {
                $hasSkipped = true;
            } elseif ($status === 'failed' || !empty($item['errors'])) {
                $hasFailure = true;
            }
        }

        if ($hasFailure) {
            $result['status'] = $hasSuccess ? 'partial' : 'failed';
        } elseif ($hasSkipped) {
            $result['status'] = $hasSuccess ? 'partial' : 'skipped';
        } else {
            $result['status'] = 'success';
        }

        return $result;
    }

    public static function successfulTemplateSlugs(array $result, array $requestedSlugs): array
    {
        $states = [];

        foreach ($requestedSlugs as $slug) {
            $slug = sanitize_key((string) $slug);

            if ($slug !== '') {
                $states[$slug] = [
                    'seen' => false,
                    'success' => true,
                ];
            }
        }

        foreach (($result['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = sanitize_key((string) ($item['template_slug'] ?? $item['template'] ?? ''));

            if (!isset($states[$slug])) {
                continue;
            }

            $states[$slug]['seen'] = true;

            if ((string) ($item['status'] ?? '') !== 'success' || !empty($item['errors'])) {
                $states[$slug]['success'] = false;
            }
        }

        $successful = [];

        foreach ($states as $slug => $state) {
            if ($state['seen'] && $state['success']) {
                $successful[] = $slug;
            }
        }

        return $successful;
    }
}
