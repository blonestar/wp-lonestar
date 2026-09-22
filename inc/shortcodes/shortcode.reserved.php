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

/**
 * Deprecated: use lonestar_shortcode_reserved() instead. Compatibility
 * alias kept for existing child-theme/template code.
 */
if (!function_exists('theme_shortcode_reserved')) {
    function theme_shortcode_reserved()
    {
        return lonestar_shortcode_reserved();
    }
}

add_shortcode('R', 'lonestar_shortcode_reserved');
