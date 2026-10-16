<?php
/*
 * @Author 		wp-smart-property
 * @Author 		WP Smart Plugin
 * Copyright: 	https://www.wpsmartplugin.com/
 */
class WPSMPR_Register_Post{
	
	/**
	 * Init, to inistall post types, Taxonomies and Metaboxes
	 */
	
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_post_status' ), 10 );
	}
	
	public static function register_post_types(){
	
		/**
		 * Create Post Types
		 */

		do_action( 'wpsmpr_register_post_type' );
	
		$labels = array(
			'name'               => __( 'Properties', 'wp-smart-property' ),
			'singular_name'      => __( 'Property', 'wp-smart-property' ),
			'menu_name'          => _x( 'Properties', 'Admin menu name', 'wp-smart-property' ),
			'add_new'            => __( 'Add Property', 'wp-smart-property' ),
			'add_new_item'       => __( 'Add New Property', 'wp-smart-property' ),
			'edit'               => __( 'Edit', 'wp-smart-property' ),
			'edit_item'          => __( 'Edit Property', 'wp-smart-property' ),
			'new_item'           => __( 'New Property', 'wp-smart-property' ),
			'view'               => __( 'View Property', 'wp-smart-property' ),
			'view_item'          => __( 'View Property', 'wp-smart-property' ),
			'search_items'       => __( 'Search Properties', 'wp-smart-property' ),
			'not_found'          => __( 'No Properties found', 'wp-smart-property' ),
			'not_found_in_trash' => __( 'No Properties found in trash', 'wp-smart-property' ),
			'parent'             => __( 'Parent Property', 'wp-smart-property' )
		);
							
		$args = array( 
			'labels' => $labels, 
			'public' => true, 
			'publicly_queryable' => true, 
			'show_ui' => true,  
			'show_in_menu' => true, 
			'query_var' => true, 
			'rewrite' => array( 'slug' => 'property' ), 
			'capability_type' => 'post', 
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => null,
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'custom-fields', 'page-attributes' ),
			'menu_icon' => WP_PLUGIN_URL .'/wp-smart-property/assets/image/icon.png'
		); 

		register_post_type( 'property', apply_filters( 'wp_smart_property_register_post_type_property', $args) );
		
		do_action( 'wpsmpr_after_register_post_type' );

	}
	
	public static function register_taxonomies(){
	
		/**
		 * Create taxonomies to the property post type
		 */
		
		do_action( 'wpsmpr_register_taxonomy' );
		
		$attribute_taxonomies = apply_filters( 'wp_smart_property_set_default_taxonomies', array( 'Property Type', 'Property Category', 'City', 'Bed Room', 'Bath Room', 'Country' ) );

		foreach ( $attribute_taxonomies as $tax ) {
			if ( !empty ( $tax ) ) {
		
				$label = $name = $tax;
				register_taxonomy( $name,
					apply_filters( 'wp_smart_property_taxonomy_objects_' . $name, array( 'property' ) ),
					apply_filters( 'wp_smart_property_taxonomy_args_' . $name, array(
						'hierarchical'          => true,
						'update_count_callback' => '_update_post_term_count',
						'labels'                => array(
								'name'              => $label,
								'singular_name'     => $label,
								'search_items'      => sprintf( __( 'Search %s', 'wp-smart-property' ), $label ),
								'all_items'         => sprintf( __( 'All %s', 'wp-smart-property' ), $label ),
								'parent_item'       => sprintf( __( 'Parent %s', 'wp-smart-property' ), $label ),
								'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp-smart-property' ), $label ),
								'edit_item'         => sprintf( __( 'Edit %s', 'wp-smart-property' ), $label ),
								'update_item'       => sprintf( __( 'Update %s', 'wp-smart-property' ), $label ),
								'add_new_item'      => sprintf( __( 'Add New %s', 'wp-smart-property' ), $label ),
								'new_item_name'     => sprintf( __( 'New %s', 'wp-smart-property' ), $label )
							),
						'show_ui'               => true,
						'query_var'             => true,
						'rewrite'               => array(
							'slug'         => ( empty( $permalinks['attribute_base'] ) ? '' : trailingslashit( $permalinks['attribute_base'] ) ) . sanitize_title( $tax ),
							'with_front'   => false,
							'hierarchical' => true
						),
					) )
				);
				
			}
		}
		
		do_action( 'wpsmpr_after_register_taxonomy' );
		
	}
	
	public static function register_post_status(){
		
		do_action( 'wpsmpr_register_post_status' );
		
		do_action( 'wpsmpr_after_register_post_status' );
		
	}
	
}
WPSMPR_Register_Post::init();
?>