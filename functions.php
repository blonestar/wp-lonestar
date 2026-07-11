<?php
if (!defined('ABSPATH')) {
    die('Direct access not permitted');
}

/**
 * Lonestar Framework Theme Functions
 *
 * @package Lonestar
 * @license GNU General Public License v2 or later
 */

/**
 * Define Constants
 */
if (!defined('LONESTAR_TEMPLATE_PATH')) {
    define('LONESTAR_TEMPLATE_PATH', trailingslashit(get_template_directory()));
}
if (!defined('LONESTAR_TEMPLATE_URI')) {
    define('LONESTAR_TEMPLATE_URI', trailingslashit(get_template_directory_uri()));
}
if (!defined('LONESTAR_ACF_BLOCKS_PATH')) {
    define('LONESTAR_ACF_BLOCKS_PATH', 'blocks/acf/');
}
if (!defined('LONESTAR_NATIVE_BLOCKS_PATH')) {
    define('LONESTAR_NATIVE_BLOCKS_PATH', 'blocks/native/');
}
if (!defined('LONESTAR_PHP_ONLY_BLOCKS_PATH')) {
    define('LONESTAR_PHP_ONLY_BLOCKS_PATH', 'blocks/php-only/');
}
if (!defined('LONESTAR_DIST_REL_PATH')) {
    define('LONESTAR_DIST_REL_PATH', 'dist/');
}

// Backward-compatible aliases. New integrations should use LONESTAR_* constants.
if (!defined('TEMPLATE_PATH')) {
    define('TEMPLATE_PATH', LONESTAR_TEMPLATE_PATH);
}
if (!defined('TEMPLATE_URI')) {
    define('TEMPLATE_URI', LONESTAR_TEMPLATE_URI);
}
if (!defined('ACF_BLOCKS_PATH')) {
    define('ACF_BLOCKS_PATH', LONESTAR_ACF_BLOCKS_PATH);
}
if (!defined('NATIVE_BLOCKS_PATH')) {
    define('NATIVE_BLOCKS_PATH', LONESTAR_NATIVE_BLOCKS_PATH);
}
if (!defined('PHP_ONLY_BLOCKS_PATH')) {
    define('PHP_ONLY_BLOCKS_PATH', LONESTAR_PHP_ONLY_BLOCKS_PATH);
}
if (!defined('DIST_REL_PATH')) {
    define('DIST_REL_PATH', LONESTAR_DIST_REL_PATH);
}

if (!function_exists('lonestar_get_theme_cache_namespace')) {
    /**
     * Build cache namespace that changes after deploy/build.
     *
     * @return string
     */
    function lonestar_get_theme_cache_namespace()
    {
        static $namespace = null;
        static $is_resolving = false;

        if (is_string($namespace) && '' !== $namespace) {
            return $namespace;
        }

        if ($is_resolving) {
            return 'lonestar-ns-guard';
        }

        $is_resolving = true;
        try {
            $theme = wp_get_theme();
            $theme_version = ($theme instanceof \WP_Theme) ? (string) $theme->get('Version') : '';
            $manifest_path = trailingslashit(get_template_directory()) . DIST_REL_PATH . 'manifest.json';
            $manifest_mtime = file_exists($manifest_path) ? (string) filemtime($manifest_path) : 'manifest-missing';
            $bootstrap_file = trailingslashit(get_template_directory()) . 'functions.php';
            $bootstrap_mtime = file_exists($bootstrap_file) ? (string) filemtime($bootstrap_file) : '0';
            $environment = function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production';
            $module_signature = function_exists('modules_get_module_runtime_signature')
                ? (string) modules_get_module_runtime_signature()
                : 'modules-unavailable';

            $namespace = substr(md5(implode('|', array($theme_version, $manifest_mtime, $bootstrap_mtime, $environment, $module_signature))), 0, 12);
        } finally {
            $is_resolving = false;
        }

        return $namespace;
    }
}

/**
 * Load core theme functionality
 * These files should not be modified for project-specific needs
 */
// Explicit order keeps bootstrap dependencies reviewable and deterministic.
$lonestar_core_files = array(
    'vite.php',
    'modules.php',
    'content-types.php',
    'blocks-acf-enqueue.php',
    'blocks-state.php',
    'blocks-acf.php',
    'blocks-native.php',
    'blocks-php-only.php',
    'helpers.php',
    'shortcodes.php',
    'theme-updates.php',
);

$lonestar_module_system_disabled = (
    (defined('MODULES_DISABLE_SYSTEM') && true === MODULES_DISABLE_SYSTEM) ||
    (defined('LONESTAR_DISABLE_MODULE_SYSTEM') && true === LONESTAR_DISABLE_MODULE_SYSTEM)
);

foreach ($lonestar_core_files as $lonestar_core_file) {
    if ('modules.php' === $lonestar_core_file && $lonestar_module_system_disabled) {
        continue;
    }

    $lonestar_core_path = LONESTAR_TEMPLATE_PATH . 'inc/core/' . $lonestar_core_file;
    if (is_readable($lonestar_core_path)) {
        require_once $lonestar_core_path;
    }
}
unset($lonestar_core_file, $lonestar_core_files, $lonestar_core_path, $lonestar_module_system_disabled);

/**
 * Load project-specific functionality
 * Add your custom inc.*.php files here for project-specific features
 */
foreach (glob(get_template_directory() . '/inc/inc.*.php') as $file) {
    require_once $file;
}

if (!function_exists('lonestar_setup')) {

    /**
     * Sets up theme defaults and registers support for various WordPress features.
     *
     * Note that this function is hooked into the after_setup_theme hook, which
     * runs before the init hook. The init hook is too late for some features, such
     * as indicating support for post thumbnails.
     *
     * @since 0.1.0
     *
     * @return void
     */
    function lonestar_setup()
    {
        // Make theme available for translation.
        load_theme_textdomain('lonestar-theme', get_template_directory() . '/languages');

        /**
         * Enable menu support
         */
        add_theme_support('menus');

        /**
         * Enable post thumbnails
         */
        add_theme_support('post-thumbnails');

        /**
         * Remove core block patterns
         */
        remove_theme_support('core-block-patterns');

        /**
         * Add support for editor styles
         */
        add_theme_support('editor-styles');

        /**
         * Add support for responsive embeds
         */
        add_theme_support('responsive-embeds');

        /**
         * Add support for align wide
         */
        add_theme_support('align-wide');
    }
}
add_action('after_setup_theme', 'lonestar_setup');

