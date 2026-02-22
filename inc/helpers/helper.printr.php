<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper for quick onscreen debug
 */
if (!function_exists('printr')) {
    function printr($arr, $die = false)
    {
        if (!(defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }

        echo "<pre>";
        print_r($arr);
        echo "</pre>";

        if ($die)
            exit;
    }
}
