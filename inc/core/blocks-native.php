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
    $index = function_exists('lonestar_get_block_runtime_index') ? lonestar_get_block_runtime_index() : array();
    $block_directories = isset($index['directories']['native']) && is_array($index['directories']['native']) ? $index['directories']['native'] : array();
    $metadata_cache = isset($index['metadata']) && is_array($index['metadata']) ? $index['metadata'] : array();

    if (empty($block_directories)) {
        return;
    }

    foreach ($block_directories as $block_directory) {
        $block_directory = untrailingslashit(wp_normalize_path((string) $block_directory));
        $entry = isset($metadata_cache[$block_directory]) ? $metadata_cache[$block_directory] : null;
        $metadata_path = (is_array($entry) && isset($entry['path'])) ? (string) $entry['path'] : '';
        $metadata = (is_array($entry) && isset($entry['data']) && is_array($entry['data'])) ? $entry['data'] : null;

        if ('' === $metadata_path || !is_array($metadata) || empty($metadata['name'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[lonestar-theme] Invalid native block metadata: ' . ('' !== $metadata_path ? $metadata_path : $block_directory));
            }
            continue;
        }

        $variant = function_exists('lonestar_get_block_variant')
            ? lonestar_get_block_variant('native', $metadata, $block_directory)
            : 'native-static';
        $errors = function_exists('lonestar_validate_block_contract')
            ? lonestar_validate_block_contract('native', $variant, $metadata, $block_directory)
            : array();

        if (!empty($errors)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[lonestar-theme] Invalid native block contract: ' . implode(' ', $errors));
            }
            continue;
        }

        register_block_type_from_metadata($block_directory);
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

    // Current consolidated block runtime index (single fixed key).
    if (function_exists('lonestar_get_block_runtime_index_transient_key')) {
        delete_transient(lonestar_get_block_runtime_index_transient_key());
    } else {
        delete_transient('lonestar_block_runtime_v1');
    }

    // Legacy per-family discovery/asset-map transients (cleanup only).
    delete_transient('lonestar_acf_blocks_to_load');
    delete_transient('lonestar_native_blocks_to_load');
    delete_transient('lonestar_acf_blocks_to_load_v2');
    delete_transient('lonestar_acf_blocks_to_load_v3');
    delete_transient('lonestar_native_blocks_to_load_v2');
    delete_transient('lonestar_php_only_blocks_to_load_v1');
    delete_transient('lonestar_blocks_to_scan_' . $cache_namespace);
    delete_transient('lonestar_blocks_to_scan_v2_' . $cache_namespace);
    delete_transient('lonestar_blocks_to_scan_v3_' . $cache_namespace);
    delete_transient('lonestar_block_asset_map_' . $cache_namespace);
    delete_transient('lonestar_block_asset_map_v2_' . $cache_namespace);
    delete_transient('lonestar_block_asset_map_v4_' . $cache_namespace);
}
