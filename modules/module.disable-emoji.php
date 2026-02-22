<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disable default WordPress emoji scripts/styles.
 */
add_action('init', 'lonestar_module_disable_emojis');

/**
 * Remove emoji-related hooks and filters.
 *
 * @return void
 */
function lonestar_module_disable_emojis()
{
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

    add_filter('tiny_mce_plugins', 'lonestar_module_disable_emojis_tinymce');
    add_filter('wp_resource_hints', 'lonestar_module_disable_emojis_dns_prefetch', 10, 2);
}

/**
 * Remove TinyMCE emoji plugin.
 *
 * @param mixed $plugins TinyMCE plugin list.
 * @return array
 */
function lonestar_module_disable_emojis_tinymce($plugins)
{
    if (!is_array($plugins)) {
        return array();
    }

    return array_values(array_diff($plugins, array('wpemoji')));
}

/**
 * Remove emoji CDN host from resource hints.
 *
 * @param array  $urls Resource hint URLs.
 * @param string $relation_type Resource hint relation type.
 * @return array
 */
function lonestar_module_disable_emojis_dns_prefetch($urls, $relation_type)
{
    if ('dns-prefetch' !== $relation_type || !is_array($urls)) {
        return $urls;
    }

    $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
    return array_values(array_diff($urls, array($emoji_svg_url)));
}
