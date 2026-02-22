<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'lonestar_register_native_block_types', 11);
add_action('after_switch_theme', 'lonestar_flush_block_discovery_caches');
add_action('upgrader_process_complete', 'lonestar_flush_block_discovery_caches', 10);

/**
 * Discover and register native block types from blocks/native.
 *
 * @return void
 */
function lonestar_register_native_block_types()
{
    $transient_key = 'lonestar_native_blocks_to_load';
    $cache_namespace = function_exists('lonestar_get_theme_cache_namespace') ? lonestar_get_theme_cache_namespace() : 'default';
    $use_cache = !lonestar_is_vite_dev_mode();
    $block_directories = false;

    if ($use_cache) {
        $cached_payload = get_transient($transient_key);
        if (
            is_array($cached_payload) &&
            isset($cached_payload['cache_namespace'], $cached_payload['directories']) &&
            $cache_namespace === $cached_payload['cache_namespace'] &&
            is_array($cached_payload['directories'])
        ) {
            $block_directories = $cached_payload['directories'];
        }
    }

    if (false === $block_directories) {
        $block_directories = lonestar_find_block_directories();
        $native_roots = function_exists('lonestar_get_native_block_root_paths')
            ? lonestar_get_native_block_root_paths()
            : array(wp_normalize_path(TEMPLATE_PATH . NATIVE_BLOCKS_PATH));

        $block_directories = array_values(
            array_filter(
                $block_directories,
                function ($directory) use ($native_roots) {
                    $normalized_directory = wp_normalize_path($directory);
                    foreach ($native_roots as $native_root) {
                        if (0 === strpos($normalized_directory, wp_normalize_path($native_root))) {
                            return true;
                        }
                    }

                    return false;
                }
            )
        );

        if ($use_cache) {
            set_transient(
                $transient_key,
                array(
                    'cache_namespace' => $cache_namespace,
                    'directories'     => $block_directories,
                ),
                HOUR_IN_SECONDS
            );
        }
    }

    if (!is_array($block_directories) || empty($block_directories)) {
        return;
    }

    foreach ($block_directories as $block_directory) {
        $metadata_path = lonestar_get_block_json_path($block_directory);
        if ('' === $metadata_path || !is_readable($metadata_path)) {
            continue;
        }

        $metadata_raw = file_get_contents($metadata_path);
        $metadata = is_string($metadata_raw) ? json_decode($metadata_raw, true) : null;

        if (!is_array($metadata) || empty($metadata['name'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[lonestar-theme] Invalid native block metadata: ' . $metadata_path);
            }
            continue;
        }

        $args = array();
        $render_file = wp_normalize_path($block_directory . '/render.php');

        if (file_exists($render_file) && is_readable($render_file)) {
            $args['render_callback'] = function ($attributes, $content, $block) use ($render_file) {
                $attributes = is_array($attributes) ? $attributes : array();
                $content = is_string($content) ? $content : '';

                ob_start();
                include $render_file;
                return (string) ob_get_clean();
            };
        }

        register_block_type_from_metadata($block_directory, $args);
    }
}

/**
 * Flush cached block discovery data.
 *
 * @param mixed $upgrader_object Upgrader instance when called from upgrader hooks.
 * @param mixed $options Upgrader options when called from upgrader hooks.
 * @return void
 */
function lonestar_flush_block_discovery_caches($upgrader_object = null, $options = null)
{
    unset($upgrader_object, $options);

    $cache_namespace = function_exists('lonestar_get_theme_cache_namespace') ? lonestar_get_theme_cache_namespace() : 'default';

    delete_transient('lonestar_acf_blocks_to_load');
    delete_transient('lonestar_native_blocks_to_load');
    delete_transient('lonestar_blocks_to_scan_' . $cache_namespace);
    delete_transient('lonestar_block_asset_map_' . $cache_namespace);
}
