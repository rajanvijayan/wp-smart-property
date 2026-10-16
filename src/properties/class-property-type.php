<?php
/**
 * The property post type (kept from 2.0, so existing URLs stay the same).
 *
 * @package EstateCraft
 */

namespace EstateCraft\Properties;

/**
 * Property listings. Capabilities are separate from posts (estate_property), mapped per listing so
 * agents can manage only their own.
 */
final class Property_Type {

	const POST_TYPE = 'property';

	/**
	 * Register the post type.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'                  => __( 'Properties', 'wp-smart-property' ),
					'singular_name'         => __( 'Property', 'wp-smart-property' ),
					'menu_name'             => _x( 'Properties', 'admin menu', 'wp-smart-property' ),
					'add_new'               => __( 'Add property', 'wp-smart-property' ),
					'add_new_item'          => __( 'Add new property', 'wp-smart-property' ),
					'edit_item'             => __( 'Edit property', 'wp-smart-property' ),
					'new_item'              => __( 'New property', 'wp-smart-property' ),
					'view_item'             => __( 'View property', 'wp-smart-property' ),
					'view_items'            => __( 'View properties', 'wp-smart-property' ),
					'search_items'          => __( 'Search properties', 'wp-smart-property' ),
					'not_found'             => __( 'No properties found.', 'wp-smart-property' ),
					'not_found_in_trash'    => __( 'No properties found in the trash.', 'wp-smart-property' ),
					'all_items'             => __( 'All properties', 'wp-smart-property' ),
					'archives'              => __( 'Property archives', 'wp-smart-property' ),
					'featured_image'        => __( 'Main photo', 'wp-smart-property' ),
					'set_featured_image'    => __( 'Set main photo', 'wp-smart-property' ),
					'remove_featured_image' => __( 'Remove main photo', 'wp-smart-property' ),
					'item_published'        => __( 'Property published.', 'wp-smart-property' ),
					'item_updated'          => __( 'Property updated.', 'wp-smart-property' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'rest_base'       => 'properties',
				'menu_icon'       => 'dashicons-admin-home',
				'menu_position'   => 25,
				'has_archive'     => true,
				'rewrite'         => array(
					'slug'       => 'property',
					'with_front' => false,
				),
				'capability_type' => array( 'estate_property', 'estate_properties' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields', 'comments', 'page-attributes' ),
				'taxonomies'      => array_keys( Taxonomies::all() ),
			)
		);
	}
}
