<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme Settings admin UI and cache invalidation hooks.
 */

/**
 * Return admin page slug for theme settings.
 *
 * @return string
 */
function modules_get_settings_page_slug()
{
    return 'lonestar-theme-modules';
}

/**
 * Return legacy admin page slug for backward compatibility.
 *
 * @return string
 */
function modules_get_legacy_settings_page_slug()
{
    return 'lonestar-theme-settings';
}

/**
 * Register Theme Settings page.
 *
 * @return void
 */
function modules_register_modules_admin_page()
{
    add_theme_page(
        __('Theme Settings', 'lonestar-theme'),
        __('Theme Settings', 'lonestar-theme'),
        'manage_options',
        modules_get_settings_page_slug(),
        'modules_render_modules_admin_page'
    );
}

/**
 * Check whether provided page slug targets this settings page.
 *
 * @param string $page_slug Query page slug.
 * @return bool
 */
function modules_is_settings_page_slug($page_slug)
{
    $page_slug = sanitize_key((string) $page_slug);
    if ('' === $page_slug) {
        return false;
    }

    return in_array(
        $page_slug,
        array(modules_get_settings_page_slug(), modules_get_legacy_settings_page_slug()),
        true
    );
}

/**
 * Return Theme Settings tabs.
 *
 * @return array<string,string> Tab key => tab label.
 */
function modules_get_settings_tabs()
{
    $tabs = array(
        'modules' => __('Modules', 'lonestar-theme'),
        'blocks'  => __('Blocks', 'lonestar-theme'),
        'changelog' => __('Changelog', 'lonestar-theme'),
        'about' => __('About', 'lonestar-theme'),
    );

    /**
     * Filter Theme Settings tab list.
     *
     * @param array<string,string> $tabs Tab key => tab label.
     */
    $tabs = apply_filters('lonestar_theme_settings_tabs', $tabs);
    if (!is_array($tabs) || empty($tabs)) {
        return array(
            'modules' => __('Modules', 'lonestar-theme'),
            'blocks'  => __('Blocks', 'lonestar-theme'),
            'changelog' => __('Changelog', 'lonestar-theme'),
            'about' => __('About', 'lonestar-theme'),
        );
    }

    $normalized_tabs = array();
    foreach ($tabs as $tab_key => $tab_label) {
        $tab_key = sanitize_key((string) $tab_key);
        $tab_label = sanitize_text_field((string) $tab_label);
        if ('' === $tab_key || '' === $tab_label) {
            continue;
        }

        $normalized_tabs[$tab_key] = $tab_label;
    }

    if (empty($normalized_tabs)) {
        return array(
            'modules' => __('Modules', 'lonestar-theme'),
            'blocks'  => __('Blocks', 'lonestar-theme'),
            'changelog' => __('Changelog', 'lonestar-theme'),
            'about' => __('About', 'lonestar-theme'),
        );
    }

    return $normalized_tabs;
}

/**
 * Return currently selected Theme Settings tab.
 *
 * @param string|null $requested_tab Optional tab key from request payload.
 * @return string
 */
function modules_get_current_settings_tab($requested_tab = null)
{
    $tabs = modules_get_settings_tabs();
    $tab_keys = array_keys($tabs);
    $default_tab = !empty($tab_keys) ? sanitize_key((string) $tab_keys[0]) : 'modules';

    if (!is_string($requested_tab) && isset($_GET['tab'])) {
        $requested_tab = (string) wp_unslash($_GET['tab']);
    }

    $requested_tab = sanitize_key((string) $requested_tab);
    if ('' === $requested_tab || !isset($tabs[$requested_tab])) {
        return $default_tab;
    }

    return $requested_tab;
}

/**
 * Build Theme Settings URL for a specific tab.
 *
 * @param string $tab Tab key.
 * @param array<string,string|int> $query_args Additional query args.
 * @return string
 */
function modules_get_settings_tab_url($tab, $query_args = array())
{
    $tab = modules_get_current_settings_tab((string) $tab);
    $args = array(
        'page' => modules_get_settings_page_slug(),
        'tab'  => $tab,
    );

    if (is_array($query_args) && !empty($query_args)) {
        foreach ($query_args as $arg_key => $arg_value) {
            $arg_key = sanitize_key((string) $arg_key);
            if ('' === $arg_key) {
                continue;
            }

            if (!is_scalar($arg_value)) {
                continue;
            }

            $args[$arg_key] = (string) $arg_value;
        }
    }

    return add_query_arg($args, admin_url('themes.php'));
}

/**
 * Return available changelog files by source.
 *
 * @return array<string,array{label:string,path:string}>
 */
function modules_get_changelog_file_map()
{
    $map = array();

    $template_path = wp_normalize_path(trailingslashit((string) get_template_directory()) . 'CHANGELOG.md');
    $map['template'] = array(
        'label' => modules_get_source_label('template'),
        'path'  => $template_path,
    );

    if (get_stylesheet_directory() !== get_template_directory()) {
        $stylesheet_path = wp_normalize_path(trailingslashit((string) get_stylesheet_directory()) . 'CHANGELOG.md');
        $map['stylesheet'] = array(
            'label' => modules_get_source_label('stylesheet'),
            'path'  => $stylesheet_path,
        );
    }

    return $map;
}

/**
 * Build a display-safe path for UI output.
 *
 * @param string $absolute_path Absolute filesystem path.
 * @return string
 */
function modules_get_display_path($absolute_path)
{
    $absolute_path = wp_normalize_path((string) $absolute_path);
    $root = wp_normalize_path(untrailingslashit((string) ABSPATH));

    if ('' !== $absolute_path && '' !== $root && 0 === strpos($absolute_path, $root . '/')) {
        return ltrim(substr($absolute_path, strlen($root)), '/');
    }

    return $absolute_path;
}

/**
 * Read changelog contents from filesystem.
 *
 * @param string $file_path Absolute file path.
 * @return string
 */
function modules_read_changelog_contents($file_path)
{
    $file_path = wp_normalize_path((string) $file_path);
    if ('' === $file_path || !file_exists($file_path) || !is_readable($file_path)) {
        return '';
    }

    $contents = file_get_contents($file_path);
    if (!is_string($contents)) {
        return '';
    }

    $contents = str_replace("\r\n", "\n", $contents);
    return trim($contents);
}

/**
 * Render Changelog tab content.
 *
 * @return void
 */
function modules_render_changelog_tab()
{
    $file_map = modules_get_changelog_file_map();

    echo '<h2>' . esc_html__('Changelog', 'lonestar-theme') . '</h2>';
    echo '<p>' . esc_html__('This tab shows CHANGELOG files for active parent and child themes.', 'lonestar-theme') . '</p>';

    foreach ($file_map as $source => $entry) {
        $source = sanitize_key((string) $source);
        $label = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : modules_get_source_label($source);
        $file_path = isset($entry['path']) ? wp_normalize_path((string) $entry['path']) : '';
        $display_path = modules_get_display_path($file_path);
        $file_exists = ('' !== $file_path && file_exists($file_path));
        $file_readable = ('' !== $file_path && is_readable($file_path));
        $file_contents = ($file_exists && $file_readable) ? modules_read_changelog_contents($file_path) : '';
        $mtime = ($file_exists && $file_readable) ? filemtime($file_path) : false;

        echo '<h3>' . esc_html($label) . '</h3>';
        echo '<p><code>' . esc_html($display_path) . '</code>';
        if (false !== $mtime) {
            echo '<br /><span class="description">' . esc_html(sprintf(__('Last updated: %s', 'lonestar-theme'), wp_date('Y-m-d H:i:s', (int) $mtime))) . '</span>';
        }
        echo '</p>';

        if (!$file_exists) {
            echo '<p class="description">' . esc_html__('CHANGELOG.md not found for this theme source.', 'lonestar-theme') . '</p>';
            continue;
        }
        if (!$file_readable) {
            echo '<p class="description">' . esc_html__('CHANGELOG.md is not readable.', 'lonestar-theme') . '</p>';
            continue;
        }

        if ('' === $file_contents) {
            echo '<p class="description">' . esc_html__('CHANGELOG.md is currently empty.', 'lonestar-theme') . '</p>';
            continue;
        }

        echo '<textarea readonly="readonly" style="width:100%;max-width:1200px;min-height:360px;font-family:monospace;line-height:1.4;">' . esc_textarea($file_contents) . '</textarea>';
    }
}

/**
 * Return parent theme repository URL.
 *
 * @return string
 */
function modules_get_parent_repository_url()
{
    $default_url = 'https://github.com/blonestar/wp-lonestar';

    /**
     * Filter parent repository URL shown in Theme Settings > About.
     *
     * @param string $default_url Default repository URL.
     */
    $repository_url = apply_filters('lonestar_parent_repository_url', $default_url);
    if (!is_string($repository_url)) {
        return $default_url;
    }

    $repository_url = trim($repository_url);
    if ('' === $repository_url) {
        return '';
    }

    return esc_url_raw($repository_url);
}

/**
 * Render About tab content.
 *
 * @return void
 */
function modules_render_about_tab()
{
    $template_slug = (string) get_template();
    $stylesheet_slug = (string) get_stylesheet();
    $template_theme = wp_get_theme($template_slug);
    $stylesheet_theme = wp_get_theme($stylesheet_slug);
    $is_child_theme = ($stylesheet_slug !== $template_slug);

    $template_name = ($template_theme instanceof \WP_Theme) ? (string) $template_theme->get('Name') : $template_slug;
    $template_version = ($template_theme instanceof \WP_Theme) ? (string) $template_theme->get('Version') : '';
    $template_author = ($template_theme instanceof \WP_Theme) ? wp_strip_all_tags((string) $template_theme->get('Author')) : '';
    $template_description = ($template_theme instanceof \WP_Theme) ? (string) $template_theme->get('Description') : '';
    $template_theme_uri = ($template_theme instanceof \WP_Theme) ? esc_url_raw((string) $template_theme->get('ThemeURI')) : '';
    $template_author_uri = ($template_theme instanceof \WP_Theme) ? esc_url_raw((string) $template_theme->get('AuthorURI')) : '';
    $template_text_domain = ($template_theme instanceof \WP_Theme) ? (string) $template_theme->get('TextDomain') : '';
    $template_requires_wp = ($template_theme instanceof \WP_Theme) ? (string) $template_theme->get('RequiresWP') : '';
    $template_requires_php = ($template_theme instanceof \WP_Theme) ? (string) $template_theme->get('RequiresPHP') : '';
    $template_path = wp_normalize_path((string) get_template_directory());
    $repository_url = modules_get_parent_repository_url();

    $active_name = ($stylesheet_theme instanceof \WP_Theme) ? (string) $stylesheet_theme->get('Name') : $stylesheet_slug;
    $active_version = ($stylesheet_theme instanceof \WP_Theme) ? (string) $stylesheet_theme->get('Version') : '';
    $active_author = ($stylesheet_theme instanceof \WP_Theme) ? wp_strip_all_tags((string) $stylesheet_theme->get('Author')) : '';
    $active_description = ($stylesheet_theme instanceof \WP_Theme) ? (string) $stylesheet_theme->get('Description') : '';
    $active_theme_uri = ($stylesheet_theme instanceof \WP_Theme) ? esc_url_raw((string) $stylesheet_theme->get('ThemeURI')) : '';
    $active_author_uri = ($stylesheet_theme instanceof \WP_Theme) ? esc_url_raw((string) $stylesheet_theme->get('AuthorURI')) : '';
    $active_text_domain = ($stylesheet_theme instanceof \WP_Theme) ? (string) $stylesheet_theme->get('TextDomain') : '';
    $active_requires_wp = ($stylesheet_theme instanceof \WP_Theme) ? (string) $stylesheet_theme->get('RequiresWP') : '';
    $active_requires_php = ($stylesheet_theme instanceof \WP_Theme) ? (string) $stylesheet_theme->get('RequiresPHP') : '';
    $active_path = wp_normalize_path((string) get_stylesheet_directory());

    echo '<h2>' . esc_html__('About', 'lonestar-theme') . '</h2>';
    echo '<p>' . esc_html__('Overview of active theme stack and framework context.', 'lonestar-theme') . '</p>';

    echo '<p><strong>' . esc_html__('Theme Mode:', 'lonestar-theme') . '</strong> ' . esc_html($is_child_theme ? __('Child Theme Active', 'lonestar-theme') : __('Parent Theme Only', 'lonestar-theme')) . '</p>';

    echo '<h3>' . esc_html__('Child Theme', 'lonestar-theme') . '</h3>';
    echo '<table class="widefat striped" style="max-width: 1200px;"><tbody>';
    if ($is_child_theme) {
        echo '<tr><th style="width:260px;">' . esc_html__('Name', 'lonestar-theme') . '</th><td><strong>' . esc_html($active_name) . '</strong></td></tr>';
        echo '<tr><th>' . esc_html__('Version', 'lonestar-theme') . '</th><td>' . ('' !== $active_version ? '<code>' . esc_html($active_version) . '</code>' : '&mdash;') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Author', 'lonestar-theme') . '</th><td>' . ('' !== $active_author ? esc_html($active_author) : '&mdash;') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Description', 'lonestar-theme') . '</th><td>' . ('' !== $active_description ? esc_html($active_description) : '&mdash;') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Text Domain', 'lonestar-theme') . '</th><td>' . ('' !== $active_text_domain ? '<code>' . esc_html($active_text_domain) . '</code>' : '&mdash;') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Requires', 'lonestar-theme') . '</th><td>';
        echo ('' !== $active_requires_wp ? esc_html(sprintf(__('WP %s+', 'lonestar-theme'), $active_requires_wp)) : esc_html__('WP n/a', 'lonestar-theme'));
        echo ' / ';
        echo ('' !== $active_requires_php ? esc_html(sprintf(__('PHP %s+', 'lonestar-theme'), $active_requires_php)) : esc_html__('PHP n/a', 'lonestar-theme'));
        echo '</td></tr>';
        echo '<tr><th>' . esc_html__('Theme URI', 'lonestar-theme') . '</th><td>';
        if ('' !== $active_theme_uri) {
            echo '<a href="' . esc_url($active_theme_uri) . '" target="_blank" rel="noopener noreferrer">' . esc_html($active_theme_uri) . '</a>';
        } else {
            echo '&mdash;';
        }
        echo '</td></tr>';
        echo '<tr><th>' . esc_html__('Author URI', 'lonestar-theme') . '</th><td>';
        if ('' !== $active_author_uri) {
            echo '<a href="' . esc_url($active_author_uri) . '" target="_blank" rel="noopener noreferrer">' . esc_html($active_author_uri) . '</a>';
        } else {
            echo '&mdash;';
        }
        echo '</td></tr>';
        echo '<tr><th>' . esc_html__('Slug', 'lonestar-theme') . '</th><td><code>' . esc_html($stylesheet_slug) . '</code></td></tr>';
        echo '<tr><th>' . esc_html__('Path', 'lonestar-theme') . '</th><td><code>' . esc_html(modules_get_display_path($active_path)) . '</code></td></tr>';
    } else {
        echo '<tr><th style="width:260px;">' . esc_html__('Status', 'lonestar-theme') . '</th><td>' . esc_html__('No child theme is active.', 'lonestar-theme') . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3 style="margin-top:20px;">' . esc_html__('Parent Theme', 'lonestar-theme') . '</h3>';
    echo '<table class="widefat striped" style="max-width: 1200px;"><tbody>';
    echo '<tr><th style="width:260px;">' . esc_html__('Name', 'lonestar-theme') . '</th><td><strong>' . esc_html($template_name) . '</strong></td></tr>';
    echo '<tr><th>' . esc_html__('Version', 'lonestar-theme') . '</th><td>' . ('' !== $template_version ? '<code>' . esc_html($template_version) . '</code>' : '&mdash;') . '</td></tr>';
    echo '<tr><th>' . esc_html__('Author', 'lonestar-theme') . '</th><td>' . ('' !== $template_author ? esc_html($template_author) : '&mdash;') . '</td></tr>';
    echo '<tr><th>' . esc_html__('Description', 'lonestar-theme') . '</th><td>' . ('' !== $template_description ? esc_html($template_description) : '&mdash;') . '</td></tr>';
    echo '<tr><th>' . esc_html__('Text Domain', 'lonestar-theme') . '</th><td>' . ('' !== $template_text_domain ? '<code>' . esc_html($template_text_domain) . '</code>' : '&mdash;') . '</td></tr>';
    echo '<tr><th>' . esc_html__('Requires', 'lonestar-theme') . '</th><td>';
    echo ('' !== $template_requires_wp ? esc_html(sprintf(__('WP %s+', 'lonestar-theme'), $template_requires_wp)) : esc_html__('WP n/a', 'lonestar-theme'));
    echo ' / ';
    echo ('' !== $template_requires_php ? esc_html(sprintf(__('PHP %s+', 'lonestar-theme'), $template_requires_php)) : esc_html__('PHP n/a', 'lonestar-theme'));
    echo '</td></tr>';
    echo '<tr><th>' . esc_html__('Theme URI', 'lonestar-theme') . '</th><td>';
    if ('' !== $template_theme_uri) {
        echo '<a href="' . esc_url($template_theme_uri) . '" target="_blank" rel="noopener noreferrer">' . esc_html($template_theme_uri) . '</a>';
    } else {
        echo '&mdash;';
    }
    echo '</td></tr>';
    echo '<tr><th>' . esc_html__('Author URI', 'lonestar-theme') . '</th><td>';
    if ('' !== $template_author_uri) {
        echo '<a href="' . esc_url($template_author_uri) . '" target="_blank" rel="noopener noreferrer">' . esc_html($template_author_uri) . '</a>';
    } else {
        echo '&mdash;';
    }
    echo '</td></tr>';
    echo '<tr><th>' . esc_html__('Slug', 'lonestar-theme') . '</th><td><code>' . esc_html($template_slug) . '</code></td></tr>';
    echo '<tr><th>' . esc_html__('Path', 'lonestar-theme') . '</th><td><code>' . esc_html(modules_get_display_path($template_path)) . '</code></td></tr>';
    echo '<tr><th>' . esc_html__('Repository', 'lonestar-theme') . '</th><td>';
    if ('' !== $repository_url) {
        echo '<a href="' . esc_url($repository_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($repository_url) . '</a>';
    } else {
        echo '&mdash;';
    }
    echo '</td></tr>';
    echo '</tbody></table>';

}

/**
 * Handle Theme Settings form submit before admin output starts.
 *
 * @return void
 */
function modules_handle_modules_admin_post()
{
    if (!is_admin()) {
        return;
    }

    if ('POST' !== strtoupper((string) $_SERVER['REQUEST_METHOD'])) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if (!modules_is_settings_page_slug($page)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('lonestar_save_theme_settings', 'lonestar_theme_settings_nonce');

    $submitted_tab = isset($_POST['lonestar_settings_tab']) ? (string) wp_unslash($_POST['lonestar_settings_tab']) : null;
    $current_tab = modules_get_current_settings_tab($submitted_tab);
    $is_updated = false;

    if ('modules' === $current_tab) {
        $module_catalog = modules_get_module_catalog();
        $selected_modules = isset($_POST['lonestar_modules']) && is_array($_POST['lonestar_modules']) ? wp_unslash($_POST['lonestar_modules']) : array();
        $selected_modules = array_values(array_unique(array_map('sanitize_key', $selected_modules)));
        $override_state = function_exists('modules_get_module_override_state') ? modules_get_module_override_state($module_catalog) : array();
        $overridden_lookup = (is_array($override_state) && isset($override_state['overridden_by_key']) && is_array($override_state['overridden_by_key']))
            ? $override_state['overridden_by_key']
            : array();

        $module_toggle_map = array();
        foreach (array_keys($module_catalog) as $module_key) {
            if (isset($overridden_lookup[$module_key])) {
                $module_toggle_map[$module_key] = false;
                continue;
            }

            $module_toggle_map[$module_key] = in_array($module_key, $selected_modules, true);
        }
        update_option(LONESTAR_MODULE_TOGGLE_OPTION, $module_toggle_map, false);
        $is_updated = true;
    } elseif ('blocks' === $current_tab) {
        $block_catalog = function_exists('lonestar_get_block_catalog') ? lonestar_get_block_catalog() : array();
        $selected_blocks = isset($_POST['lonestar_blocks']) && is_array($_POST['lonestar_blocks']) ? wp_unslash($_POST['lonestar_blocks']) : array();
        $selected_blocks = array_values(array_unique(array_map('sanitize_key', $selected_blocks)));
        $override_state = function_exists('lonestar_get_block_override_state') ? lonestar_get_block_override_state($block_catalog) : array();
        $overridden_lookup = (is_array($override_state) && isset($override_state['overridden_by_key']) && is_array($override_state['overridden_by_key']))
            ? $override_state['overridden_by_key']
            : array();

        $block_toggle_map = array();
        foreach (array_keys($block_catalog) as $block_key) {
            if (isset($overridden_lookup[$block_key])) {
                $block_toggle_map[$block_key] = false;
                continue;
            }

            $block_toggle_map[$block_key] = in_array($block_key, $selected_blocks, true);
        }
        update_option(LONESTAR_BLOCK_TOGGLE_OPTION, $block_toggle_map, false);
        $is_updated = true;
    }

    /**
     * Allow custom tabs to process their payload.
     *
     * @param string $current_tab Active tab key.
     */
    do_action('lonestar_theme_settings_handle_tab_post', $current_tab);

    $redirect_args = array(
        'page' => modules_get_settings_page_slug(),
        'tab'  => $current_tab,
    );
    if ($is_updated) {
        $redirect_args['updated'] = '1';
    }
    wp_safe_redirect(
        add_query_arg(
            $redirect_args,
            admin_url('themes.php')
        )
    );
    exit;
}

/**
 * Group catalog items by source.
 *
 * @param array<string,array> $catalog Catalog keyed by item key.
 * @return array<string,array<string,array>>
 */
function modules_group_catalog_by_source($catalog)
{
    $groups = array(
        'template'   => array(),
        'stylesheet' => array(),
    );

    if (!is_array($catalog)) {
        return $groups;
    }

    foreach ($catalog as $item_key => $item) {
        if (!is_array($item)) {
            continue;
        }

        $source = isset($item['source']) ? sanitize_key((string) $item['source']) : 'template';
        if ('' === $source) {
            $source = 'template';
        }
        if (!isset($groups[$source])) {
            $groups[$source] = array();
        }

        $groups[$source][$item_key] = $item;
    }

    return $groups;
}

/**
 * Render a module table.
 *
 * @param array<string,array> $catalog Module catalog section.
 * @param array<int,string> $enabled_keys Enabled module keys.
 * @param string $source_label Source label.
 * @param array{
 *   overridden_by_key?:array<string,string>
 * } $override_state Module override state.
 * @return void
 */
function modules_render_module_table($catalog, $enabled_keys, $source_label, $override_state = array())
{
    if (!is_array($catalog) || empty($catalog)) {
        echo '<p>' . esc_html__('No modules found for this source.', 'lonestar-theme') . '</p>';
        return;
    }

    $overridden_lookup = (is_array($override_state) && isset($override_state['overridden_by_key']) && is_array($override_state['overridden_by_key']))
        ? $override_state['overridden_by_key']
        : array();
    $full_catalog = modules_get_module_catalog();

    echo '<h3>' . esc_html($source_label) . '</h3>';
    echo '<table class="widefat striped" style="max-width: 1200px;">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Enabled', 'lonestar-theme') . '</th>';
    echo '<th>' . esc_html__('Module', 'lonestar-theme') . '</th>';
    echo '<th>' . esc_html__('Description', 'lonestar-theme') . '</th>';
    echo '<th style="width:140px;">' . esc_html__('Version', 'lonestar-theme') . '</th>';
    echo '<th style="width:180px;">' . esc_html__('Author', 'lonestar-theme') . '</th>';
    echo '<th style="width:120px;">' . esc_html__('Type', 'lonestar-theme') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($catalog as $module_key => $module) {
        $module_key = sanitize_key((string) $module_key);
        if ('' === $module_key || !is_array($module)) {
            continue;
        }

        $module_slug = isset($module['slug']) ? sanitize_key((string) $module['slug']) : modules_get_module_slug_from_key($module_key);
        $mode = isset($module['mode']) ? (string) $module['mode'] : 'file';
        $label = isset($module['label']) ? sanitize_text_field((string) $module['label']) : modules_module_label_from_slug($module_slug);
        $description = isset($module['description']) ? sanitize_text_field((string) $module['description']) : '';
        $version = isset($module['version']) ? sanitize_text_field((string) $module['version']) : '';
        $author = isset($module['author']) ? sanitize_text_field((string) $module['author']) : '';
        $admin_links = isset($module['admin_links']) && is_array($module['admin_links']) ? $module['admin_links'] : array();
        $is_enabled = in_array($module_key, $enabled_keys, true);
        $is_overridden = isset($overridden_lookup[$module_key]);
        $overriding_module_key = $is_overridden ? sanitize_key((string) $overridden_lookup[$module_key]) : '';
        $overriding_source = '';

        if ('' !== $overriding_module_key && isset($full_catalog[$overriding_module_key]) && is_array($full_catalog[$overriding_module_key])) {
            $overriding_source = isset($full_catalog[$overriding_module_key]['source'])
                ? sanitize_key((string) $full_catalog[$overriding_module_key]['source'])
                : modules_get_module_source_from_key($overriding_module_key);
        }

        if ('' === $description) {
            $description = sprintf(__('Module: %s', 'lonestar-theme'), $label);
        }

        echo '<tr>';
        echo '<td style="width: 90px;">';
        echo '<label for="' . esc_attr('lonestar-module-' . $module_key) . '" class="screen-reader-text">' . esc_html($label) . '</label>';
        echo '<input id="' . esc_attr('lonestar-module-' . $module_key) . '" type="checkbox" name="lonestar_modules[]" value="' . esc_attr($module_key) . '"' . checked($is_enabled && !$is_overridden, true, false) . disabled($is_overridden, true, false) . ' />';
        echo '</td>';

        echo '<td>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<code>' . esc_html($module_slug) . '</code>';
        if ($is_overridden) {
            $source_label_text = ('' !== $overriding_source) ? modules_get_source_label($overriding_source) : __('Child Theme', 'lonestar-theme');
            echo '<br /><span class="description">' . esc_html(sprintf(__('Overridden by %s.', 'lonestar-theme'), $source_label_text)) . '</span>';
        }

        if ($is_enabled && !$is_overridden && !empty($admin_links)) {
            $visible_links = array();
            foreach ($admin_links as $admin_link) {
                if (!is_array($admin_link)) {
                    continue;
                }

                $link_url = isset($admin_link['url']) ? esc_url((string) $admin_link['url']) : '';
                if ('' === $link_url) {
                    continue;
                }

                $link_label = isset($admin_link['label']) ? sanitize_text_field((string) $admin_link['label']) : __('Settings', 'lonestar-theme');
                if ('' === $link_label) {
                    $link_label = __('Settings', 'lonestar-theme');
                }

                $visible_links[] = array(
                    'label' => $link_label,
                    'url'   => $link_url,
                );
            }

            if (!empty($visible_links)) {
                echo '<br />';
                foreach ($visible_links as $index => $admin_link) {
                    echo '<a href="' . esc_url($admin_link['url']) . '">' . esc_html($admin_link['label']) . '</a>';
                    if ($index < (count($visible_links) - 1)) {
                        echo ' | ';
                    }
                }
            }
        }
        echo '</td>';

        echo '<td>' . esc_html($description) . '</td>';
        echo '<td>' . ('' !== $version ? esc_html($version) : '&mdash;') . '</td>';
        echo '<td>' . ('' !== $author ? esc_html($author) : '&mdash;') . '</td>';
        echo '<td>' . esc_html('folder' === $mode ? __('Folder', 'lonestar-theme') : __('File', 'lonestar-theme')) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * Render a block table.
 *
 * @param array<string,array> $catalog Block catalog section.
 * @param array<int,string> $enabled_keys Enabled block keys.
 * @param string $source_label Source label.
 * @param array{
 *   overridden_by_key?:array<string,string>
 * } $override_state Block override state.
 * @return void
 */
function modules_render_block_table($catalog, $enabled_keys, $source_label, $override_state = array())
{
    if (!is_array($catalog) || empty($catalog)) {
        echo '<p>' . esc_html__('No blocks found for this source.', 'lonestar-theme') . '</p>';
        return;
    }

    $overridden_lookup = (is_array($override_state) && isset($override_state['overridden_by_key']) && is_array($override_state['overridden_by_key']))
        ? $override_state['overridden_by_key']
        : array();
    $full_catalog = function_exists('lonestar_get_block_catalog') ? lonestar_get_block_catalog() : array();

    echo '<h3>' . esc_html($source_label) . '</h3>';
    echo '<table class="widefat striped" style="max-width: 1200px;">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Enabled', 'lonestar-theme') . '</th>';
    echo '<th>' . esc_html__('Block', 'lonestar-theme') . '</th>';
    echo '<th style="width:120px;">' . esc_html__('Type', 'lonestar-theme') . '</th>';
    echo '<th>' . esc_html__('Path', 'lonestar-theme') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($catalog as $block_key => $block) {
        $block_key = sanitize_key((string) $block_key);
        if ('' === $block_key || !is_array($block)) {
            continue;
        }

        $label = isset($block['label']) ? sanitize_text_field((string) $block['label']) : __('Unknown Block', 'lonestar-theme');
        $name = isset($block['name']) ? sanitize_text_field((string) $block['name']) : '';
        $type = isset($block['type']) ? sanitize_key((string) $block['type']) : 'unknown';
        $relative_path = isset($block['relative_path']) ? sanitize_text_field((string) $block['relative_path']) : '';
        $is_enabled = in_array($block_key, $enabled_keys, true);
        $is_overridden = isset($overridden_lookup[$block_key]);
        $overriding_block_key = $is_overridden ? sanitize_key((string) $overridden_lookup[$block_key]) : '';
        $overriding_source = '';

        if ('' !== $overriding_block_key) {
            if (isset($full_catalog[$overriding_block_key]) && is_array($full_catalog[$overriding_block_key]) && isset($full_catalog[$overriding_block_key]['source'])) {
                $overriding_source = sanitize_key((string) $full_catalog[$overriding_block_key]['source']);
            }
        }

        echo '<tr>';
        echo '<td style="width: 90px;">';
        echo '<label for="' . esc_attr('lonestar-block-' . $block_key) . '" class="screen-reader-text">' . esc_html($label) . '</label>';
        echo '<input id="' . esc_attr('lonestar-block-' . $block_key) . '" type="checkbox" name="lonestar_blocks[]" value="' . esc_attr($block_key) . '"' . checked($is_enabled && !$is_overridden, true, false) . disabled($is_overridden, true, false) . ' />';
        echo '</td>';

        echo '<td>';
        echo '<strong>' . esc_html($label) . '</strong>';
        if ('' !== $name) {
            echo '<br /><code>' . esc_html($name) . '</code>';
        }
        if ($is_overridden) {
            $source_label_text = ('' !== $overriding_source) ? modules_get_source_label($overriding_source) : __('Child Theme', 'lonestar-theme');
            echo '<br /><span class="description">' . esc_html(sprintf(__('Overridden by %s.', 'lonestar-theme'), $source_label_text)) . '</span>';
        }
        echo '</td>';

        if ('acf' === $type) {
            echo '<td>' . esc_html__('ACF', 'lonestar-theme') . '</td>';
        } elseif ('native' === $type) {
            echo '<td>' . esc_html__('Native', 'lonestar-theme') . '</td>';
        } else {
            echo '<td>' . esc_html__('Unknown', 'lonestar-theme') . '</td>';
        }

        echo '<td><code>' . esc_html($relative_path) . '</code></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * Render Theme Settings admin page.
 *
 * @return void
 */
function modules_render_modules_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $tabs = modules_get_settings_tabs();
    $current_tab = modules_get_current_settings_tab();

    $module_groups = array(
        'template'   => array(),
        'stylesheet' => array(),
    );
    $enabled_module_keys = array();
    $module_override_state = array();

    if ('modules' === $current_tab) {
        $module_catalog = modules_get_module_catalog();
        $enabled_module_keys = modules_get_enabled_module_keys(array_keys($module_catalog));
        $module_groups = modules_group_catalog_by_source($module_catalog);
        $module_override_state = function_exists('modules_get_module_override_state') ? modules_get_module_override_state($module_catalog) : array();
    }

    $block_groups = array(
        'template'   => array(),
        'stylesheet' => array(),
    );
    $enabled_block_keys = array();
    $block_override_state = array();

    if ('blocks' === $current_tab) {
        $block_catalog = function_exists('lonestar_get_block_catalog') ? lonestar_get_block_catalog() : array();
        $enabled_block_keys = function_exists('lonestar_get_enabled_block_keys') ? lonestar_get_enabled_block_keys(array_keys($block_catalog)) : array();
        $block_groups = modules_group_catalog_by_source($block_catalog);
        $block_override_state = function_exists('lonestar_get_block_override_state') ? lonestar_get_block_override_state($block_catalog) : array();
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Theme Settings', 'lonestar-theme'); ?></h1>
        <p><?php echo esc_html__('Enable or disable modules and blocks. Changes take effect immediately after save.', 'lonestar-theme'); ?></p>

        <?php $is_updated = (isset($_GET['updated']) && '1' === sanitize_text_field((string) wp_unslash($_GET['updated']))); ?>
        <?php if ($is_updated) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Theme settings updated.', 'lonestar-theme'); ?></p></div>
        <?php endif; ?>

        <h2 class="nav-tab-wrapper" style="margin-bottom: 16px;">
            <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                <?php
                $tab_class = 'nav-tab';
                if ($current_tab === $tab_key) {
                    $tab_class .= ' nav-tab-active';
                }
                ?>
                <a href="<?php echo esc_url(modules_get_settings_tab_url($tab_key)); ?>" class="<?php echo esc_attr($tab_class); ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <?php if ('changelog' === $current_tab) : ?>
            <?php modules_render_changelog_tab(); ?>
        <?php elseif ('about' === $current_tab) : ?>
            <?php modules_render_about_tab(); ?>
        <?php else : ?>
            <form method="post" action="">
                <?php wp_nonce_field('lonestar_save_theme_settings', 'lonestar_theme_settings_nonce'); ?>
                <input type="hidden" name="lonestar_settings_tab" value="<?php echo esc_attr($current_tab); ?>" />

                <?php if ('modules' === $current_tab) : ?>
                    <h2><?php echo esc_html__('Modules', 'lonestar-theme'); ?></h2>
                    <?php modules_render_module_table($module_groups['template'], $enabled_module_keys, modules_get_source_label('template'), $module_override_state); ?>
                    <?php modules_render_module_table($module_groups['stylesheet'], $enabled_module_keys, modules_get_source_label('stylesheet'), $module_override_state); ?>
                <?php elseif ('blocks' === $current_tab) : ?>
                    <h2><?php echo esc_html__('Blocks', 'lonestar-theme'); ?></h2>
                    <?php modules_render_block_table($block_groups['template'], $enabled_block_keys, modules_get_source_label('template'), $block_override_state); ?>
                    <?php modules_render_block_table($block_groups['stylesheet'], $enabled_block_keys, modules_get_source_label('stylesheet'), $block_override_state); ?>
                <?php else : ?>
                    <?php do_action('lonestar_render_theme_settings_tab', $current_tab); ?>
                <?php endif; ?>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php
                        if ('modules' === $current_tab) {
                            echo esc_html__('Save Modules', 'lonestar-theme');
                        } elseif ('blocks' === $current_tab) {
                            echo esc_html__('Save Blocks', 'lonestar-theme');
                        } else {
                            echo esc_html__('Save Settings', 'lonestar-theme');
                        }
                        ?>
                    </button>
                </p>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Flush module- and block-related caches.
 *
 * @param mixed $context_a Optional hook context value.
 * @param mixed $context_b Optional hook context value.
 * @return void
 */
function modules_flush_module_related_caches($context_a = null, $context_b = null)
{
    unset($context_a, $context_b);

    delete_transient(modules_get_module_catalog_transient_key());

    if (function_exists('lonestar_flush_block_discovery_caches')) {
        lonestar_flush_block_discovery_caches();
    }

    $cache_namespace = function_exists('lonestar_get_theme_cache_namespace') ? lonestar_get_theme_cache_namespace() : 'default';
    delete_transient('lonestar_vite_dev_probe_' . $cache_namespace);
}

/**
 * Flush caches when module/block toggle option changes.
 *
 * @param mixed $old_value Old option value.
 * @param mixed $value New option value.
 * @param string $option Option name.
 * @return void
 */
function modules_handle_module_toggle_option_update($old_value, $value, $option)
{
    unset($old_value, $value, $option);
    modules_flush_module_related_caches();
}
