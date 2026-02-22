<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HELPERS AUTOLOADER
 *
 * Automatically includes all helper files from /inc/helpers/ directory
 * Helper files should follow naming convention: helper.{feature}.php
 *
 * v0.3
 * Child theme helper files override parent helpers with the same filename.
 */

$helpers = array();
$parent_helpers = glob(get_template_directory() . '/inc/helpers/helper.*.php');
if (is_array($parent_helpers)) {
    foreach ($parent_helpers as $file) {
        $helpers[basename($file)] = $file;
    }
}

$is_child_theme = get_stylesheet_directory() !== get_template_directory();
if ($is_child_theme) {
    $child_helpers = glob(get_stylesheet_directory() . '/inc/helpers/helper.*.php');
    if (is_array($child_helpers)) {
        foreach ($child_helpers as $file) {
            $helpers[basename($file)] = $file;
        }
    }
}

ksort($helpers, SORT_NATURAL);
$debug_only_helpers = array(
    'helper.debug.php',
    'helper.printr.php',
);

foreach ($helpers as $file) {
    $basename = basename($file);
    if (in_array($basename, $debug_only_helpers, true) && !(defined('WP_DEBUG') && WP_DEBUG)) {
        continue;
    }

    include_once $file;
}
