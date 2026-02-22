<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GTM module bootstrap.
 */
foreach (glob(__DIR__ . '/inc/inc.*.php') as $file) {
    require_once $file;
}
