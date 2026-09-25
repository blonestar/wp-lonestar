<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GTM module bootstrap.
 */
foreach (glob(__DIR__ . '/inc/inc.*.php') as $lonestar_file) {
    require_once $lonestar_file;
}
