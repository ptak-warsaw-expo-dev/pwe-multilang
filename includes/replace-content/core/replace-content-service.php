<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Replace_Content_Service
{
    /**
     * @param array<string, string> $map
     * @return array<int, array<string, mixed>>
     */
    public static function build_plan(array $map): array
    {
        $plan = [];

        foreach ($map as $url => $shortcode) {
            $source_page = PWE_Multilang_Page_Locator::find_by_url((string) $url);
            $item = [
                'url' => (string) $url,
                'shortcode' => (string) $shortcode,
                'source_id' => 0,
                'translations' => [],
                'error' => '',
            ];

            if (!$source_page) {
                $item['error'] = 'Nie znaleziono strony dla wskazanego adresu.';
                $plan[] = $item;
                continue;
            }

            $item['source_id'] = (int) $source_page->ID;
            $item['translations'] = self::get_wpml_translations($source_page, (string) $shortcode);

            if (empty($item['translations'])) {
                $item['error'] = 'Nie znaleziono strony ani jej tłumaczeń WPML.';
            }

            $plan[] = $item;
        }

        return $plan;
    }

    /**
     * Tworzy bezpieczną, pozbawioną duplikatów kolejkę do przetwarzania.
     *
     * @param array<int, array<string, mixed>> $plan
     * @return array{tasks: array<int, array<string, mixed>>, result: array<string, mixed>}
     */
    public static function prepare_job(array $plan): array
    {
        $assignments = [];
        $conflicts = [];
        $plan_errors = [];

        foreach ($plan as $item) {
            $shortcode = (string) ($item['shortcode'] ?? '');

            if (!empty($item['error'])) {
                $plan_errors[] = sprintf(
                    '%s — %s',
                    (string) ($item['url'] ?? ''),
                    (string) $item['error']
                );
            }

            foreach ((array) ($item['translations'] ?? []) as $translation) {
                $post_id = (int) ($translation['id'] ?? 0);

                if ($post_id <= 0 || $shortcode === '') {
                    continue;
                }

                if (
                    isset($assignments[$post_id])
                    && (string) $assignments[$post_id]['shortcode'] !== $shortcode
                ) {
                    $conflicts[$post_id] = true;
                    continue;
                }

                $assignments[$post_id] = [
                    'post_id' => $post_id,
                    'shortcode' => $shortcode,
                    'title' => (string) ($translation['title'] ?? ''),
                    'language' => (string) ($translation['language'] ?? ''),
                    'will_change' => !empty($translation['will_change']),
                ];
            }
        }

        $result = self::empty_result();

        foreach ($plan_errors as $plan_error) {
            $result['failed']++;
            $result['errors'][] = $plan_error;
        }

        foreach (array_keys($conflicts) as $post_id) {
            unset($assignments[$post_id]);
            $result['failed']++;
            $result['errors'][] = sprintf(
                'Strona ID %d występuje w mapie z różnymi shortcode’ami i została pominięta.',
                $post_id
            );
        }

        $tasks = [];

        foreach ($assignments as $assignment) {
            if (empty($assignment['will_change'])) {
                // Strona już zgodna — liczymy od razu, bez wykonywania kroku operacji.
                $result['unchanged']++;
                continue;
            }

            $tasks[] = $assignment;
        }

        return [
            'tasks' => array_values($tasks),
            'result' => $result,
        ];
    }

    /**
     * Synchroniczny wariant używany jako fallback, gdy JavaScript jest niedostępny.
     *
     * @param array<int, array<string, mixed>> $plan
     * @return array<string, mixed>
     */
    public static function execute(array $plan): array
    {
        $job = self::prepare_job($plan);
        $result = (array) $job['result'];

        foreach ((array) $job['tasks'] as $task) {
            try {
                $page_result = self::process_page(
                    (int) ($task['post_id'] ?? 0),
                    (string) ($task['shortcode'] ?? '')
                );
            } catch (Throwable $e) {
                $page_result = [
                    'status' => 'failed',
                    'post_id' => (int) ($task['post_id'] ?? 0),
                    'message' => sprintf(
                        'Nie udało się przetworzyć strony ID %d: %s',
                        (int) ($task['post_id'] ?? 0),
                        $e->getMessage()
                    ),
                ];
            }

            self::add_page_result($result, $page_result);
        }

        return $result;
    }

    /**
     * @return array{status: string, post_id: int, message: string}
     */
    public static function process_page(int $post_id, string $shortcode): array
    {
        $post = $post_id > 0 ? get_post($post_id) : null;

        if (!$post instanceof WP_Post || $post->post_type !== 'page' || $shortcode === '') {
            return [
                'status' => 'failed',
                'post_id' => $post_id,
                'message' => sprintf('Nieprawidłowa strona lub shortcode dla ID %d.', $post_id),
            ];
        }

        $state = PWE_Multilang_Page_Meta_Adapter::inspect_replacement_state($post_id, $shortcode);
        $needs_content = !empty($state['needs_content']);
        $needs_header = !empty($state['needs_header']);
        $needs_title = !empty($state['needs_title']);

        if (!$needs_content && !$needs_header && !$needs_title) {
            return [
                'status' => 'unchanged',
                'post_id' => $post_id,
                'message' => 'Strona była już zgodna.',
            ];
        }

        if ($needs_content) {
            $update = wp_update_post([
                'ID' => $post_id,
                'post_content' => wp_slash($shortcode),
            ], true);

            if (is_wp_error($update) || !$update) {
                $message = is_wp_error($update) ? $update->get_error_message() : 'Nieznany błąd zapisu.';

                return [
                    'status' => 'failed',
                    'post_id' => $post_id,
                    'message' => sprintf('Nie udało się zaktualizować strony ID %d: %s', $post_id, $message),
                ];
            }
        }

        if ($needs_header) {
            PWE_Multilang_Page_Meta_Adapter::set_uncode_header_none($post_id);
        }

        if ($needs_title) {
            PWE_Multilang_Page_Meta_Adapter::set_uncode_show_title_off($post_id);
        }

        clean_post_cache($post_id);

        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($post_id);
        }

        if (!PWE_Multilang_Page_Meta_Adapter::verify_replacement_state($post_id, $shortcode)) {
            return [
                'status' => 'failed',
                'post_id' => $post_id,
                'message' => sprintf('Nie udało się potwierdzić zmian dla strony ID %d.', $post_id),
            ];
        }

        do_action('pwe_multilang_replace_content_page_updated', $post_id, $shortcode);

        return [
            'status' => 'updated',
            'post_id' => $post_id,
            'message' => 'Strona została zaktualizowana.',
        ];
    }

    /**
     * @return array{updated: int, unchanged: int, failed: int, errors: array<int, string>}
     */
    public static function empty_result(): array
    {
        return [
            'updated' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $page_result
     */
    public static function add_page_result(array &$result, array $page_result): void
    {
        $status = (string) ($page_result['status'] ?? 'failed');

        if (!isset($result[$status]) || !is_int($result[$status])) {
            $status = 'failed';
        }

        $result[$status]++;

        if ($status === 'failed') {
            $result['errors'][] = (string) ($page_result['message'] ?? 'Nieznany błąd.');
        }
    }

    /**
     * Recovers a step whose mutation completed but whose job result was not
     * persisted. The original plan state tells us whether that successful
     * mutation should count as updated or unchanged.
     *
     * @return array{status: string, post_id: int, message: string}
     */
    public static function recover_page_result(array $task): array
    {
        $post_id = (int) ($task['post_id'] ?? 0);
        $shortcode = (string) ($task['shortcode'] ?? '');
        $post = $post_id > 0 ? get_post($post_id) : null;

        if (
            $post instanceof WP_Post
            && $post->post_type === 'page'
            && $shortcode !== ''
            && PWE_Multilang_Page_Meta_Adapter::verify_replacement_state($post_id, $shortcode)
        ) {
            return [
                'status' => !empty($task['will_change']) ? 'updated' : 'unchanged',
                'post_id' => $post_id,
                'message' => !empty($task['will_change'])
                    ? 'Strona została zaktualizowana.'
                    : 'Strona była już zgodna.',
            ];
        }

        return self::process_page($post_id, $shortcode);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function get_wpml_translations(WP_Post $source_page, string $shortcode): array
    {
        $element_type = PWE_Multilang_Wpml_Gateway::get_element_type('page');
        $trid = PWE_Multilang_Wpml_Gateway::get_trid((int) $source_page->ID, $element_type);
        $wpml_translations = PWE_Multilang_Wpml_Gateway::get_element_translations($trid, $element_type);
        $translations = [];

        if (is_array($wpml_translations)) {
            foreach ($wpml_translations as $language_code => $wpml_translation) {
                $translation_id = is_object($wpml_translation)
                    ? (int) ($wpml_translation->element_id ?? 0)
                    : (int) ($wpml_translation['element_id'] ?? 0);
                $translation_language = is_object($wpml_translation)
                    ? (string) ($wpml_translation->language_code ?? $language_code)
                    : (string) ($wpml_translation['language_code'] ?? $language_code);

                $is_original = is_object($wpml_translation)
                    ? (string) ($wpml_translation->original ?? '0') === '1'
                    : (string) ($wpml_translation['original'] ?? '0') === '1';

                self::add_translation(
                    $translations,
                    $translation_id,
                    $translation_language,
                    $shortcode,
                    $is_original
                );
            }
        }

        if (!isset($translations[(int) $source_page->ID])) {
            self::add_translation(
                $translations,
                (int) $source_page->ID,
                self::get_page_language((int) $source_page->ID, (string) $element_type),
                $shortcode
            );
        }

        uasort($translations, static function (array $left, array $right): int {
            $left_original = !empty($left['is_original']);
            $right_original = !empty($right['is_original']);

            if ($left_original !== $right_original) {
                return $left_original ? -1 : 1;
            }

            return strcmp((string) $left['language'], (string) $right['language']);
        });

        return array_values($translations);
    }

    /**
     * @param array<int, array<string, mixed>> $translations
     */
    private static function add_translation(
        array &$translations,
        int $post_id,
        string $language,
        string $shortcode,
        bool $is_original = false
    ): void
    {
        if ($post_id <= 0 || isset($translations[$post_id])) {
            return;
        }

        $post = get_post($post_id);

        if (!$post instanceof WP_Post || $post->post_type !== 'page') {
            return;
        }

        $state = PWE_Multilang_Page_Meta_Adapter::inspect_replacement_state($post_id, $shortcode);
        $needs_content = !empty($state['needs_content']);
        $needs_header = !empty($state['needs_header']);
        $needs_title = !empty($state['needs_title']);

        $translations[$post_id] = [
            'id' => $post_id,
            'language' => $language !== '' ? strtolower($language) : '—',
            'is_original' => $is_original,
            'title' => (string) get_the_title($post_id),
            'permalink' => (string) get_permalink($post_id),
            'edit_url' => (string) get_edit_post_link($post_id, 'raw'),
            'post_status' => (string) $post->post_status,
            'needs_content' => $needs_content,
            'needs_header' => $needs_header,
            'needs_title' => $needs_title,
            'will_change' => $needs_content || $needs_header || $needs_title,
        ];
    }

    private static function get_page_language(int $post_id, string $element_type): string
    {
        return PWE_Multilang_Wpml_Gateway::get_element_language($post_id, $element_type);
    }
}
