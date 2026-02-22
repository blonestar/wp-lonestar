<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module enable/disable state and runtime paths.
 */

function modules_get_enabled_module_catalog()
{
    $catalog = modules_get_module_catalog();
    if (empty($catalog)) {
        return array();
    }

    $enabled_slugs = modules_get_enabled_module_slugs(array_keys($catalog));
    $enabled_catalog = array();

    foreach ($enabled_slugs as $slug) {
        if (isset($catalog[$slug])) {
            $enabled_catalog[$slug] = $catalog[$slug];
        }
    }

    return $enabled_catalog;
}

/**
 * Return enabled module slugs.
 *
 * Defaults to all discovered modules enabled unless explicitly disabled
 * in option map `lonestar_module_toggles`.
 *
 * Emergency controls:
 * - Set `MODULES_DISABLE_ALL` to true in wp-config.php to force-disable all modules.
 * - Set `MODULES_DISABLED` (array or comma-separated string) to force-disable specific modules.
 * - Set `LONESTAR_DISABLE_ALL_MODULES` to true in wp-config.php to force-disable all modules.
 * - Set `LONESTAR_DISABLED_MODULES` (array or comma-separated string) to force-disable specific modules.
 * - Create `{theme}/.disable-modules` file to force-disable all modules via filesystem.
 *
 * @param array<int,string>|null $available_slugs Optional discovered module slugs.
 * @return array<int,string>
 */
function modules_are_all_modules_forced_disabled()
{
    if (defined('MODULES_DISABLE_ALL') && true === MODULES_DISABLE_ALL) {
        return true;
    }

    if (defined('LONESTAR_DISABLE_ALL_MODULES') && true === LONESTAR_DISABLE_ALL_MODULES) {
        return true;
    }

    $sentinel_file = wp_normalize_path(trailingslashit(get_template_directory()) . '.disable-modules');
    if (file_exists($sentinel_file)) {
        return true;
    }

    return false;
}

/**
 * Return module slugs forced-disabled via configuration.
 *
 * @return array<int,string>
 */
function modules_get_forced_disabled_module_slugs()
{
    $configured_value = array();
    if (defined('MODULES_DISABLED')) {
        $configured_value = MODULES_DISABLED;
    } elseif (defined('LONESTAR_DISABLED_MODULES')) {
        $configured_value = LONESTAR_DISABLED_MODULES;
    }

    if (is_string($configured_value)) {
        $configured_value = array_map('trim', explode(',', $configured_value));
    }

    if (!is_array($configured_value)) {
        return array();
    }

    $slugs = array_values(array_unique(array_map('sanitize_key', $configured_value)));
    $slugs = array_values(array_filter($slugs, 'strlen'));
    sort($slugs, SORT_NATURAL);

    return $slugs;
}

/**
 * Persist-disable modules that are enabled in options but missing on filesystem.
 *
 * This mirrors plugin behavior when plugin files disappear: once missing,
 * keep it disabled even after files come back until manually re-enabled.
 *
 * @param array<int,string> $available_slugs Existing module slugs from filesystem.
 * @param array<string,bool> $toggle_map Module toggle map.
 * @return array<string,bool>
 */
function modules_persist_missing_enabled_modules($available_slugs, $toggle_map)
{
    if (!is_array($available_slugs) || !is_array($toggle_map) || empty($toggle_map)) {
        return is_array($toggle_map) ? $toggle_map : array();
    }

    $available_lookup = array_fill_keys($available_slugs, true);
    $updated_toggle_map = $toggle_map;
    $has_changes = false;

    foreach ($updated_toggle_map as $slug => $enabled) {
        if (true !== $enabled) {
            continue;
        }

        if (isset($available_lookup[$slug])) {
            continue;
        }

        $updated_toggle_map[$slug] = false;
        $has_changes = true;
    }

    if ($has_changes) {
        update_option(LONESTAR_MODULE_TOGGLE_OPTION, $updated_toggle_map, false);
    }

    return $updated_toggle_map;
}

/**
 * Return enabled module slugs.
 *
 * @param array<int,string>|null $available_slugs Optional discovered module slugs.
 * @return array<int,string>
 */
function modules_get_enabled_module_slugs($available_slugs = null)
{
    if (!is_array($available_slugs)) {
        $available_slugs = array_keys(modules_get_module_catalog());
    }

    if (empty($available_slugs)) {
        return array();
    }

    $available_slugs = array_values(array_unique(array_map('sanitize_key', $available_slugs)));
    $toggle_map = modules_get_module_toggle_map();

    if (modules_are_all_modules_forced_disabled()) {
        return array();
    }

    $toggle_map = modules_persist_missing_enabled_modules($available_slugs, $toggle_map);
    $force_disabled = modules_get_forced_disabled_module_slugs();

    $enabled_modules = array();
    foreach ($available_slugs as $slug) {
        if ('' === $slug) {
            continue;
        }

        if (in_array($slug, $force_disabled, true)) {
            continue;
        }

        if (array_key_exists($slug, $toggle_map) && false === $toggle_map[$slug]) {
            continue;
        }

        $enabled_modules[] = $slug;
    }

    /**
     * Filter enabled module slugs.
     *
     * @param array<int,string> $enabled_modules Enabled module slugs.
     * @param array<int,string> $available_slugs All discovered module slugs.
     * @param array<string,bool> $toggle_map Option-driven toggle map.
     */
    $enabled_modules = apply_filters('lonestar_enabled_modules', $enabled_modules, $available_slugs, $toggle_map);
    if (!is_array($enabled_modules)) {
        return array();
    }

    $enabled_modules = array_values(
        array_filter(
            array_unique(array_map('sanitize_key', $enabled_modules)),
            function ($slug) use ($available_slugs) {
                return in_array($slug, $available_slugs, true);
            }
        )
    );

    sort($enabled_modules, SORT_NATURAL);
    return $enabled_modules;
}

/**
 * Read module toggle option map.
 *
 * @return array<string,bool>
 */
function modules_get_module_toggle_map()
{
    $raw_value = get_option(LONESTAR_MODULE_TOGGLE_OPTION, array());
    if (!is_array($raw_value)) {
        return array();
    }

    $toggle_map = array();
    foreach ($raw_value as $slug => $enabled) {
        $sanitized_slug = sanitize_key((string) $slug);
        if ('' === $sanitized_slug) {
            continue;
        }

        $toggle_map[$sanitized_slug] = (bool) $enabled;
    }

    return $toggle_map;
}

/**
 * Build module slug => entry-file map for compatibility.
 *
 * @return array<string,string>
 */
function modules_get_module_file_map()
{
    $map = array();
    foreach (modules_get_module_catalog() as $slug => $module) {
        $entry_file = isset($module['entry_file']) ? (string) $module['entry_file'] : '';
        if ('' === $entry_file) {
            continue;
        }

        $map[$slug] = $entry_file;
    }

    return $map;
}

/**
 * Build module runtime signature for cache namespace invalidation.
 *
 * @return string
 */
function modules_get_module_runtime_signature()
{
    $catalog = modules_get_module_catalog();
    if (empty($catalog)) {
        return 'modules-none';
    }

    $toggle_map = modules_get_module_toggle_map();
    ksort($toggle_map, SORT_NATURAL);

    $parts = array('v1');
    $parts[] = wp_json_encode($toggle_map);

    foreach ($catalog as $slug => $module) {
        $entry_file = isset($module['entry_file']) ? (string) $module['entry_file'] : '';
        $module_dir = isset($module['directory']) ? (string) $module['directory'] : '';
        $mode = isset($module['mode']) ? (string) $module['mode'] : 'file';

        $entry_mtime = ('' !== $entry_file && file_exists($entry_file)) ? (string) filemtime($entry_file) : '0';
        $dir_mtime = ('' !== $module_dir && file_exists($module_dir)) ? (string) filemtime($module_dir) : '0';

        $parts[] = implode(':', array($slug, $mode, $entry_mtime, $dir_mtime));
    }

    return substr(md5(implode('|', $parts)), 0, 20);
}

/**
 * Return enabled module directories that contain a given relative path.
 *
 * @param string $relative_path Relative directory path inside module root.
 * @return array<int,string>
 */
function modules_get_enabled_module_root_paths($relative_path)
{
    $relative_path = trim((string) $relative_path, '/');
    if ('' === $relative_path) {
        return array();
    }

    $paths = array();
    foreach (modules_get_enabled_module_catalog() as $module) {
        $module_dir = isset($module['directory']) ? (string) $module['directory'] : '';
        if ('' === $module_dir) {
            continue;
        }

        $candidate = wp_normalize_path($module_dir . '/' . $relative_path);
        if (is_dir($candidate) && is_readable($candidate)) {
            $paths[] = $candidate;
        }
    }

    $paths = array_values(array_unique($paths));
    sort($paths, SORT_NATURAL);
    return $paths;
}

/**
 * Return enabled module block roots.
 *
 * @param string $block_type Block type: acf|native|all.
 * @return array<int,string>
 */
function modules_get_enabled_module_block_root_paths($block_type = 'all')
{
    $block_type = strtolower((string) $block_type);
    $paths = array();

    if ('all' === $block_type || 'acf' === $block_type) {
        $paths = array_merge($paths, modules_get_enabled_module_root_paths('blocks/acf'));
    }
    if ('all' === $block_type || 'native' === $block_type) {
        $paths = array_merge($paths, modules_get_enabled_module_root_paths('blocks/native'));
    }

    $paths = array_values(array_unique($paths));
    sort($paths, SORT_NATURAL);
    return $paths;
}

/**
 * Add enabled module acf-json directories to ACF load paths.
 *
 * @param array<int,string> $paths Existing ACF JSON load paths.
 * @return array<int,string>
 */
function modules_filter_module_acf_json_load_paths($paths)
{
    if (!is_array($paths)) {
        $paths = array();
    }

    $module_paths = modules_get_enabled_module_root_paths('acf-json');
    foreach ($module_paths as $module_path) {
        if (!in_array($module_path, $paths, true)) {
            $paths[] = $module_path;
        }
    }

    return $paths;
}
