<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('acf/init', 'lonestar_module_register_gtm_fields');

/**
 * Register local ACF fields used by GTM module.
 *
 * @return void
 */
function lonestar_module_register_gtm_fields()
{
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(
        array(
            'key' => 'group_lonestar_gtm_options',
            'title' => __('Theme Options: GTM', 'lonestar-theme'),
            'fields' => array(
                array(
                    'key' => 'field_lonestar_gtm_enabled',
                    'label' => __('Enable GTM', 'lonestar-theme'),
                    'name' => 'gtm_enabled',
                    'type' => 'true_false',
                    'default_value' => 0,
                    'ui' => 1,
                ),
                array(
                    'key' => 'field_lonestar_gtm_id',
                    'label' => __('GTM Container ID', 'lonestar-theme'),
                    'name' => 'gtm_id',
                    'type' => 'text',
                    'required' => 1,
                    'placeholder' => 'GTM-XXXXXX',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_lonestar_gtm_enabled',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'acf-options-gtm',
                    ),
                ),
            ),
            'position' => 'normal',
            'style' => 'seamless',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
            'show_in_rest' => 0,
        )
    );
}
