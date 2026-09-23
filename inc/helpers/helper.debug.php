<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Debug helper for logging
 * Writes debug information to error log when WP_DEBUG is enabled
 */

if (!function_exists('lonestar_write_log')) {

    function lonestar_write_log($log) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

}
