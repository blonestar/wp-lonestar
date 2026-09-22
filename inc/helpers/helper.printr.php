<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper for quick onscreen debug
 */
if (!function_exists('lonestar_printr') && defined('WP_DEBUG') && WP_DEBUG && function_exists('wp_get_environment_type') && 'production' !== wp_get_environment_type()) {
    function lonestar_printr($arr, $die = false)
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

/**
 * Deprecated: use lonestar_printr() instead.
 *
 * Kept as a thin compatibility alias for existing child-theme/template code.
 * Only defined under the same WP_DEBUG / non-production gate as
 * lonestar_printr(); no _deprecated_function() notice to avoid log noise.
 */
if (!function_exists('printr') && function_exists('lonestar_printr')) {
    function printr($arr, $die = false)
    {
        lonestar_printr($arr, $die);
    }
}
