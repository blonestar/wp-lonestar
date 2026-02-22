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
define('TEMPLATE_PATH', trailingslashit(get_template_directory()));
define('TEMPLATE_URI', trailingslashit(get_template_directory_uri()));
define('ACF_BLOCKS_PATH', 'blocks/acf/');
define('NATIVE_BLOCKS_PATH', 'blocks/native/');
define('DIST_REL_PATH', 'dist/');

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
foreach (glob(get_template_directory() . '/inc/core/*.php') as $file) {
    $is_module_system_disabled = (
        (defined('MODULES_DISABLE_SYSTEM') && true === MODULES_DISABLE_SYSTEM) ||
        (defined('LONESTAR_DISABLE_MODULE_SYSTEM') && true === LONESTAR_DISABLE_MODULE_SYSTEM)
    );
    $core_file_basename = basename((string) $file);
    $is_module_core_file = (1 === preg_match('/^modules(?:_.+)?\.php$/', $core_file_basename));

    if ($is_module_core_file) {
        if ($is_module_system_disabled) {
            continue;
        }

        // `modules.php` is the entrypoint and loads modules_* parts via require_once.
        if ('modules.php' !== $core_file_basename) {
            continue;
        }
    }

    require_once $file;
}

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

