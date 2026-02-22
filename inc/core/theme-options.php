<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Basic Theme Options page/menu
 */

add_action('acf/init', 'lonestar_register_theme_options_page', 20);

/**
 * Register Theme Options page when ACF Pro is available.
 *
 * @return void
 */
function lonestar_register_theme_options_page()
{
    if (!function_exists('acf_add_options_page')) {
        return;
    }

    acf_add_options_page(array(
        'page_title' => __('Theme Options', 'lonestar-theme'),
        'menu_title' => __('Theme Options', 'lonestar-theme'),
        'menu_slug'  => 'theme-options',
        'capability' => 'manage_options',
        'redirect'   => false,
    ));
}
