<?php
/**
 * Property queries for archives and listing blocks.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Agents\Agent_Types;
use EstateCraft\Properties\Agent_Link;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Properties\Taxonomies;
use EstateCraft\Search\Index;
use WP_Query;

/**
 * Sorting and room filters read the search index (one join) instead of post meta. A query opts in
 * with the estatecraft_index query var: { order, bedrooms, bathrooms, featured }.
 */
final class Query {

	const ORDERS = array( 'featured', 'newest', 'price-asc', 'price-desc' );

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'pre_get_posts', array( self::class, 'archives' ) );
		add_filter( 'posts_clauses', array( self::class, 'clauses' ), 10, 2 );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
	}

	/**
	 * Public query vars (the 2.0 bedroom and bathroom archives redirect to these).
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'bedrooms';
		$vars[] = 'bathrooms';
		return $vars;
	}

	/**
	 * Whether the main query is a property archive (post type or one of the taxonomies).
	 *
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	public static function is_property_archive( WP_Query $query ) {
		return $query->is_post_type_archive( Property_Type::POST_TYPE ) || $query->is_tax( array_keys( Taxonomies::all() ) );
	}

	/**
	 * Main archive queries: featured first, room filters, page size.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function archives( WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( self::is_property_archive( $query ) ) {
			$query->set( 'post_type', Property_Type::POST_TYPE );
			/**
			 * Filters the number of properties per archive page.
			 *
			 * @param int $per_page Properties per page.
			 */
			$query->set( 'posts_per_page', (int) apply_filters( 'estatecraft_archive_per_page', 12 ) );
			$query->set(
				'estatecraft_index',
				array(
					'order'     => 'featured',
					'bedrooms'  => absint( $query->get( 'bedrooms' ) ),
					'bathrooms' => (float) $query->get( 'bathrooms' ),
				)
			);
		} elseif ( $query->is_post_type_archive( array( Agent_Types::AGENT, Agent_Types::AGENCY ) ) ) {
			$query->set( 'posts_per_page', 24 );
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * Join the index for queries that ask for it.
	 *
	 * @param string[] $clauses Clauses.
	 * @param WP_Query $query   Query.
	 * @return string[]
	 */
	public static function clauses( $clauses, $query ) {
		$opts = $query->get( 'estatecraft_index' );
		if ( ! is_array( $opts ) ) {
			return $clauses;
		}
		global $wpdb;
		$table            = Index::table();
		$clauses['join'] .= " LEFT JOIN {$table} AS ecx ON ecx.post_id = {$wpdb->posts}.ID";
		// A property linked to two of the agents asked for must still appear once.
		if ( empty( $clauses['groupby'] ) ) {
			$clauses['groupby'] = "{$wpdb->posts}.ID";
		}
		if ( ! empty( $opts['bedrooms'] ) ) {
			$clauses['where'] .= $wpdb->prepare( ' AND ecx.bedrooms >= %d', $opts['bedrooms'] );
		}
		if ( ! empty( $opts['bathrooms'] ) ) {
			$clauses['where'] .= $wpdb->prepare( ' AND ecx.bathrooms >= %f', $opts['bathrooms'] );
		}
		if ( ! empty( $opts['featured'] ) ) {
			$clauses['where'] .= ' AND ecx.featured = 1';
		}
		$date  = "{$wpdb->posts}.post_date DESC";
		$order = isset( $opts['order'] ) && in_array( $opts['order'], self::ORDERS, true ) ? $opts['order'] : 'featured';
		switch ( $order ) {
			case 'newest':
				$clauses['orderby'] = $date;
				break;
			case 'price-asc':
				// Properties without a price (on request) last.
				$clauses['orderby'] = "COALESCE(ecx.price, 0) = 0, ecx.price ASC, $date";
				break;
			case 'price-desc':
				$clauses['orderby'] = "ecx.price DESC, $date";
				break;
			default:
				$clauses['orderby'] = "COALESCE(ecx.featured, 0) DESC, $date";
		}
		return $clauses;
	}

	/**
	 * Properties for a listing block.
	 *
	 * @param array $args {
	 *     Listing options.
	 *
	 *     @type int    $count    Number of properties.
	 *     @type string $order    featured, newest, price-asc or price-desc.
	 *     @type bool   $featured Only featured properties.
	 *     @type array  $terms    taxonomy => term slugs.
	 *     @type int[]  $agents   Only properties of these agents.
	 *     @type int    $similar  Properties like this one.
	 *     @type int    $paged    Page.
	 * }
	 * @return WP_Query
	 */
	public static function listings( array $args ) {
		$args  = wp_parse_args(
			$args,
			array(
				'count'    => 6,
				'order'    => 'featured',
				'featured' => false,
				'terms'    => array(),
				'agents'   => null,
				'similar'  => 0,
				'paged'    => 1,
			)
		);
		$query = array(
			'post_type'           => Property_Type::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 48, (int) $args['count'] ) ),
			'paged'               => max( 1, (int) $args['paged'] ),
			'ignore_sticky_posts' => true,
			'estatecraft_index'   => array(
				'order'    => $args['order'],
				'featured' => (bool) $args['featured'],
			),
		);
		$tax   = array();
		foreach ( (array) $args['terms'] as $taxonomy => $slugs ) {
			$slugs = array_filter( array_map( 'sanitize_title', (array) $slugs ) );
			if ( $slugs && taxonomy_exists( $taxonomy ) ) {
				$tax[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slugs,
				);
			}
		}
		if ( $args['similar'] ) {
			$id                    = (int) $args['similar'];
			$query['post__not_in'] = array( $id );
			foreach ( array( 'property_type', 'property_status' ) as $taxonomy ) {
				$ids = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
				if ( $ids && ! is_wp_error( $ids ) ) {
					$tax[] = array(
						'taxonomy' => $taxonomy,
						'terms'    => $ids,
					);
				}
			}
			$query['estatecraft_index']['order'] = 'newest';
		}
		if ( $tax ) {
			$query['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( null !== $args['agents'] ) {
			$agents = array_filter( array_map( 'absint', (array) $args['agents'] ) );
			if ( ! $agents ) {
				$query['post__in'] = array( 0 );
			} else {
				$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed meta_key/value lookup.
					array(
						'key'     => Agent_Link::KEY,
						'value'   => $agents,
						'compare' => 'IN',
						'type'    => 'NUMERIC',
					),
				);
			}
		}

		/**
		 * Filters the query arguments of a property listing block.
		 *
		 * @param array $query WP_Query arguments.
		 * @param array $args  Block options.
		 */
		return new WP_Query( apply_filters( 'estatecraft_listings_query', $query, $args ) );
	}

	/**
	 * Agents of an agency.
	 *
	 * @param int $agency_id Agency ID.
	 * @return int[]
	 */
	public static function agency_agents( $agency_id ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => Agent_Types::AGENT,
					'fields'      => 'ids',
					'numberposts' => 100,
					'orderby'     => 'title',
					'order'       => 'ASC',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small lookup.
						array(
							'key'   => '_estatecraft_agency',
							'value' => (int) $agency_id,
							'type'  => 'NUMERIC',
						),
					),
				)
			)
		);
	}
}
