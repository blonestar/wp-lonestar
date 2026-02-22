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
    $transient_key = 'lonestar_acf_blocks_to_load_v3';
    $cache_namespace = function_exists('lonestar_get_theme_cache_namespace') ? lonestar_get_theme_cache_namespace() : 'default';
    $use_cache = !lonestar_is_vite_dev_mode();
    $blocks = false;

    if ($use_cache) {
        $cached_payload = get_transient($transient_key);
        if (
            is_array($cached_payload) &&
            isset($cached_payload['cache_namespace'], $cached_payload['blocks']) &&
            $cache_namespace === $cached_payload['cache_namespace'] &&
            is_array($cached_payload['blocks'])
        ) {
            $blocks = $cached_payload['blocks'];
        }
    }

    if (false === $blocks) {
        $blocks = array();
        $block_directories = function_exists('lonestar_find_block_directories') ? lonestar_find_block_directories() : array();
        $acf_block_roots = function_exists('lonestar_get_acf_block_root_paths')
            ? lonestar_get_acf_block_root_paths()
            : array(wp_normalize_path(TEMPLATE_PATH . ACF_BLOCKS_PATH));

        if (empty($block_directories) || empty($acf_block_roots)) {
            return;
        }

        foreach ($block_directories as $block_directory) {
            $block_directory = untrailingslashit(wp_normalize_path((string) $block_directory));
            if ('' === $block_directory || !is_dir($block_directory)) {
                continue;
            }

            $is_acf_directory = false;
            foreach ($acf_block_roots as $acf_block_root) {
                $acf_block_root = untrailingslashit(wp_normalize_path((string) $acf_block_root));
                if ('' === $acf_block_root) {
                    continue;
                }

                if ($block_directory === $acf_block_root || 0 === strpos($block_directory, $acf_block_root . '/')) {
                    $is_acf_directory = true;
                    break;
                }
            }

            if (!$is_acf_directory) {
                continue;
            }

            $metadata_path = function_exists('lonestar_get_block_json_path') ? lonestar_get_block_json_path($block_directory) : '';
            if ('' === $metadata_path || !is_readable($metadata_path)) {
                continue;
            }

            $blocks[] = wp_normalize_path($metadata_path);
        }

        $blocks = array_values(array_unique($blocks));
        sort($blocks, SORT_NATURAL);
        if ($use_cache) {
            set_transient(
                $transient_key,
                array(
                    'cache_namespace' => $cache_namespace,
                    'blocks'          => $blocks,
                ),
                HOUR_IN_SECONDS
            );
        }
    }

    if (!is_array($blocks)) {
        return;
    }

    foreach ($blocks as $block) {
        register_block_type($block);
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
        'title' => __('Lonestar Blocks', 'lonestar-theme'),
        'icon'  => 'wordpress',
    );

    return $categories;
}

add_filter('block_categories_all', 'lonestar_theme_blocks_category', 10, 2);
