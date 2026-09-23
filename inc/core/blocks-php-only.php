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

    $index = function_exists('lonestar_get_block_runtime_index') ? lonestar_get_block_runtime_index() : array();
    $directories = isset($index['directories']['php-only']) && is_array($index['directories']['php-only']) ? $index['directories']['php-only'] : array();
    $metadata_cache = isset($index['metadata']) && is_array($index['metadata']) ? $index['metadata'] : array();

    foreach ($directories as $directory) {
        $directory = untrailingslashit(wp_normalize_path((string) $directory));
        $entry = isset($metadata_cache[$directory]) ? $metadata_cache[$directory] : null;
        $metadata = (is_array($entry) && isset($entry['data']) && is_array($entry['data'])) ? $entry['data'] : array();

        $errors = function_exists('lonestar_validate_block_contract')
            ? lonestar_validate_block_contract('php-only', 'php-only', $metadata, $directory)
            : array();

        if (!empty($errors)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[lonestar-theme] Invalid PHP-only block contract: ' . implode(' ', $errors)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-only diagnostic for an invalid block contract.
            }
            continue;
        }

        register_block_type_from_metadata($directory);
    }
}
