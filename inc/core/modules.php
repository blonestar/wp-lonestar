<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme module system entrypoint.
 */
if (!defined('MODULES_TOGGLE_OPTION')) {
    if (defined('LONESTAR_MODULE_TOGGLE_OPTION')) {
        define('MODULES_TOGGLE_OPTION', LONESTAR_MODULE_TOGGLE_OPTION);
    } else {
        define('MODULES_TOGGLE_OPTION', 'lonestar_module_toggles');
    }
}
if (!defined('LONESTAR_MODULE_TOGGLE_OPTION')) {
    define('LONESTAR_MODULE_TOGGLE_OPTION', MODULES_TOGGLE_OPTION);
}

if (!defined('MODULES_CATALOG_CACHE_TTL')) {
    if (defined('LONESTAR_MODULE_CATALOG_CACHE_TTL')) {
        define('MODULES_CATALOG_CACHE_TTL', LONESTAR_MODULE_CATALOG_CACHE_TTL);
    } else {
        define('MODULES_CATALOG_CACHE_TTL', HOUR_IN_SECONDS);
    }
}
if (!defined('LONESTAR_MODULE_CATALOG_CACHE_TTL')) {
    define('LONESTAR_MODULE_CATALOG_CACHE_TTL', MODULES_CATALOG_CACHE_TTL);
}

require_once __DIR__ . '/modules_bootstrap.php';
require_once __DIR__ . '/modules_catalog.php';
require_once __DIR__ . '/modules_state.php';
require_once __DIR__ . '/modules_admin.php';

add_action('after_setup_theme', 'modules_boot_theme_modules', 20);
add_action('admin_menu', 'modules_register_modules_admin_page', 30);
add_action('admin_init', 'modules_handle_modules_admin_post');
add_filter('acf/settings/load_json', 'modules_filter_module_acf_json_load_paths', 20);
add_action('update_option_' . LONESTAR_MODULE_TOGGLE_OPTION, 'modules_handle_module_toggle_option_update', 10, 3);
add_action('after_switch_theme', 'modules_flush_module_related_caches');
add_action('upgrader_process_complete', 'modules_flush_module_related_caches', 10, 2);
