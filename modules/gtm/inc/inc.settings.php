<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('lonestar_theme_settings_tabs', 'lonestar_module_register_gtm_settings_tab');
add_action('lonestar_render_theme_settings_tab', 'lonestar_module_render_gtm_settings_tab');
add_action('lonestar_theme_settings_handle_tab_post', 'lonestar_module_handle_gtm_settings_post');

/**
 * Return GTM module option key.
 *
 * @return string
 */
function lonestar_module_get_gtm_settings_option_key()
{
    return 'lonestar_gtm_settings';
}

/**
 * Return transient key used for GTM settings notice.
 *
 * @return string
 */
function lonestar_module_get_gtm_settings_notice_key()
{
    return 'lonestar_gtm_settings_saved_' . get_current_user_id();
}

/**
 * Insert GTM tab into Theme Settings.
 *
 * @param mixed $tabs Existing tab map.
 * @return mixed
 */
function lonestar_module_register_gtm_settings_tab($tabs)
{
    if (!is_array($tabs)) {
        return $tabs;
    }

    if (isset($tabs['gtm'])) {
        return $tabs;
    }

    $updated_tabs = array();
    $is_inserted = false;

    foreach ($tabs as $tab_key => $tab_label) {
        $updated_tabs[$tab_key] = $tab_label;

        if ('blocks' === sanitize_key((string) $tab_key)) {
            $updated_tabs['gtm'] = __('GTM', 'lonestar-theme');
            $is_inserted = true;
        }
    }

    if (!$is_inserted) {
        $updated_tabs['gtm'] = __('GTM', 'lonestar-theme');
    }

    return $updated_tabs;
}

/**
 * Sanitize GTM settings payload.
 *
 * @param mixed $settings Raw settings payload.
 * @return array{enabled:bool,container_id:string}
 */
function lonestar_module_sanitize_gtm_settings($settings)
{
    if (!is_array($settings)) {
        $settings = array();
    }

    $enabled_raw = isset($settings['enabled']) ? $settings['enabled'] : false;
    $enabled = false;
    if (is_bool($enabled_raw)) {
        $enabled = $enabled_raw;
    } elseif (is_numeric($enabled_raw)) {
        $enabled = ((int) $enabled_raw) > 0;
    } elseif (is_string($enabled_raw)) {
        $enabled = in_array(strtolower(trim($enabled_raw)), array('1', 'true', 'yes', 'on'), true);
    }

    $container_raw = isset($settings['container_id']) ? $settings['container_id'] : '';
    $container_id = lonestar_module_normalize_gtm_container_id($container_raw);
    if ('' === $container_id) {
        $enabled = false;
    }

    return array(
        'enabled'      => $enabled,
        'container_id' => $container_id,
    );
}

/**
 * Read GTM settings from legacy ACF fields for one-time migration fallback.
 *
 * @return array{enabled:bool,container_id:string}
 */
function lonestar_module_get_gtm_settings_from_acf()
{
    if (!function_exists('get_field')) {
        return lonestar_module_sanitize_gtm_settings(array());
    }

    return lonestar_module_sanitize_gtm_settings(
        array(
            'enabled'      => get_field('gtm_enabled', 'option'),
            'container_id' => get_field('gtm_id', 'option'),
        )
    );
}

/**
 * Return normalized GTM settings from native option storage.
 *
 * Falls back to legacy ACF values when native option does not exist yet.
 *
 * @return array{enabled:bool,container_id:string}
 */
function lonestar_module_get_gtm_settings()
{
    static $cached_settings = null;
    if (is_array($cached_settings)) {
        return $cached_settings;
    }

    $option_key = lonestar_module_get_gtm_settings_option_key();
    $raw_settings = get_option($option_key, null);
    if (is_array($raw_settings)) {
        $cached_settings = lonestar_module_sanitize_gtm_settings($raw_settings);
        return $cached_settings;
    }

    $fallback_settings = lonestar_module_get_gtm_settings_from_acf();
    if ($fallback_settings['enabled'] || '' !== $fallback_settings['container_id']) {
        update_option($option_key, $fallback_settings, false);
    }

    $cached_settings = $fallback_settings;
    return $cached_settings;
}

/**
 * Handle GTM settings tab save request.
 *
 * @param string $current_tab Active settings tab.
 * @return void
 */
function lonestar_module_handle_gtm_settings_post($current_tab)
{
    if ('gtm' !== sanitize_key((string) $current_tab)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $raw_settings = (isset($_POST['lonestar_gtm']) && is_array($_POST['lonestar_gtm']))
        ? wp_unslash($_POST['lonestar_gtm'])
        : array();

    $sanitized_settings = lonestar_module_sanitize_gtm_settings($raw_settings);

    update_option(
        lonestar_module_get_gtm_settings_option_key(),
        $sanitized_settings,
        false
    );

    set_transient(lonestar_module_get_gtm_settings_notice_key(), '1', 120);
}

/**
 * Render GTM settings tab.
 *
 * @param string $current_tab Active settings tab.
 * @return void
 */
function lonestar_module_render_gtm_settings_tab($current_tab)
{
    if ('gtm' !== sanitize_key((string) $current_tab)) {
        return;
    }

    $settings = lonestar_module_get_gtm_settings();
    $is_enabled = isset($settings['enabled']) ? (bool) $settings['enabled'] : false;
    $container_id = isset($settings['container_id']) ? (string) $settings['container_id'] : '';

    $notice_key = lonestar_module_get_gtm_settings_notice_key();
    $is_saved = (bool) get_transient($notice_key);
    if ($is_saved) {
        delete_transient($notice_key);
    }

    echo '<h2>' . esc_html__('Google Tag Manager', 'lonestar-theme') . '</h2>';
    echo '<p>' . esc_html__('Configure GTM output for wp_head and wp_body_open hooks.', 'lonestar-theme') . '</p>';

    if ($is_saved) {
        echo '<div class="notice notice-success inline"><p>' . esc_html__('GTM settings updated.', 'lonestar-theme') . '</p></div>';
    }

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr>';
    echo '<th scope="row">' . esc_html__('Enable GTM', 'lonestar-theme') . '</th>';
    echo '<td>';
    echo '<label for="lonestar-gtm-enabled">';
    echo '<input id="lonestar-gtm-enabled" type="checkbox" name="lonestar_gtm[enabled]" value="1"' . checked($is_enabled, true, false) . ' />';
    echo ' ' . esc_html__('Output GTM script and noscript snippets.', 'lonestar-theme');
    echo '</label>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="lonestar-gtm-container-id">' . esc_html__('GTM Container ID', 'lonestar-theme') . '</label></th>';
    echo '<td>';
    echo '<input id="lonestar-gtm-container-id" type="text" class="regular-text" name="lonestar_gtm[container_id]" value="' . esc_attr($container_id) . '" placeholder="GTM-XXXXXX" />';
    echo '<p class="description">' . esc_html__('Example: GTM-ABC1234. Prefix is optional and normalized automatically. GTM remains disabled when ID is empty or invalid.', 'lonestar-theme') . '</p>';
    echo '</td>';
    echo '</tr>';

    echo '</tbody></table>';
}
