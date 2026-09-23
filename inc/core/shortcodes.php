<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SHORTCODES AUTOLOADER
 *
 * Automatically includes all shortcode files from /inc/shortcodes/ directory.
 * Shortcode files should follow naming convention: shortcode.{feature}.php
 *
 * Child theme shortcode files override parent files with the same filename.
 */

$lonestar_shortcode_files = array();
$lonestar_parent_shortcodes = glob(get_template_directory() . '/inc/shortcodes/shortcode.*.php');
if (is_array($lonestar_parent_shortcodes)) {
    foreach ($lonestar_parent_shortcodes as $lonestar_file) {
        $lonestar_shortcode_files[basename($lonestar_file)] = $lonestar_file;
    }
}

$lonestar_is_child_theme = get_stylesheet_directory() !== get_template_directory();
if ($lonestar_is_child_theme) {
    $lonestar_child_shortcodes = glob(get_stylesheet_directory() . '/inc/shortcodes/shortcode.*.php');
    if (is_array($lonestar_child_shortcodes)) {
        foreach ($lonestar_child_shortcodes as $lonestar_file) {
            $lonestar_shortcode_files[basename($lonestar_file)] = $lonestar_file;
        }
    }
}

ksort($lonestar_shortcode_files, SORT_NATURAL);
foreach ($lonestar_shortcode_files as $lonestar_file) {
    include_once $lonestar_file;
}
