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
    $transient_key = 'lonestar_acf_blocks_to_load';
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
    $block_roots = function_exists('lonestar_get_acf_block_root_paths')
        ? lonestar_get_acf_block_root_paths()
        : array(wp_normalize_path(TEMPLATE_PATH . ACF_BLOCKS_PATH));

    if (false === $blocks) {
        $blocks = array();

        if (empty($block_roots)) {
            return;
        }

        foreach ($block_roots as $blocks_path) {
            if (!is_dir($blocks_path) || !is_readable($blocks_path)) {
                continue;
            }

            try {
                $directory = new \RecursiveDirectoryIterator($blocks_path, \FilesystemIterator::FOLLOW_SYMLINKS);
                $filter = new \RecursiveCallbackFilterIterator($directory, function ($current) {
                    $filename = $current->getFilename();

                    if ('' === $filename || '.' === $filename[0]) {
                        return false;
                    }

                    if ($current->isDir()) {
                        $skip_dirs = array('node_modules', 'dist', 'build', 'vendor', '.git');
                        return !in_array($filename, $skip_dirs, true);
                    }

                    return ('block.json' === $filename || '.block.json' === substr($filename, -11));
                });

                $files = new \RecursiveIteratorIterator($filter);

                foreach ($files as $file) {
                    $block_path = (string) $file;
                    if (!is_readable($block_path)) {
                        continue;
                    }

                    $block_json = file_get_contents($block_path);
                    if (false === $block_json) {
                        continue;
                    }

                    $block_arr = json_decode($block_json, true);
                    if (!is_array($block_arr)) {
                        throw new \RuntimeException('Block JSON is not valid: ' . $block_path);
                    }

                    $blocks[] = wp_normalize_path($block_path);
                }
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[lonestar-theme] ' . $e->getMessage());
                }
            }
        }

        $blocks = array_values(array_unique($blocks));
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
