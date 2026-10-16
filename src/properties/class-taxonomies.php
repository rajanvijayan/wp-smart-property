<?php
/**
 * Property taxonomies.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Properties;

/**
 * Type, status, category, location and features. Type and category keep the 2.0 URL slugs, so
 * /property-type/house/ and /property-category/featured/ keep working after the upgrade.
 */
final class Taxonomies {

	/**
	 * Taxonomies: key => array( URL slug, hierarchical, labels ).
	 *
	 * @return array[]
	 */
	public static function all() {
		return array(
			'property_type'     => array( 'property-type', true, __( 'Property types', 'wp-smart-property' ), __( 'Property type', 'wp-smart-property' ) ),
			'property_status'   => array( 'property-status', true, __( 'Statuses', 'wp-smart-property' ), __( 'Status', 'wp-smart-property' ) ),
			'property_category' => array( 'property-category', true, __( 'Categories', 'wp-smart-property' ), __( 'Category', 'wp-smart-property' ) ),
			'property_location' => array( 'location', true, __( 'Locations', 'wp-smart-property' ), __( 'Location', 'wp-smart-property' ) ),
			'property_feature'  => array( 'feature', false, __( 'Features', 'wp-smart-property' ), __( 'Feature', 'wp-smart-property' ) ),
		);
	}

	/**
	 * Register the taxonomies.
	 */
	public static function register() {
		foreach ( self::all() as $taxonomy => $args ) {
			list( $slug, $hierarchical, $plural, $singular ) = $args;
			register_taxonomy(
				$taxonomy,
				Property_Type::POST_TYPE,
				array(
					'labels'            => array(
						'name'          => $plural,
						'singular_name' => $singular,
						/* translators: %s: taxonomy name, e.g. Locations */
						'search_items'  => sprintf( __( 'Search %s', 'wp-smart-property' ), $plural ),
						/* translators: %s: taxonomy name, e.g. Locations */
						'all_items'     => sprintf( __( 'All %s', 'wp-smart-property' ), $plural ),
						/* translators: %s: taxonomy name, e.g. Location */
						'edit_item'     => sprintf( __( 'Edit %s', 'wp-smart-property' ), $singular ),
						/* translators: %s: taxonomy name, e.g. Location */
						'add_new_item'  => sprintf( __( 'Add %s', 'wp-smart-property' ), $singular ),
					),
					'hierarchical'      => $hierarchical,
					'public'            => true,
					'show_in_rest'      => true,
					'show_admin_column' => in_array( $taxonomy, array( 'property_type', 'property_status', 'property_location' ), true ),
					'rewrite'           => array(
						'slug'         => $slug,
						'with_front'   => false,
						'hierarchical' => $hierarchical,
					),
					'capabilities'      => array(
						'manage_terms' => 'manage_estate_terms',
						'edit_terms'   => 'manage_estate_terms',
						'delete_terms' => 'manage_estate_terms',
						'assign_terms' => 'edit_estate_properties',
					),
				)
			);
		}
	}

	/**
	 * Default terms created on first activation.
	 */
	public static function install_defaults() {
		$defaults = array(
			'property_status' => array(
				'for-sale' => __( 'For sale', 'wp-smart-property' ),
				'for-rent' => __( 'For rent', 'wp-smart-property' ),
				'sold'     => __( 'Sold', 'wp-smart-property' ),
				'rented'   => __( 'Rented', 'wp-smart-property' ),
			),
			'property_type'   => array(
				'house'      => __( 'House', 'wp-smart-property' ),
				'apartment'  => __( 'Apartment', 'wp-smart-property' ),
				'land'       => __( 'Land', 'wp-smart-property' ),
				'commercial' => __( 'Commercial', 'wp-smart-property' ),
			),
		);
		foreach ( $defaults as $taxonomy => $terms ) {
			foreach ( $terms as $slug => $name ) {
				if ( ! term_exists( $slug, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
				}
			}
		}
	}
}
