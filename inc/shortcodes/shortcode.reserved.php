<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('lonestar_shortcode_reserved')) {
    /**
     * Return reserved sign for [R].
     *
     * @return string
     */
    function lonestar_shortcode_reserved()
    {
        return '&reg;';
    }
}

add_shortcode('R', 'lonestar_shortcode_reserved');
