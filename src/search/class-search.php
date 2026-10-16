<?php
/**
 * Property search: criteria from the URL, the query, and map markers.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Search;

use EstateCraft\Frontend\View;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Settings;
use WP_Query;

/**
 * Criteria use short, shareable URL parameters, the same in links, the search form, the archive
 * page and the REST API: keyword, type, status, location, feature (lists are comma separated), price_min,
 * price_max, beds, baths (minimums), area_min, area_max, cf_{key} (custom fields marked as filters),
 * bounds (south,west,north,east), sort, view and pg (page).
 *
 * The normalize() method is the only place values are read and cleaned; everything else uses its result.
 */
final class Search {

	const SORTS = array( 'featured', 'newest', 'price-asc', 'price-desc' );

	const VIEWS = array( 'list', 'map', 'split' );

	const TAXONOMIES = array(
		'type'     => 'property_type',
		'status'   => 'property_status',
		'location' => 'property_location',
		'feature'  => 'property_feature',
	);

	/**
	 * Most markers sent for one map.
	 */
	const MAX_MARKERS = 500;

	/**
	 * Results per page.
	 *
	 * @return int
	 */
	public static function per_page() {
		/**
		 * Filters the number of properties per search or archive page.
		 *
		 * @param int $per_page Properties per page.
		 */
		return max( 1, min( 48, (int) apply_filters( 'estatecraft_archive_per_page', 12 ) ) );
	}

	/**
	 * Custom fields that can be searched.
	 *
	 * @return array[]
	 */
	public static function custom_filters() {
		return array_values(
			array_filter(
				Settings::get( 'fields' ),
				static function ( $field ) {
					return ! empty( $field['search'] );
				}
			)
		);
	}

	/**
	 * Clean criteria.
	 *
	 * @param array $input Raw parameters (e.g. $_GET or REST params).
	 * @return array Only the criteria that are set, cleaned.
	 */
	public static function normalize( array $input ) {
		$out    = array();
		$text   = static function ( $key ) use ( $input ) {
			return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? trim( sanitize_text_field( wp_unslash( (string) $input[ $key ] ) ) ) : '';
		};
		$number = static function ( $key ) use ( $input ) {
			if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
				return 0;
			}
			$value = str_replace( array( ',', ' ' ), '', (string) $input[ $key ] );
			return is_numeric( $value ) ? max( 0, (float) $value ) : 0;
		};

		$keyword = $text( 'keyword' );
		if ( '' !== $keyword ) {
			$out['keyword'] = function_exists( 'mb_substr' ) ? mb_substr( $keyword, 0, 100 ) : substr( $keyword, 0, 100 );
		}
		foreach ( self::TAXONOMIES as $key => $taxonomy ) {
			$raw   = isset( $input[ $key ] ) ? $input[ $key ] : '';
			$raw   = is_array( $raw ) ? $raw : explode( ',', is_scalar( $raw ) ? (string) $raw : '' );
			$slugs = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_title', array_map( 'strval', $raw ) ) ) ) ), 0, 20 );
			if ( $slugs ) {
				$out[ $key ] = $slugs;
			}
		}
		foreach ( array( 'price_min', 'price_max', 'area_min', 'area_max' ) as $key ) {
			if ( $number( $key ) ) {
				$out[ $key ] = $number( $key );
			}
		}
		// The 2.0 bedroom/bathroom archive redirects use bedrooms= and bathrooms=.
		$beds  = $number( 'beds' ) ? $number( 'beds' ) : $number( 'bedrooms' );
		$baths = $number( 'baths' ) ? $number( 'baths' ) : $number( 'bathrooms' );
		if ( $beds ) {
			$out['beds'] = (int) min( 50, $beds );
		}
		if ( $baths ) {
			$out['baths'] = min( 50, round( $baths * 2 ) / 2 );
		}
		foreach ( self::custom_filters() as $field ) {
			$key   = 'cf_' . $field['key'];
			$value = $text( $key );
			if ( '' === $value ) {
				continue;
			}
			if ( 'number' === $field['type'] ) {
				$value = $number( $key );
			} elseif ( 'checkbox' === $field['type'] ) {
				$value = rest_sanitize_boolean( $value ) ? 1 : 0;
			} elseif ( 'select' === $field['type'] && ! in_array( $value, $field['options'], true ) ) {
				$value = '';
			} elseif ( 'date' === $field['type'] ) {
				$value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
			}
			if ( $value ) {
				$out[ $key ] = $value;
			}
		}
		$bounds = array_map( 'floatval', explode( ',', $text( 'bounds' ) ) );
		if ( 4 === count( $bounds ) && $bounds[0] < $bounds[2] && $bounds[0] >= -90 && $bounds[2] <= 90 && $bounds[1] >= -180 && $bounds[3] <= 180 ) {
			$out['bounds'] = array_map(
				static function ( $value ) {
					return round( $value, 5 );
				},
				$bounds
			);
		}
		$sort = $text( 'sort' );
		if ( in_array( $sort, self::SORTS, true ) && 'featured' !== $sort ) {
			$out['sort'] = $sort;
		}
		$view = $text( 'view' );
		if ( in_array( $view, self::VIEWS, true ) ) {
			$out['view'] = $view;
		}
		$page = absint( $text( 'pg' ) );
		if ( $page > 1 ) {
			$out['pg'] = min( $page, 1000 );
		}
		return $out;
	}

	/**
	 * Criteria of the current request.
	 *
	 * @return array
	 */
	public static function from_request() {
		return self::normalize( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only search parameters, cleaned in normalize().
	}

	/**
	 * Criteria as URL parameters (lists comma separated, defaults left out).
	 *
	 * @param array $criteria Clean criteria.
	 * @return array
	 */
	public static function to_params( array $criteria ) {
		$params = array();
		foreach ( $criteria as $key => $value ) {
			$params[ $key ] = is_array( $value ) ? implode( ',', $value ) : $value;
		}
		return $params;
	}

	/**
	 * WP_Query arguments, merged into $args.
	 *
	 * @param array $criteria Clean criteria.
	 * @param array $args     Other arguments.
	 * @return array
	 */
	public static function query_args( array $criteria, array $args = array() ) {
		$tax = array();
		foreach ( self::TAXONOMIES as $key => $taxonomy ) {
			if ( empty( $criteria[ $key ] ) ) {
				continue;
			}
			// Several features must all be present; other lists match any of their terms.
			$tax[] = array(
				'taxonomy'         => $taxonomy,
				'field'            => 'slug',
				'terms'            => $criteria[ $key ],
				'operator'         => 'feature' === $key ? 'AND' : 'IN',
				'include_children' => 'property_location' === $taxonomy,
			);
		}
		if ( $tax ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), isset( $args['tax_query'] ) ? $args['tax_query'] : array(), $tax ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$meta = array();
		foreach ( self::custom_filters() as $field ) {
			$key = 'cf_' . $field['key'];
			if ( ! isset( $criteria[ $key ] ) ) {
				continue;
			}
			switch ( $field['type'] ) {
				case 'number':
					$meta[] = array(
						'key'     => Fields::PREFIX . $key,
						'value'   => $criteria[ $key ],
						'compare' => '>=',
						'type'    => 'DECIMAL(15,2)',
					);
					break;
				case 'text':
					$meta[] = array(
						'key'     => Fields::PREFIX . $key,
						'value'   => $criteria[ $key ],
						'compare' => 'LIKE',
					);
					break;
				case 'date':
					$meta[] = array(
						'key'     => Fields::PREFIX . $key,
						'value'   => $criteria[ $key ],
						'compare' => '>=',
						'type'    => 'DATE',
					);
					break;
				default:
					$meta[] = array(
						'key'   => Fields::PREFIX . $key,
						'value' => 'checkbox' === $field['type'] ? '1' : $criteria[ $key ],
					);
			}
		}
		if ( $meta ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only for custom fields the site marked as filters.
		}

		$index = isset( $args['estatecraft_index'] ) && is_array( $args['estatecraft_index'] ) ? $args['estatecraft_index'] : array();
		foreach ( array( 'keyword', 'price_min', 'price_max', 'area_min', 'area_max', 'beds', 'baths', 'bounds' ) as $key ) {
			if ( isset( $criteria[ $key ] ) ) {
				$index[ $key ] = $criteria[ $key ];
			}
		}
		$index['order']            = isset( $criteria['sort'] ) ? $criteria['sort'] : 'featured';
		$args['estatecraft_index'] = $index;
		return $args;
	}

	/**
	 * Run a search.
	 *
	 * @param array $criteria Clean criteria.
	 * @return WP_Query
	 */
	public static function run( array $criteria ) {
		return new WP_Query(
			self::query_args(
				$criteria,
				array(
					'post_type'           => Property_Type::POST_TYPE,
					'post_status'         => 'publish',
					'posts_per_page'      => self::per_page(),
					'paged'               => isset( $criteria['pg'] ) ? $criteria['pg'] : 1,
					'ignore_sticky_posts' => true,
				)
			)
		);
	}

	/**
	 * Map markers for all matches (not only the current page), up to MAX_MARKERS.
	 *
	 * Coordinates come from the index, which holds the approximate area for properties whose exact
	 * address is hidden, so bounds searches cannot reveal it either.
	 *
	 * @param array $criteria Clean criteria.
	 * @return array { markers: array[], total: int, capped: bool }
	 */
	public static function markers( array $criteria ) {
		global $wpdb;
		unset( $criteria['pg'] );
		$query = new WP_Query(
			self::query_args(
				$criteria,
				array(
					'post_type'              => Property_Type::POST_TYPE,
					'post_status'            => 'publish',
					'posts_per_page'         => self::MAX_MARKERS,
					'fields'                 => 'ids',
					'ignore_sticky_posts'    => true,
					'update_post_term_cache' => false,
					'estatecraft_index'      => array( 'located' => true ),
				)
			)
		);
		$ids   = array_map( 'intval', $query->posts );
		if ( ! $ids ) {
			return array(
				'markers' => array(),
				'total'   => 0,
				'capped'  => false,
			);
		}
		$table = Index::table();
		$in    = implode( ',', $ids );
		$rows  = $wpdb->get_results( "SELECT post_id, lat, lng FROM {$table} WHERE post_id IN ({$in})", OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer IDs from WP_Query, derived index table.
		_prime_post_caches( $ids, false, true );

		$markers = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $rows[ $id ] ) ) {
				continue;
			}
			$image     = View::main_image( $id );
			$markers[] = array(
				'id'          => $id,
				'lat'         => (float) $rows[ $id ]->lat,
				'lng'         => (float) $rows[ $id ]->lng,
				'approximate' => (bool) Fields::get( $id, 'hide_address' ),
				'label'       => View::short_price( $id ),
				'price'       => View::price( $id ),
				'title'       => get_the_title( $id ),
				'url'         => get_permalink( $id ),
				'image'       => $image ? (string) wp_get_attachment_image_url( $image, 'medium' ) : '',
				'facts'       => wp_strip_all_tags( implode( ' · ', wp_list_pluck( View::facts( $id, true ), 'value' ) ) ),
			);
		}
		return array(
			'markers' => $markers,
			'total'   => (int) $query->found_posts,
			'capped'  => $query->found_posts > self::MAX_MARKERS,
		);
	}

	/**
	 * Where search forms send visitors: the search page from the settings, or the archive.
	 *
	 * @return string
	 */
	public static function results_url() {
		$page = (int) Settings::get( 'pages' )['search'];
		if ( $page && 'publish' === get_post_status( $page ) ) {
			return (string) get_permalink( $page );
		}
		$archive = get_post_type_archive_link( Property_Type::POST_TYPE );
		return $archive ? $archive : home_url( '/' );
	}
}
