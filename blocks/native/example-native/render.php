<?php

if (!defined('ABSPATH')) {
    exit;
}

$heading = '';
if (isset($attributes['heading']) && is_string($attributes['heading'])) {
    $heading = $attributes['heading'];
}

$description = '';
if (isset($attributes['description']) && is_string($attributes['description'])) {
    $description = $attributes['description'];
}

if ('' === trim($heading)) {
    $heading = __('Example Native Block', 'lonestar');
}

if ('' === trim($description)) {
    $description = __('This block is rendered by PHP and edited in the block editor.', 'lonestar');
}
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'wp-block-lonestar-example-native')); ?>>
    <h3><?php echo esc_html($heading); ?></h3>
    <p><?php echo wp_kses_post($description); ?></p>
</section>
