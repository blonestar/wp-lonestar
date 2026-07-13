<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parent theme updates backed by verified GitHub Release assets.
 */

if (!defined('LONESTAR_UPDATE_REPO')) {
    define('LONESTAR_UPDATE_REPO', 'blonestar/wp-lonestar');
}
if (!defined('LONESTAR_UPDATE_URI')) {
    define('LONESTAR_UPDATE_URI', 'https://github.com/blonestar/wp-lonestar');
}
if (!defined('LONESTAR_UPDATE_TAG_PREFIX')) {
    define('LONESTAR_UPDATE_TAG_PREFIX', 'lonestar-v');
}
if (!defined('LONESTAR_UPDATE_CACHE_TTL')) {
    define('LONESTAR_UPDATE_CACHE_TTL', 6 * HOUR_IN_SECONDS);
}
if (!defined('LONESTAR_UPDATE_ERROR_CACHE_TTL')) {
    define('LONESTAR_UPDATE_ERROR_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
}

add_filter('update_themes_github.com', 'lonestar_filter_parent_theme_update', 10, 4);
add_filter('themes_api', 'lonestar_provide_parent_theme_update_info', 10, 3);
add_filter('upgrader_pre_download', 'lonestar_verify_parent_theme_update_download', 10, 4);
add_filter('debug_information', 'lonestar_add_parent_update_site_health_info');
add_action('lonestar_theme_settings_about', 'lonestar_render_parent_update_status');
add_action('after_switch_theme', 'lonestar_flush_parent_theme_update_cache');
add_action('upgrader_process_complete', 'lonestar_flush_parent_theme_update_cache', 10, 2);

/**
 * Decide whether this parent installation may use WordPress updates.
 *
 * Release packages do not contain .git, while a development checkout does.
 * LONESTAR_ALLOW_UPDATES is an explicit escape hatch in either direction.
 *
 * @return bool
 */
function lonestar_parent_theme_updates_allowed()
{
    if (defined('LONESTAR_ALLOW_UPDATES')) {
        return (bool) LONESTAR_ALLOW_UPDATES;
    }

    return !file_exists(trailingslashit(get_template_directory()) . '.git');
}

/**
 * Explain the active update policy for diagnostics.
 *
 * @return string
 */
function lonestar_get_parent_theme_update_policy_label()
{
    if (defined('LONESTAR_ALLOW_UPDATES')) {
        return LONESTAR_ALLOW_UPDATES
            ? __('Enabled by LONESTAR_ALLOW_UPDATES.', 'lonestar')
            : __('Disabled by LONESTAR_ALLOW_UPDATES.', 'lonestar');
    }

    return lonestar_parent_theme_updates_allowed()
        ? __('Enabled for release installation.', 'lonestar')
        : __('Disabled for Git checkout.', 'lonestar');
}

/**
 * Return update cache key.
 *
 * @return string
 */
function lonestar_get_parent_theme_update_cache_key()
{
    return 'lonestar_parent_update_payload_v2';
}

/**
 * Clear update check cache.
 *
 * @param mixed $context_a Optional hook argument.
 * @param mixed $context_b Optional hook argument.
 * @return void
 */
function lonestar_flush_parent_theme_update_cache($context_a = null, $context_b = null)
{
    unset($context_a, $context_b);
    delete_site_transient(lonestar_get_parent_theme_update_cache_key());
}

/**
 * Cache one updater result, including failures for rate-limit protection.
 *
 * @param array<string,mixed> $result Result payload.
 * @param int $ttl Cache lifetime.
 * @return void
 */
function lonestar_cache_parent_theme_update_result($result, $ttl)
{
    $result = is_array($result) ? $result : array();
    $result['checked_at'] = gmdate('Y-m-d H:i:s');
    set_site_transient(lonestar_get_parent_theme_update_cache_key(), $result, max(MINUTE_IN_SECONDS, (int) $ttl));
}

/**
 * Cache a sanitized updater failure and return false.
 *
 * @param string $message Error message.
 * @return false
 */
function lonestar_cache_parent_theme_update_error($message)
{
    lonestar_cache_parent_theme_update_result(
        array(
            'success' => false,
            'error'   => sanitize_text_field((string) $message),
        ),
        LONESTAR_UPDATE_ERROR_CACHE_TTL
    );

    return false;
}

/**
 * Return cached updater status without making a network request.
 *
 * @return array<string,mixed>
 */
function lonestar_get_cached_parent_theme_update_status()
{
    $cached = get_site_transient(lonestar_get_parent_theme_update_cache_key());
    return is_array($cached) ? $cached : array();
}

/**
 * Read and strictly validate the latest stable GitHub release.
 *
 * @return array<string,mixed>|false
 */
function lonestar_get_latest_parent_release_payload()
{
    if (!lonestar_parent_theme_updates_allowed()) {
        return false;
    }

    $cached = lonestar_get_cached_parent_theme_update_status();
    if (!empty($cached)) {
        return !empty($cached['success']) ? $cached : false;
    }

    $repo = trim((string) LONESTAR_UPDATE_REPO, " \t\n\r\0\x0B/");
    if (1 !== preg_match('/^[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+$/', $repo)) {
        return lonestar_cache_parent_theme_update_error(__('Invalid GitHub repository configuration.', 'lonestar'));
    }

    $theme = wp_get_theme('lonestar');
    $headers = array(
        'Accept'               => 'application/vnd.github+json',
        'User-Agent'           => 'LonestarThemeUpdater/' . (string) $theme->get('Version'),
        'X-GitHub-Api-Version' => '2022-11-28',
    );
    if (defined('LONESTAR_GITHUB_TOKEN') && is_string(LONESTAR_GITHUB_TOKEN) && '' !== trim(LONESTAR_GITHUB_TOKEN)) {
        $headers['Authorization'] = 'Bearer ' . trim((string) LONESTAR_GITHUB_TOKEN);
    }

    $response = wp_remote_get(
        sprintf('https://api.github.com/repos/%s/releases/latest', $repo),
        array(
            'headers'             => $headers,
            'timeout'             => 10,
            'redirection'         => 2,
            'limit_response_size' => 1024 * 1024,
        )
    );

    if (is_wp_error($response)) {
        return lonestar_cache_parent_theme_update_error($response->get_error_message());
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if (200 !== $status_code) {
        /* translators: %d: HTTP response status code. */
        $status_message = sprintf(__('GitHub release API returned HTTP %d.', 'lonestar'), $status_code);
        return lonestar_cache_parent_theme_update_error($status_message);
    }

    $release = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($release)) {
        return lonestar_cache_parent_theme_update_error(__('GitHub returned invalid release JSON.', 'lonestar'));
    }
    if (!empty($release['draft']) || !empty($release['prerelease'])) {
        return lonestar_cache_parent_theme_update_error(__('Latest GitHub release is not stable.', 'lonestar'));
    }

    $tag_name = isset($release['tag_name']) ? sanitize_text_field((string) $release['tag_name']) : '';
    $tag_pattern = '/^' . preg_quote((string) LONESTAR_UPDATE_TAG_PREFIX, '/') . '(\d+\.\d+\.\d+)$/';
    if (1 !== preg_match($tag_pattern, $tag_name, $tag_match)) {
        return lonestar_cache_parent_theme_update_error(__('Release tag does not match lonestar-vX.Y.Z.', 'lonestar'));
    }

    $version = $tag_match[1];
    $expected_asset = 'lonestar-' . $version . '.zip';
    $matched_asset = null;
    $assets = isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : array();
    foreach ($assets as $asset) {
        if (is_array($asset) && isset($asset['name']) && $expected_asset === (string) $asset['name']) {
            $matched_asset = $asset;
            break;
        }
    }

    if (!is_array($matched_asset)) {
        return lonestar_cache_parent_theme_update_error(__('Release is missing the exact Lonestar ZIP asset.', 'lonestar'));
    }

    $package_url = isset($matched_asset['browser_download_url']) ? esc_url_raw((string) $matched_asset['browser_download_url']) : '';
    $digest_value = isset($matched_asset['digest']) ? strtolower(trim((string) $matched_asset['digest'])) : '';
    $asset_size = isset($matched_asset['size']) ? (int) $matched_asset['size'] : 0;
    if ('' === $package_url || 1 !== preg_match('/^sha256:([a-f0-9]{64})$/', $digest_value, $digest_match) || $asset_size < 1) {
        return lonestar_cache_parent_theme_update_error(__('Release asset URL, size, or SHA-256 digest is invalid.', 'lonestar'));
    }

    $payload = array(
        'success'       => true,
        'version'       => $version,
        'package_url'   => $package_url,
        'sha256'        => $digest_match[1],
        'asset_size'    => $asset_size,
        'release_url'   => esc_url_raw((string) ($release['html_url'] ?? '')),
        'release_body'  => (string) ($release['body'] ?? ''),
        'published_at'  => sanitize_text_field((string) ($release['published_at'] ?? '')),
    );
    lonestar_cache_parent_theme_update_result($payload, LONESTAR_UPDATE_CACHE_TTL);

    return array_merge($payload, array('checked_at' => gmdate('Y-m-d H:i:s')));
}

/**
 * Supply an update only for the canonical parent stylesheet.
 *
 * @param array|false $update Existing update data.
 * @param array $theme_data Theme headers.
 * @param string $theme_stylesheet Theme directory name.
 * @param array $locales Installed locales.
 * @return array|false
 */
function lonestar_filter_parent_theme_update($update, $theme_data, $theme_stylesheet, $locales)
{
    unset($update, $locales);
    if ('lonestar' !== sanitize_key((string) $theme_stylesheet)) {
        return false;
    }

    $payload = lonestar_get_latest_parent_release_payload();
    $current_version = isset($theme_data['Version']) ? (string) $theme_data['Version'] : '';
    if (!is_array($payload) || '' === $current_version || version_compare((string) $payload['version'], $current_version, '<=')) {
        return false;
    }

    return array(
        'id'           => LONESTAR_UPDATE_URI,
        'theme'        => 'lonestar',
        'version'      => (string) $payload['version'],
        'url'          => (string) $payload['release_url'],
        'package'      => (string) $payload['package_url'],
        'tested'       => '7.0',
        'requires_php' => isset($theme_data['RequiresPHP']) ? (string) $theme_data['RequiresPHP'] : '8.2',
    );
}

/**
 * Provide the Themes screen details modal.
 *
 * @param mixed $result Existing result.
 * @param string $action API action.
 * @param object $args API arguments.
 * @return mixed
 */
function lonestar_provide_parent_theme_update_info($result, $action, $args)
{
    if ('theme_information' !== (string) $action || !is_object($args) || 'lonestar' !== sanitize_key((string) ($args->slug ?? ''))) {
        return $result;
    }

    $payload = lonestar_get_latest_parent_release_payload();
    if (!is_array($payload)) {
        return $result;
    }

    $theme = wp_get_theme('lonestar');
    $info = new stdClass();
    $info->name = (string) $theme->get('Name');
    $info->slug = 'lonestar';
    $info->version = (string) $payload['version'];
    $info->author = (string) $theme->get('Author');
    $info->homepage = (string) $payload['release_url'];
    $info->requires = (string) $theme->get('RequiresWP');
    $info->tested = '7.0';
    $info->requires_php = (string) $theme->get('RequiresPHP');
    $info->download_link = (string) $payload['package_url'];
    $info->last_updated = (string) $payload['published_at'];
    $info->sections = array(
        'description' => wp_kses_post(wpautop((string) $theme->get('Description'))),
        'changelog'   => wp_kses_post(wpautop(esc_html((string) $payload['release_body']))),
    );

    return $info;
}

/**
 * Download and verify the exact parent update package before WordPress unzips it.
 *
 * @param false|string|WP_Error $reply Existing short-circuit value.
 * @param string $package Package URL.
 * @param WP_Upgrader $upgrader Upgrader instance.
 * @param array $hook_extra Upgrade context.
 * @return false|string|WP_Error
 */
function lonestar_verify_parent_theme_update_download($reply, $package, $upgrader, $hook_extra)
{
    unset($upgrader);
    if (false !== $reply || !is_array($hook_extra)) {
        return $reply;
    }

    $theme = isset($hook_extra['theme']) ? sanitize_key((string) $hook_extra['theme']) : '';
    $themes = isset($hook_extra['themes']) && is_array($hook_extra['themes']) ? array_map('sanitize_key', $hook_extra['themes']) : array();
    if ('lonestar' !== $theme && !in_array('lonestar', $themes, true)) {
        return false;
    }

    if (!lonestar_parent_theme_updates_allowed()) {
        return new WP_Error('lonestar_updates_disabled', __('Lonestar updates are disabled for this Git checkout.', 'lonestar'));
    }

    $payload = lonestar_get_latest_parent_release_payload();
    if (!is_array($payload) || (string) $payload['package_url'] !== (string) $package || empty($payload['sha256'])) {
        return new WP_Error('lonestar_update_unverified', __('Lonestar update package does not match the verified GitHub release.', 'lonestar'));
    }

    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $temporary_file = download_url($package, 300);
    if (is_wp_error($temporary_file)) {
        return $temporary_file;
    }

    $checksum_result = lonestar_verify_parent_theme_package_checksum($temporary_file, (string) $payload['sha256']);
    if (is_wp_error($checksum_result)) {
        wp_delete_file($temporary_file);
        return $checksum_result;
    }

    return $temporary_file;
}

/**
 * Verify one downloaded package against an expected SHA-256 digest.
 *
 * @param string $file_path Downloaded package path.
 * @param string $expected_digest Expected lowercase SHA-256 digest.
 * @return true|WP_Error
 */
function lonestar_verify_parent_theme_package_checksum($file_path, $expected_digest)
{
    $file_path = wp_normalize_path((string) $file_path);
    $expected_digest = strtolower(trim((string) $expected_digest));
    if (!is_readable($file_path) || 1 !== preg_match('/^[a-f0-9]{64}$/', $expected_digest)) {
        return new WP_Error('lonestar_update_checksum_invalid', __('Lonestar update checksum metadata is invalid.', 'lonestar'));
    }

    $actual_digest = hash_file('sha256', $file_path);
    if (!is_string($actual_digest) || !hash_equals($expected_digest, strtolower($actual_digest))) {
        return new WP_Error('lonestar_update_checksum_mismatch', __('Lonestar update failed SHA-256 verification.', 'lonestar'));
    }

    return true;
}

/**
 * Add cached updater diagnostics to Site Health.
 *
 * @param array $debug_info Existing Site Health information.
 * @return array
 */
function lonestar_add_parent_update_site_health_info($debug_info)
{
    $status = lonestar_get_cached_parent_theme_update_status();
    $debug_info['lonestar-update'] = array(
        'label'  => __('Lonestar parent update', 'lonestar'),
        'fields' => array(
            'installed_version' => array('label' => __('Installed version', 'lonestar'), 'value' => (string) wp_get_theme('lonestar')->get('Version')),
            'update_policy'     => array('label' => __('Update policy', 'lonestar'), 'value' => lonestar_get_parent_theme_update_policy_label()),
            'latest_version'    => array('label' => __('Latest checked version', 'lonestar'), 'value' => (string) ($status['version'] ?? __('Unknown', 'lonestar'))),
            'checked_at'        => array('label' => __('Last check (UTC)', 'lonestar'), 'value' => (string) ($status['checked_at'] ?? __('Not checked', 'lonestar'))),
            'release_url'       => array('label' => __('Release URL', 'lonestar'), 'value' => (string) ($status['release_url'] ?? '')),
            'sha256'            => array('label' => __('SHA-256', 'lonestar'), 'value' => (string) ($status['sha256'] ?? '')),
            'error'             => array('label' => __('Last error', 'lonestar'), 'value' => (string) ($status['error'] ?? '')),
        ),
    );

    return $debug_info;
}

/**
 * Render cached updater status on Theme Settings -> About.
 *
 * @return void
 */
function lonestar_render_parent_update_status()
{
    $status = lonestar_get_cached_parent_theme_update_status();
    echo '<h3 style="margin-top:20px;">' . esc_html__('Parent Update Status', 'lonestar') . '</h3>';
    echo '<table class="widefat striped" style="max-width:1200px;"><tbody>';
    echo '<tr><th style="width:260px;">' . esc_html__('Installed version', 'lonestar') . '</th><td><code>' . esc_html((string) wp_get_theme('lonestar')->get('Version')) . '</code></td></tr>';
    echo '<tr><th>' . esc_html__('Update policy', 'lonestar') . '</th><td>' . esc_html(lonestar_get_parent_theme_update_policy_label()) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Latest checked version', 'lonestar') . '</th><td>' . esc_html((string) ($status['version'] ?? __('Unknown', 'lonestar'))) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Last check (UTC)', 'lonestar') . '</th><td>' . esc_html((string) ($status['checked_at'] ?? __('Not checked', 'lonestar'))) . '</td></tr>';
    echo '<tr><th>' . esc_html__('SHA-256', 'lonestar') . '</th><td><code>' . esc_html((string) ($status['sha256'] ?? '')) . '</code></td></tr>';
    echo '<tr><th>' . esc_html__('Status', 'lonestar') . '</th><td>' . esc_html(!empty($status['success']) ? __('Verified release metadata available.', 'lonestar') : (string) ($status['error'] ?? __('No cached check yet.', 'lonestar'))) . '</td></tr>';
    echo '</tbody></table>';
}
