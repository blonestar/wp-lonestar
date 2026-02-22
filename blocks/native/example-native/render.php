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
    $heading = __('Example Native Block', 'lonestar-theme');
}

if ('' === trim($description)) {
    $description = __('This block is rendered by PHP and edited in the block editor.', 'lonestar-theme');
}
?>
<section class="wp-block-lonestar-example-native">
    <h3><?php echo esc_html($heading); ?></h3>
    <p><?php echo esc_html($description); ?></p>
</section>
