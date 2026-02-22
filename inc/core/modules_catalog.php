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

    $transient_key = 'lonestar_mod_catalog_' . substr(md5((string) $theme_fingerprint), 0, 12);
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
            $catalog = $cached_catalog;
            return $catalog;
        }
    }

    $catalog = array();
    $modules_directory = modules_get_modules_directory();
    if ('' === $modules_directory) {
        if ($use_cache) {
            set_transient($cache_key, $catalog, LONESTAR_MODULE_CATALOG_CACHE_TTL);
        }
        return $catalog;
    }

    $flat_module_files = glob($modules_directory . '/module.*.php');
    if (is_array($flat_module_files)) {
        sort($flat_module_files, SORT_NATURAL);
        foreach ($flat_module_files as $module_file) {
            $slug = modules_module_slug_from_entry_file($module_file);
            if ('' === $slug) {
                continue;
            }

            $module_directory = untrailingslashit(wp_normalize_path(dirname($module_file)));
            $entry_file = wp_normalize_path($module_file);

            $catalog[$slug] = array(
                'slug'       => $slug,
                'label'      => modules_module_label_from_slug($slug),
                'description'=> modules_get_module_description($slug, $module_directory, $entry_file),
                'admin_links'=> modules_get_module_admin_links($slug, $module_directory, $entry_file, 'file'),
                'mode'       => 'file',
                'directory'  => $module_directory,
                'entry_file' => $entry_file,
                'features'   => modules_detect_module_features($module_directory, $entry_file),
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

            $module_directory = untrailingslashit(wp_normalize_path($module_folder));
            $entry_file = wp_normalize_path($module_directory . '/module.' . $slug . '.php');
            if (!file_exists($entry_file) || !is_readable($entry_file)) {
                $entry_file = '';
            }

            // Folder module takes precedence over flat module with the same slug.
            $catalog[$slug] = array(
                'slug'       => $slug,
                'label'      => modules_module_label_from_slug($slug),
                'description'=> modules_get_module_description($slug, $module_directory, $entry_file),
                'admin_links'=> modules_get_module_admin_links($slug, $module_directory, $entry_file, 'folder'),
                'mode'       => 'folder',
                'directory'  => $module_directory,
                'entry_file' => $entry_file,
                'features'   => modules_detect_module_features($module_directory, $entry_file),
            );
        }
    }

    /**
     * Filter discovered module catalog.
     *
     * @param array<string,array> $catalog Module catalog keyed by slug.
     */
    $catalog = apply_filters('lonestar_module_catalog', $catalog);
    if (!is_array($catalog)) {
        $catalog = array();
    }

    ksort($catalog, SORT_NATURAL);

    if ($use_cache) {
        set_transient($cache_key, $catalog, LONESTAR_MODULE_CATALOG_CACHE_TTL);
    }

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
        return sprintf(__('Module: %s', 'lonestar-theme'), modules_module_label_from_slug($slug));
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
 * Strategy:
 * 1) module.json `admin_links`
 * 2) Auto-detect ACF options pages from module PHP files
 *
 * @param string $slug Module slug.
 * @param string $module_directory Module directory.
 * @param string $entry_file Module entry file.
 * @param string $mode Module mode (file|folder).
 * @return array<int,array{label:string,url:string}>
 */
function modules_get_module_admin_links($slug, $module_directory, $entry_file = '', $mode = 'folder')
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

    // 1) Explicit links from module.json
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

        foreach ($json['admin_links'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : __('Settings', 'lonestar-theme');
            $page_slug = isset($item['page']) ? sanitize_key((string) $item['page']) : '';
            $raw_url = isset($item['url']) ? trim((string) $item['url']) : '';

            if ('' !== $page_slug) {
                modules_add_module_admin_page_link($links, $seen_pages, $page_slug, $label);
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
                    'label' => $label,
                    'url'   => $resolved_url,
                );
                $seen_urls[$resolved_url] = true;
            }
        }
    }

    // 2) Auto-discover ACF options pages/subpages in module PHP files
    if ('file' === $mode) {
        $php_files = ('' !== $entry_file && file_exists($entry_file) && is_readable($entry_file))
            ? array($entry_file)
            : array();
    } else {
        $php_files = modules_get_module_php_files_for_scanning($module_directory);
    }

    foreach ($php_files as $php_file) {
        $contents = file_get_contents($php_file);
        if (false === $contents || '' === trim((string) $contents)) {
            continue;
        }

        // ACF options page/subpage args parsing.
        if (preg_match_all('/acf_add_options_(?:sub_)?page\s*\(\s*array\s*\((.*?)\)\s*\)\s*;?/is', (string) $contents, $calls)) {
            $arg_blocks = isset($calls[1]) && is_array($calls[1]) ? $calls[1] : array();
            foreach ($arg_blocks as $args) {
                $menu_slug = '';
                $menu_title = '';
                $page_title = '';

                if (preg_match('/[\'"]menu_slug[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i', (string) $args, $match)) {
                    $menu_slug = sanitize_key((string) $match[1]);
                }
                if (preg_match('/[\'"]menu_title[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i', (string) $args, $match)) {
                    $menu_title = sanitize_text_field((string) $match[1]);
                }
                if (preg_match('/[\'"]page_title[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i', (string) $args, $match)) {
                    $page_title = sanitize_text_field((string) $match[1]);
                }

                if ('' === $menu_slug) {
                    $title_for_slug = '' !== $menu_title ? $menu_title : $page_title;
                    if ('' !== $title_for_slug) {
                        $menu_slug = 'acf-options-' . sanitize_title($title_for_slug);
                    }
                }

                if ('' !== $menu_slug) {
                    $label = '' !== $menu_title ? $menu_title : ('' !== $page_title ? $page_title : __('Settings', 'lonestar-theme'));
                    modules_add_module_admin_page_link($links, $seen_pages, $menu_slug, $label);
                }
            }
        }

        // Fallback from explicit ACF options page value usage in field groups.
        if (preg_match_all('/acf-options-[a-z0-9_-]+/i', (string) $contents, $matches)) {
            $pages = isset($matches[0]) && is_array($matches[0]) ? $matches[0] : array();
            foreach ($pages as $page_slug) {
                $page_slug = sanitize_key((string) $page_slug);
                if ('' !== $page_slug) {
                    modules_add_module_admin_page_link($links, $seen_pages, $page_slug, __('Settings', 'lonestar-theme'));
                }
            }
        }
    }

    return $links;
}

/**
 * Add admin page link to module link list (deduplicated by page slug).
 *
 * @param array<int,array{label:string,url:string}> $links Link list.
 * @param array<string,bool> $seen_pages Seen page slugs.
 * @param string $page_slug Admin page slug.
 * @param string $label Link label.
 * @return void
 */
function modules_add_module_admin_page_link(&$links, &$seen_pages, $page_slug, $label)
{
    $page_slug = sanitize_key((string) $page_slug);
    if ('' === $page_slug) {
        return;
    }

    if (isset($seen_pages[$page_slug])) {
        return;
    }

    $label = sanitize_text_field((string) $label);
    if ('' === $label) {
        $label = __('Settings', 'lonestar-theme');
    }

    $url = add_query_arg('page', $page_slug, admin_url('admin.php'));
    $url = esc_url_raw($url);
    if ('' === $url) {
        return;
    }

    $links[] = array(
        'label' => $label,
        'url'   => $url,
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
        'assets'        => is_dir($module_directory . '/assets'),
        'acf_json'      => is_dir($module_directory . '/acf-json'),
        'inc'           => is_dir($module_directory . '/inc'),
        'shortcodes'    => (is_dir($module_directory . '/inc/shortcodes') || is_dir($module_directory . '/shortcodes')),
        'walkers'       => (is_dir($module_directory . '/inc/walkers') || is_dir($module_directory . '/walkers')),
    );

    return $features;
}
