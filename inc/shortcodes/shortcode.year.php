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

add_shortcode('year', 'lonestar_shortcode_year');
add_shortcode('Y', 'lonestar_shortcode_year');
