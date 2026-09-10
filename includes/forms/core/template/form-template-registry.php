<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Discovers allowed templates and exposes their read-only payloads.
 */
final class PWE_Multilang_Form_Template_Registry
{
    private static ?array $providers = null;
    private static array $templateTitleCache = [];

    public static function getTemplateTitle(string $templateSlug, int $formsYear): ?string
    {
        $templateSlug = sanitize_key($templateSlug);
        $formsYear = PWE_Multilang_Year_Resolver::normalise($formsYear);

        if ($templateSlug === '') {
            return null;
        }

        if (array_key_exists($templateSlug, self::$templateTitleCache[$formsYear] ?? [])) {
            return self::$templateTitleCache[$formsYear][$templateSlug];
        }

        $provider = self::providers()[$templateSlug] ?? null;

        if (!$provider instanceof PWE_Multilang_Form_Template_Provider_Adapter) {
            self::$templateTitleCache[$formsYear][$templateSlug] = null;
            return null;
        }

        $payload = $provider->payload($formsYear);
        $title = trim((string) ($payload['title'] ?? ''));
        $resolved = $title !== '' ? $title : null;
        self::$templateTitleCache[$formsYear][$templateSlug] = $resolved;

        return $resolved;
    }

    public static function getTemplates(?int $formsYear = null): array
    {
        $formsYear = $formsYear === null
            ? PWE_Multilang_Year_Resolver::configured()
            : PWE_Multilang_Year_Resolver::normalise($formsYear);
        $templates = [];

        foreach (self::providers() as $slug => $provider) {
            $payload = $provider->payload($formsYear);

            if (empty($payload['title'])) {
                continue;
            }

            $targetPayloads = PWE_Multilang_Form_Payload_Expander::expand($payload);
            $targets = [];
            $existsCount = 0;
            $managedCount = 0;
            $manualModifiedCount = 0;
            $firstFormId = 0;

            foreach ($targetPayloads as $targetPayload) {
                if (!is_array($targetPayload)) {
                    continue;
                }

                $targetPayload = PWE_Multilang_Form_Identity::attach($targetPayload, $slug);
                $targetTitle = (string) ($targetPayload['title'] ?? '');
                $targetKey = (string) $targetPayload[PWE_Multilang_Form_Identity::TARGET_KEY];
                $existing = null;

                if (class_exists('GFAPI')) {
                    $existing = PWE_Multilang_Form_Finder::byIdentity($slug, $targetKey);

                    if (empty($existing) && $targetTitle !== '') {
                        $existing = PWE_Multilang_Form_Finder::byTitle($targetTitle);
                    }
                }

                $formId = !empty($existing['id']) ? (int) $existing['id'] : 0;

                if ($firstFormId === 0 && $formId > 0) {
                    $firstFormId = $formId;
                }

                if (!empty($existing)) {
                    $existsCount++;
                }

                if (!empty($existing) && PWE_Multilang_Form_Core::isManaged($existing)) {
                    $managedCount++;
                }

                if (PWE_Multilang_Form_Core::isManualModified($existing)) {
                    $manualModifiedCount++;
                }

                $targets[] = [
                    'title' => $targetTitle,
                    'payload' => $targetPayload,
                    'existing' => $existing,
                    'form_id' => $formId,
                    'lang' => $targetPayload['_pwe_separate_lang'] ?? null,
                    'is_separate' => !empty($targetPayload['_pwe_separate_forms']),
                    'target_key' => $targetKey,
                ];
            }

            $targetsCount = count($targets);
            $isSeparate = false;

            foreach ($targets as $target) {
                if (!empty($target['is_separate'])) {
                    $isSeparate = true;
                    break;
                }
            }

            $templates[$slug] = [
                'slug' => $slug,
                'name' => basename(dirname($provider->file())),
                'class' => $provider->className(),
                'file' => $provider->file(),
                'title' => (string) $payload['title'],
                'payload' => $payload,
                'targets' => $targets,
                'existing' => $targets[0]['existing'] ?? null,
                'form_id' => $firstFormId,
                'exists' => $targetsCount > 0 && $existsCount === $targetsCount,
                'managed' => $existsCount > 0 && $managedCount === $existsCount,
                'manual_modified' => $manualModifiedCount > 0,
                'is_separate' => $isSeparate,
                'targets_count' => $targetsCount,
                'exists_count' => $existsCount,
                'missing_count' => max(0, $targetsCount - $existsCount),
                'manual_modified_count' => $manualModifiedCount,
            ];
        }

        return $templates;
    }

    /**
     * @return array<string, PWE_Multilang_Form_Template_Provider_Adapter>
     */
    public static function providers(): array
    {
        if (self::$providers !== null) {
            return self::$providers;
        }

        $formsDir = dirname(__DIR__, 2) . '/form-templates';
        $providers = [];

        foreach (glob($formsDir . '/*', GLOB_ONLYDIR) ?: [] as $formDir) {
            $slug = basename($formDir);

            if (!PWE_Multilang_Site_Group::is_form_template_allowed($slug)) {
                continue;
            }

            $mainFile = $formDir . '/' . $slug . '.php';

            if (!is_file($mainFile)) {
                $mainFile = self::fallbackTemplateFile($formDir);
            }

            if ($mainFile === null) {
                continue;
            }

            $providers[$slug] = new PWE_Multilang_Form_Template_Provider_Adapter($slug, $mainFile);
        }

        ksort($providers);
        self::$providers = $providers;

        return self::$providers;
    }

    public static function resetCache(): void
    {
        self::$providers = null;
        self::$templateTitleCache = [];
    }

    private static function fallbackTemplateFile(string $formDir): ?string
    {
        foreach (glob($formDir . '/*.php') ?: [] as $file) {
            if (basename($file) !== 'field-translations.php') {
                return $file;
            }
        }

        return null;
    }
}
