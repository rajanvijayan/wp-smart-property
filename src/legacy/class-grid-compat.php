<?php
/**
 * The [post_grid id] shortcode of WP Smart Property 2.0.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Legacy;

use EstateCraft\Frontend\Assets;
use EstateCraft\Frontend\Icons;
use EstateCraft\Frontend\View;
use EstateCraft\Properties\Property_Type;
use WP_Query;

/**
 * 2.0 bundled a modified copy of the Post Grid plugin to list properties. Its grids (post type
 * post_grid, settings in post_grid_meta_options) keep working here: the shortcode reads a grid's
 * post types, terms, order and page size, translates 2.0 terms to the new taxonomies, and shows
 * the posts as EstateCraft cards. Grid layouts, skins and custom CSS/JS are not carried over.
 *
 * When the Post Grid plugin itself is active it owns the post type and shortcode, and nothing here
 * is registered.
 */
final class Grid_Compat {

	const POST_TYPE = 'post_grid';

	const META = 'post_grid_meta_options';

	/**
	 * Order keys a grid may use.
	 */
	const ORDERBY = array( 'date', 'title', 'modified', 'menu_order', 'rand', 'ID', 'comment_count', 'name', 'author' );

	/**
	 * Whether the Post Grid plugin is active.
	 *
	 * @return bool
	 */
	public static function plugin_active() {
		return class_exists( 'PostGrid' )
			|| in_array( 'post-grid/post-grid.php', (array) get_option( 'active_plugins', array() ), true )
			|| ( is_multisite() && array_key_exists( 'post-grid/post-grid.php', (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	/**
	 * Hooks.
	 */
	public static function register() {
		if ( self::plugin_active() ) {
			return;
		}
		add_action( 'init', array( self::class, 'register_type' ) );
		add_shortcode( 'post_grid', array( self::class, 'shortcode' ) );
	}

	/**
	 * Keep the grids (their settings are what the shortcode reads), out of sight.
	 */
	public static function register_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Property grids (2.0)', 'wp-smart-property' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
			)
		);
	}

	/**
	 * Query arguments for a grid's saved settings.
	 *
	 * @param array $options Grid settings (post_grid_meta_options).
	 * @param int   $paged   Page.
	 * @return array WP_Query arguments.
	 */
	public static function query_args( array $options, $paged = 1 ) {
		$types    = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );
		$types    = array_values(
			array_filter(
				array_map( 'sanitize_key', $types ),
				static function ( $type ) {
					$object = get_post_type_object( $type );
					return $object && $object->public;
				}
			)
		);
		$per_page = isset( $options['posts_per_page'] ) ? (int) $options['posts_per_page'] : 0;
		$per_page = $per_page > 0 ? min( $per_page, 100 ) : 12;

		$args = array(
			'post_type'           => $types ? $types : array( Property_Type::POST_TYPE ),
			// Only published posts, whatever the grid asked for (as since 2.0.1).
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => max( 1, (int) $paged ),
			'ignore_sticky_posts' => true,
		);

		$order = isset( $options['query_order'] ) && 'ASC' === strtoupper( (string) $options['query_order'] ) ? 'ASC' : 'DESC';
		$keys  = isset( $options['query_orderby'] ) ? (array) $options['query_orderby'] : array( 'date' );
		$keys  = array_values( array_intersect( array_map( 'strval', $keys ), self::ORDERBY ) );
		if ( $keys ) {
			$args['orderby'] = array_fill_keys( $keys, $order );
		}

		if ( ! empty( $options['offset'] ) && (int) $options['offset'] > 0 ) {
			$args['offset'] = (int) $options['offset'] + ( $args['paged'] - 1 ) * $per_page;
		}
		if ( ! empty( $options['exclude_post_id'] ) ) {
			$exclude = array_filter( array_map( 'absint', explode( ',', (string) $options['exclude_post_id'] ) ) );
			if ( $exclude ) {
				$args['post__not_in'] = $exclude; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- a short list the grid's author chose.
			}
		}

		// Terms are "taxonomy,term_id" pairs, often in the 2.0 taxonomies.
		$terms = array();
		$rooms = array();
		$map   = Migrator::term_map();
		foreach ( isset( $options['categories'] ) ? (array) $options['categories'] : array() as $pair ) {
			$parts = explode( ',', (string) $pair, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$taxonomy = trim( $parts[0] );
			$term_id  = absint( $parts[1] );
			if ( in_array( $taxonomy, array( Legacy_Taxonomies::BEDS, Legacy_Taxonomies::BATHS ), true ) ) {
				$term  = get_term( $term_id, $taxonomy );
				$count = $term && ! is_wp_error( $term ) ? Migrator::parse_count( $term->name, Legacy_Taxonomies::BATHS === $taxonomy ) : null;
				if ( null !== $count ) {
					$key           = Legacy_Taxonomies::BEDS === $taxonomy ? 'beds' : 'baths';
					$rooms[ $key ] = isset( $rooms[ $key ] ) ? min( $rooms[ $key ], $count ) : $count;
				}
				continue;
			}
			if ( isset( $map[ $term_id ] ) ) {
				$new = get_term( $map[ $term_id ] );
				if ( $new && ! is_wp_error( $new ) ) {
					$terms[ $new->taxonomy ][] = (int) $new->term_id;
				}
			} elseif ( taxonomy_exists( $taxonomy ) && ! array_key_exists( $taxonomy, Legacy_Taxonomies::all() ) ) {
				$terms[ $taxonomy ][] = $term_id;
			}
		}
		if ( $terms ) {
			$relation = isset( $options['categories_relation'] ) && 'AND' === strtoupper( (string) $options['categories_relation'] ) ? 'AND' : 'OR';
			$operator = isset( $options['terms_relation'] ) && in_array( strtoupper( (string) $options['terms_relation'] ), array( 'IN', 'NOT IN', 'AND' ), true ) ? strtoupper( (string) $options['terms_relation'] ) : 'IN';
			$tax      = array( 'relation' => $relation );
			foreach ( $terms as $taxonomy => $ids ) {
				$tax[] = array(
					'taxonomy' => $taxonomy,
					'terms'    => array_values( array_unique( $ids ) ),
					'operator' => $operator,
				);
			}
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( $rooms && array( Property_Type::POST_TYPE ) === $args['post_type'] ) {
			$args['estatecraft_index'] = $rooms + array( 'order' => 'none' );
		}

		/**
		 * Filters the query of a 2.0 [post_grid] grid.
		 *
		 * @param array $args    WP_Query arguments.
		 * @param array $options Grid settings.
		 */
		return apply_filters( 'estatecraft_legacy_grid_query', $args, $options );
	}

	/**
	 * Card for a post that is not a property.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function post_card( $post_id ) {
		$link  = get_permalink( $post_id );
		$html  = '<article class="estatecraft-card is-grid">';
		$html .= '<a class="estatecraft-card__media" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">';
		$html .= has_post_thumbnail( $post_id ) ? get_the_post_thumbnail(
			$post_id,
			'medium_large',
			array(
				'class' => 'estatecraft-card__image',
				'alt'   => '',
			)
		) : '<span class="estatecraft-card__placeholder">' . Icons::get( 'home' ) . '</span>';
		$html .= '</a><div class="estatecraft-card__body">';
		$html .= '<h3 class="estatecraft-card__title"><a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></h3>';
		$html .= '<p class="estatecraft-card__address">' . esc_html( wp_trim_words( get_the_excerpt( $post_id ), 20 ) ) . '</p>';
		return $html . '</div></article>';
	}

	/**
	 * The shortcode.
	 *
	 * @param array|string $atts Attributes (id).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'post_grid' );
		$grid = get_post( absint( $atts['id'] ) );
		if ( ! $grid || self::POST_TYPE !== $grid->post_type || 'publish' !== $grid->post_status ) {
			return '';
		}
		$options = get_post_meta( $grid->ID, self::META, true );
		$paged   = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$query   = new WP_Query( self::query_args( is_array( $options ) ? $options : array(), $paged ) );

		$items = '';
		foreach ( $query->posts as $post ) {
			$card   = Property_Type::POST_TYPE === $post->post_type ? View::card( $post->ID ) : self::post_card( $post->ID );
			$items .= '<li class="estatecraft-listings__item">' . $card . '</li>';
		}
		if ( '' === $items ) {
			return '';
		}
		$html  = '<div id="post-grid-' . esc_attr( $grid->ID ) . '" class="estatecraft-listings estatecraft-legacy-grid">';
		$html .= '<ul class="estatecraft-listings__list is-grid" style="--estatecraft-columns:3">' . $items . '</ul>';
		if ( $query->max_num_pages > 1 ) {
			$links = paginate_links(
				array(
					'total'     => (int) $query->max_num_pages,
					'current'   => $paged,
					'prev_text' => __( 'Previous', 'wp-smart-property' ),
					'next_text' => __( 'Next', 'wp-smart-property' ),
				)
			);
			$html .= $links ? '<nav class="estatecraft-pagination" aria-label="' . esc_attr__( 'Grid pages', 'wp-smart-property' ) . '">' . $links . '</nav>' : '';
		}
		Assets::style();
		return $html . '</div>';
	}
}
