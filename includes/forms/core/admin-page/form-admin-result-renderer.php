<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Admin_Result_Renderer
{
    public static function render($result): void
    {
        if (empty($result) || !is_array($result)) {
            return;
        }

        [$compactItems, $messageItems] = self::partitionItems(
            is_array($result['items'] ?? null) ? $result['items'] : []
        );

        PWE_Multilang_Admin_UI::cardOpen('pwe-card pwe-form-result-log');
        PWE_Multilang_Admin_UI::cardTitle('<span class="dashicons dashicons-list-view"></span> Log synchronizacji');
        PWE_Multilang_Admin_UI::cardDesc(
            'Zakres: <strong>' . esc_html((string) ($result['scope'] ?? '-')) . '</strong>. '
            . 'Tryb tylko dodawania brakujących: '
            . '<strong>' . (!empty($result['add_only']) ? 'tak' : 'nie') . '</strong>.'
        );

        ?>

            <?php self::renderResultErrors($result); ?>

            <?php self::renderCompactItems($compactItems); ?>

            <?php foreach ($messageItems as $item) : ?>
                <?php self::renderItem($item); ?>
            <?php endforeach; ?>
        <?php
        PWE_Multilang_Admin_UI::cardClose();
    }

    /**
     * @param array<int, mixed> $items
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private static function partitionItems(array $items): array
    {
        $compactItems = [];
        $messageItems = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (self::requiresMessageCard($item)) {
                $messageItems[] = $item;
                continue;
            }

            $compactItems[] = $item;
        }

        return [$compactItems, $messageItems];
    }

    private static function requiresMessageCard(array $item): bool
    {
        if (!empty($item['errors']) || !empty($item['skipped'])) {
            return true;
        }

        $status = sanitize_key((string) ($item['status'] ?? ''));
        if ($status !== '' && $status !== 'success') {
            return true;
        }

        $diff = is_array($item['diff'] ?? null) ? $item['diff'] : [];
        foreach (array_keys(self::diffWarningGroups()) as $key) {
            if (!empty($diff[$key])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $items */
    private static function renderCompactItems(array $items): void
    {
        if (empty($items)) {
            return;
        }

        ?>
        <p><strong>Formularze zakończone bez błędów:</strong></p>
        <ul class="pwe-form-result-details">
            <?php foreach ($items as $item) : ?>
                <li>
                    <strong><?php echo esc_html((string) ($item['template'] ?? '-')); ?></strong>

                    <?php if (!empty($item['title'])) : ?>
                        — <?php echo esc_html((string) $item['title']); ?>
                    <?php endif; ?>

                    <?php if (!empty($item['form_id'])) : ?>
                        (ID: <?php echo esc_html((string) $item['form_id']); ?>)
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private static function renderItem(array $item): void
    {
        ?>
        <div class="pwe-form-result-item">
            <h3>
                <?php echo esc_html((string) ($item['template'] ?? '-')); ?>
                <small><?php echo esc_html((string) ($item['title'] ?? '')); ?></small>
            </h3>

            <p>
                ID formularza:
                <strong><?php echo !empty($item['form_id']) ? esc_html((string) $item['form_id']) : '-'; ?></strong>
                | Akcja:
                <strong><?php echo esc_html((string) ($item['action'] ?? '-')); ?></strong>

                <?php if (!empty($item['lang'])) : ?>
                    | Język:
                    <strong><?php echo esc_html(strtoupper((string) $item['lang'])); ?></strong>
                <?php endif; ?>
            </p>

            <ul class="pwe-form-result-summary">
                <li>Utworzone: <?php echo esc_html((string) count($item['created'] ?? [])); ?></li>
                <li>Zaktualizowane: <?php echo esc_html((string) count($item['updated'] ?? [])); ?></li>
                <li>Pominięte: <?php echo esc_html((string) count($item['skipped'] ?? [])); ?></li>
                <li>Błędy: <?php echo esc_html((string) count($item['errors'] ?? [])); ?></li>
            </ul>

            <?php self::renderItemDetails($item); ?>
            <?php self::renderDiffWarnings(is_array($item['diff'] ?? null) ? $item['diff'] : []); ?>
            <?php self::renderItemErrors($item); ?>
        </div>
        <?php
    }

    private static function renderResultErrors(array $result): void
    {
        if (empty($result['errors']) || !is_array($result['errors'])) {
            return;
        }

        PWE_Multilang_Admin_UI::notice(
            'pwe-notice-error',
            'dashicons-warning',
            esc_html(implode(' ', array_map('strval', $result['errors'])))
        );
    }

    private static function renderItemDetails(array $item): void
    {
        if (empty($item['details']) || !is_array($item['details'])) {
            return;
        }

        ?>
        <ul class="pwe-form-result-details">
            <?php foreach ($item['details'] as $detail) : ?>
                <li><?php echo esc_html((string) $detail); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private static function renderItemErrors(array $item): void
    {
        if (empty($item['errors']) || !is_array($item['errors'])) {
            return;
        }

        ?>
        <ul class="pwe-form-result-errors">
            <?php foreach ($item['errors'] as $error) : ?>
                <li><?php echo esc_html((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private static function renderDiffWarnings(array $diff): void
    {
        foreach (self::diffWarningGroups() as $key => $label) {
            if (empty($diff[$key]) || !is_array($diff[$key])) {
                continue;
            }

            self::renderDiffWarningGroup($label, $diff[$key]);
        }
    }

    private static function renderDiffWarningGroup(string $label, array $warnings): void
    {
        ob_start();
        ?>
                <strong><?php echo esc_html($label); ?></strong>
                <ul>
                    <?php foreach ($warnings as $warning) : ?>
                        <?php self::renderDiffWarning(is_array($warning) ? $warning : []); ?>
                    <?php endforeach; ?>
                </ul>
        <?php
        PWE_Multilang_Admin_UI::notice('pwe-notice-warning', 'dashicons-warning', (string) ob_get_clean());
    }

    private static function renderDiffWarning(array $warning): void
    {
        ?>
        <li>
            <?php echo esc_html((string) ($warning['message'] ?? 'Znaleziono element poza template')); ?>:
            <strong><?php echo esc_html((string) ($warning['name'] ?? '-')); ?></strong>

            <?php if (!empty($warning['adminLabel'])) : ?>
                <small>adminLabel: <?php echo esc_html((string) $warning['adminLabel']); ?></small>
            <?php endif; ?>
        </li>
        <?php
    }

    private static function diffWarningGroups(): array
    {
        return [
            'fields_extra' => 'Pola poza template',
            'notifications_extra' => 'Powiadomienia poza template',
            'confirmations_extra' => 'Potwierdzenia poza template',
        ];
    }
}
