<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module admin UI and cache invalidation hooks.
 */

function modules_register_modules_admin_page()
{
    add_theme_page(
        __('Theme Modules', 'lonestar-theme'),
        __('Theme Modules', 'lonestar-theme'),
        'manage_options',
        'lonestar-theme-modules',
        'modules_render_modules_admin_page'
    );
}

/**
 * Handle Theme Modules form submit before admin output starts.
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
    if ('lonestar-theme-modules' !== $page) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('lonestar_save_modules', 'lonestar_modules_nonce');

    $catalog = modules_get_module_catalog();
    $selected = isset($_POST['lonestar_modules']) && is_array($_POST['lonestar_modules']) ? wp_unslash($_POST['lonestar_modules']) : array();
    $selected = array_values(array_unique(array_map('sanitize_key', $selected)));

    $toggle_map = array();
    foreach (array_keys($catalog) as $slug) {
        $toggle_map[$slug] = in_array($slug, $selected, true);
    }

    update_option(LONESTAR_MODULE_TOGGLE_OPTION, $toggle_map, false);

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'    => 'lonestar-theme-modules',
                'updated' => '1',
            ),
            admin_url('themes.php')
        )
    );
    exit;
}

/**
 * Render Theme Modules admin page and handle save action.
 *
 * @return void
 */
function modules_render_modules_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $catalog = modules_get_module_catalog();
    $enabled_slugs = modules_get_enabled_module_slugs(array_keys($catalog));
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Theme Modules', 'lonestar-theme'); ?></h1>
        <p><?php echo esc_html__('Enable or disable individual modules. Changes take effect immediately after save.', 'lonestar-theme'); ?></p>

        <?php $is_updated = (isset($_GET['updated']) && '1' === sanitize_text_field((string) wp_unslash($_GET['updated']))); ?>
        <?php if ($is_updated) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Module settings updated.', 'lonestar-theme'); ?></p></div>
        <?php endif; ?>

        <?php if (empty($catalog)) : ?>
            <p><?php echo esc_html__('No modules found in /modules.', 'lonestar-theme'); ?></p>
        <?php else : ?>
            <form method="post" action="">
                <?php wp_nonce_field('lonestar_save_modules', 'lonestar_modules_nonce'); ?>
                <table class="widefat striped" style="max-width: 960px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Enabled', 'lonestar-theme'); ?></th>
                            <th><?php echo esc_html__('Module', 'lonestar-theme'); ?></th>
                            <th><?php echo esc_html__('Description', 'lonestar-theme'); ?></th>
                            <th style="width:120px;"><?php echo esc_html__('Type', 'lonestar-theme'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catalog as $slug => $module) : ?>
                            <?php
                            $is_enabled = in_array($slug, $enabled_slugs, true);
                            $mode = isset($module['mode']) ? (string) $module['mode'] : 'file';
                            $label = isset($module['label']) ? (string) $module['label'] : modules_module_label_from_slug($slug);
                            $description = isset($module['description']) ? (string) $module['description'] : '';
                            $admin_links = isset($module['admin_links']) && is_array($module['admin_links']) ? $module['admin_links'] : array();
                            if ('' === trim($description)) {
                                $description = sprintf(__('Module: %s', 'lonestar-theme'), $label);
                            }
                            ?>
                            <tr>
                                <td style="width: 90px;">
                                    <label for="<?php echo esc_attr('lonestar-module-' . $slug); ?>" class="screen-reader-text"><?php echo esc_html($label); ?></label>
                                    <input
                                        id="<?php echo esc_attr('lonestar-module-' . $slug); ?>"
                                        type="checkbox"
                                        name="lonestar_modules[]"
                                        value="<?php echo esc_attr($slug); ?>"
                                        <?php checked($is_enabled); ?>
                                    />
                                </td>
                                <td>
                                    <strong><?php echo esc_html($label); ?></strong><br />
                                    <code><?php echo esc_html($slug); ?></code>
                                    <?php if ($is_enabled && !empty($admin_links)) : ?>
                                        <br />
                                        <?php
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
                                        ?>
                                        <?php foreach ($visible_links as $index => $admin_link) : ?>
                                            <a href="<?php echo esc_url($admin_link['url']); ?>"><?php echo esc_html($admin_link['label']); ?></a><?php if ($index < (count($visible_links) - 1)) { echo ' | '; } ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($description); ?></td>
                                <td><?php echo esc_html('folder' === $mode ? __('Folder', 'lonestar-theme') : __('File', 'lonestar-theme')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Modules', 'lonestar-theme'); ?></button>
                </p>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Flush module-related caches.
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
 * Flush caches when module toggle option changes.
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

