<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('LONESTAR_MODULE_TOGGLE_OPTION')) {
    define('LONESTAR_MODULE_TOGGLE_OPTION', 'lonestar_module_toggles');
}

if (!defined('LONESTAR_BLOCK_TOGGLE_OPTION')) {
    define('LONESTAR_BLOCK_TOGGLE_OPTION', 'lonestar_block_toggles');
}

if (!defined('LONESTAR_MODULE_CATALOG_CACHE_TTL')) {
    define('LONESTAR_MODULE_CATALOG_CACHE_TTL', HOUR_IN_SECONDS);
}

require_once __DIR__ . '/modules_bootstrap.php';
require_once __DIR__ . '/modules_catalog.php';
require_once __DIR__ . '/modules_state.php';
require_once __DIR__ . '/modules_admin.php';

add_action('after_setup_theme', 'lonestar_boot_theme_modules', 20);
add_action('admin_menu', 'lonestar_register_modules_admin_page', 30);
add_action('admin_init', 'lonestar_handle_modules_admin_post');
add_action('admin_init', 'lonestar_reconcile_missing_enabled_modules', 5);
add_filter('acf/settings/load_json', 'lonestar_filter_module_acf_json_load_paths', 20);
add_action('update_option_' . LONESTAR_MODULE_TOGGLE_OPTION, 'lonestar_handle_module_toggle_option_update', 10, 3);
add_action('update_option_' . LONESTAR_BLOCK_TOGGLE_OPTION, 'lonestar_handle_module_toggle_option_update', 10, 3);
add_action('after_switch_theme', 'lonestar_flush_module_related_caches');
add_action('upgrader_process_complete', 'lonestar_flush_module_related_caches', 10, 2);
