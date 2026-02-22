<?php

if (!defined('ABSPATH')) {
    exit;
}

$title = (string) get_field('title');
$text = (string) get_field('text');

if ('' === trim($title)) {
    $title = __('Example ACF Block', 'lonestar-theme');
}

if ('' === trim($text)) {
    $text = __('This is a starter ACF block. Create ACF fields "title" and "text" to customize content.', 'lonestar-theme');
}
?>
<section class="wp-block-lonestar-example-acf">
    <h3><?php echo esc_html($title); ?></h3>
    <p><?php echo esc_html($text); ?></p>
</section>
