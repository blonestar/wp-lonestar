<?php

if (!defined('ABSPATH')) {
    exit;
}

$lonestar_heading = isset($attributes['heading']) && is_string($attributes['heading'])
    ? trim($attributes['heading'])
    : '';
$lonestar_description = isset($attributes['description']) && is_string($attributes['description'])
    ? trim($attributes['description'])
    : '';
$lonestar_tone = isset($attributes['tone']) && in_array($attributes['tone'], array('neutral', 'accent'), true)
    ? $attributes['tone']
    : 'neutral';
$lonestar_show_divider = !isset($attributes['showDivider']) || true === $attributes['showDivider'];

if ('' === $lonestar_heading) {
    $lonestar_heading = __('Example PHP-only Block', 'lonestar');
}
if ('' === $lonestar_description) {
    $lonestar_description = __('This block is registered, edited, and rendered without block JavaScript.', 'lonestar');
}

$lonestar_wrapper_attributes = get_block_wrapper_attributes(
    array('class' => 'wp-block-lonestar-example-php-only is-tone-' . sanitize_html_class($lonestar_tone))
);
?>
<section <?php echo $lonestar_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress returns escaped wrapper attributes. ?>>
    <h3><?php echo esc_html($lonestar_heading); ?></h3>
    <?php if ($lonestar_show_divider) : ?>
        <hr aria-hidden="true" />
    <?php endif; ?>
    <p><?php echo esc_html($lonestar_description); ?></p>
</section>
