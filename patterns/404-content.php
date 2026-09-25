<?php
/**
 * Title: 404 content
 * Slug: lonestar/404-content
 * Inserter: no
 *
 * @package Lonestar
 */

if (!defined('ABSPATH')) {
    exit;
}

$lonestar_home_link_attrs = wp_json_encode(array('label' => __('Go Home', 'lonestar')));
?>
<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="has-text-align-center"><?php esc_html_e('404 - Page Not Found', 'lonestar'); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php esc_html_e('The page you are looking for could not be found.', 'lonestar'); ?></p>
<!-- /wp:paragraph -->

<!-- wp:home-link <?php echo $lonestar_home_link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is serialized for a WordPress block comment, not an HTML attribute. ?> /-->
