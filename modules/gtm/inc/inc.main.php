<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', 'lonestar_module_output_gtm_head_snippet', 1);
add_action('wp_body_open', 'lonestar_module_output_gtm_body_snippet', 1);

/**
 * Resolve GTM container ID from native module settings.
 *
 * @return string
 */
function lonestar_module_get_gtm_container_id()
{
    if (!function_exists('lonestar_module_get_gtm_settings')) {
        return '';
    }

    $settings = lonestar_module_get_gtm_settings();
    $is_enabled = isset($settings['enabled']) ? (bool) $settings['enabled'] : false;
    if (!$is_enabled) {
        return '';
    }

    $container_id = isset($settings['container_id']) ? (string) $settings['container_id'] : '';
    return lonestar_module_normalize_gtm_container_id($container_id);
}

/**
 * Print GTM script in document head when enabled.
 *
 * @return void
 */
function lonestar_module_output_gtm_head_snippet()
{
    $container_id = lonestar_module_get_gtm_container_id();
    if ('' === $container_id) {
        return;
    }

    echo lonestar_module_get_gtm_head_markup($container_id);
}

/**
 * Print GTM noscript block after body opens when enabled.
 *
 * @return void
 */
function lonestar_module_output_gtm_body_snippet()
{
    $container_id = lonestar_module_get_gtm_container_id();
    if ('' === $container_id) {
        return;
    }

    echo lonestar_module_get_gtm_body_markup($container_id);
}
