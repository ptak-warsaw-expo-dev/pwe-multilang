<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Notification_Templates
{
    public static function hydrate(array $notifications, string $formDir): array
    {
        foreach ($notifications as &$notification) {
            if (empty($notification['_template'])) {
                continue;
            }

            $template = ltrim($notification['_template'], '/');
            $lang = self::getLangFromTemplateName($template) ?: self::getLangFromNotification($notification);

            if ($lang) {
                $notification['_pwe_lang'] = $lang;
            }

            $templateBase = preg_replace('/(?:-[a-z]{2})?\.html$/', '', $template);
            $templateFullBase = preg_replace('/\.html$/', '', $template);
            $notificationBaseDir = rtrim($formDir, '/') . '/notifications/';

            // Prefer exact directory derived from full filename (e.g. platyna-en/platyna-en.html),
            // then fall back to language-stripped base (e.g. platyna/platyna.html).
            $candidateDirs = [];

            if ($templateFullBase !== '') {
                $candidateDirs[] = $notificationBaseDir . $templateFullBase;
            }

            if ($templateBase !== '' && $templateBase !== $templateFullBase) {
                $candidateDirs[] = $notificationBaseDir . $templateBase;
            }

            foreach ($candidateDirs as $templateDir) {
                $dirName = basename($templateDir);
                $customPath = $templateDir . '/' . $template;
                $defaultPath = $templateDir . '/' . $dirName . '.html';

                if (is_file($customPath)) {
                    $notification['message'] = file_get_contents($customPath);
                    break;
                }

                if (is_file($defaultPath)) {
                    $notification['message'] = file_get_contents($defaultPath);
                    break;
                }
            }

            if (!empty($notification['message']) && $lang) {
                $notification['message'] = self::applyTranslations($notification['message'], $lang, $templateBase, $formDir);
            }

            unset($notification['_template']);
        }

        unset($notification);

        return $notifications;
    }

    public static function replaceLangShortcodes(array $notifications): array
    {
        foreach ($notifications as &$notification) {
            $lang = self::getLangFromNotification($notification);

            if (!$lang) {
                continue;
            }

            $lang = esc_attr($lang);
            $urlLang = $lang === 'pl' ? '' : $lang . '/';

            foreach ($notification as $key => $value) {
                if (
                    $key === 'id'
                    || !is_string($value)
                    || (
                        strpos($value, '[pwe_name_lang]') === false
                        && strpos($value, '{pwe_name_lang}') === false
                        && strpos($value, 'https://[trade_fair_domainadress]/lang/?utm') === false
                    )
                ) {
                    continue;
                }

                $notification[$key] = str_replace(
                    [
                        '[pwe_name_lang]',
                        '{pwe_name_lang}',
                        'https://[trade_fair_domainadress]/lang/?utm',
                    ],
                    [
                        '[pwe_name_' . $lang . ']',
                        '{pwe_name_' . $lang . '}',
                        'https://[trade_fair_domainadress]/' . $urlLang . '?utm',
                    ],
                    $value
                );
            }
        }

        unset($notification);

        return $notifications;
    }

    private static function getLangFromTemplateName(string $template): ?string
    {
        if (preg_match('/-([a-z]{2})\.html$/', $template, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private static function applyTranslations(string $html, string $lang, string $templateBase, string $formDir): string
    {
        $translations = self::getTranslations($lang, $templateBase, $formDir);
        $translations['lang'] = $lang;

        foreach ($translations as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }

        return str_replace(
            ['{lang}', '%7Blang%7D', '%7B%7Blang%7D%7D'],
            $lang,
            $html
        );
    }

    private static function getTranslations(string $lang, string $templateBase, string $formDir): array
    {
        $file = rtrim($formDir, '/') . '/notifications/' . $templateBase . '/translations.php';

        if (!is_file($file)) {
            return [];
        }

        $translations = include $file;

        if (!is_array($translations)) {
            return [];
        }

        return $translations[$lang] ?? [];
    }

    private static function getLangFromNotification(array $notification): ?string
    {
        if (!empty($notification['_pwe_lang'])) {
            return strtolower($notification['_pwe_lang']);
        }

        if (!empty($notification['name'])) {
            if (preg_match('/-\s*([A-Z]{2})(?:\s-|$)/', $notification['name'], $matches)) {
                return strtolower($matches[1]);
            }

            if (preg_match('/-([a-z]{2})$/', $notification['name'], $matches)) {
                return strtolower($matches[1]);
            }
        }

        foreach (($notification['conditionalLogic']['rules'] ?? []) as $rule) {
            if (($rule['field'] ?? '') === 'lang' && !empty($rule['value'])) {
                return strtolower($rule['value']);
            }
        }

        return null;
    }
}
