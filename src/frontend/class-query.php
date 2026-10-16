<?php
/**
 * Property queries for archives and listing blocks.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Agents\Agent_Types;
use EstateCraft\Properties\Agent_Link;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Properties\Taxonomies;
use EstateCraft\Search\Index;
use EstateCraft\Search\Search;
use WP_Query;

/**
 * Sorting and room filters read the search index (one join) instead of post meta. A query opts in
 * with the estatecraft_index query var: { order, featured, located, beds, baths, price_min,
 * price_max, area_min, area_max, bounds, keyword } (see Search::query_args()).
 */
final class Query {

	const ORDERS = array( 'featured', 'newest', 'price-asc', 'price-desc' );

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'pre_get_posts', array( self::class, 'archives' ) );
		add_filter( 'posts_clauses', array( self::class, 'clauses' ), 10, 2 );
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
			// Archives are search results: the same URL parameters filter and sort them.
			$criteria = Search::from_request();
			$query->set( 'post_type', Property_Type::POST_TYPE );
			$query->set( 'posts_per_page', Search::per_page() );
			$query->set( 'paged', isset( $criteria['pg'] ) ? $criteria['pg'] : max( 1, (int) $query->get( 'paged' ) ) );
			foreach ( Search::query_args( $criteria ) as $key => $value ) {
				$query->set( $key, $value );
			}
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
		$where = '';
		// Minimum rooms (bedrooms/bathrooms are the older names used by listing blocks).
		$beds  = ! empty( $opts['beds'] ) ? $opts['beds'] : ( ! empty( $opts['bedrooms'] ) ? $opts['bedrooms'] : 0 );
		$baths = ! empty( $opts['baths'] ) ? $opts['baths'] : ( ! empty( $opts['bathrooms'] ) ? $opts['bathrooms'] : 0 );
		if ( $beds ) {
			$where .= $wpdb->prepare( ' AND ecx.bedrooms >= %d', $beds );
		}
		if ( $baths ) {
			$where .= $wpdb->prepare( ' AND ecx.bathrooms >= %f', $baths );
		}
		foreach ( array(
			'price_min' => 'ecx.price >= %f',
			'price_max' => 'ecx.price <= %f',
			'area_min'  => 'ecx.area >= %f',
			'area_max'  => 'ecx.area <= %f',
		) as $key => $sql ) {
			if ( ! empty( $opts[ $key ] ) ) {
				$where .= $wpdb->prepare( ' AND ' . $sql, $opts[ $key ] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- fixed SQL fragments with placeholders.
			}
		}
		// A price range leaves out properties without a price.
		if ( ! empty( $opts['price_min'] ) || ! empty( $opts['price_max'] ) ) {
			$where .= ' AND ecx.price > 0';
		}
		if ( ! empty( $opts['featured'] ) ) {
			$where .= ' AND ecx.featured = 1';
		}
		if ( ! empty( $opts['located'] ) || ! empty( $opts['bounds'] ) ) {
			$where .= ' AND ( ecx.lat <> 0 OR ecx.lng <> 0 )';
		}
		if ( ! empty( $opts['bounds'] ) && 4 === count( $opts['bounds'] ) ) {
			list( $south, $west, $north, $east ) = array_map( 'floatval', $opts['bounds'] );
			$where                              .= $wpdb->prepare( ' AND ecx.lat BETWEEN %f AND %f', $south, $north );
			// A box across the 180th meridian has west > east.
			$where .= $west <= $east
				? $wpdb->prepare( ' AND ecx.lng BETWEEN %f AND %f', $west, $east )
				: $wpdb->prepare( ' AND ( ecx.lng >= %f OR ecx.lng <= %f )', $west, $east );
		}
		if ( ! empty( $opts['keyword'] ) ) {
			// Title, excerpt and content, or the listing reference. Not WordPress search (s=), which
			// would turn an archive into a search results page.
			$like   = '%' . $wpdb->esc_like( $opts['keyword'] ) . '%';
			$where .= $wpdb->prepare(
				" AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS ecm WHERE ecm.post_id = {$wpdb->posts}.ID AND ecm.meta_key = %s AND ecm.meta_value = %s ) )",
				$like,
				$like,
				$like,
				Fields::PREFIX . 'reference',
				$opts['keyword']
			);
		}
		$clauses['where'] .= $where;
		$date              = "{$wpdb->posts}.post_date DESC";
		$order             = isset( $opts['order'] ) && in_array( $opts['order'], self::ORDERS, true ) ? $opts['order'] : 'featured';
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
			$id = (int) $args['similar'];
			// One extra, so the property itself can be dropped afterwards (cheaper than post__not_in).
			++$query['posts_per_page'];
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
		$result = new WP_Query( apply_filters( 'estatecraft_listings_query', $query, $args ) );
		if ( $args['similar'] ) {
			$result->posts      = array_slice(
				array_values(
					array_filter(
						$result->posts,
						static function ( $post ) use ( $args ) {
							return (int) $post->ID !== (int) $args['similar'];
						}
					)
				),
				0,
				max( 1, (int) $args['count'] )
			);
			$result->post_count = count( $result->posts );
		}
		return $result;
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
