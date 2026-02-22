<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('theme_shortcode_year')) {
    /**
     * Return current year for [year] and [Y].
     *
     * @return string
     */
    function theme_shortcode_year()
    {
        return wp_date('Y');
    }
}

add_shortcode('year', 'theme_shortcode_year');
add_shortcode('Y', 'theme_shortcode_year');

