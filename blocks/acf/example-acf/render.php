<?php

if (!defined('ABSPATH')) {
    exit;
}

$title = (string) get_field('title');
$text = (string) get_field('text');

if ('' === trim($title)) {
    $title = __('Example ACF Block', 'lonestar');
}

if ('' === trim($text)) {
    $text = __('This starter block is powered by its bundled local ACF field group.', 'lonestar');
}

$extra_attributes = array('class' => 'wp-block-lonestar-example-acf');
if (isset($block['anchor']) && is_string($block['anchor']) && '' !== $block['anchor']) {
    $extra_attributes['id'] = sanitize_title($block['anchor']);
}
?>
<section <?php echo get_block_wrapper_attributes($extra_attributes); ?>>
    <h3><?php echo esc_html($title); ?></h3>
    <p><?php echo wp_kses_post($text); ?></p>
</section>
