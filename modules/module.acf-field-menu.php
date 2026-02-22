<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('acf/include_field_types', 'lonestar_module_register_acf_menu_selector_field');

/**
 * Register ACF custom field type: Menu Selector.
 *
 * @return void
 */
function lonestar_module_register_acf_menu_selector_field()
{
    if (!class_exists('acf_field')) {
        return;
    }

    if (!class_exists('LONESTAR_ACF_Field_Menu_Selector')) {
        class LONESTAR_ACF_Field_Menu_Selector extends acf_field
        {
            public function __construct()
            {
                $this->name = 'menu_selector';
                $this->label = __('Menu Selector', 'lonestar-theme');
                $this->category = 'choice';
                parent::__construct();
            }

            /**
             * Render field control.
             *
             * @param array $field ACF field data.
             * @return void
             */
            public function render_field($field)
            {
                $menus = wp_get_nav_menus(array('hide_empty' => false));
                $input_id = isset($field['id']) ? $field['id'] : 'acf-' . (isset($field['key']) ? $field['key'] : wp_generate_uuid4());
                $input_name = isset($field['name']) ? $field['name'] : '';
                $input_class = isset($field['class']) ? $field['class'] : '';
                $selected_value = isset($field['value']) ? (string) $field['value'] : '';

                printf(
                    '<select id="%s" name="%s" class="%s">',
                    esc_attr($input_id),
                    esc_attr($input_name),
                    esc_attr($input_class)
                );
                echo '<option value="">' . esc_html__('None', 'lonestar-theme') . '</option>';

                foreach ($menus as $menu) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr((string) $menu->term_id),
                        selected($selected_value, (string) $menu->term_id, false),
                        esc_html($menu->name)
                    );
                }

                echo '</select>';
            }
        }
    }

    new LONESTAR_ACF_Field_Menu_Selector();
}
