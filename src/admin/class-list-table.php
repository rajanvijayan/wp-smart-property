<?php
/**
 * Columns, filters and sorting on the properties list.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Admin;

use EstateCraft\Format;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use WP_Query;

/**
 * Photo, price, rooms, agent and expiry columns; status and type filters; sort by price.
 */
final class List_Table {

	/**
	 * Hooks.
	 */
	public static function register() {
		$type = Property_Type::POST_TYPE;
		add_filter( "manage_{$type}_posts_columns", array( self::class, 'columns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( self::class, 'cell' ), 10, 2 );
		add_filter( "manage_edit-{$type}_sortable_columns", array( self::class, 'sortable' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'filters' ) );
		add_action( 'pre_get_posts', array( self::class, 'sort' ) );
	}

	/**
	 * Columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out['estatecraft_photo'] = '<span class="screen-reader-text">' . esc_html__( 'Photo', 'wp-smart-property' ) . '</span>';
			}
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['estatecraft_price'] = __( 'Price', 'wp-smart-property' );
				$out['estatecraft_rooms'] = __( 'Beds / baths', 'wp-smart-property' );
			}
		}
		$out['estatecraft_agent'] = __( 'Agent', 'wp-smart-property' );
		return $out;
	}

	/**
	 * Cell content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Property.
	 */
	public static function cell( $column, $post_id ) {
		switch ( $column ) {
			case 'estatecraft_photo':
				echo get_the_post_thumbnail( $post_id, array( 60, 60 ), array( 'style' => 'width:60px;height:45px;object-fit:cover;border-radius:3px' ) );
				break;
			case 'estatecraft_price':
				echo esc_html( Format::price( Fields::get( $post_id, 'price' ), Fields::get( $post_id, 'price_period' ), Fields::get( $post_id, 'price_on_request' ) ) );
				if ( Fields::get( $post_id, 'featured' ) ) {
					echo ' <span class="dashicons dashicons-star-filled" style="color:#dba617" title="' . esc_attr__( 'Featured', 'wp-smart-property' ) . '"></span>';
				}
				$expires = Fields::get( $post_id, 'expires' );
				if ( '' !== $expires ) {
					/* translators: %s: date */
					echo '<br /><small>' . esc_html( sprintf( __( 'Expires %s', 'wp-smart-property' ), mysql2date( get_option( 'date_format' ), $expires ) ) ) . '</small>';
				}
				break;
			case 'estatecraft_rooms':
				$beds  = Fields::get( $post_id, 'bedrooms' );
				$baths = Fields::get( $post_id, 'bathrooms' );
				echo $beds || $baths ? esc_html( $beds . ' / ' . $baths ) : '—';
				break;
			case 'estatecraft_agent':
				$names = array();
				foreach ( Fields::get( $post_id, 'agents' ) as $agent ) {
					$names[] = '<a href="' . esc_url( get_edit_post_link( $agent ) ) . '">' . esc_html( get_the_title( $agent ) ) . '</a>';
				}
				echo $names ? wp_kses_post( implode( ', ', $names ) ) : '—';
				break;
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function sortable( $columns ) {
		$columns['estatecraft_price'] = 'estatecraft_price';
		return $columns;
	}

	/**
	 * Status and type filters above the list.
	 *
	 * @param string $post_type Post type.
	 */
	public static function filters( $post_type ) {
		if ( Property_Type::POST_TYPE !== $post_type ) {
			return;
		}
		foreach ( array( 'property_status', 'property_type' ) as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			wp_dropdown_categories(
				array(
					/* translators: %s: taxonomy name, e.g. Statuses */
					'show_option_all' => sprintf( __( 'All %s', 'wp-smart-property' ), strtolower( $object->labels->name ) ),
					'taxonomy'        => $taxonomy,
					'name'            => $taxonomy,
					'value_field'     => 'slug',
					'selected'        => isset( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
					'hierarchical'    => true,
					'hide_empty'      => false,
				)
			);
		}
	}

	/**
	 * Sort by price.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function sort( WP_Query $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'estatecraft_price' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', Fields::PREFIX . 'price' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin list sort.
		$query->set( 'orderby', 'meta_value_num' );
	}
}
