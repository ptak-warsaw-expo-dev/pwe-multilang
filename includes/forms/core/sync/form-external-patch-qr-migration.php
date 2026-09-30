<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_External_Patch_QR_Migration
{
    public static function apply(string $key): array
    {
        $item = [
            'template' => 'external-patch:' . ($key !== '' ? $key : 'migration_to_new_qr'),
            'title' => 'migration_to_new_qr',
            'form_id' => 0,
            'action' => 'external_patch',
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
            'details' => [],
            'diff' => [],
        ];
        $forms = GFAPI::get_forms();

        if (!is_array($forms)) {
            $item['errors'][] = 'migration_to_new_qr: nie udało się pobrać formularzy';
            return $item;
        }

        $feeds = GFAPI::get_feeds();

        if (!is_array($feeds)) {
            $item['errors'][] = 'migration_to_new_qr: nie udało się pobrać feedów';
            return $item;
        }

        $formsWithQr = self::collectFormIdsWithQrFeeds($feeds);

        if (empty($formsWithQr)) {
            $item['details'][] = 'migration_to_new_qr: brak formularzy z feedem QR';
            $item['skipped'][] = 'forms';
            return $item;
        }

        foreach ($forms as $summary) {
            if (
                PWE_Multilang_Form_Operation_Lock::ownedByCurrentRequest()
                && !PWE_Multilang_Form_Operation_Lock::refreshOwned()
            ) {
                $item['errors'][] = 'migration_to_new_qr: przerwano, ponieważ utracono blokadę operacji';
                break;
            }

            $formId = (int) ($summary['id'] ?? ($summary->id ?? 0));

            if ($formId <= 0 || empty($formsWithQr[$formId])) {
                continue;
            }

            $form = GFAPI::get_form($formId);

            if (!is_array($form)) {
                $item['errors'][] = 'migration_to_new_qr: nie udało się pobrać pełnego formularza ID ' . $formId;
                $item['details'][] = 'migration_to_new_qr: pominięto formularz ID ' . $formId . ' - brak danych formularza';
                continue;
            }

            $title = (string) ($form['title'] ?? ($summary['title'] ?? ($summary->title ?? '')));

            if ($title === '') {
                $item['errors'][] = 'migration_to_new_qr: formularz ID ' . $formId . ' nie ma tytułu';
                $item['details'][] = 'migration_to_new_qr: pominięto formularz ID ' . $formId . ' - pusty tytuł';
                continue;
            }

            $hadNewFeed = self::hasFeedSlug($feeds, $formId, 'pwe_qr');
            $hadLegacyFeed = self::hasFeedSlug($feeds, $formId, 'qr-code');

            try {
                PWE_Multilang_Form_Manual_Modified::run(static function () use ($formId, $title): void {
                    PWE_Multilang_Form_QR::processAfterSave(
                        $formId,
                        ['title' => $title, 'qr' => ['enabled' => true]],
                        $title
                    );
                });
            } catch (\Throwable $error) {
                $item['errors'][] = 'migration_to_new_qr: błąd formularza ID ' . $formId . ' - ' . $error->getMessage();
                $item['details'][] = 'migration_to_new_qr: pominięto formularz ID ' . $formId . ' przez błąd wykonania';
                continue;
            }

            if (!$hadNewFeed && $hadLegacyFeed) {
                $item['created'][] = 'form:' . $formId;
                $item['details'][] = 'migration_to_new_qr: utworzono pwe_qr z qr-code dla formularza ID ' . $formId;
                continue;
            }

            $item['updated'][] = 'form:' . $formId;
            $item['details'][] = 'migration_to_new_qr: zaktualizowano shortcode QR dla formularza ID ' . $formId;
        }

        if (empty($item['created']) && empty($item['updated'])) {
            $item['skipped'][] = 'forms';
            $item['details'][] = 'migration_to_new_qr: brak formularzy do migracji';
            return $item;
        }

        $item['details'][] = 'migration_to_new_qr: zakończono migrację QR';
        return $item;
    }

    private static function collectFormIdsWithQrFeeds(array $feeds): array
    {
        $ids = [];

        foreach ($feeds as $feed) {
            if (!is_array($feed)) {
                continue;
            }

            $formId = (int) ($feed['form_id'] ?? 0);
            $slug = (string) ($feed['addon_slug'] ?? '');

            if ($formId > 0 && in_array($slug, ['qr-code', 'pwe_qr'], true)) {
                $ids[$formId] = true;
            }
        }

        return $ids;
    }

    private static function hasFeedSlug(array $feeds, int $formId, string $slug): bool
    {
        foreach ($feeds as $feed) {
            if (
                is_array($feed)
                && (int) ($feed['form_id'] ?? 0) === $formId
                && (string) ($feed['addon_slug'] ?? '') === $slug
            ) {
                return true;
            }
        }

        return false;
    }
}
