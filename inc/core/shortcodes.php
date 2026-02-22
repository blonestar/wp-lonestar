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

$shortcode_files = array();
$parent_shortcodes = glob(get_template_directory() . '/inc/shortcodes/shortcode.*.php');
if (is_array($parent_shortcodes)) {
    foreach ($parent_shortcodes as $file) {
        $shortcode_files[basename($file)] = $file;
    }
}

$is_child_theme = get_stylesheet_directory() !== get_template_directory();
if ($is_child_theme) {
    $child_shortcodes = glob(get_stylesheet_directory() . '/inc/shortcodes/shortcode.*.php');
    if (is_array($child_shortcodes)) {
        foreach ($child_shortcodes as $file) {
            $shortcode_files[basename($file)] = $file;
        }
    }
}

ksort($shortcode_files, SORT_NATURAL);
foreach ($shortcode_files as $file) {
    include_once $file;
}

