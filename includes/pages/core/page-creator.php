<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Page_Creator
{
    private const CREATION_STATE_META = '_pwe_multilang_creation_state';
    private const CREATION_STATE_INSERTED = 'inserted';
    private const CREATION_STATE_CONNECTED = 'wpml_connected';
    private const CREATION_STATE_CONFIGURED = 'configured';
    private const CREATION_STATE_COMPLETE = 'complete';

    public static function create_missing_wpml_pages_from_json(int $event_year = 0): array
    {
        if (!class_exists('PWE_Multilang_Form_Operation_Lock')) {
            return self::create_missing_wpml_pages_unlocked($event_year);
        }

        $token = PWE_Multilang_Form_Operation_Lock::acquire('page_sync');

        if ($token === null) {
            return [
                'status' => 'locked',
                'message' => 'Inna operacja generowania stron lub formularzy jest w toku.',
                'errors' => ['Nie uruchomiono równoległej operacji generowania.'],
            ];
        }

        try {
            return self::create_missing_wpml_pages_unlocked($event_year);
        } finally {
            PWE_Multilang_Form_Operation_Lock::release($token);
        }
    }

    private static function create_missing_wpml_pages_unlocked(int $event_year): array
    {
        $event_year = self::normalize_event_year($event_year);

        if (!PWE_Multilang_Wpml_Gateway::is_active()) {
            return [
                'status' => 'error',
                'message' => 'WPML nie jest aktywny.',
            ];
        }

        $pages = PWE_Multilang_Page_Json_Repository::get_pages_for_current_group();
        $skip_languages = PWE_Multilang_Page_Config::get_skipped_languages();
        $allowed_language_codes = PWE_Multilang_Page_Language_Helper::get_allowed_language_codes($pages);

        $summary = [
            'event_year' => $event_year,
            'allowed_languages_by_home' => $allowed_language_codes,
            'created' => [],
            'skipped_existing' => [],
            'skipped_no_en_source' => [],
            'skipped_invalid_data' => [],
            'form_templates' => [],
            'errors' => [],
        ];

        $plan = self::build_plan(
            $pages,
            $skip_languages,
            $allowed_language_codes,
            $summary
        );
        $inserted_pages = 0;

        self::execute_plan($plan, $event_year, $summary, $inserted_pages);

        if ($inserted_pages > 0) {
            flush_rewrite_rules(false);
        }

        return $summary;
    }

    private static function normalize_event_year(int $event_year): int
    {
        return PWE_Multilang_Year_Resolver::normalise($event_year);
    }

    /**
     * Builds a mutation-free plan. Existing incomplete pages created by this
     * module are included as recovery tasks instead of being skipped forever.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function build_plan(
        array $pages,
        array $skip_languages,
        array $allowed_language_codes,
        array &$summary
    ): array {
        $plan = [];

        foreach ($pages as $page_key => $translations) {
            if (!is_array($translations) || empty($translations['en']['url'])) {
                $summary['skipped_invalid_data'][] = $page_key . ' — brak EN URL';
                continue;
            }

            $en_page = self::get_source_page((string) $translations['en']['url']);

            if (!$en_page) {
                $summary['skipped_no_en_source'][] = $page_key . ' — nie znaleziono strony EN: ' . $translations['en']['url'];
                continue;
            }

            $element_type = PWE_Multilang_Wpml_Gateway::get_element_type('page');
            $trid = PWE_Multilang_Wpml_Gateway::get_trid((int) $en_page->ID, $element_type);

            if ($trid <= 0) {
                $summary['errors'][] = $page_key . ' — nie udało się pobrać TRID strony EN';
                continue;
            }

            foreach ($translations as $lang_code => $data) {
                $lang_code = (string) $lang_code;

                if (in_array($lang_code, $skip_languages, true)) {
                    continue;
                }

                if (!in_array($lang_code, $allowed_language_codes, true)) {
                    continue;
                }

                if (!is_array($data) || empty($data['label']) || !isset($data['url'])) {
                    $summary['skipped_invalid_data'][] = $page_key . ' / ' . $lang_code . ' — brak label lub url';
                    continue;
                }

                $url = (string) $data['url'];
                $slug = self::get_slug($url, $lang_code);

                if ($slug === '' && $url !== '/') {
                    $summary['errors'][] = $page_key . ' / ' . $lang_code . ' — pusty slug po sanitizacji: ' . $url;
                    continue;
                }

                $recoverable_page = self::find_recoverable_page((string) $page_key, $lang_code, $url);
                $existing_page = self::get_existing_page($url, $lang_code);

                if (!$recoverable_page && !$existing_page) {
                    $recoverable_page = self::find_managed_page((string) $page_key, $lang_code, $url, false);
                }

                if ($existing_page && (!$recoverable_page || $existing_page->ID !== $recoverable_page->ID)) {
                    $summary['skipped_existing'][] = $page_key . ' / ' . $lang_code . ' — istnieje: ' . $url;
                    continue;
                }

                $plan[] = [
                    'page_key' => (string) $page_key,
                    'language_code' => $lang_code,
                    'data' => $data,
                    'url' => $url,
                    'slug' => $slug,
                    'source_page' => $en_page,
                    'element_type' => $element_type,
                    'trid' => $trid,
                    'target_id' => $recoverable_page ? (int) $recoverable_page->ID : 0,
                ];
            }
        }

        return $plan;
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     */
    private static function execute_plan(
        array $plan,
        int $event_year,
        array &$summary,
        int &$inserted_pages
    ): void {
        foreach ($plan as $task) {
            if (
                class_exists('PWE_Multilang_Form_Operation_Lock')
                && PWE_Multilang_Form_Operation_Lock::ownedByCurrentRequest()
                && !PWE_Multilang_Form_Operation_Lock::refreshOwned()
            ) {
                $summary['errors'][] = 'Przerwano generowanie, ponieważ utracono blokadę operacji.';
                return;
            }

            $page_key = (string) $task['page_key'];
            $lang_code = (string) $task['language_code'];
            $url = (string) $task['url'];
            $target_id = (int) $task['target_id'];
            $source_page = $task['source_page'];
            $page_form_templates = [];

            try {
                if (!$source_page instanceof WP_Post) {
                    throw new RuntimeException('Nieprawidłowa strona źródłowa EN.');
                }

                $post_data = self::prepare_translation_post_data(
                    $source_page,
                    (array) $task['data'],
                    (string) $task['slug'],
                    $lang_code,
                    $event_year,
                    $page_form_templates
                );

                if ($target_id <= 0) {
                    $new_page_id = self::insert_translation_page(
                        $post_data,
                        $lang_code,
                        $page_key,
                        $url
                    );

                    if (is_wp_error($new_page_id)) {
                        $summary['errors'][] = $page_key . ' / ' . $lang_code . ' — wp_insert_post: ' . $new_page_id->get_error_message();
                        continue;
                    }

                    $target_id = (int) $new_page_id;
                    $inserted_pages++;
                } else {
                    $updated_page_id = self::update_translation_page($target_id, $post_data, $lang_code);

                    if (is_wp_error($updated_page_id)) {
                        throw new RuntimeException('wp_update_post: ' . $updated_page_id->get_error_message());
                    }

                    if ((int) $updated_page_id !== $target_id) {
                        throw new RuntimeException('WordPress nie potwierdził aktualizacji częściowo utworzonej strony.');
                    }
                }

                $summary['form_templates'] = array_values(array_unique(array_merge(
                    $summary['form_templates'],
                    $page_form_templates
                )));

                self::connect_wpml_translation(
                    $target_id,
                    (string) $task['element_type'],
                    (int) $task['trid'],
                    $lang_code
                );
                update_post_meta($target_id, self::CREATION_STATE_META, self::CREATION_STATE_CONNECTED);

                self::copy_source_data(
                    (int) $source_page->ID,
                    $target_id,
                    $page_key,
                    $lang_code,
                    $url
                );

                if (!self::verify_created_page(
                    $target_id,
                    $post_data,
                    $page_key,
                    $lang_code,
                    $url,
                    (string) $task['element_type'],
                    (int) $task['trid']
                )) {
                    $summary['errors'][] = $page_key . ' / ' . $lang_code . ' — nie udało się potwierdzić konfiguracji strony';
                    continue;
                }

                update_post_meta($target_id, self::CREATION_STATE_META, self::CREATION_STATE_COMPLETE);

                if ((string) get_post_meta($target_id, self::CREATION_STATE_META, true) !== self::CREATION_STATE_COMPLETE) {
                    $summary['errors'][] = $page_key . ' / ' . $lang_code . ' — nie udało się zapisać stanu końcowego strony';
                    continue;
                }

                clean_post_cache($target_id);
                $summary['created'][] = $page_key . ' / ' . $lang_code . ' — utworzono: ' . $url;
            } catch (Throwable $e) {
                $summary['errors'][] = $page_key . ' / ' . $lang_code . ' — ' . $e->getMessage();
            }
        }
    }

    private static function get_source_page(string $source_url): ?WP_Post
    {
        return $source_url === '/'
            ? PWE_Multilang_Page_Locator::get_home_page_in_language('en')
            : PWE_Multilang_Page_Locator::find_by_url_and_language($source_url, 'en');
    }

    private static function get_existing_page(string $url, string $lang_code): ?WP_Post
    {
        return $url === '/'
            ? PWE_Multilang_Page_Locator::get_home_page_in_language($lang_code)
            : PWE_Multilang_Page_Locator::find_by_url_and_language($url, $lang_code);
    }

    private static function get_slug(string $url, string $lang_code): string
    {
        return $url === '/'
            ? 'home-' . sanitize_key($lang_code)
            : PWE_Multilang_Page_Slug::from_url($url);
    }

    private static function find_recoverable_page(string $page_key, string $lang_code, string $url): ?WP_Post
    {
        return self::find_managed_page($page_key, $lang_code, $url, true);
    }

    private static function find_managed_page(
        string $page_key,
        string $lang_code,
        string $url,
        bool $incomplete_only
    ): ?WP_Post {
        $meta_query = [
            'relation' => 'AND',
            [
                'key' => '_pwe_json_page_key',
                'value' => sanitize_key($page_key),
            ],
            [
                'key' => '_pwe_json_language',
                'value' => sanitize_key($lang_code),
            ],
            [
                'key' => '_pwe_json_url',
                'value' => esc_url_raw($url),
            ],
        ];

        if ($incomplete_only) {
            $meta_query[] = [
                'key' => self::CREATION_STATE_META,
                'value' => self::CREATION_STATE_COMPLETE,
                'compare' => '!=',
            ];
        }

        $post_ids = get_posts([
            'post_type' => 'page',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'DESC',
            'suppress_filters' => true,
            'meta_query' => $meta_query,
        ]);

        if (empty($post_ids[0])) {
            return null;
        }

        $post = get_post((int) $post_ids[0]);

        return ($post instanceof WP_Post && $post->post_type === 'page' && $post->post_status !== 'trash')
            ? $post
            : null;
    }

    /**
     * Builds the canonical post fields once, so initial creation and recovery
     * cannot drift apart.
     *
     * @return array<string, mixed>
     */
    private static function prepare_translation_post_data(
        WP_Post $en_page,
        array $data,
        string $slug,
        string $lang_code,
        int $event_year,
        array &$form_template_slugs
    ): array {
        $parent_id = PWE_Multilang_Page_Locator::get_translated_parent_id((int) $en_page->post_parent, $lang_code);
        $post_content = PWE_Multilang_Page_Content_Transformer::transform_for_language(
            (string) $en_page->post_content,
            $lang_code,
            $event_year,
            $form_template_slugs
        );

        $en_block_id = (int) get_post_meta($en_page->ID, PWE_Multilang_Page_Meta_Adapter::UNCODE_HEADER_BLOCK_META, true);
        $en_header_type = (string) get_post_meta($en_page->ID, PWE_Multilang_Page_Meta_Adapter::UNCODE_HEADER_META, true);

        if ($en_block_id > 0 && $en_header_type === 'header_uncodeblock' && get_post_type($en_block_id) === 'uncodeblock') {
            $post_content = '[pwe-elements-component-simple-header]' . "\n" . $post_content;
        }

        return [
            'post_status' => 'publish',
            'post_title' => sanitize_text_field((string) ($data['label'] ?? '')),
            'post_name' => $slug,
            'post_content' => $post_content,
            'post_excerpt' => (string) $en_page->post_excerpt,
            'post_parent' => $parent_id !== null ? $parent_id : 0,
            'comment_status' => (string) $en_page->comment_status,
            'ping_status' => (string) $en_page->ping_status,
            'menu_order' => (int) $en_page->menu_order,
        ];
    }

    private static function insert_translation_page(
        array $post_data,
        string $lang_code,
        string $page_key,
        string $url
    ) {
        $post_data['post_content'] = wp_slash((string) ($post_data['post_content'] ?? ''));
        $post_data['post_excerpt'] = wp_slash((string) ($post_data['post_excerpt'] ?? ''));

        return PWE_Multilang_Wpml_Gateway::with_language(
            $lang_code,
            static function () use ($post_data, $page_key, $lang_code, $url) {
                return wp_insert_post(array_merge($post_data, [
                    'post_type' => 'page',
                    'meta_input' => [
                        '_pwe_json_page_key' => sanitize_key($page_key),
                        '_pwe_json_language' => sanitize_key($lang_code),
                        '_pwe_json_url' => esc_url_raw($url),
                        '_pwe_created_as_home_translation' => $url === '/' ? '1' : '0',
                        self::CREATION_STATE_META => self::CREATION_STATE_INSERTED,
                    ],
                ]), true);
            }
        );
    }

    private static function update_translation_page(
        int $target_id,
        array $post_data,
        string $lang_code
    ) {
        $post_data['ID'] = $target_id;
        $post_data['post_content'] = wp_slash((string) ($post_data['post_content'] ?? ''));
        $post_data['post_excerpt'] = wp_slash((string) ($post_data['post_excerpt'] ?? ''));

        return PWE_Multilang_Wpml_Gateway::with_language(
            $lang_code,
            static function () use ($post_data) {
                return wp_update_post($post_data, true);
            }
        );
    }

    private static function connect_wpml_translation(
        int $new_page_id,
        string $element_type,
        int $trid,
        string $lang_code
    ): void {
        PWE_Multilang_Wpml_Gateway::set_element_language_details(
            $new_page_id,
            $element_type,
            $trid,
            $lang_code,
            'en'
        );
    }

    private static function copy_source_data(
        int $source_id,
        int $target_id,
        string $page_key,
        string $lang_code,
        string $url
    ): void {
        PWE_Multilang_Page_Meta_Copier::copy_basic_page_meta($source_id, $target_id);
        PWE_Multilang_Page_Meta_Copier::copy_wp_rocket_page_meta($source_id, $target_id);
        PWE_Multilang_Page_Meta_Copier::copy_wp_rocket_cache_reject_uri($source_id, $target_id);

        update_post_meta($target_id, '_pwe_json_page_key', sanitize_key($page_key));
        update_post_meta($target_id, '_pwe_json_language', sanitize_key($lang_code));
        update_post_meta($target_id, '_pwe_json_url', esc_url_raw($url));
        update_post_meta($target_id, '_pwe_created_as_home_translation', $url === '/' ? '1' : '0');
        update_post_meta($target_id, self::CREATION_STATE_META, self::CREATION_STATE_CONFIGURED);

        clean_post_cache($target_id);
    }

    private static function verify_created_page(
        int $post_id,
        array $expected_post_data,
        string $page_key,
        string $lang_code,
        string $url,
        string $element_type,
        int $trid
    ): bool {
        $post = get_post($post_id);

        if (!$post instanceof WP_Post || $post->post_type !== 'page') {
            return false;
        }

        $string_fields = [
            'post_status',
            'post_title',
            'post_name',
            'post_content',
            'post_excerpt',
            'comment_status',
            'ping_status',
        ];

        foreach ($string_fields as $field) {
            if ((string) $post->{$field} !== (string) ($expected_post_data[$field] ?? '')) {
                return false;
            }
        }

        if ((int) $post->post_parent !== (int) ($expected_post_data['post_parent'] ?? 0)) {
            return false;
        }

        if ((int) $post->menu_order !== (int) ($expected_post_data['menu_order'] ?? 0)) {
            return false;
        }

        if ((string) get_post_meta($post_id, '_pwe_json_page_key', true) !== sanitize_key($page_key)) {
            return false;
        }

        if ((string) get_post_meta($post_id, '_pwe_json_language', true) !== sanitize_key($lang_code)) {
            return false;
        }

        if ((string) get_post_meta($post_id, '_pwe_json_url', true) !== esc_url_raw($url)) {
            return false;
        }

        if ((string) get_post_meta($post_id, '_pwe_created_as_home_translation', true) !== ($url === '/' ? '1' : '0')) {
            return false;
        }

        if ((string) get_post_meta($post_id, self::CREATION_STATE_META, true) !== self::CREATION_STATE_CONFIGURED) {
            return false;
        }

        if ((string) get_post_meta($post_id, PWE_Multilang_Page_Meta_Adapter::UNCODE_HEADER_META, true) !== 'none') {
            return false;
        }

        if (metadata_exists('post', $post_id, PWE_Multilang_Page_Meta_Adapter::UNCODE_HEADER_BLOCK_META)) {
            return false;
        }

        if (PWE_Multilang_Wpml_Gateway::get_trid($post_id, $element_type) !== $trid) {
            return false;
        }

        return PWE_Multilang_Wpml_Gateway::get_element_language($post_id, $element_type) === $lang_code;
    }
}
