<?php

if (!defined('ABSPATH')) {
    exit;
}

$title = (string) get_field('title');
$lonestar_text = (string) get_field('text');

if ('' === trim($title)) {
    $title = __('Example ACF Block', 'lonestar');
}

if ('' === trim($lonestar_text)) {
    $lonestar_text = __('This starter block is powered by its bundled local ACF field group.', 'lonestar');
}

$lonestar_extra_attributes = array('class' => 'wp-block-lonestar-example-acf');
if (isset($block['anchor']) && is_string($block['anchor']) && '' !== $block['anchor']) {
    $lonestar_extra_attributes['id'] = sanitize_title($block['anchor']);
}
?>
<section <?php echo get_block_wrapper_attributes($lonestar_extra_attributes); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress returns escaped wrapper attributes. ?>>
    <h3><?php echo esc_html($title); ?></h3>
    <p><?php echo wp_kses_post($lonestar_text); ?></p>
</section>
