<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module discovery, metadata, and catalog caching.
 */

function modules_get_modules_directory()
{
    $modules_directory = wp_normalize_path(trailingslashit(get_template_directory()) . 'modules');
    if (!is_dir($modules_directory) || !is_readable($modules_directory)) {
        return '';
    }

    return untrailingslashit($modules_directory);
}

/**
 * Return normalized module source key.
 *
 * @param string $source Source key.
 * @return string
 */
function modules_normalize_source($source)
{
    $source = sanitize_key((string) $source);
    if ('' === $source) {
        return 'template';
    }

    return $source;
}

/**
 * Return source label for admin UI.
 *
 * @param string $source Source key.
 * @return string
 */
function modules_get_source_label($source)
{
    $source = modules_normalize_source($source);
    if ('stylesheet' === $source) {
        return __('Child Theme', 'lonestar');
    }

    return __('Parent Theme', 'lonestar');
}

/**
 * Return source priority for conflict resolution.
 *
 * Higher value means higher runtime priority.
 *
 * @param string $source Source key.
 * @return int
 */
function modules_get_source_priority($source)
{
    $source = modules_normalize_source($source);
    if ('stylesheet' === $source) {
        return 20;
    }
    if ('template' === $source) {
        return 10;
    }

    return 0;
}

/**
 * Build module key from source and slug.
 *
 * @param string $source Source key.
 * @param string $slug Module slug.
 * @return string
 */
function modules_build_module_key($source, $slug)
{
    $source = modules_normalize_source($source);
    $slug = sanitize_key((string) $slug);
    if ('' === $slug) {
        return '';
    }

    return sanitize_key($source . '__' . $slug);
}

/**
 * Split module key into source + slug.
 *
 * @param string $module_key Module key.
 * @return array{source:string,slug:string}
 */
function modules_split_module_key($module_key)
{
    $module_key = sanitize_key((string) $module_key);
    if ('' === $module_key) {
        return array(
            'source' => '',
            'slug'   => '',
        );
    }

    $parts = explode('__', $module_key, 2);
    if (2 === count($parts)) {
        return array(
            'source' => modules_normalize_source($parts[0]),
            'slug'   => sanitize_key($parts[1]),
        );
    }

    // Legacy key compatibility (slug-only keys from older versions).
    return array(
        'source' => 'template',
        'slug'   => $module_key,
    );
}

/**
 * Return module source directories for discovery.
 *
 * @return array<int,array{source:string,directory:string}>
 */
function modules_get_module_source_directories()
{
    $sources = array();

    $template_modules_dir = wp_normalize_path(trailingslashit(get_template_directory()) . 'modules');
    if (is_dir($template_modules_dir) && is_readable($template_modules_dir)) {
        $sources[] = array(
            'source'    => 'template',
            'directory' => untrailingslashit($template_modules_dir),
        );
    }

    $is_child_theme = (get_stylesheet_directory() !== get_template_directory());
    if (!$is_child_theme) {
        return $sources;
    }

    $stylesheet_modules_dir = wp_normalize_path(trailingslashit(get_stylesheet_directory()) . 'modules');
    if (!is_dir($stylesheet_modules_dir) || !is_readable($stylesheet_modules_dir)) {
        return $sources;
    }

    $sources[] = array(
        'source'    => 'stylesheet',
        'directory' => untrailingslashit($stylesheet_modules_dir),
    );

    return $sources;
}

/**
 * Resolve the module source fingerprint mode.
 *
 * 'full' walks every top-level module entry (glob + filemtime per entry) on
 * every request that needs the cache key, which is unnecessary filesystem
 * work once a site is deployed and its module set is stable. 'fast' hashes
 * only the modules root directory mtimes (which change on add/remove/rename
 * of a top-level entry on typical filesystems) plus the theme version.
 *
 * Defaults to 'fast' in the 'production' environment type and 'full'
 * otherwise (local/development/staging), where module folders are actively
 * being added/edited and per-entry precision is more valuable than the
 * saved filesystem calls.
 *
 * @return string 'full'|'fast'
 */
function modules_get_module_fingerprint_mode()
{
    static $mode = null;
    if (is_string($mode) && '' !== $mode) {
        return $mode;
    }

    $environment = function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production';
    $default_mode = ('production' === $environment) ? 'fast' : 'full';

    /**
     * Filter the module source fingerprint mode.
     *
     * @param string $mode Fingerprint mode: 'full' or 'fast'.
     */
    $filtered_mode = (string) apply_filters('lonestar_module_fingerprint_mode', $default_mode);
    $mode = in_array($filtered_mode, array('full', 'fast'), true) ? $filtered_mode : $default_mode;

    return $mode;
}

/**
 * Build module source filesystem fingerprint for cache namespace.
 *
 * In 'full' mode the fingerprint tracks top-level module entries
 * (file/folder + mtime) across parent and child module roots so add/remove
 * operations invalidate cache keys. In 'fast' mode (see
 * modules_get_module_fingerprint_mode()) only the modules root directory
 * mtimes plus the theme version are hashed.
 *
 * @return string
 */
function modules_get_module_source_fingerprint()
{
    $module_sources = modules_get_module_source_directories();
    if (empty($module_sources)) {
        return 'none';
    }

    if ('fast' === modules_get_module_fingerprint_mode()) {
        $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;
        $theme_version = ($theme instanceof \WP_Theme) ? (string) $theme->get('Version') : '';

        $fast_chunks = array();
        foreach ($module_sources as $module_source) {
            $source = isset($module_source['source']) ? modules_normalize_source($module_source['source']) : 'template';
            $modules_directory = isset($module_source['directory']) ? untrailingslashit(wp_normalize_path((string) $module_source['directory'])) : '';
            if ('' === $modules_directory || !is_dir($modules_directory) || !is_readable($modules_directory)) {
                continue;
            }

            $dir_mtime = file_exists($modules_directory) ? filemtime($modules_directory) : false;
            $fast_chunks[] = $source . '|' . $modules_directory . '|' . ((false !== $dir_mtime) ? (string) $dir_mtime : '0');
        }

        if (empty($fast_chunks)) {
            return 'none';
        }

        sort($fast_chunks, SORT_NATURAL);
        return substr(md5($theme_version . '||' . implode('||', $fast_chunks)), 0, 12);
    }

    $fingerprint_chunks = array();

    foreach ($module_sources as $module_source) {
        $source = isset($module_source['source']) ? modules_normalize_source($module_source['source']) : 'template';
        $modules_directory = isset($module_source['directory']) ? untrailingslashit(wp_normalize_path((string) $module_source['directory'])) : '';
        if ('' === $modules_directory || !is_dir($modules_directory) || !is_readable($modules_directory)) {
            continue;
        }

        $entries = glob($modules_directory . '/*');
        if (!is_array($entries)) {
            $entries = array();
        }

        $entry_chunks = array();
        foreach ($entries as $entry) {
            $entry = wp_normalize_path((string) $entry);
            $entry_name = basename($entry);
            if ('' === $entry_name || '.' === $entry_name[0]) {
                continue;
            }

            $entry_type = is_dir($entry) ? 'd' : 'f';
            $entry_mtime = file_exists($entry) ? filemtime($entry) : false;
            $entry_chunks[] = $entry_type . ':' . $entry_name . ':' . ((false !== $entry_mtime) ? (string) $entry_mtime : '0');
        }

        sort($entry_chunks, SORT_NATURAL);
        $fingerprint_chunks[] = $source . '|' . $modules_directory . '|' . implode(',', $entry_chunks);
    }

    if (empty($fingerprint_chunks)) {
        return 'none';
    }

    sort($fingerprint_chunks, SORT_NATURAL);
    return substr(md5(implode('||', $fingerprint_chunks)), 0, 12);
}

/**
 * Return module catalog transient key.
 *
 * @return string
 */
function modules_get_module_catalog_transient_key()
{
    static $transient_key = null;
    if (is_string($transient_key) && '' !== $transient_key) {
        return $transient_key;
    }

    $theme_fingerprint = sanitize_key((string) get_stylesheet());
    if ('' === $theme_fingerprint) {
        $theme_fingerprint = md5((string) get_template_directory());
    }

    // v5: catalog caches untranslated label/description source values +
    // textdomain instead of pre-translated strings, so a cached catalog
    // built under one locale still renders correctly for readers in a
    // different locale (see modules_localize_module_catalog()).
    $catalog_schema_version = 'v5';
    $source_fingerprint = modules_get_module_source_fingerprint();
    $cache_seed = $catalog_schema_version . '|' . $theme_fingerprint . '|' . $source_fingerprint;
    $transient_key = 'lonestar_mod_catalog_' . $catalog_schema_version . '_' . substr(md5((string) $cache_seed), 0, 12);
    return $transient_key;
}

/**
 * Determine whether module catalog transient cache should be used.
 *
 * @return bool
 */
function modules_should_use_module_catalog_cache()
{
    static $use_cache = null;
    if (null !== $use_cache) {
        return $use_cache;
    }

    if (defined('LONESTAR_DISABLE_MODULE_CATALOG_CACHE') && true === LONESTAR_DISABLE_MODULE_CATALOG_CACHE) {
        $use_cache = false;
        return $use_cache;
    }

    // Avoid recursive cache-namespace resolution by not calling lonestar_is_vite_dev_mode() here.
    if (defined('LONESTAR_VITE_DEVELOPMENT') && true === LONESTAR_VITE_DEVELOPMENT) {
        $use_cache = false;
        return $use_cache;
    }
    if (defined('IS_VITE_DEVELOPMENT') && true === IS_VITE_DEVELOPMENT) {
        $use_cache = false;
        return $use_cache;
    }

    $env_flag = getenv('LONESTAR_VITE_DEV');
    if (false !== $env_flag) {
        $is_vite_dev = in_array(strtolower((string) $env_flag), array('1', 'true', 'yes', 'on'), true);
        if ($is_vite_dev) {
            $use_cache = false;
            return $use_cache;
        }
    }

    $use_cache = true;

    /**
     * Filter module catalog transient cache usage.
     *
     * @param bool $use_cache Whether to use module catalog cache.
     */
    $use_cache = (bool) apply_filters('lonestar_use_module_catalog_cache', $use_cache);
    return $use_cache;
}

/**
 * Build module catalog.
 *
 * @return array<string,array>
 */
function modules_get_module_catalog()
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $use_cache = modules_should_use_module_catalog_cache();
    $cache_key = modules_get_module_catalog_transient_key();
    if ($use_cache) {
        $cached_catalog = get_transient($cache_key);
        if (is_array($cached_catalog)) {
            $catalog = modules_refresh_module_catalog_availability($cached_catalog);
            $catalog = modules_localize_module_catalog($catalog);
            return $catalog;
        }
    }

    $catalog = array();
    $module_sources = modules_get_module_source_directories();
    if (empty($module_sources)) {
        if ($use_cache) {
            set_transient($cache_key, $catalog, LONESTAR_MODULE_CATALOG_CACHE_TTL);
        }
        return $catalog;
    }

    foreach ($module_sources as $module_source) {
        $source = isset($module_source['source']) ? modules_normalize_source($module_source['source']) : 'template';
        $modules_directory = isset($module_source['directory']) ? untrailingslashit(wp_normalize_path((string) $module_source['directory'])) : '';
        if ('' === $modules_directory || !is_dir($modules_directory) || !is_readable($modules_directory)) {
            continue;
        }

        $flat_module_files = glob($modules_directory . '/module.*.php');
        if (is_array($flat_module_files)) {
            sort($flat_module_files, SORT_NATURAL);
            foreach ($flat_module_files as $module_file) {
                $slug = modules_module_slug_from_entry_file($module_file);
                if ('' === $slug) {
                    continue;
                }

                $module_key = modules_build_module_key($source, $slug);
                if ('' === $module_key) {
                    continue;
                }

                $module_directory = untrailingslashit(wp_normalize_path(dirname($module_file)));
                $entry_file = wp_normalize_path($module_file);
                $resolved_meta = modules_resolve_module_metadata($slug, $module_directory, $entry_file, 'file', $source);
                $requirements = modules_get_module_requirements($module_directory, $entry_file, 'file');
                $availability = modules_get_module_availability($requirements);

                $catalog[$module_key] = array(
                    'key'                     => $module_key,
                    'slug'                    => $slug,
                    // 'label'/'description' hold untranslated source values here;
                    // modules_localize_module_catalog() translates them on every
                    // read (cache hit or miss) using the accompanying textdomain.
                    'label'                   => isset($resolved_meta['label']) ? (string) $resolved_meta['label'] : modules_module_label_from_slug($slug),
                    'label_textdomain'        => isset($resolved_meta['label_textdomain']) ? (string) $resolved_meta['label_textdomain'] : '',
                    'description'             => isset($resolved_meta['description']) ? (string) $resolved_meta['description'] : '',
                    'description_textdomain'  => isset($resolved_meta['description_textdomain']) ? (string) $resolved_meta['description_textdomain'] : '',
                    'description_is_default'  => !empty($resolved_meta['description_is_default']),
                    'version'     => isset($resolved_meta['version']) ? (string) $resolved_meta['version'] : '',
                    'author'      => isset($resolved_meta['author']) ? (string) $resolved_meta['author'] : '',
                    'source'      => $source,
                    'source_label'=> modules_get_source_label($source),
                    'admin_links' => modules_get_module_admin_links($slug, $module_directory, $entry_file, 'file', $source),
                    'requires'    => $requirements,
                    'available'   => $availability['available'],
                    'status'      => $availability['status'],
                    'mode'        => 'file',
                    'directory'   => $module_directory,
                    'entry_file'  => $entry_file,
                    'features'    => modules_detect_module_features($module_directory, $entry_file),
                );
            }
        }

        $module_folders = glob($modules_directory . '/*', GLOB_ONLYDIR);
        if (is_array($module_folders)) {
            sort($module_folders, SORT_NATURAL);
            foreach ($module_folders as $module_folder) {
                $slug = sanitize_key(basename($module_folder));
                if ('' === $slug) {
                    continue;
                }

                $module_key = modules_build_module_key($source, $slug);
                if ('' === $module_key) {
                    continue;
                }

                $module_directory = untrailingslashit(wp_normalize_path($module_folder));
                $entry_file = wp_normalize_path($module_directory . '/module.' . $slug . '.php');
                if (!file_exists($entry_file) || !is_readable($entry_file)) {
                    $entry_file = '';
                }

                // Folder module takes precedence over flat module with the same key (source + slug).
                $resolved_meta = modules_resolve_module_metadata($slug, $module_directory, $entry_file, 'folder', $source);
                $requirements = modules_get_module_requirements($module_directory, $entry_file, 'folder');
                $availability = modules_get_module_availability($requirements);
                $catalog[$module_key] = array(
                    'key'                     => $module_key,
                    'slug'                    => $slug,
                    'label'                   => isset($resolved_meta['label']) ? (string) $resolved_meta['label'] : modules_module_label_from_slug($slug),
                    'label_textdomain'        => isset($resolved_meta['label_textdomain']) ? (string) $resolved_meta['label_textdomain'] : '',
                    'description'             => isset($resolved_meta['description']) ? (string) $resolved_meta['description'] : '',
                    'description_textdomain'  => isset($resolved_meta['description_textdomain']) ? (string) $resolved_meta['description_textdomain'] : '',
                    'description_is_default'  => !empty($resolved_meta['description_is_default']),
                    'version'     => isset($resolved_meta['version']) ? (string) $resolved_meta['version'] : '',
                    'author'      => isset($resolved_meta['author']) ? (string) $resolved_meta['author'] : '',
                    'source'      => $source,
                    'source_label'=> modules_get_source_label($source),
                    'admin_links' => modules_get_module_admin_links($slug, $module_directory, $entry_file, 'folder', $source),
                    'requires'    => $requirements,
                    'available'   => $availability['available'],
                    'status'      => $availability['status'],
                    'mode'        => 'folder',
                    'directory'   => $module_directory,
                    'entry_file'  => $entry_file,
                    'features'    => modules_detect_module_features($module_directory, $entry_file),
                );
            }
        }
    }

    /**
     * Filter discovered module catalog.
     *
     * @param array<string,array> $catalog Module catalog keyed by module key (`source__slug`).
     */
    $catalog = apply_filters('lonestar_module_catalog', $catalog);
    if (!is_array($catalog)) {
        $catalog = array();
    }

    $catalog = modules_refresh_module_catalog_availability($catalog);

    ksort($catalog, SORT_NATURAL);

    if ($use_cache) {
        // Cache raw (untranslated) label/description source values; see
        // modules_localize_module_catalog() for the read-time translation step.
        set_transient($cache_key, $catalog, LONESTAR_MODULE_CATALOG_CACHE_TTL);
    }

    $catalog = modules_localize_module_catalog($catalog);

    return $catalog;
}

/**
 * Parse module slug from entry filename.
 *
 * @param string $module_file Module entry file path.
 * @return string
 */
function modules_module_slug_from_entry_file($module_file)
{
    $filename = pathinfo((string) $module_file, PATHINFO_FILENAME);
    if (!is_string($filename) || 0 !== strpos($filename, 'module.')) {
        return '';
    }

    return sanitize_key(substr($filename, 7));
}

/**
 * Build human-readable module name from slug.
 *
 * @param string $slug Module slug.
 * @return string
 */
function modules_module_label_from_slug($slug)
{
    $slug = sanitize_key((string) $slug);
    if ('' === $slug) {
        return '';
    }

    return ucwords(str_replace(array('-', '_'), ' ', $slug));
}

/**
 * Resolve module metadata from JSON + docblock + README fallbacks.
 *
 * @param string $slug Module slug.
 * @param string $module_directory Module directory.
 * @param string $entry_file Module entry file path.
 * @param string $mode Module mode (file|folder).
 * @param string $source Theme source (template|stylesheet).
 * @return array{label:string,description:string,version:string,author:string}
 */
function modules_resolve_module_metadata($slug, $module_directory, $entry_file = '', $mode = 'folder', $source = 'template')
{
    $slug = sanitize_key((string) $slug);
    $version = '';
    $author = '';
    $mode = ('file' === strtolower((string) $mode)) ? 'file' : 'folder';

    $json_meta = modules_get_module_json_metadata($module_directory, $entry_file, $mode);
    $doc_meta = modules_extract_module_docblock_metadata($entry_file);
    $textdomain = modules_get_module_metadata_textdomain($json_meta, $source);

    // Untranslated source values are cached (see modules_get_module_catalog());
    // translation happens on every read via modules_localize_module_catalog()
    // so cached catalogs render in the current request's locale instead of
    // whichever locale happened to be active when the cache was built.
    $label = modules_module_label_from_slug($slug);
    $label_textdomain = '';
    $label_candidates = array(
        array('value' => isset($json_meta['name']) ? (string) $json_meta['name'] : '', 'textdomain' => $textdomain),
        array('value' => isset($json_meta['title']) ? (string) $json_meta['title'] : '', 'textdomain' => $textdomain),
        array('value' => isset($doc_meta['module']) ? (string) $doc_meta['module'] : '', 'textdomain' => ''),
        array('value' => isset($doc_meta['name']) ? (string) $doc_meta['name'] : '', 'textdomain' => ''),
    );
    foreach ($label_candidates as $candidate) {
        $candidate_value = sanitize_text_field(trim((string) $candidate['value']));
        if ('' !== $candidate_value) {
            $label = $candidate_value;
            $label_textdomain = $candidate['textdomain'];
            break;
        }
    }

    $description = '';
    $description_textdomain = '';
    $description_candidates = array(
        array('value' => isset($json_meta['description']) ? (string) $json_meta['description'] : '', 'textdomain' => $textdomain),
        array('value' => isset($doc_meta['description']) ? (string) $doc_meta['description'] : '', 'textdomain' => ''),
        array('value' => modules_extract_module_readme_description($module_directory . '/README.md'), 'textdomain' => ''),
        array('value' => modules_extract_module_docblock_summary($entry_file), 'textdomain' => ''),
    );
    foreach ($description_candidates as $candidate) {
        $candidate_value = sanitize_text_field(trim((string) $candidate['value']));
        if ('' !== $candidate_value) {
            $description = $candidate_value;
            $description_textdomain = $candidate['textdomain'];
            break;
        }
    }

    $description_is_default = ('' === $description);

    $version_candidates = array(
        isset($json_meta['version']) ? (string) $json_meta['version'] : '',
        isset($doc_meta['version']) ? (string) $doc_meta['version'] : '',
    );
    foreach ($version_candidates as $candidate) {
        $candidate = sanitize_text_field(trim((string) $candidate));
        if ('' !== $candidate) {
            $version = $candidate;
            break;
        }
    }

    $author_candidates = array(
        isset($json_meta['author']) ? (string) $json_meta['author'] : '',
        isset($doc_meta['author']) ? (string) $doc_meta['author'] : '',
    );
    foreach ($author_candidates as $candidate) {
        $candidate = sanitize_text_field(trim((string) $candidate));
        if ('' !== $candidate) {
            $author = $candidate;
            break;
        }
    }

    return array(
        'label'                   => $label,
        'label_textdomain'        => $label_textdomain,
        'description'             => $description,
        'description_textdomain'  => $description_textdomain,
        'description_is_default'  => $description_is_default,
        'version'                 => $version,
        'author'                  => $author,
    );
}

/**
 * Translate a module catalog entry's label/description and admin link
 * labels for the current request locale.
 *
 * Catalog entries store untranslated source strings plus their textdomain
 * (see modules_resolve_module_metadata() / modules_get_module_admin_links());
 * this step applies translate()/__() using whichever locale is active when
 * the catalog is read, regardless of whether the catalog itself came from a
 * fresh scan or a transient built under a different user's locale.
 *
 * @param array<string,array> $catalog Module catalog with raw i18n fields.
 * @return array<string,array> Module catalog with localized label/description.
 */
function modules_localize_module_catalog($catalog)
{
    if (!is_array($catalog)) {
        return array();
    }

    foreach ($catalog as $module_key => $module) {
        if (!is_array($module)) {
            continue;
        }

        $slug = isset($module['slug']) ? (string) $module['slug'] : '';
        $raw_label = isset($module['label']) ? (string) $module['label'] : '';
        $label_textdomain = isset($module['label_textdomain']) ? (string) $module['label_textdomain'] : '';
        $label = ('' !== $label_textdomain)
            ? modules_translate_module_metadata_value($raw_label, $label_textdomain)
            : sanitize_text_field($raw_label);
        if ('' === $label) {
            $label = modules_module_label_from_slug($slug);
        }

        $description_is_default = !empty($module['description_is_default']);
        $raw_description = isset($module['description']) ? (string) $module['description'] : '';
        $description_textdomain = isset($module['description_textdomain']) ? (string) $module['description_textdomain'] : '';

        if ($description_is_default || '' === $raw_description) {
            $description = sprintf(__('Module: %s', 'lonestar'), $label);
        } else {
            $description = ('' !== $description_textdomain)
                ? modules_translate_module_metadata_value($raw_description, $description_textdomain)
                : sanitize_text_field($raw_description);
            if ('' === $description) {
                $description = sprintf(__('Module: %s', 'lonestar'), $label);
            }
        }

        $module['label'] = $label;
        $module['description'] = $description;
        unset($module['label_textdomain'], $module['description_textdomain'], $module['description_is_default']);

        if (isset($module['admin_links']) && is_array($module['admin_links'])) {
            foreach ($module['admin_links'] as $link_index => $link) {
                if (!is_array($link)) {
                    continue;
                }

                $raw_link_label = isset($link['label']) ? (string) $link['label'] : '';
                $link_textdomain = isset($link['label_textdomain']) ? (string) $link['label_textdomain'] : '';
                $link_label = ('' !== $raw_link_label)
                    ? (('' !== $link_textdomain) ? modules_translate_module_metadata_value($raw_link_label, $link_textdomain) : sanitize_text_field($raw_link_label))
                    : '';
                if ('' === $link_label) {
                    $link_label = __('Settings', 'lonestar');
                }

                $link['label'] = $link_label;
                unset($link['label_textdomain']);
                $module['admin_links'][$link_index] = $link;
            }
        }

        $catalog[$module_key] = $module;
    }

    return $catalog;
}

/**
 * Resolve the translation domain for declarative module metadata.
 *
 * Parent metadata defaults to the framework domain. Child metadata remains
 * literal unless its owner explicitly declares a textdomain in module.json.
 *
 * @param array  $metadata Parsed module metadata.
 * @param string $source Theme source.
 * @return string
 */
function modules_get_module_metadata_textdomain($metadata, $source)
{
    if (is_array($metadata) && isset($metadata['textdomain']) && is_string($metadata['textdomain'])) {
        return sanitize_key($metadata['textdomain']);
    }

    return ('template' === modules_normalize_source($source)) ? 'lonestar' : '';
}

/**
 * Translate an extractable declarative metadata value when it has a domain.
 *
 * @param mixed  $value Metadata value.
 * @param string $textdomain Translation domain.
 * @return string
 */
function modules_translate_module_metadata_value($value, $textdomain)
{
    $value = sanitize_text_field((string) $value);
    if ('' === $value || '' === $textdomain) {
        return $value;
    }

    return translate($value, $textdomain);
}

/**
 * Read module JSON metadata.
 *
 * @param string $module_directory Module directory.
 * @param string $entry_file Module entry file path.
 * @param string $mode Module mode (file|folder).
 * @return array<string,mixed>
 */
function modules_get_module_json_metadata($module_directory, $entry_file = '', $mode = 'folder')
{
    $module_directory = untrailingslashit(wp_normalize_path((string) $module_directory));
    $entry_file = wp_normalize_path((string) $entry_file);
    $mode = ('file' === strtolower((string) $mode)) ? 'file' : 'folder';

    $candidates = array();
    if ('folder' === $mode && '' !== $module_directory) {
        $candidates[] = $module_directory . '/module.json';
    }
    if ('file' === $mode && '' !== $entry_file) {
        $sidecar = preg_replace('/\.php$/i', '.json', $entry_file);
        if (is_string($sidecar) && '' !== $sidecar) {
            $candidates[] = $sidecar;
        }
    }

    foreach ($candidates as $candidate) {
        if (!file_exists($candidate) || !is_readable($candidate)) {
            continue;
        }

        $contents = file_get_contents($candidate);
        if (false === $contents) {
            continue;
        }

        $decoded = json_decode((string) $contents, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return array();
}

/**
 * Read explicit module runtime requirements from module JSON metadata.
 *
 * @param string $module_directory Module directory.
 * @param string $entry_file Module entry file.
 * @param string $mode Module mode.
 * @return array<int,string>
 */
function modules_get_module_requirements($module_directory, $entry_file = '', $mode = 'folder')
{
    $metadata = modules_get_module_json_metadata($module_directory, $entry_file, $mode);
    $requirements = isset($metadata['requires']) ? $metadata['requires'] : array();
    if (is_string($requirements)) {
        $requirements = array($requirements);
    }
    if (!is_array($requirements)) {
        return array();
    }

    $requirements = array_values(array_unique(array_filter(array_map('sanitize_key', $requirements), 'strlen')));
    sort($requirements, SORT_NATURAL);
    return $requirements;
}

/**
 * Resolve whether declared module requirements are available.
 *
 * @param array<int,string> $requirements Requirement slugs.
 * @return array{available:bool,status:string}
 */
function modules_get_module_availability($requirements)
{
    $missing = array();
    $labels = array(
        'acf'           => __('ACF Pro', 'lonestar'),
        'gravity-forms' => __('Gravity Forms', 'lonestar'),
    );

    foreach (is_array($requirements) ? $requirements : array() as $requirement) {
        $requirement = sanitize_key((string) $requirement);
        if ('' === $requirement) {
            continue;
        }

        if ('acf' === $requirement) {
            $available = function_exists('acf_register_block_type') || class_exists('ACF');
        } elseif ('gravity-forms' === $requirement) {
            $available = class_exists('GFForms') || class_exists('RGFormsModel');
        } else {
            $available = (bool) apply_filters('lonestar_module_requirement_available', false, $requirement);
        }

        if (!$available) {
            $missing[] = isset($labels[$requirement]) ? $labels[$requirement] : $requirement;
        }
    }

    /* translators: %s: Comma-separated list of missing module requirements. */
    $requires_message = sprintf(__('Requires: %s.', 'lonestar'), implode(', ', $missing));

    return array(
        'available' => empty($missing),
        'status'    => empty($missing)
            ? ''
            : $requires_message,
    );
}

/**
 * Re-evaluate dependency availability after reading a cached catalog.
 *
 * @param array<string,array> $catalog Module catalog.
 * @return array<string,array>
 */
function modules_refresh_module_catalog_availability($catalog)
{
    if (!is_array($catalog)) {
        return array();
    }

    foreach ($catalog as $module_key => $module) {
        if (!is_array($module)) {
            continue;
        }
        $requirements = isset($module['requires']) && is_array($module['requires']) ? $module['requires'] : array();
        $availability = modules_get_module_availability($requirements);
        $catalog[$module_key]['available'] = $availability['available'];
        $catalog[$module_key]['status'] = $availability['status'];
    }

    return $catalog;
}

/**
 * Extract simple metadata headers from module docblock.
 *
 * Supported keys:
 * - Module / Name
 * - Description
 * - Version
 * - Author
 *
 * @param string $file_path Module entry file path.
 * @return array<string,string>
 */
function modules_extract_module_docblock_metadata($file_path)
{
    $file_path = wp_normalize_path((string) $file_path);
    if ('' === $file_path || !file_exists($file_path) || !is_readable($file_path)) {
        return array();
    }

    $contents = file_get_contents($file_path);
    if (false === $contents) {
        return array();
    }

    if (!preg_match('/\/\*\*(.*?)\*\//s', (string) $contents, $matches)) {
        return array();
    }

    $block = isset($matches[1]) ? (string) $matches[1] : '';
    if ('' === $block) {
        return array();
    }

    $metadata = array();
    $lines = preg_split('/\R+/', $block);
    if (!is_array($lines)) {
        return $metadata;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        $line = preg_replace('/^\*\s?/', '', $line);
        $line = trim((string) $line);
        if ('' === $line) {
            continue;
        }

        if (!preg_match('/^([A-Za-z][A-Za-z0-9 _-]*?)\s*:\s*(.+)$/', $line, $header_match)) {
            continue;
        }

        $raw_key = isset($header_match[1]) ? strtolower(trim((string) $header_match[1])) : '';
        $raw_value = isset($header_match[2]) ? sanitize_text_field(trim((string) $header_match[2])) : '';
        if ('' === $raw_key || '' === $raw_value) {
            continue;
        }

        $normalized_key = '';
        if (in_array($raw_key, array('module', 'name'), true)) {
            $normalized_key = 'module';
        } elseif ('description' === $raw_key) {
            $normalized_key = 'description';
        } elseif ('version' === $raw_key) {
            $normalized_key = 'version';
        } elseif ('author' === $raw_key) {
            $normalized_key = 'author';
        }

        if ('' === $normalized_key) {
            continue;
        }

        $metadata[$normalized_key] = $raw_value;
    }

    return $metadata;
}

/**
 * Resolve module description from metadata/readme/docblock.
 *
 * Priority:
 * 1) module.json `description`
 * 2) README.md first non-heading line
 * 3) entry file docblock summary
 *
 * @param string $slug Module slug.
 * @param string $module_directory Module directory.
 * @param string $entry_file Module entry file path.
 * @return string
 */
function modules_get_module_description($slug, $module_directory, $entry_file = '')
{
    $slug = sanitize_key((string) $slug);
    $module_directory = untrailingslashit(wp_normalize_path((string) $module_directory));
    $entry_file = wp_normalize_path((string) $entry_file);
    $description = '';

    $descriptor_file = $module_directory . '/module.json';
    if (file_exists($descriptor_file) && is_readable($descriptor_file)) {
        $json = json_decode((string) file_get_contents($descriptor_file), true);
        if (is_array($json) && isset($json['description']) && is_string($json['description'])) {
            $description = trim((string) $json['description']);
        }
    }

    if ('' === $description && '' !== $entry_file) {
        $doc_meta = modules_extract_module_docblock_metadata($entry_file);
        if (isset($doc_meta['description']) && is_string($doc_meta['description'])) {
            $description = trim((string) $doc_meta['description']);
        }
    }

    if ('' === $description) {
        $description = modules_extract_module_readme_description($module_directory . '/README.md');
    }

    if ('' === $description && '' !== $entry_file) {
        $description = modules_extract_module_docblock_summary($entry_file);
    }

    $description = is_string($description) ? trim($description) : '';

    /**
     * Filter module description.
     *
     * @param string $description Resolved module description.
     * @param string $slug Module slug.
     * @param string $module_directory Module directory.
     * @param string $entry_file Module entry file.
     */
    $description = apply_filters('lonestar_module_description', $description, $slug, $module_directory, $entry_file);
    $description = is_string($description) ? trim($description) : '';

    if ('' === $description) {
        return sprintf(__('Module: %s', 'lonestar'), modules_module_label_from_slug($slug));
    }

    return sanitize_text_field($description);
}

/**
 * Read first meaningful line from module README.
 *
 * @param string $readme_path README file path.
 * @return string
 */
function modules_extract_module_readme_description($readme_path)
{
    $readme_path = wp_normalize_path((string) $readme_path);
    if (!file_exists($readme_path) || !is_readable($readme_path)) {
        return '';
    }

    $contents = file_get_contents($readme_path);
    if (false === $contents || '' === trim((string) $contents)) {
        return '';
    }

    $lines = preg_split('/\R+/', (string) $contents);
    if (!is_array($lines)) {
        return '';
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ('' === $line) {
            continue;
        }

        if (0 === strpos($line, '#') || 0 === strpos($line, '<!--')) {
            continue;
        }

        return sanitize_text_field($line);
    }

    return '';
}

/**
 * Extract first non-tag line from file docblock.
 *
 * @param string $file_path PHP file path.
 * @return string
 */
function modules_extract_module_docblock_summary($file_path)
{
    $file_path = wp_normalize_path((string) $file_path);
    if (!file_exists($file_path) || !is_readable($file_path)) {
        return '';
    }

    $contents = file_get_contents($file_path);
    if (false === $contents) {
        return '';
    }

    if (!preg_match('/\/\*\*(.*?)\*\//s', (string) $contents, $matches)) {
        return '';
    }

    $block = isset($matches[1]) ? (string) $matches[1] : '';
    if ('' === $block) {
        return '';
    }

    $lines = preg_split('/\R+/', $block);
    if (!is_array($lines)) {
        return '';
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        $line = preg_replace('/^\*\s?/', '', $line);
        $line = trim((string) $line);

        if ('' === $line || 0 === strpos($line, '@')) {
            continue;
        }

        return sanitize_text_field($line);
    }

    return '';
}

/**
 * Resolve module admin links (settings pages).
 *
 * Links must be declared in module.json `admin_links`.
 *
 * @param string $slug Module slug.
 * @param string $module_directory Module directory.
 * @param string $entry_file Module entry file.
 * @param string $mode Module mode (file|folder).
 * @return array<int,array{label:string,url:string}>
 */
function modules_get_module_admin_links($slug, $module_directory, $entry_file = '', $mode = 'folder', $source = 'template')
{
    unset($slug);

    $module_directory = untrailingslashit(wp_normalize_path((string) $module_directory));
    $entry_file = wp_normalize_path((string) $entry_file);
    $mode = ('file' === strtolower((string) $mode)) ? 'file' : 'folder';
    if ('' === $module_directory || !is_dir($module_directory)) {
        return array();
    }

    $links = array();
    $seen_pages = array();
    $seen_urls = array();

    // Explicit links from module.json.
    $descriptor_candidates = array();
    if ('folder' === $mode) {
        $descriptor_candidates[] = $module_directory . '/module.json';
    } elseif ('' !== $entry_file) {
        $sidecar = preg_replace('/\.php$/i', '.json', $entry_file);
        if (is_string($sidecar) && '' !== $sidecar) {
            $descriptor_candidates[] = $sidecar;
        }
    }

    foreach ($descriptor_candidates as $descriptor_file) {
        if (!file_exists($descriptor_file) || !is_readable($descriptor_file)) {
            continue;
        }

        $json = json_decode((string) file_get_contents($descriptor_file), true);
        if (!is_array($json) || !isset($json['admin_links']) || !is_array($json['admin_links'])) {
            continue;
        }

        $textdomain = modules_get_module_metadata_textdomain($json, $source);
        foreach ($json['admin_links'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Store the raw (untranslated) label + textdomain; translation
            // happens at read time in modules_localize_module_catalog() so
            // cached links render in the current request's locale.
            $raw_label = isset($item['label']) ? sanitize_text_field(trim((string) $item['label'])) : '';
            $page_slug = isset($item['page']) ? sanitize_key((string) $item['page']) : '';
            $raw_url = isset($item['url']) ? trim((string) $item['url']) : '';

            if ('' !== $page_slug) {
                modules_add_module_admin_page_link($links, $seen_pages, $page_slug, $raw_label, $textdomain);
                continue;
            }

            if ('' !== $raw_url) {
                $resolved_url = $raw_url;
                if (0 === strpos($raw_url, 'admin.php') || 0 === strpos($raw_url, 'themes.php')) {
                    $resolved_url = admin_url(ltrim($raw_url, '/'));
                }

                $resolved_url = esc_url_raw($resolved_url);
                if ('' === $resolved_url) {
                    continue;
                }

                if (isset($seen_urls[$resolved_url])) {
                    continue;
                }

                $links[] = array(
                    'label'            => $raw_label,
                    'label_textdomain' => $textdomain,
                    'url'              => $resolved_url,
                );
                $seen_urls[$resolved_url] = true;
            }
        }
    }

    return $links;
}

/**
 * Add admin page link to module link list (deduplicated by page slug).
 *
 * Stores the raw (untranslated) label + textdomain; translation happens at
 * read time in modules_localize_module_catalog(), so a link's label renders
 * in the current request's locale even when the catalog itself is served
 * from a transient built under a different locale.
 *
 * @param array<int,array{label:string,label_textdomain:string,url:string}> $links Link list.
 * @param array<string,bool> $seen_pages Seen page slugs.
 * @param string $page_slug Admin page slug.
 * @param string $label Raw (untranslated) link label; empty resolves to "Settings" at read time.
 * @param string $textdomain Translation domain for $label (empty = not translatable / already-literal).
 * @return void
 */
function modules_add_module_admin_page_link(&$links, &$seen_pages, $page_slug, $label, $textdomain = '')
{
    $page_slug = sanitize_key((string) $page_slug);
    if ('' === $page_slug) {
        return;
    }

    if (isset($seen_pages[$page_slug])) {
        return;
    }

    $label = sanitize_text_field((string) $label);

    $url = add_query_arg('page', $page_slug, admin_url('admin.php'));
    $url = esc_url_raw($url);
    if ('' === $url) {
        return;
    }

    $links[] = array(
        'label'            => $label,
        'label_textdomain' => sanitize_key((string) $textdomain),
        'url'              => $url,
    );
    $seen_pages[$page_slug] = true;
}

/**
 * Collect module PHP files for metadata scanning.
 *
 * @param string $module_directory Module directory.
 * @return array<int,string>
 */
function modules_get_module_php_files_for_scanning($module_directory)
{
    $module_directory = untrailingslashit(wp_normalize_path((string) $module_directory));
    if ('' === $module_directory || !is_dir($module_directory) || !is_readable($module_directory)) {
        return array();
    }

    $files = array();

    try {
        $directory = new \RecursiveDirectoryIterator($module_directory, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator(
            $directory,
            function ($current) {
                $name = $current->getFilename();
                if ('' === $name || '.' === $name[0]) {
                    return false;
                }

                if ($current->isDir()) {
                    $skip_dirs = array('node_modules', 'dist', 'build', 'vendor', '.git');
                    return !in_array($name, $skip_dirs, true);
                }

                return ('php' === strtolower((string) pathinfo($name, PATHINFO_EXTENSION)));
            }
        );

        $iterator = new \RecursiveIteratorIterator($filter);
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = wp_normalize_path((string) $file);
            }
        }
    } catch (\Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[lonestar-theme] Module scan error: ' . $e->getMessage());
        }
    }

    $files = array_values(array_unique($files));
    sort($files, SORT_NATURAL);
    return $files;
}

/**
 * Detect available features inside module directory.
 *
 * @param string $module_directory Absolute module directory.
 * @param string $entry_file Optional module entry file.
 * @return array<string,bool>
 */
function modules_detect_module_features($module_directory, $entry_file = '')
{
    $module_directory = untrailingslashit(wp_normalize_path($module_directory));
    $features = array(
        'entry'         => ('' !== $entry_file),
        'blocks_acf'    => is_dir($module_directory . '/blocks/acf'),
        'blocks_native' => is_dir($module_directory . '/blocks/native'),
        'blocks_php_only' => is_dir($module_directory . '/blocks/php-only'),
        'assets'        => is_dir($module_directory . '/assets'),
        'acf_json'      => is_dir($module_directory . '/acf-json'),
        'inc'           => is_dir($module_directory . '/inc'),
        'shortcodes'    => (is_dir($module_directory . '/inc/shortcodes') || is_dir($module_directory . '/shortcodes')),
        'walkers'       => (is_dir($module_directory . '/inc/walkers') || is_dir($module_directory . '/walkers')),
    );

    return $features;
}
