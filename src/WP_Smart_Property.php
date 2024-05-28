<?php

namespace WPSmartProperty;

use WPSmartProperty\Controllers\PropertyController;

class WP_Smart_Property {

    private static $instance = null;

    private function __construct() {
        add_action('init', [$this, 'register_property_post_type']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Load the Property Controller
        new PropertyController();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_property_post_type() {
        $labels = [
            'name'               => _x('Properties', 'post type general name', 'wp-smart-property'),
            'singular_name'      => _x('Property', 'post type singular name', 'wp-smart-property'),
            'menu_name'          => _x('Properties', 'admin menu', 'wp-smart-property'),
            'name_admin_bar'     => _x('Property', 'add new on admin bar', 'wp-smart-property'),
            'add_new'            => _x('Add New', 'property', 'wp-smart-property'),
            'add_new_item'       => __('Add New Property', 'wp-smart-property'),
            'new_item'           => __('New Property', 'wp-smart-property'),
            'edit_item'          => __('Edit Property', 'wp-smart-property'),
            'view_item'          => __('View Property', 'wp-smart-property'),
            'all_items'          => __('All Properties', 'wp-smart-property'),
            'search_items'       => __('Search Properties', 'wp-smart-property'),
            'parent_item_colon'  => __('Parent Properties:', 'wp-smart-property'),
            'not_found'          => __('No properties found.', 'wp-smart-property'),
            'not_found_in_trash' => __('No properties found in Trash.', 'wp-smart-property')
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'property'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ];

        register_post_type('property', $args);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Property Setup', 'wp-smart-property'),
            __('Property Setup', 'wp-smart-property'),
            'manage_options',
            'wp-smart-property',
            [$this, 'create_admin_page'],
            'dashicons-admin-home'
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Property Setup', 'wp-smart-property'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_smart_property_options_group');
                do_settings_sections('wp_smart_property');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
