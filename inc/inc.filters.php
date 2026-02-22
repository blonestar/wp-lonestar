<?php

/**
 * Custom Filters
 *
 * Add project-specific WordPress filters and actions here
 */

if (!defined('ABSPATH')) {
    exit;
}



/**
 * Fix ACF Taxonomy Field Update Issues
 *
 * Bugfix for ACF REST API/ACF Pro issue preventing updates to taxonomy ACF field data.
 * @link https://github.com/airesvsg/acf-to-rest-api/issues/301
 */
function lonestar_match_taxonomy_acf($result, $rule, $screen, $field_group)
{
    unset($field_group);

    if ($result) {
        return $result;
    }

    $post_id = isset($screen['post_id']) ? (string) $screen['post_id'] : '';
    $rule_value = isset($rule['value']) ? (string) $rule['value'] : '';

    if ('' === $post_id || '' === $rule_value) {
        return $result;
    }

    $separator_pos = strrpos($post_id, '_');
    if (false === $separator_pos) {
        return $result;
    }

    $post_taxonomy = substr($post_id, 0, $separator_pos);
    if ($post_taxonomy === $rule_value) {
        return true;
    }

    return $result;
}
add_filter('acf/location/rule_match/taxonomy', 'lonestar_match_taxonomy_acf', 10, 4);

/**
 * Add reusable blocks UI to WordPress Menu
 */
if (!function_exists('lonestar_reusable_blocks_ui')) {
    function lonestar_reusable_blocks_ui()
    {
        add_submenu_page('themes.php', __('Reusable Blocks', 'lonestar-theme'), __('Reusable Blocks', 'lonestar-theme'), 'edit_posts', 'edit.php?post_type=wp_block', '', 22);
    }
    add_action('admin_menu', 'lonestar_reusable_blocks_ui');
}

/**
 * Add AVIF mime type support
 */
function lonestar_filter_allowed_mimes_avif($mime_types)
{
    $mime_types['avif'] = 'image/avif';
    return $mime_types;
}
add_filter('upload_mimes', 'lonestar_filter_allowed_mimes_avif', 1000, 1);

/* ============================================
 * Add your project-specific filters below
 * ============================================ */
