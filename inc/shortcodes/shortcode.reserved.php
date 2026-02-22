<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('theme_shortcode_reserved')) {
    /**
     * Return reserved sign for [R].
     *
     * @return string
     */
    function theme_shortcode_reserved()
    {
        return '&reg;';
    }
}

add_shortcode('R', 'theme_shortcode_reserved');

