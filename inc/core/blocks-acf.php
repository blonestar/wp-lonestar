<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ACF Blocks Loader
 * v2.1.0
 * ACF v6.0 ready - searching for and loading block.json files.
 *
 * Constants required (defined in functions.php):
 * - TEMPLATE_PATH: Theme directory path with trailing slash
 * - ACF_BLOCKS_PATH: Relative path to ACF blocks directory
 */

add_action('init', 'lonestar_register_acf_block_types');

/**
 * Discover and register ACF block types from block.json metadata files.
 *
 * @return void
 */
function lonestar_register_acf_block_types()
{
    if (!function_exists('lonestar_is_acf_block_runtime_available') || !lonestar_is_acf_block_runtime_available()) {
        return;
    }

    $index = function_exists('lonestar_get_block_runtime_index') ? lonestar_get_block_runtime_index() : array();
    $directories = isset($index['directories']['acf']) && is_array($index['directories']['acf']) ? $index['directories']['acf'] : array();
    $metadata_cache = isset($index['metadata']) && is_array($index['metadata']) ? $index['metadata'] : array();

    if (empty($directories)) {
        return;
    }

    foreach ($directories as $block_directory) {
        $block_directory = untrailingslashit(wp_normalize_path((string) $block_directory));
        if ('' === $block_directory || !is_dir($block_directory)) {
            continue;
        }

        $entry = isset($metadata_cache[$block_directory]) ? $metadata_cache[$block_directory] : null;
        $metadata_path = (is_array($entry) && isset($entry['path'])) ? (string) $entry['path'] : '';
        $metadata = (is_array($entry) && isset($entry['data']) && is_array($entry['data'])) ? $entry['data'] : array();

        if ('' === $metadata_path) {
            continue;
        }

        $errors = function_exists('lonestar_validate_block_contract')
            ? lonestar_validate_block_contract('acf', 'acf', $metadata, $block_directory)
            : array();

        if (!empty($errors)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[lonestar-theme] Invalid ACF block contract: ' . implode(' ', $errors));
            }
            continue;
        }

        $fields_path = wp_normalize_path($block_directory . '/fields.php');
        if (function_exists('acf_add_local_field_group') && is_readable($fields_path)) {
            $field_group = include $fields_path;
            if (is_array($field_group) && !empty($field_group['key'])) {
                acf_add_local_field_group($field_group);
            }
        }

        register_block_type($metadata_path);
    }
}

/**
 * Create custom category for theme-based blocks.
 *
 * @param array $categories Existing block categories.
 * @param mixed $post Current post.
 * @return array
 */
function lonestar_theme_blocks_category($categories, $post)
{
    unset($post);

    foreach ($categories as $category) {
        if (isset($category['slug']) && 'lonestar-blocks' === $category['slug']) {
            return $categories;
        }
    }

    $categories[] = array(
        'slug'  => 'lonestar-blocks',
        'title' => __('Lonestar Blocks', 'lonestar'),
        'icon'  => 'wordpress',
    );

    return $categories;
}

add_filter('block_categories_all', 'lonestar_theme_blocks_category', 10, 2);
