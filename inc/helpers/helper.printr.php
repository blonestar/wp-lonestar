<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper for quick onscreen debug
 */
if (!function_exists('printr') && defined('WP_DEBUG') && WP_DEBUG && function_exists('wp_get_environment_type') && 'production' !== wp_get_environment_type()) {
    function printr($arr, $die = false)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<pre>';
        echo esc_html(print_r($arr, true));
        echo '</pre>';

        if ($die)
            exit;
    }
}
