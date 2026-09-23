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

$lonestar_helpers = array();
$lonestar_parent_helpers = glob(get_template_directory() . '/inc/helpers/helper.*.php');
if (is_array($lonestar_parent_helpers)) {
    foreach ($lonestar_parent_helpers as $lonestar_file) {
        $lonestar_helpers[basename($lonestar_file)] = $lonestar_file;
    }
}

$lonestar_is_child_theme = get_stylesheet_directory() !== get_template_directory();
if ($lonestar_is_child_theme) {
    $lonestar_child_helpers = glob(get_stylesheet_directory() . '/inc/helpers/helper.*.php');
    if (is_array($lonestar_child_helpers)) {
        foreach ($lonestar_child_helpers as $lonestar_file) {
            $lonestar_helpers[basename($lonestar_file)] = $lonestar_file;
        }
    }
}

ksort($lonestar_helpers, SORT_NATURAL);
$lonestar_debug_only_helpers = array(
    'helper.debug.php',
    'helper.printr.php',
);

foreach ($lonestar_helpers as $lonestar_file) {
    $lonestar_basename = basename($lonestar_file);
    if (in_array($lonestar_basename, $lonestar_debug_only_helpers, true) && !(defined('WP_DEBUG') && WP_DEBUG)) {
        continue;
    }

    include_once $lonestar_file;
}
