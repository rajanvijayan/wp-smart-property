<?php
/**
 * Search blocks: the filter form and the results (list, map or both).
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Format;
use EstateCraft\Search\Search;
use EstateCraft\Settings;
use WP_Block;
use WP_Query;

/**
 * Both work without JavaScript: the form is a GET form to the results page, the results are
 * rendered on the server from the URL. The search script then updates results, URL and map in
 * place.
 */
final class Search_Blocks {

	const FIELDS = array( 'keyword', 'location', 'type', 'status', 'price', 'beds', 'baths' );

	/**
	 * "12 properties".
	 *
	 * @param int $total Matches.
	 * @return string
	 */
	public static function summary( $total ) {
		return $total
			/* translators: %s: number of properties */
			? sprintf( _n( '%s property', '%s properties', $total, 'wp-smart-property' ), number_format_i18n( $total ) )
			: __( 'No properties match your search.', 'wp-smart-property' );
	}

	/**
	 * Result cards.
	 *
	 * @param WP_Query $query  Results.
	 * @param string   $layout grid or list.
	 * @return string
	 */
	public static function cards( WP_Query $query, $layout = 'grid' ) {
		$html = '';
		foreach ( $query->posts as $post ) {
			$html .= '<li class="estatecraft-listings__item" data-id="' . esc_attr( $post->ID ) . '">' . View::card( $post->ID, 'h2', $layout ) . '</li>';
		}
		return $html;
	}

	/**
	 * Page links that keep the criteria (?pg=N).
	 *
	 * @param WP_Query $query    Results.
	 * @param array    $criteria Clean criteria.
	 * @param string   $base     Page URL without query.
	 * @return string
	 */
	public static function pagination( WP_Query $query, array $criteria, $base ) {
		if ( $query->max_num_pages < 2 ) {
			return '';
		}
		unset( $criteria['pg'] );
		// Built here rather than with paginate_links(), which adds the current request's query
		// string to every link (in a REST request, the API's own parameters).
		$params  = Search::to_params( $criteria );
		$total   = (int) $query->max_num_pages;
		$current = max( 1, min( $total, (int) $query->get( 'paged' ) ) );
		$link    = static function ( $page ) use ( $params, $base ) {
			return add_query_arg( $page > 1 ? array_merge( $params, array( 'pg' => $page ) ) : $params, $base );
		};
		$links   = '';
		if ( $current > 1 ) {
			$links .= '<a class="prev page-numbers" href="' . esc_url( $link( $current - 1 ) ) . '">' . esc_html__( 'Previous', 'wp-smart-property' ) . '</a>';
		}
		$gap = false;
		for ( $page = 1; $page <= $total; $page++ ) {
			if ( 1 !== $page && $total !== $page && abs( $page - $current ) > 1 ) {
				if ( ! $gap ) {
					$links .= '<span class="page-numbers dots">&hellip;</span>';
					$gap    = true;
				}
				continue;
			}
			$gap    = false;
			$links .= $page === $current
				? '<span aria-current="page" class="page-numbers current">' . esc_html( number_format_i18n( $page ) ) . '</span>'
				: '<a class="page-numbers" href="' . esc_url( $link( $page ) ) . '">' . esc_html( number_format_i18n( $page ) ) . '</a>';
		}
		if ( $current < $total ) {
			$links .= '<a class="next page-numbers" href="' . esc_url( $link( $current + 1 ) ) . '">' . esc_html__( 'Next', 'wp-smart-property' ) . '</a>';
		}
		return $links ? '<nav class="estatecraft-pagination" aria-label="' . esc_attr__( 'Results pages', 'wp-smart-property' ) . '">' . $links . '</nav>' : '';
	}

	/**
	 * The term of the property taxonomy archive being viewed, as criteria.
	 *
	 * @return array
	 */
	public static function archive_term() {
		if ( ! is_tax( array_values( Search::TAXONOMIES ) ) ) {
			return array();
		}
		$term = get_queried_object();
		$key  = $term instanceof \WP_Term ? array_search( $term->taxonomy, Search::TAXONOMIES, true ) : false;
		return false === $key ? array() : array( $key => array( $term->slug ) );
	}

	/**
	 * Hidden inputs for an action URL's own query (plain permalinks: ?post_type=property).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function action_inputs( $url ) {
		$html  = '';
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $args );
		foreach ( $args as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}
		}
		return $html;
	}

	/**
	 * Options of a select.
	 *
	 * @param array  $options value => label.
	 * @param string $current Selected value.
	 * @return string
	 */
	private static function options( array $options, $current ) {
		$html = '';
		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( (string) $value, (string) $current, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html;
	}

	/**
	 * Terms of a taxonomy as options, children indented.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $any      Label of the empty option.
	 * @return array
	 */
	private static function term_options( $taxonomy, $any ) {
		$options = array( '' => $any );
		$terms   = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => 'property_location' === $taxonomy,
				'orderby'    => 'name',
				'number'     => 500,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $options;
		}
		$children = array();
		foreach ( $terms as $term ) {
			$children[ (int) $term->parent ][] = $term;
		}
		$walk = static function ( $parent_id, $depth ) use ( &$walk, &$options, $children ) {
			foreach ( isset( $children[ $parent_id ] ) ? $children[ $parent_id ] : array() as $term ) {
				$options[ $term->slug ] = str_repeat( "\u{2003}", $depth ) . $term->name;
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		// Terms whose parent is not listed (hidden because empty) still appear.
		foreach ( $terms as $term ) {
			if ( ! isset( $options[ $term->slug ] ) ) {
				$options[ $term->slug ] = $term->name;
			}
		}
		return $options;
	}

	/**
	 * One labelled field.
	 *
	 * @param string $id      Input ID.
	 * @param string $label   Label.
	 * @param string $control Control markup.
	 * @param string $key     Field key (class).
	 * @return string
	 */
	private static function field( $id, $label, $control, $key ) {
		return '<div class="estatecraft-search__field is-' . esc_attr( $key ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>' . $control . '</div>';
	}

	/**
	 * The search form.
	 *
	 * @param array    $attributes Attributes (fields, more, buttonText, layout).
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function form( $attributes, $content, $block ) {
		static $count = 0;
		++$count;
		$p = 'estatecraft-search-' . $count . '-';
		// On a type, status, location or feature archive its term is part of the search.
		$criteria = Search::from_request() + self::archive_term();
		$fields   = ! empty( $attributes['fields'] ) && is_array( $attributes['fields'] ) ? $attributes['fields'] : self::FIELDS;
		$value    = static function ( $key ) use ( $criteria ) {
			if ( ! isset( $criteria[ $key ] ) ) {
				return '';
			}
			return is_array( $criteria[ $key ] ) ? (string) reset( $criteria[ $key ] ) : (string) $criteria[ $key ];
		};
		$symbol   = Settings::get( 'general' )['symbol'];
		$main     = '';
		foreach ( $fields as $key ) {
			switch ( $key ) {
				case 'keyword':
					$main .= self::field( $p . 'keyword', __( 'Keyword', 'wp-smart-property' ), '<input type="search" id="' . esc_attr( $p ) . 'keyword" name="keyword" value="' . esc_attr( $value( 'keyword' ) ) . '" placeholder="' . esc_attr__( 'Street, area or reference', 'wp-smart-property' ) . '" autocomplete="off" />', $key );
					break;
				case 'location':
				case 'type':
				case 'status':
					$labels = array(
						'location' => array( __( 'Location', 'wp-smart-property' ), __( 'Any location', 'wp-smart-property' ) ),
						'type'     => array( __( 'Property type', 'wp-smart-property' ), __( 'Any type', 'wp-smart-property' ) ),
						'status'   => array( __( 'For sale or rent', 'wp-smart-property' ), __( 'Any', 'wp-smart-property' ) ),
					);
					$main  .= self::field( $p . $key, $labels[ $key ][0], '<select id="' . esc_attr( $p . $key ) . '" name="' . esc_attr( $key ) . '">' . self::options( self::term_options( Search::TAXONOMIES[ $key ], $labels[ $key ][1] ), $value( $key ) ) . '</select>', $key );
					break;
				case 'price':
					$main .= '<fieldset class="estatecraft-search__field is-price"><legend>' . esc_html__( 'Price', 'wp-smart-property' ) . '</legend><div class="estatecraft-search__range">'
						. '<label class="screen-reader-text" for="' . esc_attr( $p ) . 'price_min">' . esc_html__( 'Minimum price', 'wp-smart-property' ) . '</label>'
						/* translators: %s: currency symbol */
						. '<input type="number" inputmode="numeric" min="0" step="any" id="' . esc_attr( $p ) . 'price_min" name="price_min" value="' . esc_attr( $value( 'price_min' ) ) . '" placeholder="' . esc_attr( sprintf( __( 'Min %s', 'wp-smart-property' ), $symbol ) ) . '" />'
						. '<span aria-hidden="true">–</span>'
						. '<label class="screen-reader-text" for="' . esc_attr( $p ) . 'price_max">' . esc_html__( 'Maximum price', 'wp-smart-property' ) . '</label>'
						/* translators: %s: currency symbol */
						. '<input type="number" inputmode="numeric" min="0" step="any" id="' . esc_attr( $p ) . 'price_max" name="price_max" value="' . esc_attr( $value( 'price_max' ) ) . '" placeholder="' . esc_attr( sprintf( __( 'Max %s', 'wp-smart-property' ), $symbol ) ) . '" />'
						. '</div></fieldset>';
					break;
				case 'beds':
				case 'baths':
					$opts = array( '' => __( 'Any', 'wp-smart-property' ) );
					foreach ( range( 1, 'beds' === $key ? 6 : 4 ) as $n ) {
						/* translators: %d: minimum number of rooms */
						$opts[ $n ] = sprintf( __( '%d+', 'wp-smart-property' ), $n );
					}
					$main .= self::field( $p . $key, 'beds' === $key ? __( 'Bedrooms', 'wp-smart-property' ) : __( 'Bathrooms', 'wp-smart-property' ), '<select id="' . esc_attr( $p . $key ) . '" name="' . esc_attr( $key ) . '">' . self::options( $opts, $value( $key ) ) . '</select>', $key );
					break;
			}
		}

		$more = '';
		if ( $attributes['more'] ?? true ) {
			$unit  = Format::unit();
			$more .= '<fieldset class="estatecraft-search__field is-area"><legend>' . esc_html(
				/* translators: %s: area unit */
				sprintf( __( 'Living area (%s)', 'wp-smart-property' ), $unit )
			) . '</legend><div class="estatecraft-search__range">'
				. '<label class="screen-reader-text" for="' . esc_attr( $p ) . 'area_min">' . esc_html__( 'Minimum area', 'wp-smart-property' ) . '</label><input type="number" inputmode="numeric" min="0" id="' . esc_attr( $p ) . 'area_min" name="area_min" value="' . esc_attr( $value( 'area_min' ) ) . '" placeholder="' . esc_attr__( 'Min', 'wp-smart-property' ) . '" />'
				. '<span aria-hidden="true">–</span>'
				. '<label class="screen-reader-text" for="' . esc_attr( $p ) . 'area_max">' . esc_html__( 'Maximum area', 'wp-smart-property' ) . '</label><input type="number" inputmode="numeric" min="0" id="' . esc_attr( $p ) . 'area_max" name="area_max" value="' . esc_attr( $value( 'area_max' ) ) . '" placeholder="' . esc_attr__( 'Max', 'wp-smart-property' ) . '" />'
				. '</div></fieldset>';
			foreach ( Search::custom_filters() as $field ) {
				$key = 'cf_' . $field['key'];
				$id  = $p . $key;
				switch ( $field['type'] ) {
					case 'select':
						$control = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '">' . self::options( array( '' => __( 'Any', 'wp-smart-property' ) ) + array_combine( $field['options'], $field['options'] ), $value( $key ) ) . '</select>';
						break;
					case 'checkbox':
						$more .= '<div class="estatecraft-search__field is-check"><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( '1', $value( $key ), false ) . ' /> ' . esc_html( $field['label'] ) . '</label></div>';
						continue 2;
					case 'number':
						$control = '<input type="number" min="0" step="any" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value( $key ) ) . '" placeholder="' . esc_attr__( 'At least', 'wp-smart-property' ) . '" />';
						break;
					case 'date':
						$control = '<input type="date" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value( $key ) ) . '" />';
						break;
					default:
						$control = '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value( $key ) ) . '" />';
				}
				$more .= self::field( $id, $field['label'], $control, 'custom' );
			}
			$features = get_terms(
				array(
					'taxonomy'   => 'property_feature',
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 30,
				)
			);
			if ( $features && ! is_wp_error( $features ) ) {
				$chosen = isset( $criteria['feature'] ) ? $criteria['feature'] : array();
				$boxes  = '';
				foreach ( $features as $term ) {
					$boxes .= '<label><input type="checkbox" name="feature[]" value="' . esc_attr( $term->slug ) . '"' . checked( in_array( $term->slug, $chosen, true ), true, false ) . ' /> ' . esc_html( $term->name ) . '</label>';
				}
				$more .= '<fieldset class="estatecraft-search__features"><legend>' . esc_html__( 'Features', 'wp-smart-property' ) . '</legend>' . $boxes . '</fieldset>';
			}
		}

		$action = Search::results_url();
		$html   = '<form class="estatecraft-search__form" method="get" action="' . esc_url( strtok( $action, '?' ) ) . '" role="search" aria-label="' . esc_attr__( 'Property search', 'wp-smart-property' ) . '" data-estatecraft-search>';
		$html  .= self::action_inputs( $action );
		$html  .= '<div class="estatecraft-search__main">' . $main . '<div class="estatecraft-search__submit"><button type="submit" class="estatecraft-button">' . esc_html( ! empty( $attributes['buttonText'] ) ? $attributes['buttonText'] : __( 'Search', 'wp-smart-property' ) ) . '</button></div></div>';
		if ( '' !== $more ) {
			$open  = array_intersect( array_keys( $criteria ), array( 'area_min', 'area_max', 'feature' ) ) || preg_grep( '/^cf_/', array_keys( $criteria ) );
			$html .= '<details class="estatecraft-search__more"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html__( 'More filters', 'wp-smart-property' ) . '</summary><div class="estatecraft-search__more-fields">' . $more . '</div></details>';
		}
		// Keep the sort order and view when searching again.
		foreach ( array( 'sort', 'view' ) as $key ) {
			if ( isset( $criteria[ $key ] ) ) {
				$html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $criteria[ $key ] ) . '" />';
			}
		}
		$html .= '</form>';
		unset( $content, $block );
		return Blocks::wrap( $html, 'estatecraft-search is-' . ( 'stacked' === ( $attributes['layout'] ?? 'bar' ) ? 'stacked' : 'bar' ) );
	}

	/**
	 * Search results with sorting, list/map views and page links.
	 *
	 * @param array    $attributes Attributes (view, layout, columns, mapHeight).
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function results( $attributes, $content, $block ) {
		global $wp_query;
		$criteria = Search::from_request();
		$archive  = $wp_query instanceof WP_Query && Query::is_property_archive( $wp_query );
		// On property archives the main query is already this search.
		$query  = $archive ? $wp_query : Search::run( $criteria );
		$base   = $archive ? strtok( get_pagenum_link( 1, false ), '?' ) : ( is_singular() ? get_permalink() : Search::results_url() );
		$base   = (string) $base;
		$view   = isset( $criteria['view'] ) ? $criteria['view'] : ( in_array( $attributes['view'] ?? 'split', Search::VIEWS, true ) ? $attributes['view'] : 'split' );
		$layout = 'list' === ( $attributes['layout'] ?? 'grid' ) ? 'list' : 'grid';
		$maps   = Settings::get( 'maps' );
		$params = Search::to_params( $criteria );
		unset( $params['pg'] );

		$config = array(
			'endpoint' => rest_url( 'estatecraft/v1/search' ),
			'base'     => $base,
			'layout'   => $layout,
			'tiles'    => $maps['tiles'],
			'attrib'   => $maps['attribution'],
			'cluster'  => (bool) $maps['cluster'],
			'center'   => array( $maps['lat'], $maps['lng'] ),
			'zoom'     => (int) $maps['zoom'],
		);

		$sorts  = array(
			'featured'   => __( 'Featured first', 'wp-smart-property' ),
			'newest'     => __( 'Newest', 'wp-smart-property' ),
			'price-asc'  => __( 'Price, low to high', 'wp-smart-property' ),
			'price-desc' => __( 'Price, high to low', 'wp-smart-property' ),
		);
		$hidden = '';
		foreach ( $params as $key => $value ) {
			if ( 'sort' !== $key ) {
				$hidden .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}
		}
		$views  = array(
			'list'  => __( 'List', 'wp-smart-property' ),
			'split' => __( 'List and map', 'wp-smart-property' ),
			'map'   => __( 'Map', 'wp-smart-property' ),
		);
		$switch = '';
		foreach ( $views as $key => $label ) {
			$switch .= '<a class="estatecraft-results__view' . ( $key === $view ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( array_merge( $params, array( 'view' => $key ) ), $base ) ) . '" data-view="' . esc_attr( $key ) . '"' . ( $key === $view ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}

		$html  = '<div class="estatecraft-results__bar">';
		$html .= '<p class="estatecraft-results__summary" role="status" aria-live="polite">' . esc_html( self::summary( (int) $query->found_posts ) ) . '</p>';
		$html .= '<form class="estatecraft-results__sort" method="get" action="' . esc_url( $base ) . '">' . $hidden . '<label for="estatecraft-sort">' . esc_html__( 'Sort by', 'wp-smart-property' ) . '</label> <select id="estatecraft-sort" name="sort">' . self::options( $sorts, isset( $criteria['sort'] ) ? $criteria['sort'] : 'featured' ) . '</select> <button type="submit" class="estatecraft-button is-secondary" data-estatecraft-nojs>' . esc_html__( 'Sort', 'wp-smart-property' ) . '</button></form>';
		$html .= '<nav class="estatecraft-results__views" aria-label="' . esc_attr__( 'Result views', 'wp-smart-property' ) . '">' . $switch . '</nav>';
		$html .= '</div>';
		$html .= '<div class="estatecraft-results__body">';
		$html .= '<div class="estatecraft-results__list"><ul class="estatecraft-listings__list is-' . esc_attr( $layout ) . '" style="--estatecraft-columns:' . esc_attr( max( 1, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) ) ) . '">' . self::cards( $query, $layout ) . '</ul>';
		$html .= '<div class="estatecraft-results__pages">' . self::pagination( $query, $criteria, $base ) . '</div></div>';
		$html .= '<div class="estatecraft-results__map" style="--estatecraft-map-height:' . esc_attr( max( 300, min( 1000, (int) ( $attributes['mapHeight'] ?? 600 ) ) ) ) . 'px">';
		$html .= '<div class="estatecraft-results__canvas" role="region" aria-label="' . esc_attr__( 'Map of the results. The list shows the same properties.', 'wp-smart-property' ) . '"></div>';
		$html .= '<label class="estatecraft-results__bounds" hidden><input type="checkbox" data-estatecraft-bounds' . checked( isset( $criteria['bounds'] ), true, false ) . ' /> ' . esc_html__( 'Search as I move the map', 'wp-smart-property' ) . '</label>';
		$html .= '<p class="estatecraft-results__note" hidden></p>';
		$html .= '</div></div>';

		Assets::search();
		unset( $content, $block );
		return Blocks::wrap(
			$html,
			'estatecraft-results is-view-' . $view,
			'div',
			array(
				'data-estatecraft-results' => wp_json_encode( $config ),
				'data-view'                => $view,
			)
		);
	}
}
