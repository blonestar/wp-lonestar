<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('LONESTAR_BLOCK_TOGGLE_OPTION')) {
    define('LONESTAR_BLOCK_TOGGLE_OPTION', 'lonestar_block_toggles');
}

/**
 * Block catalog + toggle state helpers.
 */

/**
 * Return available block source roots.
 *
 * @return array<string,string> Source key => absolute root path.
 */
function lonestar_get_block_source_roots()
{
    $roots = array(
        'template' => untrailingslashit(wp_normalize_path(get_template_directory())),
    );

    if (get_stylesheet_directory() === get_template_directory()) {
        return $roots;
    }

    $roots['stylesheet'] = untrailingslashit(wp_normalize_path(get_stylesheet_directory()));
    return $roots;
}

/**
 * Resolve source key for a filesystem path.
 *
 * @param string $absolute_path Absolute path.
 * @return string
 */
function lonestar_get_block_source_for_path($absolute_path)
{
    $absolute_path = wp_normalize_path((string) $absolute_path);
    if ('' === $absolute_path) {
        return 'template';
    }

    $roots = lonestar_get_block_source_roots();
    foreach ($roots as $source => $root_path) {
        $root_path = untrailingslashit(wp_normalize_path((string) $root_path));
        if ('' === $root_path) {
            continue;
        }

        if ($absolute_path === $root_path || 0 === strpos($absolute_path, $root_path . '/')) {
            return sanitize_key((string) $source);
        }
    }

    return 'template';
}

/**
 * Build source-relative path from absolute path.
 *
 * @param string $absolute_path Absolute path.
 * @param string $source Source key.
 * @return string
 */
function lonestar_get_block_source_relative_path($absolute_path, $source)
{
    $absolute_path = untrailingslashit(wp_normalize_path((string) $absolute_path));
    $source = sanitize_key((string) $source);

    $roots = lonestar_get_block_source_roots();
    if (!isset($roots[$source])) {
        return '';
    }

    $root_path = untrailingslashit(wp_normalize_path((string) $roots[$source]));
    if ('' === $root_path) {
        return '';
    }

    if ($absolute_path === $root_path) {
        return '';
    }

    if (0 !== strpos($absolute_path, $root_path . '/')) {
        return '';
    }

    return ltrim(substr($absolute_path, strlen($root_path)), '/');
}

/**
 * Resolve block type from directory path.
 *
 * @param string $block_directory Absolute block directory path.
 * @return string
 */
function lonestar_get_block_type_from_directory($block_directory)
{
    $block_directory = wp_normalize_path((string) $block_directory);
    if (false !== strpos($block_directory, '/blocks/acf/')) {
        return 'acf';
    }
    if (false !== strpos($block_directory, '/blocks/native/')) {
        return 'native';
    }

    return 'unknown';
}

/**
 * Build stable block key for toggle storage.
 *
 * @param string $source Source key.
 * @param string $type Block type.
 * @param string $relative_path Source-relative block path.
 * @param string $fallback_slug Fallback slug.
 * @return string
 */
function lonestar_build_block_key($source, $type, $relative_path, $fallback_slug = '')
{
    $source = sanitize_key((string) $source);
    $type = sanitize_key((string) $type);
    $relative_path = wp_normalize_path((string) $relative_path);
    $fallback_slug = sanitize_key((string) $fallback_slug);

    $path_hash = substr(md5($relative_path), 0, 10);
    $base_slug = ('' !== $fallback_slug) ? $fallback_slug : 'block';
    return sanitize_key($source . '__' . $type . '__' . $base_slug . '__' . $path_hash);
}

/**
 * Return source priority for block conflict resolution.
 *
 * Higher value means higher runtime priority.
 *
 * @param string $source Source key.
 * @return int
 */
function lonestar_get_block_source_priority($source)
{
    $source = sanitize_key((string) $source);

    if (function_exists('modules_get_source_priority')) {
        return (int) modules_get_source_priority($source);
    }

    if ('stylesheet' === $source) {
        return 20;
    }
    if ('template' === $source) {
        return 10;
    }

    return 0;
}

/**
 * Build comparable identity for block override detection.
 *
 * @param string $block_key Block key.
 * @param array<string,mixed> $block Block catalog entry.
 * @return string
 */
function lonestar_get_block_identity($block_key, $block)
{
    $block_key = sanitize_key((string) $block_key);
    $block = is_array($block) ? $block : array();

    $name = isset($block['name']) ? strtolower((string) $block['name']) : '';
    $name = sanitize_text_field($name);
    if ('' !== $name) {
        return 'name:' . $name;
    }

    $type = isset($block['type']) ? sanitize_key((string) $block['type']) : '';
    $relative_path = isset($block['relative_path']) ? wp_normalize_path((string) $block['relative_path']) : '';
    $relative_path = ltrim(strtolower($relative_path), '/');
    if ('' !== $type && '' !== $relative_path) {
        return 'path:' . $type . ':' . $relative_path;
    }

    $slug = isset($block['slug']) ? sanitize_key((string) $block['slug']) : '';
    if ('' !== $slug) {
        return 'slug:' . $slug;
    }

    if ('' !== $block_key) {
        return 'key:' . $block_key;
    }

    return 'key:unknown';
}

/**
 * Build block override state map.
 *
 * If a block identity exists in multiple sources, higher-priority source wins.
 *
 * @param array<string,array>|null $catalog Optional block catalog.
 * @return array{
 *   groups:array<string,array<int,string>>,
 *   winner_by_identity:array<string,string>,
 *   overridden_by_key:array<string,string>
 * }
 */
function lonestar_get_block_override_state($catalog = null)
{
    if (!is_array($catalog)) {
        $catalog = lonestar_get_block_catalog();
    }

    $state = array(
        'groups'             => array(),
        'winner_by_identity' => array(),
        'overridden_by_key'  => array(),
    );

    if (empty($catalog)) {
        return $state;
    }

    foreach ($catalog as $block_key => $block) {
        $block_key = sanitize_key((string) $block_key);
        if ('' === $block_key || !is_array($block)) {
            continue;
        }

        $identity = lonestar_get_block_identity($block_key, $block);
        if (!isset($state['groups'][$identity])) {
            $state['groups'][$identity] = array();
        }

        $state['groups'][$identity][] = $block_key;
    }

    foreach ($state['groups'] as $identity => $candidate_keys) {
        if (!is_array($candidate_keys) || empty($candidate_keys)) {
            continue;
        }

        $candidate_keys = array_values(array_unique(array_map('sanitize_key', $candidate_keys)));
        if (1 === count($candidate_keys)) {
            $state['winner_by_identity'][$identity] = $candidate_keys[0];
            continue;
        }

        usort(
            $candidate_keys,
            function ($left_key, $right_key) use ($catalog) {
                $left_source = isset($catalog[$left_key]['source']) ? sanitize_key((string) $catalog[$left_key]['source']) : 'template';
                $right_source = isset($catalog[$right_key]['source']) ? sanitize_key((string) $catalog[$right_key]['source']) : 'template';

                $left_priority = lonestar_get_block_source_priority($left_source);
                $right_priority = lonestar_get_block_source_priority($right_source);
                if ($left_priority === $right_priority) {
                    return strnatcasecmp((string) $left_key, (string) $right_key);
                }

                // Descending priority: higher value wins.
                return ($left_priority > $right_priority) ? -1 : 1;
            }
        );

        $winner_key = $candidate_keys[0];
        $state['winner_by_identity'][$identity] = $winner_key;

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
 * Resolve enabled block keys by applying source conflict rules.
 *
 * @param array<int,string> $enabled_keys Enabled block keys.
 * @param array<string,array>|null $catalog Optional block catalog.
 * @return array<int,string>
 */
function lonestar_resolve_enabled_block_key_conflicts($enabled_keys, $catalog = null)
{
    if (!is_array($enabled_keys) || empty($enabled_keys)) {
        return array();
    }

    if (!is_array($catalog)) {
        $catalog = lonestar_get_block_catalog();
    }
    if (!is_array($catalog) || empty($catalog)) {
        $normalized = array_values(array_unique(array_map('sanitize_key', $enabled_keys)));
        sort($normalized, SORT_NATURAL);
        return $normalized;
    }

    $override_state = lonestar_get_block_override_state($catalog);
    $overridden_lookup = isset($override_state['overridden_by_key']) && is_array($override_state['overridden_by_key'])
        ? $override_state['overridden_by_key']
        : array();

    $resolved = array();
    foreach ($enabled_keys as $block_key) {
        $block_key = sanitize_key((string) $block_key);
        if ('' === $block_key || !isset($catalog[$block_key])) {
            continue;
        }
        if (isset($overridden_lookup[$block_key])) {
            continue;
        }

        $resolved[] = $block_key;
    }

    $resolved = array_values(array_unique(array_map('sanitize_key', $resolved)));
    sort($resolved, SORT_NATURAL);
    return $resolved;
}

/**
 * Return block catalog keyed by block key.
 *
 * @return array<string,array>
 */
function lonestar_get_block_catalog()
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $catalog = array();
    if (!function_exists('lonestar_find_block_directories') || !function_exists('lonestar_get_block_json_path')) {
        return $catalog;
    }

    $block_directories = lonestar_find_block_directories(false);
    if (!is_array($block_directories) || empty($block_directories)) {
        return $catalog;
    }

    foreach ($block_directories as $block_directory) {
        $block_directory = untrailingslashit(wp_normalize_path((string) $block_directory));
        if ('' === $block_directory || !is_dir($block_directory)) {
            continue;
        }

        $metadata_path = lonestar_get_block_json_path($block_directory);
        if ('' === $metadata_path || !is_readable($metadata_path)) {
            continue;
        }

        $source = lonestar_get_block_source_for_path($block_directory);
        $relative_path = lonestar_get_block_source_relative_path($block_directory, $source);
        $type = lonestar_get_block_type_from_directory($block_directory);
        $slug = sanitize_key((string) basename($block_directory));
        $key = lonestar_build_block_key($source, $type, $relative_path, $slug);

        $metadata_contents = file_get_contents($metadata_path);
        $metadata = is_string($metadata_contents) ? json_decode($metadata_contents, true) : array();
        if (!is_array($metadata)) {
            $metadata = array();
        }

        $block_name = isset($metadata['name']) && is_string($metadata['name']) ? sanitize_text_field($metadata['name']) : '';
        $block_title = isset($metadata['title']) && is_string($metadata['title']) ? sanitize_text_field($metadata['title']) : '';
        if ('' === $block_title && '' !== $block_name) {
            $block_title = $block_name;
        }
        if ('' === $block_title) {
            $block_title = ucwords(str_replace(array('-', '_'), ' ', $slug));
        }

        $catalog[$key] = array(
            'key'           => $key,
            'slug'          => $slug,
            'name'          => $block_name,
            'label'         => $block_title,
            'type'          => $type,
            'source'        => $source,
            'source_label'  => function_exists('modules_get_source_label') ? modules_get_source_label($source) : ucfirst($source),
            'relative_path' => $relative_path,
            'directory'     => $block_directory,
            'metadata_path' => wp_normalize_path($metadata_path),
        );
    }

    ksort($catalog, SORT_NATURAL);
    return $catalog;
}

/**
 * Read block toggle option map.
 *
 * @return array<string,bool>
 */
function lonestar_get_block_toggle_map()
{
    $raw_value = get_option(LONESTAR_BLOCK_TOGGLE_OPTION, array());
    if (!is_array($raw_value)) {
        return array();
    }

    $toggle_map = array();
    foreach ($raw_value as $block_key => $enabled) {
        $block_key = sanitize_key((string) $block_key);
        if ('' === $block_key) {
            continue;
        }

        $toggle_map[$block_key] = (bool) $enabled;
    }

    return $toggle_map;
}

/**
 * Return enabled block keys.
 *
 * @param array<int,string>|null $available_keys Optional block keys.
 * @return array<int,string>
 */
function lonestar_get_enabled_block_keys($available_keys = null)
{
    if (!is_array($available_keys)) {
        $available_keys = array_keys(lonestar_get_block_catalog());
    }
    if (empty($available_keys)) {
        return array();
    }

    $available_keys = array_values(array_unique(array_map('sanitize_key', $available_keys)));
    $available_lookup = array_fill_keys($available_keys, true);
    $toggle_map = lonestar_get_block_toggle_map();

    $enabled_keys = array();
    foreach ($available_keys as $block_key) {
        if (array_key_exists($block_key, $toggle_map) && false === $toggle_map[$block_key]) {
            continue;
        }
        $enabled_keys[] = $block_key;
    }

    /**
     * Filter enabled block keys.
     *
     * @param array<int,string> $enabled_keys Enabled block keys.
     * @param array<int,string> $available_keys Available block keys.
     * @param array<string,bool> $toggle_map Block toggle map.
     */
    $enabled_keys = apply_filters('lonestar_enabled_blocks', $enabled_keys, $available_keys, $toggle_map);
    if (!is_array($enabled_keys)) {
        return array();
    }

    $enabled_keys = array_values(
        array_filter(
            array_unique(array_map('sanitize_key', $enabled_keys)),
            function ($block_key) use ($available_lookup) {
                return isset($available_lookup[$block_key]);
            }
        )
    );

    $catalog = lonestar_get_block_catalog();
    $catalog_subset = array();
    foreach ($available_keys as $available_key) {
        if (isset($catalog[$available_key]) && is_array($catalog[$available_key])) {
            $catalog_subset[$available_key] = $catalog[$available_key];
        }
    }
    $enabled_keys = lonestar_resolve_enabled_block_key_conflicts($enabled_keys, $catalog_subset);

    sort($enabled_keys, SORT_NATURAL);
    return $enabled_keys;
}

/**
 * Filter block directories by enabled block keys.
 *
 * @param array<int,string> $directories Block directories.
 * @return array<int,string>
 */
function lonestar_filter_enabled_block_directories($directories)
{
    if (!is_array($directories) || empty($directories)) {
        return array();
    }

    $catalog = lonestar_get_block_catalog();
    if (empty($catalog)) {
        return array_values(array_unique(array_map('wp_normalize_path', $directories)));
    }

    $enabled_keys = lonestar_get_enabled_block_keys(array_keys($catalog));
    if (empty($enabled_keys)) {
        return array();
    }

    $enabled_directory_lookup = array();
    foreach ($enabled_keys as $block_key) {
        if (!isset($catalog[$block_key]) || !is_array($catalog[$block_key])) {
            continue;
        }

        $directory = isset($catalog[$block_key]['directory']) ? wp_normalize_path((string) $catalog[$block_key]['directory']) : '';
        if ('' !== $directory) {
            $enabled_directory_lookup[$directory] = true;
        }
    }

    $filtered = array();
    foreach ($directories as $directory) {
        $directory = wp_normalize_path((string) $directory);
        if ('' === $directory) {
            continue;
        }
        if (!isset($enabled_directory_lookup[$directory])) {
            continue;
        }

        $filtered[] = $directory;
    }

    $filtered = array_values(array_unique($filtered));
    sort($filtered, SORT_NATURAL);
    return $filtered;
}
