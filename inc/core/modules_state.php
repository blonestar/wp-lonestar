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

    $enabled_keys = modules_get_enabled_module_keys(array_keys($catalog));
    $enabled_catalog = array();

    foreach ($enabled_keys as $module_key) {
        if (isset($catalog[$module_key])) {
            $enabled_catalog[$module_key] = $catalog[$module_key];
        }
    }

    return $enabled_catalog;
}

/**
 * Backward-compatible alias for enabled module keys.
 *
 * @param array<int,string>|null $available_keys Optional discovered module keys.
 * @return array<int,string>
 */
function modules_get_enabled_module_slugs($available_keys = null)
{
    return modules_get_enabled_module_keys($available_keys);
}

/**
 * Return enabled module keys.
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
 * @param array<int,string>|null $available_keys Optional discovered module keys.
 * @return array<int,string>
 */
function modules_get_enabled_module_keys($available_keys = null)
{
    if (!is_array($available_keys)) {
        $available_keys = array_keys(modules_get_module_catalog());
    }

    if (empty($available_keys)) {
        return array();
    }

    $available_keys = array_values(array_unique(array_map('sanitize_key', $available_keys)));
    $toggle_map = modules_get_module_toggle_map();

    if (modules_are_all_modules_forced_disabled()) {
        return array();
    }

    $toggle_map = modules_persist_missing_enabled_modules($available_keys, $toggle_map);
    $force_disabled = modules_get_forced_disabled_module_slugs();

    $enabled_keys = array();
    foreach ($available_keys as $module_key) {
        if ('' === $module_key) {
            continue;
        }

        $slug = modules_get_module_slug_from_key($module_key);
        if (modules_is_module_forced_disabled($module_key, $slug, $force_disabled)) {
            continue;
        }

        if (!modules_get_module_toggle_value($toggle_map, $module_key, $slug)) {
            continue;
        }

        $enabled_keys[] = $module_key;
    }

    $enabled_keys = modules_resolve_enabled_module_key_conflicts($enabled_keys, modules_get_module_catalog());

    /**
     * Filter enabled module keys.
     *
     * @param array<int,string> $enabled_keys Enabled module keys.
     * @param array<int,string> $available_keys All discovered module keys.
     * @param array<string,bool> $toggle_map Option-driven toggle map.
     */
    $enabled_keys = apply_filters('lonestar_enabled_module_keys', $enabled_keys, $available_keys, $toggle_map);

    /**
     * Backward-compatible filter name.
     *
     * @param array<int,string> $enabled_keys Enabled module keys.
     * @param array<int,string> $available_keys All discovered module keys.
     * @param array<string,bool> $toggle_map Option-driven toggle map.
     */
    $enabled_keys = apply_filters('lonestar_enabled_modules', $enabled_keys, $available_keys, $toggle_map);
    if (!is_array($enabled_keys)) {
        return array();
    }

    $enabled_keys = modules_normalize_enabled_module_keys($enabled_keys, $available_keys);
    $enabled_keys = modules_resolve_enabled_module_key_conflicts($enabled_keys, modules_get_module_catalog());

    sort($enabled_keys, SORT_NATURAL);
    return $enabled_keys;
}

/**
 * Return whether all modules are forced disabled.
 *
 * @return bool
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
 * Return module keys/slugs forced-disabled via configuration.
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
 * @param array<int,string> $available_keys Existing module keys from filesystem.
 * @param array<string,bool> $toggle_map Module toggle map.
 * @return array<string,bool>
 */
function modules_persist_missing_enabled_modules($available_keys, $toggle_map)
{
    if (!is_array($available_keys) || !is_array($toggle_map) || empty($toggle_map)) {
        return is_array($toggle_map) ? $toggle_map : array();
    }

    $available_lookup = array_fill_keys($available_keys, true);
    $updated_toggle_map = $toggle_map;
    $has_changes = false;

    foreach ($updated_toggle_map as $module_key => $enabled) {
        if (true !== $enabled) {
            continue;
        }

        if (isset($available_lookup[$module_key])) {
            continue;
        }

        $updated_toggle_map[$module_key] = false;
        $has_changes = true;
    }

    if ($has_changes) {
        update_option(LONESTAR_MODULE_TOGGLE_OPTION, $updated_toggle_map, false);
    }

    return $updated_toggle_map;
}

/**
 * Return module slug from module key.
 *
 * @param string $module_key Module key.
 * @return string
 */
function modules_get_module_slug_from_key($module_key)
{
    $split = modules_split_module_key($module_key);
    return isset($split['slug']) ? sanitize_key((string) $split['slug']) : '';
}

/**
 * Return module source from module key.
 *
 * @param string $module_key Module key.
 * @return string
 */
function modules_get_module_source_from_key($module_key)
{
    $split = modules_split_module_key($module_key);
    return isset($split['source']) ? modules_normalize_source((string) $split['source']) : 'template';
}

/**
 * Check whether module is forced disabled by config.
 *
 * @param string $module_key Module key.
 * @param string $module_slug Module slug.
 * @param array<int,string> $force_disabled Forced-disabled keys/slugs.
 * @return bool
 */
function modules_is_module_forced_disabled($module_key, $module_slug, $force_disabled)
{
    if (!is_array($force_disabled) || empty($force_disabled)) {
        return false;
    }

    if (in_array($module_key, $force_disabled, true)) {
        return true;
    }

    if ('' !== $module_slug && in_array($module_slug, $force_disabled, true)) {
        return true;
    }

    return false;
}

/**
 * Resolve module toggle state.
 *
 * Supports legacy slug-only toggle keys for backward compatibility.
 *
 * @param array<string,bool> $toggle_map Module toggle map.
 * @param string $module_key Module key.
 * @param string $module_slug Module slug.
 * @return bool
 */
function modules_get_module_toggle_value($toggle_map, $module_key, $module_slug)
{
    if (!is_array($toggle_map)) {
        return true;
    }

    if (array_key_exists($module_key, $toggle_map)) {
        return (bool) $toggle_map[$module_key];
    }

    if ('' !== $module_slug && array_key_exists($module_slug, $toggle_map)) {
        return (bool) $toggle_map[$module_slug];
    }

    return true;
}

/**
 * Build module override state grouped by slug.
 *
 * If the same slug exists in multiple sources, higher-priority source wins.
 *
 * @param array<string,array>|null $catalog Optional module catalog.
 * @return array{
 *   groups:array<string,array<int,string>>,
 *   winner_by_slug:array<string,string>,
 *   overridden_by_key:array<string,string>
 * }
 */
function modules_get_module_override_state($catalog = null)
{
    if (!is_array($catalog)) {
        $catalog = modules_get_module_catalog();
    }

    $state = array(
        'groups'            => array(),
        'winner_by_slug'    => array(),
        'overridden_by_key' => array(),
    );

    if (!is_array($catalog) || empty($catalog)) {
        return $state;
    }

    foreach ($catalog as $module_key => $module) {
        $module_key = sanitize_key((string) $module_key);
        if ('' === $module_key || !is_array($module)) {
            continue;
        }

        $slug = isset($module['slug']) ? sanitize_key((string) $module['slug']) : '';
        if ('' === $slug) {
            $slug = modules_get_module_slug_from_key($module_key);
        }
        if ('' === $slug) {
            $slug = $module_key;
        }

        if (!isset($state['groups'][$slug])) {
            $state['groups'][$slug] = array();
        }
        $state['groups'][$slug][] = $module_key;
    }

    foreach ($state['groups'] as $slug => $candidate_keys) {
        if (!is_array($candidate_keys) || empty($candidate_keys)) {
            continue;
        }

        $candidate_keys = array_values(array_unique(array_map('sanitize_key', $candidate_keys)));
        if (1 === count($candidate_keys)) {
            $state['winner_by_slug'][$slug] = $candidate_keys[0];
            continue;
        }

        usort(
            $candidate_keys,
            function ($left_key, $right_key) use ($catalog) {
                $left_source = isset($catalog[$left_key]['source']) ? (string) $catalog[$left_key]['source'] : modules_get_module_source_from_key($left_key);
                $right_source = isset($catalog[$right_key]['source']) ? (string) $catalog[$right_key]['source'] : modules_get_module_source_from_key($right_key);

                $left_priority = modules_get_source_priority($left_source);
                $right_priority = modules_get_source_priority($right_source);

                if ($left_priority === $right_priority) {
                    return strnatcasecmp((string) $left_key, (string) $right_key);
                }

                // Descending priority: higher value wins.
                return ($left_priority > $right_priority) ? -1 : 1;
            }
        );

        $winner_key = $candidate_keys[0];
        $state['winner_by_slug'][$slug] = $winner_key;

        foreach ($candidate_keys as $candidate_key) {
            if ($candidate_key === $winner_key) {
                continue;
            }

            $state['overridden_by_key'][$candidate_key] = $winner_key;
        }
    }

    return $state;
}

/**
 * Resolve enabled module conflicts by slug.
 *
 * If the same slug is enabled in both parent and child sources, child wins.
 *
 * @param array<int,string> $enabled_keys Enabled module keys.
 * @param array<string,array> $catalog Module catalog.
 * @return array<int,string>
 */
function modules_resolve_enabled_module_key_conflicts($enabled_keys, $catalog)
{
    if (!is_array($enabled_keys) || empty($enabled_keys)) {
        return array();
    }
    if (!is_array($catalog) || empty($catalog)) {
        return array_values(array_unique(array_map('sanitize_key', $enabled_keys)));
    }

    $override_state = modules_get_module_override_state($catalog);
    $overridden_lookup = (is_array($override_state) && isset($override_state['overridden_by_key']) && is_array($override_state['overridden_by_key']))
        ? $override_state['overridden_by_key']
        : array();

    $resolved = array();
    foreach ($enabled_keys as $module_key) {
        $module_key = sanitize_key((string) $module_key);
        if ('' === $module_key || !isset($catalog[$module_key])) {
            continue;
        }
        if (isset($overridden_lookup[$module_key])) {
            continue;
        }

        $resolved[] = $module_key;
    }

    $resolved = array_values(array_unique(array_map('sanitize_key', $resolved)));
    sort($resolved, SORT_NATURAL);
    return $resolved;
}

/**
 * Normalize enabled module key list.
 *
 * Supports legacy filter returns that may still provide slug-only values.
 * Ambiguous slug values (present in multiple sources) are ignored.
 *
 * @param array<int,string> $candidate_keys Candidate key list.
 * @param array<int,string> $available_keys Available module keys.
 * @return array<int,string>
 */
function modules_normalize_enabled_module_keys($candidate_keys, $available_keys)
{
    if (!is_array($candidate_keys) || !is_array($available_keys)) {
        return array();
    }

    $available_keys = array_values(array_unique(array_map('sanitize_key', $available_keys)));
    $available_lookup = array_fill_keys($available_keys, true);
    $slug_map = array();

    foreach ($available_keys as $available_key) {
        $slug = modules_get_module_slug_from_key($available_key);
        if ('' === $slug) {
            continue;
        }
        if (!isset($slug_map[$slug])) {
            $slug_map[$slug] = array();
        }
        $slug_map[$slug][] = $available_key;
    }

    $normalized = array();
    foreach ($candidate_keys as $candidate_key) {
        $candidate_key = sanitize_key((string) $candidate_key);
        if ('' === $candidate_key) {
            continue;
        }

        if (isset($available_lookup[$candidate_key])) {
            $normalized[] = $candidate_key;
            continue;
        }

        // Legacy slug-only value support, only when unambiguous.
        if (isset($slug_map[$candidate_key]) && 1 === count($slug_map[$candidate_key])) {
            $normalized[] = $slug_map[$candidate_key][0];
        }
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized, SORT_NATURAL);
    return $normalized;
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
    foreach ($raw_value as $module_key => $enabled) {
        $module_key = sanitize_key((string) $module_key);
        if ('' === $module_key) {
            continue;
        }

        $toggle_map[$module_key] = (bool) $enabled;
    }

    return $toggle_map;
}

/**
 * Build module key => entry-file map.
 *
 * @return array<string,string>
 */
function modules_get_module_file_map()
{
    $map = array();
    foreach (modules_get_module_catalog() as $module_key => $module) {
        $entry_file = isset($module['entry_file']) ? (string) $module['entry_file'] : '';
        if ('' === $entry_file) {
            continue;
        }

        $map[$module_key] = $entry_file;
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

    $parts = array('v2');
    $parts[] = wp_json_encode($toggle_map);

    foreach ($catalog as $module_key => $module) {
        $entry_file = isset($module['entry_file']) ? (string) $module['entry_file'] : '';
        $module_dir = isset($module['directory']) ? (string) $module['directory'] : '';
        $mode = isset($module['mode']) ? (string) $module['mode'] : 'file';
        $source = isset($module['source']) ? (string) $module['source'] : modules_get_module_source_from_key($module_key);

        $entry_mtime = ('' !== $entry_file && file_exists($entry_file)) ? (string) filemtime($entry_file) : '0';
        $dir_mtime = ('' !== $module_dir && file_exists($module_dir)) ? (string) filemtime($module_dir) : '0';

        $parts[] = implode(':', array($module_key, $source, $mode, $entry_mtime, $dir_mtime));
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
