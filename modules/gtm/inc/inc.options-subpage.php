<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('acf/init', 'lonestar_module_register_gtm_options_subpage', 20);

/**
 * Register GTM options subpage under Theme Options.
 *
 * @return void
 */
function lonestar_module_register_gtm_options_subpage()
{
    if (!function_exists('acf_add_options_sub_page')) {
        return;
    }

    acf_add_options_sub_page(
        array(
            'page_title'  => __('GTM', 'lonestar-theme'),
            'menu_title'  => __('GTM', 'lonestar-theme'),
            'parent_slug' => 'theme-options',
        )
    );
}
