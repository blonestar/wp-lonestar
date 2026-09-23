<?php

if (!defined('ABSPATH')) {
    exit;
}

$lonestar_heading = '';
if (isset($attributes['heading']) && is_string($attributes['heading'])) {
    $lonestar_heading = $attributes['heading'];
}

$lonestar_description = '';
if (isset($attributes['description']) && is_string($attributes['description'])) {
    $lonestar_description = $attributes['description'];
}

if ('' === trim($lonestar_heading)) {
    $lonestar_heading = __('Example Native Block', 'lonestar');
}

if ('' === trim($lonestar_description)) {
    $lonestar_description = __('This block is rendered by PHP and edited in the block editor.', 'lonestar');
}
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'wp-block-lonestar-example-native')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress returns escaped wrapper attributes. ?>>
    <h3><?php echo esc_html($lonestar_heading); ?></h3>
    <p><?php echo wp_kses_post($lonestar_description); ?></p>
</section>
