<?php

if (!defined('ABSPATH')) {
    exit;
}

$heading = isset($attributes['heading']) && is_string($attributes['heading'])
    ? trim($attributes['heading'])
    : '';
$description = isset($attributes['description']) && is_string($attributes['description'])
    ? trim($attributes['description'])
    : '';
$tone = isset($attributes['tone']) && in_array($attributes['tone'], array('neutral', 'accent'), true)
    ? $attributes['tone']
    : 'neutral';
$show_divider = !isset($attributes['showDivider']) || true === $attributes['showDivider'];

if ('' === $heading) {
    $heading = __('Example PHP-only Block', 'lonestar');
}
if ('' === $description) {
    $description = __('This block is registered, edited, and rendered without block JavaScript.', 'lonestar');
}

$wrapper_attributes = get_block_wrapper_attributes(
    array('class' => 'wp-block-lonestar-example-php-only is-tone-' . sanitize_html_class($tone))
);
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <h3><?php echo esc_html($heading); ?></h3>
    <?php if ($show_divider) : ?>
        <hr aria-hidden="true" />
    <?php endif; ?>
    <p><?php echo esc_html($description); ?></p>
</section>
