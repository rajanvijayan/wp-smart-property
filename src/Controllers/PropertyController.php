<?php

namespace WPSmartProperty\Controllers;

class PropertyController {

    public function __construct() {
        add_action('admin_init', [$this, 'settings_init']);
        add_action('init', [$this, 'register_dynamic_taxonomies']);
        add_action('add_meta_boxes', [$this, 'add_property_meta_boxes']);
        add_action('save_post', [$this, 'save_property_meta']);
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
        if (!is_array($input)) {
            return [];
        }
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

    public function register_dynamic_taxonomies() {
        $options = get_option('wp_smart_property_options');
        if (isset($options['property_taxonomies'])) {
            $taxonomies = explode(',', $options['property_taxonomies']);
            foreach ($taxonomies as $taxonomy) {
                $taxonomy = sanitize_title($taxonomy);
                register_taxonomy($taxonomy, 'property', [
                    'label' => ucfirst($taxonomy),
                    'rewrite' => ['slug' => $taxonomy],
                    'hierarchical' => true,
                ]);
            }
        }
    }

    public function add_property_meta_boxes() {
        $options = get_option('wp_smart_property_options');
        if (isset($options['property_meta'])) {
            $meta_keys = explode(',', $options['property_meta']);
            foreach ($meta_keys as $meta_key) {
                add_meta_box(
                    $meta_key . '_meta_box',
                    ucfirst($meta_key),
                    [$this, 'render_meta_box'],
                    'property',
                    'normal',
                    'default',
                    ['meta_key' => $meta_key]
                );
            }
        }
    }

    public function render_meta_box($post, $meta) {
        $meta_key = $meta['args']['meta_key'];
        $value = get_post_meta($post->ID, $meta_key, true);
        ?>
        <label for="<?php echo esc_attr($meta_key); ?>"><?php echo ucfirst($meta_key); ?>:</label>
        <input type="text" id="<?php echo esc_attr($meta_key); ?>" name="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($value); ?>" />
        <?php
    }

    public function save_property_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $options = get_option('wp_smart_property_options');
        if (isset($options['property_meta'])) {
            $meta_keys = explode(',', $options['property_meta']);
            foreach ($meta_keys as $meta_key) {
                if (isset($_POST[$meta_key])) {
                    update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
                }
            }
        }
    }
}

new PropertyController();
