<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared adapter for the Uncode page state managed by both page modules.
 */
final class PWE_Multilang_Page_Meta_Adapter
{
    public const UNCODE_HEADER_META = '_uncode_header_type';
    public const UNCODE_HEADER_BLOCK_META = '_uncode_blocks_list';
    public const UNCODE_SHOW_TITLE_META = '_uncode_specific_title';

    /**
     * @return array<string, mixed>
     */
    public static function inspect_replacement_state(int $post_id, string $shortcode): array
    {
        $current_content = (string) get_post_field('post_content', $post_id, 'raw');
        $current_header = (string) get_post_meta($post_id, self::UNCODE_HEADER_META, true);
        $current_show_title = (string) get_post_meta($post_id, self::UNCODE_SHOW_TITLE_META, true);
        $has_header_block = metadata_exists('post', $post_id, self::UNCODE_HEADER_BLOCK_META);

        $needs_content = $current_content !== $shortcode;
        $needs_header = $current_header !== 'none' || $has_header_block;
        $needs_title = $current_show_title !== 'off';

        return [
            'content' => $current_content,
            'header' => $current_header,
            'show_title' => $current_show_title,
            'has_header_block' => $has_header_block,
            'needs_content' => $needs_content,
            'needs_header' => $needs_header,
            'needs_title' => $needs_title,
            'will_change' => $needs_content || $needs_header || $needs_title,
        ];
    }

    public static function set_uncode_header_none(int $post_id): void
    {
        delete_post_meta($post_id, self::UNCODE_HEADER_BLOCK_META);
        delete_post_meta($post_id, self::UNCODE_HEADER_META);
        update_post_meta($post_id, self::UNCODE_HEADER_META, 'none');
    }

    public static function set_uncode_show_title_off(int $post_id): void
    {
        delete_post_meta($post_id, self::UNCODE_SHOW_TITLE_META);
        update_post_meta($post_id, self::UNCODE_SHOW_TITLE_META, 'off');
    }

    public static function verify_replacement_state(int $post_id, string $shortcode): bool
    {
        $state = self::inspect_replacement_state($post_id, $shortcode);

        return empty($state['will_change']) && empty($state['has_header_block']);
    }
}
