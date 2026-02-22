<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parent theme update integration via GitHub Releases.
 */

if (!defined('LONESTAR_UPDATE_REPO')) {
    define('LONESTAR_UPDATE_REPO', 'blonestar/wp-lonestar');
}

if (!defined('LONESTAR_UPDATE_TAG_PREFIX')) {
    define('LONESTAR_UPDATE_TAG_PREFIX', 'lonestar-v');
}

if (!defined('LONESTAR_UPDATE_CACHE_TTL')) {
    define('LONESTAR_UPDATE_CACHE_TTL', 6 * HOUR_IN_SECONDS);
}

add_filter('pre_set_site_transient_update_themes', 'lonestar_maybe_inject_parent_theme_update');
add_filter('themes_api', 'lonestar_provide_parent_theme_update_info', 10, 3);
add_action('after_switch_theme', 'lonestar_flush_parent_theme_update_cache');
add_action('upgrader_process_complete', 'lonestar_flush_parent_theme_update_cache', 10, 2);

/**
 * Return update transient cache key.
 *
 * @return string
 */
function lonestar_get_parent_theme_update_cache_key()
{
    return 'lonestar_parent_update_payload_v1';
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
    delete_transient(lonestar_get_parent_theme_update_cache_key());
}

/**
 * Read latest parent-theme release payload from GitHub.
 *
 * @return array<string,string>|false
 */
function lonestar_get_latest_parent_release_payload()
{
    $cache_key = lonestar_get_parent_theme_update_cache_key();
    $cached_payload = get_transient($cache_key);
    if (is_array($cached_payload) && !empty($cached_payload['version']) && !empty($cached_payload['package_url'])) {
        return $cached_payload;
    }

    $repo = trim((string) LONESTAR_UPDATE_REPO);
    if ('' === $repo) {
        return false;
    }

    $release_api_url = sprintf('https://api.github.com/repos/%s/releases/latest', rawurlencode($repo));
    $headers = array(
        'Accept'     => 'application/vnd.github+json',
        'User-Agent' => 'LonestarThemeUpdater/' . (string) wp_get_theme(get_template())->get('Version'),
    );

    if (defined('LONESTAR_GITHUB_TOKEN') && is_string(LONESTAR_GITHUB_TOKEN) && '' !== trim(LONESTAR_GITHUB_TOKEN)) {
        $headers['Authorization'] = 'Bearer ' . trim((string) LONESTAR_GITHUB_TOKEN);
    }

    $response = wp_remote_get(
        $release_api_url,
        array(
            'headers' => $headers,
            'timeout' => 10,
        )
    );

    if (is_wp_error($response)) {
        return false;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if (200 !== $status_code) {
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $release_data = json_decode((string) $response_body, true);
    if (!is_array($release_data)) {
        return false;
    }

    if (!empty($release_data['draft']) || !empty($release_data['prerelease'])) {
        return false;
    }

    $tag_name = sanitize_text_field((string) ($release_data['tag_name'] ?? ''));
    $tag_prefix = (string) LONESTAR_UPDATE_TAG_PREFIX;
    if ('' === $tag_name || '' === $tag_prefix || 0 !== strpos($tag_name, $tag_prefix)) {
        return false;
    }

    $version = substr($tag_name, strlen($tag_prefix));
    $version = preg_replace('/[^0-9A-Za-z\.\-_]/', '', (string) $version);
    if (!is_string($version) || '' === $version) {
        return false;
    }

    $expected_asset_name = 'lonestar-' . $version . '.zip';
    $package_url = '';
    $assets = isset($release_data['assets']) && is_array($release_data['assets']) ? $release_data['assets'] : array();
    foreach ($assets as $asset) {
        if (!is_array($asset)) {
            continue;
        }

        $asset_name = isset($asset['name']) ? sanitize_text_field((string) $asset['name']) : '';
        $asset_url = isset($asset['browser_download_url']) ? esc_url_raw((string) $asset['browser_download_url']) : '';
        if ('' === $asset_name || '' === $asset_url) {
            continue;
        }

        if ($asset_name === $expected_asset_name) {
            $package_url = $asset_url;
            break;
        }

        if ('' === $package_url && 0 === strpos($asset_name, 'lonestar-') && '.zip' === substr($asset_name, -4)) {
            $package_url = $asset_url;
        }
    }

    if ('' === $package_url) {
        return false;
    }

    $payload = array(
        'version'      => $version,
        'package_url'  => $package_url,
        'release_url'  => esc_url_raw((string) ($release_data['html_url'] ?? '')),
        'release_body' => (string) ($release_data['body'] ?? ''),
        'published_at' => sanitize_text_field((string) ($release_data['published_at'] ?? '')),
    );

    set_transient($cache_key, $payload, (int) LONESTAR_UPDATE_CACHE_TTL);
    return $payload;
}

/**
 * Inject parent-theme update into core theme update transient.
 *
 * @param object|false $transient Theme update transient.
 * @return object|false
 */
function lonestar_maybe_inject_parent_theme_update($transient)
{
    if (!is_object($transient) || !isset($transient->checked) || !is_array($transient->checked)) {
        return $transient;
    }

    $theme_slug = sanitize_key((string) get_template());
    if ('' === $theme_slug || !isset($transient->checked[$theme_slug])) {
        return $transient;
    }

    $current_version = (string) $transient->checked[$theme_slug];
    if ('' === $current_version) {
        $current_version = (string) wp_get_theme($theme_slug)->get('Version');
    }

    $payload = lonestar_get_latest_parent_release_payload();
    if (!is_array($payload) || empty($payload['version']) || empty($payload['package_url'])) {
        return $transient;
    }

    $remote_version = (string) $payload['version'];
    if (version_compare($remote_version, $current_version, '<=')) {
        if (isset($transient->response[$theme_slug])) {
            unset($transient->response[$theme_slug]);
        }
        return $transient;
    }

    $theme = wp_get_theme($theme_slug);
    $transient->response[$theme_slug] = array(
        'theme'        => $theme_slug,
        'new_version'  => $remote_version,
        'url'          => (string) ($payload['release_url'] ?? ''),
        'package'      => (string) $payload['package_url'],
        'requires'     => (string) $theme->get('RequiresWP'),
        'requires_php' => (string) $theme->get('RequiresPHP'),
    );

    return $transient;
}

/**
 * Provide theme information modal data for parent updates.
 *
 * @param mixed  $result Existing API result.
 * @param string $action Current API action.
 * @param object $args API arguments.
 * @return mixed
 */
function lonestar_provide_parent_theme_update_info($result, $action, $args)
{
    if ('theme_information' !== (string) $action || !is_object($args) || !isset($args->slug)) {
        return $result;
    }

    $theme_slug = sanitize_key((string) get_template());
    if ($theme_slug !== sanitize_key((string) $args->slug)) {
        return $result;
    }

    $payload = lonestar_get_latest_parent_release_payload();
    if (!is_array($payload)) {
        return $result;
    }

    $theme = wp_get_theme($theme_slug);
    $changelog_text = trim((string) ($payload['release_body'] ?? ''));
    if ('' === $changelog_text) {
        $changelog_text = __('See GitHub release notes for details.', 'lonestar-theme');
    }

    $info = new stdClass();
    $info->name = (string) $theme->get('Name');
    $info->slug = $theme_slug;
    $info->version = (string) ($payload['version'] ?? $theme->get('Version'));
    $info->author = (string) $theme->get('Author');
    $info->homepage = (string) ($payload['release_url'] ?? '');
    $info->requires = (string) $theme->get('RequiresWP');
    $info->requires_php = (string) $theme->get('RequiresPHP');
    $info->download_link = (string) ($payload['package_url'] ?? '');
    $info->last_updated = (string) ($payload['published_at'] ?? '');
    $info->sections = array(
        'description' => wp_kses_post(wpautop((string) $theme->get('Description'))),
        'changelog'   => wp_kses_post(wpautop(esc_html($changelog_text))),
    );

    return $info;
}
