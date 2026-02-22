<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module bootstrapping and loading conventions.
 */


/**
 * Discover enabled modules and bootstrap them.
 *
 * @return void
 */
function modules_boot_theme_modules()
{
    static $booted_modules = array();
    $enabled_catalog = modules_get_enabled_module_catalog();

    foreach ($enabled_catalog as $module_slug => $module) {
        if (isset($booted_modules[$module_slug])) {
            continue;
        }

        modules_boot_single_module($module);
        $booted_modules[$module_slug] = true;
    }
}

/**
 * Bootstrap one module.
 *
 * @param array $module Module metadata.
 * @return void
 */
function modules_boot_single_module($module)
{
    if (!is_array($module)) {
        return;
    }

    $entry_file = isset($module['entry_file']) ? (string) $module['entry_file'] : '';
    if ('' !== $entry_file && file_exists($entry_file) && is_readable($entry_file)) {
        require_once $entry_file;
    }

    $mode = isset($module['mode']) ? (string) $module['mode'] : 'file';
    $directory = isset($module['directory']) ? (string) $module['directory'] : '';
    if ('folder' !== $mode || '' === $directory) {
        return;
    }

    modules_include_module_support_files($directory);
}

/**
 * Include convention-based module support files from a module directory.
 *
 * @param string $module_directory Absolute module directory.
 * @return void
 */
function modules_include_module_support_files($module_directory)
{
    $module_directories = modules_normalize_base_directories($module_directory);
    if (empty($module_directories)) {
        return;
    }

    foreach (modules_get_module_convention_globs() as $relative_pattern => $debug_sensitive) {
        modules_include_globbed_files($module_directories, $relative_pattern, $debug_sensitive);
    }
}

/**
 * Include files from a relative glob inside given base directory.
 *
 * @param string|array<int,string> $base_directories Absolute base directory (or ordered list of directories).
 * @param string $relative_pattern Relative glob pattern.
 * @param bool   $debug_sensitive Whether debug-only helper names should be filtered by WP_DEBUG.
 * @return void
 */
function modules_include_globbed_files($base_directories, $relative_pattern, $debug_sensitive = false)
{
    $relative_pattern = ltrim((string) $relative_pattern, '/');
    $debug_sensitive = (bool) $debug_sensitive;
    $base_directories = modules_normalize_base_directories($base_directories);

    if (empty($base_directories) || '' === $relative_pattern) {
        return;
    }

    $file_map = array();
    foreach ($base_directories as $base_directory) {
        $pattern = $base_directory . '/' . $relative_pattern;
        $files = glob($pattern);
        if (!is_array($files) || empty($files)) {
            continue;
        }

        sort($files, SORT_NATURAL);

        foreach ($files as $file) {
            if (!is_string($file) || '' === $file) {
                continue;
            }

            $basename = basename($file);
            if (
                $debug_sensitive &&
                in_array($basename, array('helper.debug.php', 'helper.printr.php'), true) &&
                !(defined('WP_DEBUG') && WP_DEBUG)
            ) {
                continue;
            }

            // Later directories override earlier ones by filename (useful for child-theme overrides).
            $file_map[$basename] = $file;
        }
    }

    if (empty($file_map)) {
        return;
    }

    ksort($file_map, SORT_NATURAL);
    foreach ($file_map as $file) {
        require_once $file;
    }
}

/**
 * Normalize one or more base directories to unique, readable absolute paths.
 *
 * @param string|array<int,string> $base_directories Base directory (or ordered list of directories).
 * @return array<int,string>
 */
function modules_normalize_base_directories($base_directories)
{
    if (is_string($base_directories)) {
        $base_directories = array($base_directories);
    }
    if (!is_array($base_directories)) {
        return array();
    }

    $normalized_directories = array();
    foreach ($base_directories as $base_directory) {
        $base_directory = untrailingslashit(wp_normalize_path((string) $base_directory));
        if ('' === $base_directory || !is_dir($base_directory) || !is_readable($base_directory)) {
            continue;
        }

        $normalized_directories[] = $base_directory;
    }

    $normalized_directories = array_values(array_unique($normalized_directories));
    return $normalized_directories;
}

/**
 * Return convention-based relative globs for folder modules.
 *
 * @return array<string,bool> Relative glob pattern => debug-sensitive flag.
 */
function modules_get_module_convention_globs()
{

    return array(
        'inc/*.php' => false,
        'inc/helpers/helper.*.php' => true,
        'inc/shortcodes/shortcode.*.php' => false,
        'inc/walkers/walker.*.php' => false,
        'shortcodes/shortcode.*.php' => false,
        'walkers/walker.*.php' => false,
    );
}
