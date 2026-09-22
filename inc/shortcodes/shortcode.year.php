<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('lonestar_shortcode_year')) {
    /**
     * Return current year for [year] and [Y].
     *
     * @return string
     */
    function lonestar_shortcode_year()
    {
        return wp_date('Y');
    }
}

/**
 * Deprecated: use lonestar_shortcode_year() instead. Compatibility alias
 * kept for existing child-theme/template code.
 */
if (!function_exists('theme_shortcode_year')) {
    function theme_shortcode_year()
    {
        return lonestar_shortcode_year();
    }
}

add_shortcode('year', 'lonestar_shortcode_year');
add_shortcode('Y', 'lonestar_shortcode_year');
