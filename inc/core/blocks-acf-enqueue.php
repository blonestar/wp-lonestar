<?php

if (!defined('ABSPATH')) {
	exit;
}

add_action('init', 'lonestar_register_block_files', 20);

/**
 * Check if theme is in Vite development mode.
 *
 * @return bool
 */
function lonestar_is_vite_dev_mode()
{
	if (defined('LONESTAR_VITE_DEVELOPMENT')) {
		return LONESTAR_VITE_DEVELOPMENT === true;
	}

	$env_flag = getenv('LONESTAR_VITE_DEV');
	if (false !== $env_flag) {
		return in_array(strtolower((string) $env_flag), array('1', 'true', 'yes', 'on'), true);
	}

	if (function_exists('wp_get_environment_type') && 'production' === wp_get_environment_type()) {
		return false;
	}

	if (!defined('LONESTAR_VITE_SERVER') || !function_exists('wp_remote_get')) {
		return false;
	}

	// The automatic HTTP probe is only safe to run on environments where a
	// local Vite dev server is plausibly running. Staging/other environment
	// types must opt in explicitly via LONESTAR_VITE_DEVELOPMENT or
	// LONESTAR_VITE_DEV rather than pay an HTTP round-trip per request.
	$probe_environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
	$probe_enabled = in_array($probe_environment, array('local', 'development'), true);

	/**
	 * Filter whether the automatic Vite dev-server HTTP probe may run.
	 *
	 * Explicit LONESTAR_VITE_DEVELOPMENT / LONESTAR_VITE_DEV opt-ins bypass
	 * this filter entirely (handled above). This only gates
	 * the environment-type based automatic probe.
	 *
	 * @param bool   $probe_enabled Whether the probe is allowed to run.
	 * @param string $probe_environment Current wp_get_environment_type() value.
	 */
	$probe_enabled = (bool) apply_filters('lonestar_vite_dev_probe_enabled', $probe_enabled, $probe_environment);
	if (!$probe_enabled) {
		return false;
	}

	$namespace = function_exists('lonestar_get_theme_cache_namespace') ? lonestar_get_theme_cache_namespace() : 'default';
	$cache_key = 'lonestar_vite_dev_probe_' . $namespace;
	$cached = get_transient($cache_key);
	if (is_array($cached) && isset($cached['is_running'])) {
		return (bool) $cached['is_running'];
	}

	$probe_url = rtrim(LONESTAR_VITE_SERVER, '/') . '/@vite/client';
	$response = wp_remote_get(
		$probe_url,
		array(
			'timeout'     => 0.35,
			'redirection' => 0,
			'sslverify'   => false,
		)
	);
	$is_running = (!is_wp_error($response) && 200 === (int) wp_remote_retrieve_response_code($response));

	set_transient($cache_key, array('is_running' => $is_running), MINUTE_IN_SECONDS);
	return $is_running;
}

/**
 * Return cache namespace for this theme/environment.
 *
 * @return string
 */
function lonestar_get_block_cache_namespace()
{
	if (function_exists('lonestar_get_theme_cache_namespace')) {
		return lonestar_get_theme_cache_namespace();
	}

	return 'default';
}

/**
 * Return theme-level block roots.
 *
 * @param string $block_type Block type: acf|native|php-only|all.
 * @return array
 */
function lonestar_get_theme_block_root_paths($block_type = 'all')
{
	$block_type = strtolower((string) $block_type);
	$relative_paths = array();

	if (('all' === $block_type || 'acf' === $block_type) && defined('LONESTAR_ACF_BLOCKS_PATH')) {
		$relative_paths[] = LONESTAR_ACF_BLOCKS_PATH;
	}
	if (('all' === $block_type || 'native' === $block_type) && defined('LONESTAR_NATIVE_BLOCKS_PATH')) {
		$relative_paths[] = LONESTAR_NATIVE_BLOCKS_PATH;
	}
	if (('all' === $block_type || 'php-only' === $block_type) && defined('LONESTAR_PHP_ONLY_BLOCKS_PATH')) {
		$relative_paths[] = LONESTAR_PHP_ONLY_BLOCKS_PATH;
	}

	$roots = array();
	$theme_base_paths = array(wp_normalize_path(untrailingslashit(LONESTAR_TEMPLATE_PATH)));
	$stylesheet_path = wp_normalize_path(untrailingslashit(get_stylesheet_directory()));
	if ('' !== $stylesheet_path && !in_array($stylesheet_path, $theme_base_paths, true)) {
		$theme_base_paths[] = $stylesheet_path;
	}

	foreach ($theme_base_paths as $theme_base_path) {
		foreach ($relative_paths as $relative_path) {
			$absolute_path = wp_normalize_path($theme_base_path . '/' . ltrim($relative_path, '/'));
			if (is_dir($absolute_path) && is_readable($absolute_path)) {
				$roots[] = $absolute_path;
			}
		}
	}

	$roots = array_values(array_unique($roots));
	sort($roots, SORT_NATURAL);
	return $roots;
}

/**
 * Return normalized source context metadata for parent/child theme paths.
 *
 * @return array<string,array{path:string,uri:string,dist_path:string,dist_uri:string}>
 */
function lonestar_get_theme_source_contexts()
{
	static $contexts = null;
	if (is_array($contexts)) {
		return $contexts;
	}

	$contexts = array();
	$dist_def = trim(defined('LONESTAR_DIST_REL_PATH') ? (string) LONESTAR_DIST_REL_PATH : 'dist/', '/');

	$template_path = wp_normalize_path(untrailingslashit((string) get_template_directory()));
	$template_uri = untrailingslashit((string) get_template_directory_uri());
	if ('' !== $template_path) {
		$contexts['template'] = array(
			'path'      => $template_path,
			'uri'       => $template_uri,
			'dist_path' => wp_normalize_path($template_path . '/' . $dist_def),
			'dist_uri'  => $template_uri . '/' . $dist_def,
		);
	}

	$stylesheet_path = wp_normalize_path(untrailingslashit((string) get_stylesheet_directory()));
	$stylesheet_uri = untrailingslashit((string) get_stylesheet_directory_uri());
	if ('' !== $stylesheet_path && $stylesheet_path !== $template_path) {
		$contexts['stylesheet'] = array(
			'path'      => $stylesheet_path,
			'uri'       => $stylesheet_uri,
			'dist_path' => wp_normalize_path($stylesheet_path . '/' . $dist_def),
			'dist_uri'  => $stylesheet_uri . '/' . $dist_def,
		);
	}

	return $contexts;
}

/**
 * Resolve source context key from absolute filesystem path.
 *
 * @param string $absolute_path Absolute path.
 * @return string
 */
function lonestar_get_source_context_key_for_path($absolute_path)
{
	$absolute_path = wp_normalize_path((string) $absolute_path);
	if ('' === $absolute_path) {
		return 'template';
	}

	$contexts = lonestar_get_theme_source_contexts();
	foreach ($contexts as $context_key => $context) {
		$base_path = isset($context['path']) ? wp_normalize_path((string) $context['path']) : '';
		if ('' === $base_path) {
			continue;
		}

		if (0 === strpos($absolute_path, $base_path . '/')) {
			return sanitize_key((string) $context_key);
		}
	}

	return 'template';
}

/**
 * Return all block roots (theme + enabled modules).
 *
 * @param string $block_type Block type: acf|native|php-only|all.
 * @return array
 */
function lonestar_get_block_root_paths($block_type = 'all')
{
	$roots = lonestar_get_theme_block_root_paths($block_type);

	if (function_exists('modules_get_enabled_module_block_root_paths')) {
		$roots = array_merge($roots, modules_get_enabled_module_block_root_paths($block_type));
	}

	$roots = array_values(array_unique($roots));
	sort($roots, SORT_NATURAL);
	return $roots;
}

/**
 * Return ACF block root paths.
 *
 * @return array
 */
function lonestar_get_acf_block_root_paths()
{
	return lonestar_get_block_root_paths('acf');
}

/**
 * Return native block root paths.
 *
 * @return array
 */
function lonestar_get_native_block_root_paths()
{
	return lonestar_get_block_root_paths('native');
}

/**
 * Return WordPress 7 PHP-only block root paths.
 *
 * @return array
 */
function lonestar_get_php_only_block_root_paths()
{
	return lonestar_get_block_root_paths('php-only');
}

/**
 * Find all block directories by scanning block roots for metadata files.
 *
 * @param bool $apply_toggle_filter Whether to remove disabled blocks.
 *
 * @return array
 */
function lonestar_find_block_directories($apply_toggle_filter = true)
{
    $apply_toggle_filter = (bool) $apply_toggle_filter;
    $directories = array();
    $roots = lonestar_get_block_root_paths();

	if (empty($roots)) {
		return $directories;
	}

	foreach ($roots as $root_path) {
		try {
			$directory = new \RecursiveDirectoryIterator($root_path, \FilesystemIterator::SKIP_DOTS);
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

					return ('block.json' === $name || '.block.json' === substr($name, -11));
				}
			);

			$iterator = new \RecursiveIteratorIterator($filter);
			foreach ($iterator as $file) {
				$directories[] = wp_normalize_path(dirname((string) $file));
			}
		} catch (\Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[lonestar-theme] ' . $e->getMessage());
			}
		}
	}

    $directories = array_values(array_unique($directories));
    sort($directories, SORT_NATURAL);

    if ($apply_toggle_filter && function_exists('lonestar_filter_enabled_block_directories')) {
        return lonestar_filter_enabled_block_directories($directories);
    }

    return $directories;
}

/**
 * Resolve block metadata path for block directory.
 *
 * @param string $block_directory Block directory path.
 * @return string
 */
function lonestar_get_block_json_path($block_directory)
{
	$slug = basename($block_directory);
	$candidates = array(
		$block_directory . '/block.json',
		$block_directory . '/' . $slug . '.block.json',
	);

	foreach ($candidates as $path) {
		if (file_exists($path) && is_readable($path)) {
			return $path;
		}
	}

	return '';
}

/**
 * Check whether block metadata uses file-based assets (create-block compatible mode).
 *
 * @param array $metadata Parsed block.json metadata.
 * @return bool
 */
function lonestar_block_metadata_uses_file_assets($metadata)
{
	if (!is_array($metadata)) {
		return false;
	}

	$asset_fields = array(
		'script',
		'editorScript',
		'viewScript',
		'viewScriptModule',
		'style',
		'editorStyle',
		'viewStyle',
	);

	foreach ($asset_fields as $field) {
		if (!isset($metadata[$field])) {
			continue;
		}

		$values = is_array($metadata[$field]) ? $metadata[$field] : array($metadata[$field]);
		foreach ($values as $value) {
			if (is_string($value) && 0 === strpos($value, 'file:')) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Resolve candidate source file for a block asset type.
 *
 * @param string $block_directory Block directory path.
 * @param string $asset_type Asset type (js|css).
 * @return string
 */
function lonestar_get_block_source_file($block_directory, $asset_type)
{
	$slug = basename($block_directory);
	$candidates = array();

	if ('js' === $asset_type) {
		$candidates = array(
			$block_directory . '/' . $slug . '.js',
			$block_directory . '/index.js',
		);
	}

	if ('css' === $asset_type) {
		$candidates = array(
			$block_directory . '/' . $slug . '.css',
			$block_directory . '/style.css',
		);
	}

	foreach ($candidates as $candidate) {
		if (file_exists($candidate) && is_readable($candidate)) {
			return wp_normalize_path($candidate);
		}
	}

	return '';
}

/**
 * Build theme-relative asset path from absolute filesystem path.
 *
 * @param string $absolute_path Absolute asset path.
 * @return string
 */
function lonestar_get_theme_relative_asset_path($absolute_path, $source_context = '')
{
	$absolute_path = wp_normalize_path($absolute_path);
	$source_context = sanitize_key((string) $source_context);
	$contexts = lonestar_get_theme_source_contexts();

	if ('' !== $source_context && isset($contexts[$source_context])) {
		$context = $contexts[$source_context];
		$theme_root = wp_normalize_path(untrailingslashit((string) $context['path']));
	} else {
		$resolved_context = lonestar_get_source_context_key_for_path($absolute_path);
		$theme_root = isset($contexts[$resolved_context]['path']) ? wp_normalize_path(untrailingslashit((string) $contexts[$resolved_context]['path'])) : '';
	}

	if ('' === $theme_root || 0 !== strpos($absolute_path, $theme_root . '/')) {
		return '';
	}

	return ltrim(substr($absolute_path, strlen($theme_root)), '/');
}

/**
 * Build Vite dev-server URL for a source file in the theme.
 *
 * @param string $absolute_file Absolute source file path.
 * @return string
 */
function lonestar_get_vite_dev_asset_url($absolute_file)
{
	if (!defined('LONESTAR_VITE_SERVER')) {
		return '';
	}

	$source_context = lonestar_get_source_context_key_for_path($absolute_file);
	$relative_path = lonestar_get_theme_relative_asset_path($absolute_file, $source_context);
	if ('' === $relative_path) {
		return '';
	}

	$vite_server = rtrim(LONESTAR_VITE_SERVER, '/');
	return $vite_server . '/' . ltrim($relative_path, '/');
}

/**
 * Build theme URI for an absolute source file path.
 *
 * @param string $absolute_file Absolute source file path.
 * @return string
 */
function lonestar_get_theme_asset_url($absolute_file)
{
	$source_context = lonestar_get_source_context_key_for_path($absolute_file);
	$relative_path = lonestar_get_theme_relative_asset_path($absolute_file, $source_context);
	if ('' === $relative_path) {
		return '';
	}

	$contexts = lonestar_get_theme_source_contexts();
	$theme_uri = isset($contexts[$source_context]['uri'])
		? untrailingslashit((string) $contexts[$source_context]['uri'])
		: (defined('LONESTAR_TEMPLATE_URI') ? untrailingslashit(LONESTAR_TEMPLATE_URI) : get_template_directory_uri());
	return $theme_uri . '/' . ltrim($relative_path, '/');
}

/**
 * Load Vite manifest once for block asset resolution.
 *
 * @return array|null
 */
function lonestar_get_block_assets_manifest($source_context = 'template')
{
	static $manifests = array();
	static $loaded = array();
	$source_context = sanitize_key((string) $source_context);
	if ('' === $source_context) {
		$source_context = 'template';
	}

	if (isset($loaded[$source_context]) && true === $loaded[$source_context]) {
		return isset($manifests[$source_context]) ? $manifests[$source_context] : null;
	}

	$loaded[$source_context] = true;
	$contexts = lonestar_get_theme_source_contexts();
	$dist_path = '';
	if (isset($contexts[$source_context]['dist_path'])) {
		$dist_path = wp_normalize_path((string) $contexts[$source_context]['dist_path']);
	} elseif (defined('LONESTAR_DIST_PATH')) {
		$dist_path = wp_normalize_path((string) LONESTAR_DIST_PATH);
	}

	if ('' === $dist_path) {
		return null;
	}

	$manifest_path = $dist_path . '/manifest.json';
	if (!file_exists($manifest_path) || !is_readable($manifest_path)) {
		return null;
	}

	$manifest_contents = file_get_contents($manifest_path);
	if (false === $manifest_contents) {
		return null;
	}

	$decoded = json_decode($manifest_contents, true);
	if (!is_array($decoded)) {
		return null;
	}

	$manifests[$source_context] = $decoded;
	return $manifests[$source_context];
}

/**
 * Resolve built file from manifest by source file path.
 *
 * @param array  $manifest Vite manifest data.
 * @param string $source_file Absolute source file path.
 * @return string
 */
function lonestar_get_manifest_built_file($manifest, $source_file, $source_context = '')
{
	if (!is_array($manifest) || !is_string($source_file) || '' === $source_file) {
		return '';
	}

	$source_context = sanitize_key((string) $source_context);
	if ('' === $source_context) {
		$source_context = lonestar_get_source_context_key_for_path($source_file);
	}

	$relative_source = lonestar_get_theme_relative_asset_path($source_file, $source_context);
	if ('' === $relative_source || !isset($manifest[$relative_source]) || !is_array($manifest[$relative_source])) {
		return '';
	}

	$entry = $manifest[$relative_source];
	if (empty($entry['file']) || !is_string($entry['file'])) {
		return '';
	}

	return ltrim($entry['file'], '/');
}

/**
 * Collect non-file handles from metadata fields with a fallback handle.
 *
 * @param array  $metadata Parsed block metadata.
 * @param array  $fields Metadata fields that may contain handles.
 * @param string $fallback Default handle if metadata has no explicit handle.
 * @return array
 */
function lonestar_collect_metadata_handles($metadata, $fields, $fallback)
{
	$handles = array();

	foreach ($fields as $field) {
		if (!isset($metadata[$field])) {
			continue;
		}

		$values = is_array($metadata[$field]) ? $metadata[$field] : array($metadata[$field]);
		foreach ($values as $value) {
			if (!is_string($value) || '' === $value || 0 === strpos($value, 'file:')) {
				continue;
			}
			$handles[] = $value;
		}
	}

	if (is_string($fallback) && '' !== $fallback) {
		$handles[] = $fallback;
	}

	return array_values(array_unique($handles));
}

/**
 * Resolve WordPress dependencies for block JavaScript registered by Vite.
 *
 * Native editor bundles use WordPress globals and must load after the editor APIs.
 *
 * @param array $metadata Parsed block metadata.
 * @return array<int,string>
 */
function lonestar_get_block_script_dependencies($metadata)
{
	if (!is_array($metadata) || empty($metadata['editorScript'])) {
		return array();
	}

	return array('wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n');
}

/**
 * Resolve a block script text domain without claiming child-owned strings.
 *
 * @param array  $metadata Parsed block metadata.
 * @param string $source_context Theme source context.
 * @return string
 */
function lonestar_get_block_script_textdomain($metadata, $source_context)
{
	if (is_array($metadata) && isset($metadata['textdomain']) && is_string($metadata['textdomain'])) {
		return sanitize_key($metadata['textdomain']);
	}

	return ('template' === $source_context) ? 'lonestar' : '';
}

/**
 * Register translations for a block script after its script handle exists.
 *
 * @param string $handle Script handle.
 * @param string $textdomain Script text domain.
 * @param string $source_context Theme source context.
 * @return void
 */
function lonestar_register_block_script_translations($handle, $textdomain, $source_context)
{
	if ('' === $handle || '' === $textdomain || !function_exists('wp_set_script_translations')) {
		return;
	}

	$contexts = lonestar_get_theme_source_contexts();
	$languages_path = isset($contexts[$source_context]['path'])
		? trailingslashit($contexts[$source_context]['path']) . 'languages'
		: trailingslashit(get_template_directory()) . 'languages';

	wp_set_script_translations($handle, $textdomain, $languages_path);
}

/**
 * Build block asset registration map from discovered blocks.
 *
 * @param array $block_directories List of block directories.
 * @return array
 */
function lonestar_build_block_asset_registration_map($block_directories)
{
	$map = array();
	if (!is_array($block_directories)) {
		return $map;
	}

	foreach ($block_directories as $block_directory) {
		$block_json_path = lonestar_get_block_json_path($block_directory);
		if ('' === $block_json_path) {
			continue;
		}

		$json_file = file_get_contents($block_json_path);
		if (false === $json_file) {
			continue;
		}

		$json_contents = json_decode($json_file, true);
		if (JSON_ERROR_NONE !== json_last_error() || !isset($json_contents['name'])) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[lonestar-theme] Invalid block metadata: ' . $block_json_path);
			}
			continue;
		}

		if (lonestar_block_metadata_uses_file_assets($json_contents)) {
			// file: assets are managed by register_block_type_from_metadata (create-block mode)
			continue;
		}

		$raw_handle = $json_contents['name'];
		if (!is_string($raw_handle) || '' === $raw_handle) {
			continue;
		}

		$fallback_css_handle = str_replace('/', '-', $raw_handle);
		$separator_pos = strrpos($raw_handle, '/');
		$fallback_js_handle = (false !== $separator_pos) ? substr($raw_handle, $separator_pos + 1) : $raw_handle;

		$source_context = lonestar_get_source_context_key_for_path($block_directory);
		$map[] = array(
			'css_handles' => lonestar_collect_metadata_handles($json_contents, array('style', 'editorStyle', 'viewStyle'), $fallback_css_handle),
			'js_handles'  => lonestar_collect_metadata_handles($json_contents, array('script', 'editorScript', 'viewScript'), $fallback_js_handle),
			'module_handles' => lonestar_collect_metadata_handles($json_contents, array('viewScriptModule'), ''),
			'js_dependencies' => lonestar_get_block_script_dependencies($json_contents),
			'js_textdomain'   => lonestar_get_block_script_textdomain($json_contents, $source_context),
			'source_context'  => $source_context,
			'js_source'   => lonestar_get_block_source_file($block_directory, 'js'),
			'css_source'  => lonestar_get_block_source_file($block_directory, 'css'),
		);
	}

	return $map;
}

/**
 * Return the block runtime index transient key.
 *
 * A single fixed key is used; the cached payload carries its own
 * cache_namespace and is discarded on mismatch, so a namespace change
 * (deploy/build) never leaves an orphaned transient behind under an old key.
 *
 * @return string
 */
function lonestar_get_block_runtime_index_transient_key()
{
	return 'lonestar_block_runtime_v1';
}

/**
 * Decode a block.json file once, for reuse by directory + metadata caching.
 *
 * @param string $metadata_path Absolute block.json (or *.block.json) path.
 * @return array|null Decoded metadata, or null on read/parse failure.
 */
function lonestar_decode_block_metadata_file($metadata_path)
{
	if (!is_string($metadata_path) || '' === $metadata_path || !is_readable($metadata_path)) {
		return null;
	}

	$raw = file_get_contents($metadata_path);
	if (false === $raw) {
		return null;
	}

	$decoded = json_decode($raw, true);
	return (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) ? $decoded : null;
}

/**
 * Build the consolidated block runtime index from a fresh filesystem scan.
 *
 * Discovers block directories once, decodes each block.json once, and
 * builds the JS/CSS asset registration map from the same directory list and
 * decoded metadata pool — replacing four separate discovery passes (ACF,
 * native, PHP-only registration, plus the asset map) that each previously
 * re-scanned the filesystem and re-parsed block.json independently.
 *
 * @return array{
 *   cache_namespace:string,
 *   directories:array{acf:array<int,string>,native:array<int,string>,"php-only":array<int,string>},
 *   metadata:array<string,array{path:string,data:array|null}>,
 *   asset_map:array
 * }
 */
function lonestar_build_block_runtime_index()
{
	$directories = lonestar_find_block_directories();

	$by_type = array(
		'acf'      => array(),
		'native'   => array(),
		'php-only' => array(),
	);
	$metadata = array();

	foreach ($directories as $block_directory) {
		$block_directory = untrailingslashit(wp_normalize_path((string) $block_directory));
		if ('' === $block_directory) {
			continue;
		}

		$type = function_exists('lonestar_get_block_type_from_directory')
			? lonestar_get_block_type_from_directory($block_directory)
			: 'unknown';
		if (isset($by_type[$type])) {
			$by_type[$type][] = $block_directory;
		}

		$metadata_path = lonestar_get_block_json_path($block_directory);
		if ('' === $metadata_path || !is_readable($metadata_path)) {
			continue;
		}

		$metadata[$block_directory] = array(
			'path' => wp_normalize_path($metadata_path),
			'data' => lonestar_decode_block_metadata_file($metadata_path),
		);
	}

	$asset_map = lonestar_build_block_asset_registration_map($directories);

	return array(
		'cache_namespace' => lonestar_get_block_cache_namespace(),
		'directories'     => $by_type,
		'metadata'        => $metadata,
		'asset_map'       => $asset_map,
	);
}

/**
 * Return the consolidated block runtime index (directories + decoded
 * metadata + asset map), request-memoized and transient-backed.
 *
 * Replaces the separate lonestar_acf_blocks_to_load_v3,
 * lonestar_native_blocks_to_load_v2, lonestar_php_only_blocks_to_load_v1,
 * lonestar_blocks_to_scan_v3_{ns}, and lonestar_block_asset_map_v4_{ns}
 * transients with a single cache entry. Dev mode always bypasses the
 * transient (fresh discovery per request) but is still request-memoized so
 * repeated calls within one request don't re-scan the filesystem.
 *
 * @return array
 */
function lonestar_get_block_runtime_index()
{
	static $index = null;
	if (is_array($index)) {
		return $index;
	}

	if (lonestar_is_vite_dev_mode()) {
		$index = lonestar_build_block_runtime_index();
		return $index;
	}

	$transient_key = lonestar_get_block_runtime_index_transient_key();
	// ACF availability changes block availability but is not part of the theme
	// cache namespace, so an index built without ACF must not survive activation.
	$cache_namespace = lonestar_get_block_cache_namespace() . (lonestar_is_acf_block_runtime_available() ? '|acf' : '|no-acf');
	$cached = get_transient($transient_key);
	if (
		is_array($cached) &&
		isset($cached['cache_namespace'], $cached['directories'], $cached['metadata'], $cached['asset_map']) &&
		$cache_namespace === $cached['cache_namespace']
	) {
		$index = $cached;
		return $index;
	}

	$index = lonestar_build_block_runtime_index();
	$index['cache_namespace'] = $cache_namespace;
	set_transient($transient_key, $index, HOUR_IN_SECONDS);
	return $index;
}

/**
 * Return the block asset registration map from the runtime index.
 *
 * @return array
 */
function lonestar_get_block_asset_registration_map()
{
	$index = lonestar_get_block_runtime_index();
	return isset($index['asset_map']) && is_array($index['asset_map']) ? $index['asset_map'] : array();
}

/**
 * Register block JavaScript and CSS files for frontend and editor.
 *
 * @return void
 */
function lonestar_register_block_files()
{
	$block_assets_map = lonestar_get_block_asset_registration_map();
	if (empty($block_assets_map) || !is_array($block_assets_map)) {
		return;
	}

	$is_vite_dev_mode = lonestar_is_vite_dev_mode();
	$source_contexts = lonestar_get_theme_source_contexts();

	foreach ($block_assets_map as $asset_map) {
		$js_source = isset($asset_map['js_source']) && is_string($asset_map['js_source']) ? $asset_map['js_source'] : '';
		$css_source = isset($asset_map['css_source']) && is_string($asset_map['css_source']) ? $asset_map['css_source'] : '';
		$js_handles = isset($asset_map['js_handles']) && is_array($asset_map['js_handles']) ? $asset_map['js_handles'] : array();
		$module_handles = isset($asset_map['module_handles']) && is_array($asset_map['module_handles']) ? $asset_map['module_handles'] : array();
		$js_dependencies = isset($asset_map['js_dependencies']) && is_array($asset_map['js_dependencies']) ? $asset_map['js_dependencies'] : array();
		$js_textdomain = isset($asset_map['js_textdomain']) ? sanitize_key((string) $asset_map['js_textdomain']) : '';
		$source_context = isset($asset_map['source_context']) ? sanitize_key((string) $asset_map['source_context']) : 'template';
		$css_handles = isset($asset_map['css_handles']) && is_array($asset_map['css_handles']) ? $asset_map['css_handles'] : array();

		if ($is_vite_dev_mode) {
			if ('' !== $js_source) {
				$js_url = lonestar_get_vite_dev_asset_url($js_source);
				if ('' !== $js_url) {
					foreach ($js_handles as $js_handle) {
						if (wp_script_is($js_handle, 'registered')) {
							continue;
						}
						wp_register_script($js_handle, $js_url, $js_dependencies, null, true);
						lonestar_register_block_script_translations($js_handle, $js_textdomain, $source_context);
					}

					if (!empty($module_handles) && function_exists('wp_register_script_module')) {
						foreach ($module_handles as $module_handle) {
							wp_register_script_module($module_handle, $js_url, array(), null);
						}
					}
				}
			}

			if ('' !== $css_source) {
				$css_url = lonestar_get_vite_dev_asset_url($css_source);
				if ('' !== $css_url) {
					foreach ($css_handles as $css_handle) {
						if (wp_style_is($css_handle, 'registered')) {
							continue;
						}
						wp_register_style($css_handle, $css_url, array(), null);
					}
				}
			}

			continue;
		}

		if ('' !== $js_source) {
			$js_source_context = lonestar_get_source_context_key_for_path($js_source);
			$js_context = isset($source_contexts[$js_source_context]) ? $source_contexts[$js_source_context] : null;
			$manifest = $is_vite_dev_mode ? null : lonestar_get_block_assets_manifest($js_source_context);
			$js_file_path = '';
			$js_file_version = null;

			if (is_array($manifest)) {
				$built_js_file = lonestar_get_manifest_built_file($manifest, $js_source, $js_source_context);
				if ('' !== $built_js_file) {
					$js_dist_path = is_array($js_context) && isset($js_context['dist_path'])
						? wp_normalize_path((string) $js_context['dist_path']) . '/' . $built_js_file
						: (defined('LONESTAR_DIST_PATH') ? LONESTAR_DIST_PATH . '/' . $built_js_file : '');
					$js_file_path = is_array($js_context) && isset($js_context['dist_uri'])
						? untrailingslashit((string) $js_context['dist_uri']) . '/' . $built_js_file
						: (defined('LONESTAR_DIST_URI') ? LONESTAR_DIST_URI . '/' . $built_js_file : '');
					$js_file_version = file_exists($js_dist_path) ? filemtime($js_dist_path) : null;
				}
			}

			if ('' === $js_file_path) {
				$js_file_path = lonestar_get_theme_asset_url($js_source);
				$js_file_version = file_exists($js_source) ? filemtime($js_source) : null;
			}

			if ('' !== $js_file_path) {
				foreach ($js_handles as $js_handle) {
					if (wp_script_is($js_handle, 'registered')) {
						continue;
					}
					wp_register_script($js_handle, $js_file_path, $js_dependencies, $js_file_version, true);
					lonestar_register_block_script_translations($js_handle, $js_textdomain, $source_context);
				}

				if (!empty($module_handles) && function_exists('wp_register_script_module')) {
					foreach ($module_handles as $module_handle) {
						wp_register_script_module($module_handle, $js_file_path, array(), $js_file_version);
					}
				}
			}
		}

		if ('' !== $css_source) {
			$css_source_context = lonestar_get_source_context_key_for_path($css_source);
			$css_context = isset($source_contexts[$css_source_context]) ? $source_contexts[$css_source_context] : null;
			$manifest = $is_vite_dev_mode ? null : lonestar_get_block_assets_manifest($css_source_context);
			$css_file_uri = '';
			$css_file_version = null;

			if (is_array($manifest)) {
				$built_css_file = lonestar_get_manifest_built_file($manifest, $css_source, $css_source_context);
				if ('' !== $built_css_file) {
					$css_dist_path = is_array($css_context) && isset($css_context['dist_path'])
						? wp_normalize_path((string) $css_context['dist_path']) . '/' . $built_css_file
						: (defined('LONESTAR_DIST_PATH') ? LONESTAR_DIST_PATH . '/' . $built_css_file : '');
					$css_file_uri = is_array($css_context) && isset($css_context['dist_uri'])
						? untrailingslashit((string) $css_context['dist_uri']) . '/' . $built_css_file
						: (defined('LONESTAR_DIST_URI') ? LONESTAR_DIST_URI . '/' . $built_css_file : '');
					$css_file_version = file_exists($css_dist_path) ? filemtime($css_dist_path) : null;
				}
			}

			if ('' === $css_file_uri) {
				$css_file_uri = lonestar_get_theme_asset_url($css_source);
				$css_file_version = file_exists($css_source) ? filemtime($css_source) : null;
			}

			if ('' !== $css_file_uri) {
				foreach ($css_handles as $css_handle) {
					if (wp_style_is($css_handle, 'registered')) {
						continue;
					}
					wp_register_style($css_handle, $css_file_uri, array(), $css_file_version);
				}
			}
		}
	}
}

/**
 * Resolve a theme (template/stylesheet) asset URL to a local filesystem path.
 *
 * Maps the URL against the template and stylesheet directory URIs and
 * resolves to get_template_directory()/get_stylesheet_directory(), which is
 * correct even when WP_CONTENT_DIR is outside ABSPATH (e.g. Bedrock).
 *
 * @param string $url Asset URL (no query string expected).
 * @return string Normalized absolute path, or '' if not a theme asset.
 */
function lonestar_theme_asset_url_to_path($url)
{
	if (!is_string($url) || '' === $url) {
		return '';
	}

	$contexts = array(
		untrailingslashit((string) get_template_directory_uri()) => untrailingslashit((string) get_template_directory()),
	);
	$stylesheet_uri = untrailingslashit((string) get_stylesheet_directory_uri());
	if ('' !== $stylesheet_uri && !isset($contexts[$stylesheet_uri])) {
		$contexts[$stylesheet_uri] = untrailingslashit((string) get_stylesheet_directory());
	}

	foreach ($contexts as $theme_uri => $theme_path) {
		if ('' === $theme_uri || '' === $theme_path) {
			continue;
		}

		if (0 === strpos($url, $theme_uri . '/')) {
			$relative = ltrim(substr($url, strlen($theme_uri)), '/');
			return wp_normalize_path($theme_path . '/' . $relative);
		}
	}

	return '';
}

/**
 * Fallback cache-busting for block CSS and JS files assigned as handle.
 *
 * Only rewrites the version for assets enqueued with WordPress's default
 * `ver` value (the core version string), which indicates the asset was
 * registered/enqueued without an explicit version. Theme-registered block
 * assets already pass explicit filemtime-based versions in
 * lonestar_register_block_files() and are left untouched here.
 *
 * @param string $src Asset source URL.
 * @param string $handle Enqueued handle.
 * @return string
 */
function lonestar_remove_query_string_from_static_files($src, $handle)
{
	unset($handle);

	if (!is_string($src) || false === strpos($src, '?ver=')) {
		return $src;
	}

	$ver = wp_parse_url($src, PHP_URL_QUERY);
	$ver_args = array();
	if (is_string($ver) && '' !== $ver) {
		wp_parse_str($ver, $ver_args);
	}
	if (!isset($ver_args['ver']) || (string) $ver_args['ver'] !== (string) get_bloginfo('version')) {
		// Asset already carries an explicit (non-core) version; leave it alone.
		return $src;
	}

	$template_uri = untrailingslashit((string) get_template_directory_uri());
	$stylesheet_uri = untrailingslashit((string) get_stylesheet_directory_uri());

	$is_theme_asset = false;
	foreach (array($template_uri, $stylesheet_uri) as $theme_uri) {
		if ('' === $theme_uri) {
			continue;
		}

		if (0 === strpos($src, $theme_uri . '/')) {
			$is_theme_asset = true;
			break;
		}
	}

	if (!$is_theme_asset) {
		return $src;
	}

	static $mtime_memo = array();

	$original_src = $src;
	$src = remove_query_arg('ver', $src);
	$file_path = lonestar_theme_asset_url_to_path($src);
	if ('' === $file_path) {
		return $original_src;
	}

	if (array_key_exists($file_path, $mtime_memo)) {
		$version = $mtime_memo[$file_path];
	} else {
		$version = file_exists($file_path) ? filemtime($file_path) : false;
		$mtime_memo[$file_path] = $version;
	}

	if (false === $version) {
		return $original_src;
	}

	return add_query_arg('ver', $version, $src);
}

add_filter('style_loader_src', 'lonestar_remove_query_string_from_static_files', 10, 2);
add_filter('script_loader_src', 'lonestar_remove_query_string_from_static_files', 10, 2);
