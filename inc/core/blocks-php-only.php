<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'lonestar_register_php_only_block_types', 12);

/**
 * Register WordPress 7 PHP-only blocks from blocks/php-only roots.
 *
 * @return void
 */
function lonestar_register_php_only_block_types()
{
    if (version_compare((string) get_bloginfo('version'), '7.0', '<')) {
        return;
    }

    $cache_key = 'lonestar_php_only_blocks_to_load_v1';
    $cache_namespace = function_exists('lonestar_get_theme_cache_namespace') ? lonestar_get_theme_cache_namespace() : 'default';
    $use_cache = !lonestar_is_vite_dev_mode();
    $directories = false;

    if ($use_cache) {
        $cached = get_transient($cache_key);
        if (
            is_array($cached) &&
            isset($cached['cache_namespace'], $cached['directories']) &&
            $cache_namespace === $cached['cache_namespace'] &&
            is_array($cached['directories'])
        ) {
            $directories = $cached['directories'];
        }
    }

    if (false === $directories) {
        $directories = function_exists('lonestar_find_block_directories') ? lonestar_find_block_directories() : array();
        $roots = function_exists('lonestar_get_php_only_block_root_paths') ? lonestar_get_php_only_block_root_paths() : array();
        $directories = array_values(
            array_filter(
                is_array($directories) ? $directories : array(),
                function ($directory) use ($roots) {
                    $directory = untrailingslashit(wp_normalize_path((string) $directory));
                    foreach ($roots as $root) {
                        $root = untrailingslashit(wp_normalize_path((string) $root));
                        if ($directory === $root || 0 === strpos($directory, $root . '/')) {
                            return true;
                        }
                    }

                    return false;
                }
            )
        );

        if ($use_cache) {
            set_transient(
                $cache_key,
                array(
                    'cache_namespace' => $cache_namespace,
                    'directories'     => $directories,
                ),
                HOUR_IN_SECONDS
            );
        }
    }

    foreach ($directories as $directory) {
        $metadata_path = lonestar_get_block_json_path($directory);
        $metadata_raw = is_readable($metadata_path) ? file_get_contents($metadata_path) : false;
        $metadata = is_string($metadata_raw) ? json_decode($metadata_raw, true) : array();
        $errors = function_exists('lonestar_validate_block_contract')
            ? lonestar_validate_block_contract('php-only', 'php-only', $metadata, $directory)
            : array();

        if (!empty($errors)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[lonestar-theme] Invalid PHP-only block contract: ' . implode(' ', $errors));
            }
            continue;
        }

        register_block_type_from_metadata($directory);
    }
}
