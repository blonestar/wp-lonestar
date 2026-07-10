<?php

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'key'      => 'group_lonestar_example_acf',
    'title'    => __('Lonestar Example ACF', 'lonestar-theme'),
    'fields'   => array(
        array(
            'key'   => 'field_lonestar_example_acf_title',
            'label' => __('Title', 'lonestar-theme'),
            'name'  => 'title',
            'type'  => 'text',
        ),
        array(
            'key'          => 'field_lonestar_example_acf_text',
            'label'        => __('Text', 'lonestar-theme'),
            'name'         => 'text',
            'type'         => 'textarea',
            'new_lines'    => 'wpautop',
        ),
    ),
    'location' => array(
        array(
            array(
                'param'    => 'block',
                'operator' => '==',
                'value'    => 'lonestar/example-acf',
            ),
        ),
    ),
    'active'   => true,
);
