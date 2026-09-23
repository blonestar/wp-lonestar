<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*
 * VITE & Tailwind JIT development
 * Inspired by https://github.com/andrefelipe/vite-php-setup
 *
 */
// define('IS_VITE_DEVELOPMENT', TRUE);

// dist subfolder - defined in vite.config.mjs
if (!defined('DIST_DEF')) {
    define('DIST_DEF', trim(defined('DIST_REL_PATH') ? DIST_REL_PATH : 'dist/', '/'));
}

// defining some base urls and paths
if (!defined('DIST_URI')) {
    $theme_uri = defined('TEMPLATE_URI') ? untrailingslashit(TEMPLATE_URI) : get_template_directory_uri();
    define('DIST_URI', $theme_uri . '/' . DIST_DEF);
}
if (!defined('DIST_PATH')) {
    $theme_path = defined('TEMPLATE_PATH') ? untrailingslashit(TEMPLATE_PATH) : get_template_directory();
    define('DIST_PATH', $theme_path . '/' . DIST_DEF);
}

// js enqueue settings
if (!defined('JS_DEPENDENCY')) {
    define('JS_DEPENDENCY', array()); // array('jquery') as example
}
if (!defined('JS_LOAD_IN_FOOTER')) {
    define('JS_LOAD_IN_FOOTER', true); // load scripts in footer?
}

// default server address, port and entry point can be customized in vite.config.mjs
if (!defined('VITE_SERVER')) {
    define('VITE_SERVER', 'http://localhost:3000');
}
if (!defined('VITE_ENTRY_POINT')) {
    define('VITE_ENTRY_POINT', '/main.js');
}

add_action('wp_enqueue_scripts', 'lonestar_enqueue_reset_css', 0);
add_action('wp_enqueue_scripts', 'lonestar_enqueue_vite_assets', 20);
add_action('enqueue_block_editor_assets', 'lonestar_enqueue_vite_editor_hmr_client', 1);
add_action('admin_notices', 'lonestar_missing_vite_build_notice');
add_filter('wp_script_attributes', 'lonestar_filter_module_script_attributes');

/**
 * Emit type="module" on <script> tags for handles registered with
 * wp_script_add_data( $handle, 'type', 'module' ).
 *
 * Core's class-wp-scripts.php does not natively act on the 'type' script
 * data key, so wp_script_add_data() alone is silently ignored. WP 6.3+
 * provides the wp_script_attributes filter (used for both the frontend
 * and the block editor) which we use here to add the attribute back in.
 *
 * @param array $attributes Script tag attributes, includes 'id' as "{handle}-js".
 * @return array
 */
function lonestar_filter_module_script_attributes($attributes)
{
    if (empty($attributes['id']) || !is_string($attributes['id'])) {
        return $attributes;
    }

    $handle = preg_replace('/-js$/', '', $attributes['id']);
    if ('' === $handle || 'module' !== wp_scripts()->get_data($handle, 'type')) {
        return $attributes;
    }

    $attributes['type'] = 'module';

    return $attributes;
}

/**
 * Build-safe file version helper.
 *
 * @param string $file_path Absolute file path.
 * @return int|null
 */
function lonestar_asset_version($file_path)
{
    if (!is_string($file_path) || '' === $file_path || !file_exists($file_path)) {
        return null;
    }

    return filemtime($file_path);
}

/**
 * Enqueue baseline reset stylesheet.
 *
 * @return void
 */
function lonestar_enqueue_reset_css()
{
    if (is_admin()) {
        return;
    }

    $theme_path = defined('TEMPLATE_PATH') ? untrailingslashit(TEMPLATE_PATH) : get_template_directory();
    $theme_uri = defined('TEMPLATE_URI') ? untrailingslashit(TEMPLATE_URI) : get_template_directory_uri();
    $reset_file = $theme_path . '/assets/css/reset.css';
    wp_enqueue_style('lonestar_reset', $theme_uri . '/assets/css/reset.css', array(), lonestar_asset_version($reset_file));
}

/**
 * Enqueue Vite client script for HMR.
 *
 * @return void
 */
function lonestar_enqueue_vite_client()
{
    if (!lonestar_is_vite_dev_mode()) {
        return;
    }

    $vite_server = rtrim(VITE_SERVER, '/');
    $client_handle = 'lonestar-vite-client';
    $client_src = $vite_server . '/@vite/client';

    if (!wp_script_is($client_handle, 'registered')) {
        wp_register_script($client_handle, $client_src, array(), null, true);
        wp_script_add_data($client_handle, 'type', 'module');
    }
    wp_enqueue_script($client_handle);
}

/**
 * Enqueue Vite entry script and ensure HMR client is loaded first.
 *
 * @return void
 */
function lonestar_enqueue_vite_entry_script()
{
    if (!lonestar_is_vite_dev_mode()) {
        return;
    }

    lonestar_enqueue_vite_client();

    $vite_server = rtrim(VITE_SERVER, '/');
    $entry_point = '/' . ltrim(VITE_ENTRY_POINT, '/');
    $entry_handle = 'lonestar-vite-entry';
    $entry_src = $vite_server . $entry_point;

    if (!wp_script_is($entry_handle, 'registered')) {
        wp_register_script($entry_handle, $entry_src, array('lonestar-vite-client'), null, true);
        wp_script_add_data($entry_handle, 'type', 'module');
    }
    wp_enqueue_script($entry_handle);
}

/**
 * Enqueue HMR client in block editor so block module scripts can hot-reload.
 *
 * @return void
 */
function lonestar_enqueue_vite_editor_hmr_client()
{
    lonestar_enqueue_vite_client();
}

/**
 * Read and cache Vite manifest data.
 *
 * @return array|null
 */
function lonestar_get_vite_manifest()
{
    static $manifest = null;
    static $manifest_loaded = false;

    if ($manifest_loaded) {
        return $manifest;
    }

    $manifest_loaded = true;
    $manifest_path = DIST_PATH . '/manifest.json';
    if (!file_exists($manifest_path) || !is_readable($manifest_path)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[lonestar-theme] Vite manifest is missing or unreadable: ' . $manifest_path);
        }
        return null;
    }

    $manifest_content = file_get_contents($manifest_path);
    if (false === $manifest_content) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[lonestar-theme] Unable to read Vite manifest: ' . $manifest_path);
        }
        return null;
    }

    $decoded_manifest = json_decode($manifest_content, true);
    if (!is_array($decoded_manifest)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[lonestar-theme] Invalid Vite manifest JSON: ' . $manifest_path);
        }
        return null;
    }

    $manifest = $decoded_manifest;
    return $manifest;
}

/**
 * Read Vite manifest and return entry metadata.
 *
 * @param string $entry_key Entry key from Vite manifest.
 * @return array|null
 */
function lonestar_get_vite_manifest_entry($entry_key)
{
    $manifest = lonestar_get_vite_manifest();
    if (!is_array($manifest)) {
        return null;
    }

    $candidates = array_values(array_unique(array($entry_key, 'main.js')));
    foreach ($candidates as $candidate) {
        if (isset($manifest[$candidate]) && is_array($manifest[$candidate])) {
            return $manifest[$candidate];
        }
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[lonestar-theme] Missing Vite entry in manifest: ' . $entry_key);
    }
    return null;
}

/**
 * Enqueue source assets as a safe fallback when build artifacts are missing.
 *
 * @return void
 */
function lonestar_enqueue_theme_source_fallback_assets()
{
    $theme_path = defined('TEMPLATE_PATH') ? untrailingslashit(TEMPLATE_PATH) : get_template_directory();
    $theme_uri = defined('TEMPLATE_URI') ? untrailingslashit(TEMPLATE_URI) : get_template_directory_uri();

    $fallback_css = $theme_path . '/assets/css/styles.css';
    if (file_exists($fallback_css)) {
        wp_enqueue_style(
            'lonestar-theme-fallback',
            $theme_uri . '/assets/css/styles.css',
            array('lonestar_reset'),
            lonestar_asset_version($fallback_css)
        );
    }

    $fallback_js = $theme_path . '/assets/js/scripts.js';
    if (file_exists($fallback_js)) {
        wp_enqueue_script(
            'lonestar-theme-fallback',
            $theme_uri . '/assets/js/scripts.js',
            array(),
            lonestar_asset_version($fallback_js),
            JS_LOAD_IN_FOOTER
        );
    }
}

/**
 * Show admin warning when production build artifacts are unavailable.
 *
 * @return void
 */
function lonestar_missing_vite_build_notice()
{
    if (lonestar_is_vite_dev_mode() || !current_user_can('manage_options')) {
        return;
    }

    if (is_array(lonestar_get_vite_manifest())) {
        return;
    }

    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('Lonestar Theme: Vite build artifacts are missing. Run "npm run build" before deployment.', 'lonestar');
    echo '</p></div>';
}

/**
 * Enqueue compiled assets in production and HMR scripts in development.
 *
 * @return void
 */
function lonestar_enqueue_vite_assets()
{
    $entry_key = ltrim(VITE_ENTRY_POINT, '/');

    if (lonestar_is_vite_dev_mode()) {
        lonestar_enqueue_vite_entry_script();
        return;
    }

    $manifest_entry = lonestar_get_vite_manifest_entry($entry_key);
    if (!is_array($manifest_entry) || empty($manifest_entry['file'])) {
        lonestar_enqueue_theme_source_fallback_assets();
        return;
    }

    if (!empty($manifest_entry['css']) && is_array($manifest_entry['css'])) {
        foreach ($manifest_entry['css'] as $css_file) {
            if (!is_string($css_file) || '' === $css_file) {
                continue;
            }

            $css_file = ltrim($css_file, '/');
            $css_path = DIST_PATH . '/' . $css_file;
            $handle = pathinfo($css_file, PATHINFO_FILENAME);
            wp_enqueue_style($handle, DIST_URI . '/' . $css_file, array(), lonestar_asset_version($css_path));
        }
    }

    $main_file = ltrim($manifest_entry['file'], '/');
    $main_path = DIST_PATH . '/' . $main_file;
    wp_enqueue_script('main', DIST_URI . '/' . $main_file, JS_DEPENDENCY, lonestar_asset_version($main_path), JS_LOAD_IN_FOOTER);
    wp_script_add_data('main', 'type', 'module');
}

/**
 * Register block editor styles so editor content preview matches the front end.
 *
 * The frontend already loads assets/css/reset.css and the built Vite CSS
 * (see lonestar_enqueue_reset_css() and lonestar_enqueue_vite_assets()),
 * but `add_theme_support('editor-styles')` alone does not pull those files
 * into the editor iframe — each stylesheet must be registered explicitly
 * via add_editor_style().
 *
 * In Vite dev mode we skip the (possibly stale/missing) dist CSS entirely:
 * the editor already gets live styles via the Vite HMR client, enqueued in
 * lonestar_enqueue_vite_editor_hmr_client() on enqueue_block_editor_assets.
 *
 * Hooked on after_setup_theme at priority 20, after lonestar_setup() (which
 * runs at the default priority 10 and declares editor-styles support) and
 * after this file's own top-level code has run. lonestar_is_vite_dev_mode()
 * caches its Vite dev-server probe in a transient (see
 * inc/core/blocks-acf-enqueue.php), so calling it here costs at most one
 * fast options read; it never re-probes on every request.
 *
 * @return void
 */
function lonestar_register_editor_styles()
{
    add_editor_style('assets/css/reset.css');

    if (lonestar_is_vite_dev_mode()) {
        return;
    }

    $entry_key = ltrim(VITE_ENTRY_POINT, '/');
    $manifest_entry = lonestar_get_vite_manifest_entry($entry_key);
    if (!is_array($manifest_entry) || empty($manifest_entry['css']) || !is_array($manifest_entry['css'])) {
        return;
    }

    foreach ($manifest_entry['css'] as $css_file) {
        if (!is_string($css_file) || '' === $css_file) {
            continue;
        }

        add_editor_style(trailingslashit(DIST_DEF) . ltrim($css_file, '/'));
    }
}
add_action('after_setup_theme', 'lonestar_register_editor_styles', 20);
