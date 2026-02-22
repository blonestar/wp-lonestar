<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('acf/include_field_types', 'lonestar_module_register_acf_number_with_unit_field');

/**
 * Register ACF custom field type: Number with Unit.
 *
 * @return void
 */
function lonestar_module_register_acf_number_with_unit_field()
{
    if (!class_exists('acf_field')) {
        return;
    }

    if (!class_exists('LONESTAR_ACF_Field_Number_With_Unit')) {
        class LONESTAR_ACF_Field_Number_With_Unit extends acf_field
        {
            public function __construct()
            {
                $this->name = 'number_with_unit';
                $this->label = __('Number with Unit', 'lonestar-theme');
                $this->category = 'basic';
                $this->defaults = array(
                    'units'         => '%,px,em,vw,vh',
                    'default_unit'  => '%',
                    'return_format' => 'string',
                );
                parent::__construct();
            }

            /**
             * Render field settings in ACF UI.
             *
             * @param array $field Field settings.
             * @return void
             */
            public function render_field_settings($field)
            {
                acf_render_field_setting(
                    $field,
                    array(
                        'label'        => __('Units', 'lonestar-theme'),
                        'instructions' => __('Comma-separated list. Example: %, px, rem', 'lonestar-theme'),
                        'type'         => 'text',
                        'name'         => 'units',
                        'required'     => true,
                    )
                );

                acf_render_field_setting(
                    $field,
                    array(
                        'label' => __('Default Unit', 'lonestar-theme'),
                        'type'  => 'text',
                        'name'  => 'default_unit',
                    )
                );

                acf_render_field_setting(
                    $field,
                    array(
                        'label'   => __('Return Format', 'lonestar-theme'),
                        'type'    => 'radio',
                        'name'    => 'return_format',
                        'choices' => array(
                            'string' => __('Combined String (e.g. 24px)', 'lonestar-theme'),
                            'array'  => __('Array (value/unit/combined)', 'lonestar-theme'),
                        ),
                    )
                );
            }

            /**
             * Render field control.
             *
             * @param array $field Field data.
             * @return void
             */
            public function render_field($field)
            {
                $units = $this->parse_units(isset($field['units']) ? $field['units'] : '');
                if (empty($units)) {
                    $units = array('%', 'px');
                }

                $default_value = isset($field['default_value']) ? (string) $field['default_value'] : '';
                $default_unit = isset($field['default_unit']) ? (string) $field['default_unit'] : $units[0];
                $value_data = $this->normalize_value(isset($field['value']) ? $field['value'] : null, $default_value, $default_unit);

                ?>
                <div class="acf-input-wrap" style="display:flex;gap:6px;align-items:center;">
                    <input
                        type="number"
                        step="any"
                        name="<?php echo esc_attr($field['name']); ?>[value]"
                        value="<?php echo esc_attr($value_data['value']); ?>"
                        style="flex:1 1 auto;min-width:0;"
                    />
                    <select name="<?php echo esc_attr($field['name']); ?>[unit]" style="flex:0 0 auto;min-width:72px;">
                        <?php foreach ($units as $unit) : ?>
                            <option value="<?php echo esc_attr($unit); ?>" <?php selected($value_data['unit'], $unit); ?>>
                                <?php echo esc_html($unit); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php
            }

            /**
             * Sanitize and persist field config.
             *
             * @param array $field Field config.
             * @return array
             */
            public function update_field($field)
            {
                $units = $this->parse_units(isset($field['units']) ? $field['units'] : '');
                $field['units'] = implode(',', $units);
                $field['default_unit'] = isset($field['default_unit']) ? sanitize_text_field((string) $field['default_unit']) : '';
                $field['return_format'] = (isset($field['return_format']) && 'array' === $field['return_format']) ? 'array' : 'string';
                return $field;
            }

            /**
             * Validate user value.
             *
             * @param mixed  $valid Validation status.
             * @param mixed  $value Submitted value.
             * @param array  $field Field config.
             * @param string $input Input name.
             * @return mixed
             */
            public function validate_value($valid, $value, $field, $input)
            {
                unset($input);

                if (true !== $valid) {
                    return $valid;
                }

                if (!is_array($value)) {
                    return __('Invalid value format.', 'lonestar-theme');
                }

                $number = isset($value['value']) ? trim((string) $value['value']) : '';
                $unit = isset($value['unit']) ? trim((string) $value['unit']) : '';
                $allowed_units = $this->parse_units(isset($field['units']) ? $field['units'] : '');

                if (!empty($field['required']) && ('' === $number || '' === $unit)) {
                    return __('This field is required.', 'lonestar-theme');
                }

                if ('' !== $number && !is_numeric($number)) {
                    return __('The value must be numeric.', 'lonestar-theme');
                }

                if ('' !== $unit && !empty($allowed_units) && !in_array($unit, $allowed_units, true)) {
                    return __('Selected unit is not allowed.', 'lonestar-theme');
                }

                return $valid;
            }

            /**
             * Sanitize value before saving.
             *
             * @param mixed  $value Submitted value.
             * @param int    $post_id Post ID.
             * @param array  $field Field config.
             * @return array|null
             */
            public function update_value($value, $post_id, $field)
            {
                unset($post_id);

                if (!is_array($value)) {
                    return null;
                }

                $number = isset($value['value']) ? trim((string) $value['value']) : '';
                $unit = isset($value['unit']) ? trim((string) $value['unit']) : '';
                $allowed_units = $this->parse_units(isset($field['units']) ? $field['units'] : '');

                if ('' === $number && '' === $unit) {
                    return null;
                }

                if ('' !== $number && is_numeric($number)) {
                    $number = (string) (0 + $number);
                }

                if (!in_array($unit, $allowed_units, true)) {
                    $unit = isset($field['default_unit']) ? sanitize_text_field((string) $field['default_unit']) : '';
                }

                return array(
                    'value' => $number,
                    'unit'  => $unit,
                );
            }

            /**
             * Format value returned by get_field().
             *
             * @param mixed $value Stored value.
             * @param int   $post_id Post ID.
             * @param array $field Field config.
             * @return mixed
             */
            public function format_value($value, $post_id, $field)
            {
                unset($post_id);

                if (!is_array($value)) {
                    return null;
                }

                $number = isset($value['value']) ? (string) $value['value'] : '';
                $unit = isset($value['unit']) ? (string) $value['unit'] : '';
                if ('' === $number && '' === $unit) {
                    return null;
                }

                $combined = $number . $unit;
                $return_format = (isset($field['return_format']) && 'array' === $field['return_format']) ? 'array' : 'string';

                if ('array' === $return_format) {
                    return array(
                        'value'    => $number,
                        'unit'     => $unit,
                        'combined' => $combined,
                    );
                }

                return $combined;
            }

            /**
             * Normalize raw value into array representation.
             *
             * @param mixed  $raw_value Raw stored value.
             * @param string $default_value Default numeric value.
             * @param string $default_unit Default unit.
             * @return array{value:string,unit:string}
             */
            private function normalize_value($raw_value, $default_value, $default_unit)
            {
                if (is_array($raw_value)) {
                    return array(
                        'value' => isset($raw_value['value']) ? (string) $raw_value['value'] : $default_value,
                        'unit'  => isset($raw_value['unit']) ? (string) $raw_value['unit'] : $default_unit,
                    );
                }

                if (is_string($raw_value) && preg_match('/^\s*([\-+]?\d*\.?\d+)\s*([a-z%]+)\s*$/i', $raw_value, $matches)) {
                    return array(
                        'value' => (string) $matches[1],
                        'unit'  => (string) $matches[2],
                    );
                }

                return array(
                    'value' => $default_value,
                    'unit'  => $default_unit,
                );
            }

            /**
             * Parse and sanitize configured unit list.
             *
             * @param string $units_csv CSV string of units.
             * @return array<int,string>
             */
            private function parse_units($units_csv)
            {
                $items = array_map('trim', explode(',', (string) $units_csv));
                $items = array_map(
                    function ($item) {
                        $item = trim((string) $item, "\"' ");
                        return preg_replace('/[^a-z0-9%_-]/i', '', $item);
                    },
                    $items
                );
                $items = array_filter($items, 'strlen');
                return array_values(array_unique($items));
            }
        }
    }

    new LONESTAR_ACF_Field_Number_With_Unit();
}
