<?php
/**
 * The taxonomies registered by WP Smart Property 2.0.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Legacy;

use EstateCraft\Properties\Property_Type;

/**
 * 2.0 registered six taxonomies under their display names (with spaces). They stay registered,
 * hidden, so the migration can read them and so their old archive URLs can redirect: type and
 * category URLs are taken over by the new taxonomies (same slugs); city, country, bedroom and
 * bathroom URLs redirect to the new location archive or a filtered property archive.
 */
final class Legacy_Taxonomies {

	const TYPE     = 'Property Type';
	const CATEGORY = 'Property Category';
	const CITY     = 'City';
	const COUNTRY  = 'Country';
	const BEDS     = 'Bed Room';
	const BATHS    = 'Bath Room';

	/**
	 * Legacy taxonomies with the URL slug they had (or false when the new taxonomy now owns it).
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			self::TYPE     => false,
			self::CATEGORY => false,
			self::CITY     => 'city',
			self::COUNTRY  => 'country',
			self::BEDS     => 'bed-room',
			self::BATHS    => 'bath-room',
		);
	}

	/**
	 * Register them hidden.
	 */
	public static function register() {
		foreach ( self::all() as $taxonomy => $slug ) {
			register_taxonomy(
				$taxonomy,
				Property_Type::POST_TYPE,
				array(
					'hierarchical'       => true,
					'public'             => false !== $slug,
					'publicly_queryable' => false !== $slug,
					'show_ui'            => false,
					'show_in_menu'       => false,
					'show_in_nav_menus'  => false,
					'show_in_rest'       => false,
					'show_tagcloud'      => false,
					'query_var'          => false !== $slug ? 'estatecraft_legacy_' . $slug : false,
					'rewrite'            => false !== $slug ? array(
						'slug'         => $slug,
						'with_front'   => false,
						'hierarchical' => true,
					) : false,
				)
			);
		}
		add_action( 'template_redirect', array( self::class, 'redirect' ), 1 );
	}

	/**
	 * Redirect old archive URLs to where their content lives now.
	 */
	public static function redirect() {
		foreach ( array( self::CITY, self::COUNTRY, self::BEDS, self::BATHS ) as $taxonomy ) {
			if ( ! is_tax( $taxonomy ) ) {
				continue;
			}
			$term = get_queried_object();
			$url  = $term instanceof \WP_Term ? self::new_url( $term ) : '';
			if ( '' !== $url ) {
				wp_safe_redirect( $url, 301 );
				exit;
			}
		}
	}

	/**
	 * New URL for a legacy term.
	 *
	 * @param \WP_Term $term Legacy term.
	 * @return string
	 */
	public static function new_url( \WP_Term $term ) {
		if ( in_array( $term->taxonomy, array( self::CITY, self::COUNTRY ), true ) ) {
			$map = Migrator::term_map();
			if ( isset( $map[ $term->term_id ] ) ) {
				$link = get_term_link( (int) $map[ $term->term_id ], 'property_location' );
				return is_wp_error( $link ) ? '' : $link;
			}
			return '';
		}
		$count = Migrator::parse_count( $term->name, self::BATHS === $term->taxonomy );
		$base  = get_post_type_archive_link( Property_Type::POST_TYPE );
		if ( null === $count || ! $base ) {
			return (string) $base;
		}
		return add_query_arg( self::BEDS === $term->taxonomy ? 'bedrooms' : 'bathrooms', $count, $base );
	}
}
