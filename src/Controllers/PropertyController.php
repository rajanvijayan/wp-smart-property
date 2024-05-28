<?php

namespace WPSmartProperty\Controllers;

class PropertyController {

    public function __construct() {
        add_action('admin_init', [$this, 'settings_init']);
    }

    public function settings_init() {
        register_setting('wp_smart_property_options_group', 'wp_smart_property_options', [$this, 'sanitize']);

        add_settings_section(
            'wp_smart_property_section',
            __('Manage Property Settings', 'wp-smart-property'),
            [$this, 'settings_section_callback'],
            'wp_smart_property'
        );

        add_settings_field(
            'property_taxonomies',
            __('Property Taxonomies', 'wp-smart-property'),
            [$this, 'property_taxonomies_render'],
            'wp_smart_property',
            'wp_smart_property_section'
        );

        add_settings_field(
            'property_meta',
            __('Property Meta Information', 'wp-smart-property'),
            [$this, 'property_meta_render'],
            'wp_smart_property',
            'wp_smart_property_section'
        );
    }

    public function sanitize($input) {
        return array_map('sanitize_text_field', $input);
    }

    public function settings_section_callback() {
        echo __('Configure the taxonomies and meta information for properties.', 'wp-smart-property');
    }

    public function property_taxonomies_render() {
        $options = get_option('wp_smart_property_options');
        $taxonomies = isset($options['property_taxonomies']) ? $options['property_taxonomies'] : '';
        ?>
        <input type='text' name='wp_smart_property_options[property_taxonomies]' value='<?php echo esc_attr($taxonomies); ?>'>
        <p class="description"><?php _e('Enter comma-separated taxonomies (e.g., Location, Type).', 'wp-smart-property'); ?></p>
        <?php
    }

    public function property_meta_render() {
        $options = get_option('wp_smart_property_options');
        $meta = isset($options['property_meta']) ? $options['property_meta'] : '';
        ?>
        <input type='text' name='wp_smart_property_options[property_meta]' value='<?php echo esc_attr($meta); ?>'>
        <p class="description"><?php _e('Enter comma-separated meta keys (e.g., Price, Bedrooms).', 'wp-smart-property'); ?></p>
        <?php
    }
}

new PropertyController();
