<?php
/**
 * Property page blocks.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Admin\Assets as Built;
use EstateCraft\Agents\Agent_Types;
use EstateCraft\Format;
use EstateCraft\Properties\Fields;
use WP_Block;

/**
 * Server-rendered blocks: the same markup on the site, in block theme templates, in classic theme
 * templates (through do_blocks()) and in editor previews.
 */
final class Blocks {

	/**
	 * Block folder => render method.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(
			'property-gallery'  => array( self::class, 'gallery' ),
			'property-price'    => array( self::class, 'price' ),
			'property-address'  => array( self::class, 'address' ),
			'property-facts'    => array( self::class, 'facts' ),
			'property-details'  => array( self::class, 'details' ),
			'property-features' => array( self::class, 'features' ),
			'floor-plans'       => array( self::class, 'floor_plans' ),
			'property-video'    => array( self::class, 'video' ),
			'property-map'      => array( self::class, 'map' ),
			'open-houses'       => array( self::class, 'open_houses' ),
			'property-agents'   => array( self::class, 'agents' ),
			'property-actions'  => array( self::class, 'actions' ),
			'property-listings' => array( Listing_Blocks::class, 'listings' ),
			'featured-property' => array( Listing_Blocks::class, 'featured' ),
			'agents'            => array( Listing_Blocks::class, 'agent_list' ),
			'agent-profile'     => array( Listing_Blocks::class, 'profile' ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( self::class, 'category' ) );
	}

	/**
	 * Register the blocks and their editor script.
	 */
	public static function register_blocks() {
		$asset = Built::asset( 'blocks' );
		wp_register_script( 'estatecraft-blocks', ESTATECRAFT_URL . 'build/blocks.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'estatecraft-blocks', 'wp-smart-property' );
		wp_register_style( 'estatecraft-blocks-editor', ESTATECRAFT_URL . 'build/blocks.css', array(), $asset['version'] );
		foreach ( self::all() as $folder => $callback ) {
			register_block_type( ESTATECRAFT_DIR . 'blocks/' . $folder, array( 'render_callback' => $callback ) );
		}
	}

	/**
	 * "Real estate" block category.
	 *
	 * @param array[] $categories Categories.
	 * @return array[]
	 */
	public static function category( $categories ) {
		array_unshift(
			$categories,
			array(
				'slug'  => 'estatecraft',
				'title' => __( 'Real estate', 'wp-smart-property' ),
				'icon'  => null,
			)
		);
		return $categories;
	}

	/**
	 * Wrap block output.
	 *
	 * @param string $html  Inner markup.
	 * @param string $css_class Block class.
	 * @param string $tag   Wrapper tag.
	 * @param array  $extra Extra wrapper attributes.
	 * @return string
	 */
	public static function wrap( $html, $css_class, $tag = 'div', array $extra = array() ) {
		if ( '' === $html ) {
			return '';
		}
		Assets::style();
		$attrs = get_block_wrapper_attributes( array_merge( array( 'class' => $css_class ), $extra ) );
		return '<' . $tag . ' ' . $attrs . '>' . $html . '</' . $tag . '>';
	}

	/**
	 * Placeholder shown only in editor previews when a block has nothing to show.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function empty_preview( $text ) {
		return View::is_preview() ? '<p class="estatecraft-empty">' . esc_html( $text ) . '</p>' : '';
	}

	/**
	 * Image data for the lightbox.
	 *
	 * @param int    $id      Attachment ID.
	 * @param string $caption Caption override.
	 * @return array|null
	 */
	private static function lightbox_item( $id, $caption = '' ) {
		$full = wp_get_attachment_image_src( $id, 'full' );
		if ( ! $full ) {
			return null;
		}
		return array(
			'src'     => $full[0],
			'width'   => (int) $full[1],
			'height'  => (int) $full[2],
			'srcset'  => (string) wp_get_attachment_image_srcset( $id, 'full' ),
			'alt'     => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption' => '' !== $caption ? $caption : (string) wp_get_attachment_caption( $id ),
		);
	}

	/**
	 * Gallery: a large first photo with a mosaic of the next four, and a full-screen viewer.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function gallery( $attributes, $content, $block ) {
		$id     = View::property_id( $block );
		$images = $id ? View::images( $id ) : array();
		if ( ! $images ) {
			return self::empty_preview( __( 'This property has no photos yet.', 'wp-smart-property' ) );
		}
		$title = get_the_title( $id );
		$items = array_values( array_filter( array_map( array( self::class, 'lightbox_item' ), $images ) ) );
		$shown = 'single' === ( $attributes['layout'] ?? 'mosaic' ) ? 1 : min( 5, count( $images ) );
		$html  = '';
		foreach ( array_slice( $images, 0, $shown ) as $index => $image ) {
			$full  = wp_get_attachment_image_url( $image, 'full' );
			$label = sprintf(
				/* translators: 1: photo number, 2: number of photos, 3: property title */
				__( 'Photo %1$d of %2$d: %3$s', 'wp-smart-property' ),
				$index + 1,
				count( $images ),
				$title
			);
			$more  = count( $images ) - $shown;
			$html .= '<a class="estatecraft-gallery__item' . ( 0 === $index ? ' is-main' : '' ) . '" href="' . esc_url( $full ) . '" data-index="' . esc_attr( $index ) . '" aria-label="' . esc_attr( $label ) . '">';
			$html .= wp_get_attachment_image(
				$image,
				0 === $index ? 'large' : 'medium_large',
				false,
				array_merge(
					array(
						'class' => 'estatecraft-gallery__image',
						'sizes' => 0 === $index ? '(max-width: 800px) 100vw, 800px' : '(max-width: 800px) 50vw, 400px',
					),
					// The first photo is usually the largest thing on the page.
					0 === $index ? array(
						'loading'       => false,
						'fetchpriority' => 'high',
					) : array()
				)
			);
			if ( $index === $shown - 1 && $more > 0 ) {
				/* translators: %d: number of further photos */
				$html .= '<span class="estatecraft-gallery__more" aria-hidden="true">' . esc_html( sprintf( __( '+%d', 'wp-smart-property' ), $more ) ) . '</span>';
			}
			$html .= '</a>';
		}
		$button = count( $images ) > 1 ? '<button type="button" class="estatecraft-gallery__all" data-index="0" hidden>' . Icons::get( 'expand' ) . esc_html(
			sprintf(
				/* translators: %d: number of photos */
				_n( 'Show %d photo', 'Show all %d photos', count( $images ), 'wp-smart-property' ),
				count( $images )
			)
		) . '</button>' : '';

		Assets::script();
		return self::wrap(
			'<div class="estatecraft-gallery__grid is-count-' . esc_attr( $shown ) . '">' . $html . '</div>' . $button,
			'estatecraft-gallery',
			'div',
			array(
				'data-estatecraft-gallery' => wp_json_encode( $items ),
				'data-title'               => $title,
			)
		);
	}

	/**
	 * Price with status.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function price( $attributes, $content, $block ) {
		$id = View::property_id( $block );
		if ( ! $id ) {
			return '';
		}
		$html  = ( $attributes['showStatus'] ?? true ) ? View::badges( $id ) : '';
		$ref   = Fields::get( $id, 'reference' );
		$html .= '<p class="estatecraft-price__amount">' . esc_html( View::price( $id ) ) . '</p>';
		if ( '' !== $ref && ( $attributes['showReference'] ?? true ) ) {
			/* translators: %s: listing reference */
			$html .= '<p class="estatecraft-price__ref">' . esc_html( sprintf( __( 'Ref. %s', 'wp-smart-property' ), $ref ) ) . '</p>';
		}
		return self::wrap( $html, 'estatecraft-price' );
	}

	/**
	 * Address (or only the area when the exact address is hidden).
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function address( $attributes, $content, $block ) {
		$id      = View::property_id( $block );
		$address = $id ? View::address( $id ) : '';
		if ( '' === $address ) {
			return self::empty_preview( __( 'No address yet.', 'wp-smart-property' ) );
		}
		return self::wrap( Icons::get( 'pin' ) . '<span>' . esc_html( $address ) . '</span>', 'estatecraft-address', 'p' );
	}

	/**
	 * Key facts bar.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function facts( $attributes, $content, $block ) {
		$id = View::property_id( $block );
		return $id ? self::wrap( View::facts_html( $id ), 'estatecraft-facts-block' ) : '';
	}

	/**
	 * All detail rows.
	 *
	 * @param int $id Property ID.
	 * @return array[] key => { label, value }
	 */
	public static function detail_rows( $id ) {
		$terms = static function ( $taxonomy ) use ( $id ) {
			return implode( ', ', wp_list_pluck( View::terms( $id, $taxonomy ), 'name' ) );
		};
		$rows  = array(
			'reference'    => array( __( 'Reference', 'wp-smart-property' ), Fields::get( $id, 'reference' ) ),
			'type'         => array( __( 'Type', 'wp-smart-property' ), $terms( 'property_type' ) ),
			'status'       => array( __( 'Status', 'wp-smart-property' ), $terms( 'property_status' ) ),
			'price'        => array( __( 'Price', 'wp-smart-property' ), View::price( $id ) ),
			'bedrooms'     => array( __( 'Bedrooms', 'wp-smart-property' ), Fields::get( $id, 'bedrooms' ) ? View::number( Fields::get( $id, 'bedrooms' ) ) : '' ),
			'bathrooms'    => array( __( 'Bathrooms', 'wp-smart-property' ), Fields::get( $id, 'bathrooms' ) ? View::number( Fields::get( $id, 'bathrooms' ) ) : '' ),
			'garages'      => array( __( 'Parking spaces', 'wp-smart-property' ), Fields::get( $id, 'garages' ) ? View::number( Fields::get( $id, 'garages' ) ) : '' ),
			'area'         => array( __( 'Living area', 'wp-smart-property' ), Format::area( Fields::get( $id, 'area' ) ) ),
			'land_area'    => array( __( 'Land area', 'wp-smart-property' ), Format::area( Fields::get( $id, 'land_area' ) ) ),
			'year_built'   => array( __( 'Year built', 'wp-smart-property' ), Fields::get( $id, 'year_built' ) ? (string) Fields::get( $id, 'year_built' ) : '' ),
			'energy_class' => array( __( 'Energy class', 'wp-smart-property' ), Fields::get( $id, 'energy_class' ) ),
			'category'     => array( __( 'Category', 'wp-smart-property' ), $terms( 'property_category' ) ),
		);
		foreach ( View::custom_values( $id ) as $custom ) {
			$rows[ $custom['key'] ] = array( $custom['label'], $custom['value'] );
		}
		$out = array();
		foreach ( $rows as $key => $row ) {
			$out[ $key ] = array(
				'label' => $row[0],
				'value' => (string) $row[1],
			);
		}
		return $out;
	}

	/**
	 * Details table.
	 *
	 * @param array    $attributes Attributes (fields: keys to show, empty for all).
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function details( $attributes, $content, $block ) {
		$id = View::property_id( $block );
		if ( ! $id ) {
			return '';
		}
		$rows = self::detail_rows( $id );
		$keys = ! empty( $attributes['fields'] ) && is_array( $attributes['fields'] ) ? $attributes['fields'] : array_keys( $rows );
		$html = '';
		foreach ( $keys as $key ) {
			if ( isset( $rows[ $key ] ) && '' !== $rows[ $key ]['value'] ) {
				$html .= '<div class="estatecraft-details__row"><dt>' . esc_html( $rows[ $key ]['label'] ) . '</dt><dd>' . esc_html( $rows[ $key ]['value'] ) . '</dd></div>';
			}
		}
		if ( '' === $html ) {
			return '';
		}
		return self::wrap( View::heading( $attributes, __( 'Details', 'wp-smart-property' ) ) . '<dl class="estatecraft-details__list">' . $html . '</dl>', 'estatecraft-details', 'section' );
	}

	/**
	 * Features (amenities) list.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function features( $attributes, $content, $block ) {
		$id    = View::property_id( $block );
		$terms = $id ? View::terms( $id, 'property_feature' ) : array();
		if ( ! $terms ) {
			return self::empty_preview( __( 'No features yet.', 'wp-smart-property' ) );
		}
		$html = '';
		foreach ( $terms as $term ) {
			$link  = get_term_link( $term );
			$html .= '<li>' . Icons::get( 'check' ) . ( is_wp_error( $link ) ? esc_html( $term->name ) : '<a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>' ) . '</li>';
		}
		return self::wrap( View::heading( $attributes, __( 'Features', 'wp-smart-property' ) ) . '<ul class="estatecraft-features__list">' . $html . '</ul>', 'estatecraft-features', 'section' );
	}

	/**
	 * Floor plans (open in the gallery viewer).
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function floor_plans( $attributes, $content, $block ) {
		$id    = View::property_id( $block );
		$plans = $id ? Fields::get( $id, 'floor_plans' ) : array();
		$items = array();
		$html  = '';
		foreach ( (array) $plans as $plan ) {
			$facts = array_filter(
				array(
					$plan['area'] ? Format::area( $plan['area'] ) : '',
					/* translators: %s: number of bedrooms */
					$plan['bedrooms'] ? sprintf( _n( '%s bedroom', '%s bedrooms', $plan['bedrooms'], 'wp-smart-property' ), View::number( $plan['bedrooms'] ) ) : '',
					/* translators: %s: number of bathrooms */
					$plan['bathrooms'] ? sprintf( _n( '%s bathroom', '%s bathrooms', $plan['bathrooms'] > 1 ? 2 : 1, 'wp-smart-property' ), View::number( $plan['bathrooms'] ) ) : '',
				)
			);
			$title = '' !== $plan['title'] ? $plan['title'] : __( 'Floor plan', 'wp-smart-property' );
			$item  = $plan['image_id'] ? self::lightbox_item( $plan['image_id'], $title ) : null;
			$html .= '<figure class="estatecraft-plan">';
			if ( $item ) {
				$html   .= '<a class="estatecraft-gallery__item" href="' . esc_url( $item['src'] ) . '" data-index="' . esc_attr( count( $items ) ) . '">' . wp_get_attachment_image( $plan['image_id'], 'medium_large', false, array( 'alt' => $title ) ) . '</a>';
				$items[] = $item;
			}
			$html .= '<figcaption><strong>' . esc_html( $title ) . '</strong>' . ( $facts ? ' <span>' . esc_html( implode( ' · ', $facts ) ) . '</span>' : '' ) . '</figcaption></figure>';
		}
		if ( '' === $html ) {
			return self::empty_preview( __( 'No floor plans yet.', 'wp-smart-property' ) );
		}
		if ( $items ) {
			Assets::script();
		}
		return self::wrap(
			View::heading( $attributes, __( 'Floor plans', 'wp-smart-property' ) ) . '<div class="estatecraft-plans">' . $html . '</div>',
			'estatecraft-floor-plans',
			'section',
			$items ? array(
				'data-estatecraft-gallery' => wp_json_encode( $items ),
				'data-title'               => get_the_title( $id ),
			) : array()
		);
	}

	/**
	 * Video and virtual tour.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function video( $attributes, $content, $block ) {
		$id = View::property_id( $block );
		if ( ! $id ) {
			return '';
		}
		$html  = '';
		$video = Fields::get( $id, 'video_url' );
		if ( '' !== $video ) {
			$embed = wp_oembed_get( $video, array( 'width' => 800 ) );
			$html .= $embed ? '<div class="estatecraft-video__embed">' . $embed . '</div>' : '<p><a class="estatecraft-button" href="' . esc_url( $video ) . '">' . esc_html__( 'Watch the video', 'wp-smart-property' ) . '</a></p>';
		}
		$tour = Fields::get( $id, 'tour_url' );
		if ( '' !== $tour ) {
			if ( 0 === strpos( $tour, 'https://' ) ) {
				// A third-party page in a sandbox; it cannot reach this site.
				$html .= '<div class="estatecraft-video__tour"><iframe src="' . esc_url( $tour ) . '" title="' . esc_attr__( 'Virtual tour', 'wp-smart-property' ) . '" loading="lazy" allow="fullscreen; xr-spatial-tracking; gyroscope; accelerometer" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-forms" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
			}
			$html .= '<p><a class="estatecraft-button is-secondary" href="' . esc_url( $tour ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the virtual tour', 'wp-smart-property' ) . '</a></p>';
		}
		if ( '' === $html ) {
			return self::empty_preview( __( 'No video or virtual tour yet.', 'wp-smart-property' ) );
		}
		return self::wrap( View::heading( $attributes, __( 'Video and tour', 'wp-smart-property' ) ) . $html, 'estatecraft-video', 'section' );
	}

	/**
	 * Location map.
	 *
	 * @param array    $attributes Attributes (height, zoom).
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function map( $attributes, $content, $block ) {
		$id    = View::property_id( $block );
		$point = $id ? View::map_point( $id ) : null;
		if ( ! $point ) {
			return self::empty_preview( __( 'This property has no map pin yet.', 'wp-smart-property' ) );
		}
		$zoom   = max( 3, min( 19, (int) ( $attributes['zoom'] ?? 15 ) ) );
		$height = max( 200, min( 800, (int) ( $attributes['height'] ?? 360 ) ) );
		$config = array_merge(
			Assets::tiles(),
			$point,
			array( 'zoom' => $point['approximate'] ? min( $zoom, 14 ) : $zoom )
		);
		$note   = $point['approximate']
			? __( 'The map shows the area, not the exact location.', 'wp-smart-property' )
			: '';
		$osm    = $point['approximate'] ? '' : sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=17/%1$s/%2$s', rawurlencode( (string) $point['lat'] ), rawurlencode( (string) $point['lng'] ) );

		Assets::map();
		$html  = View::heading( $attributes, __( 'Location', 'wp-smart-property' ) );
		$html .= '<div class="estatecraft-map__canvas" style="height:' . esc_attr( $height ) . 'px" data-estatecraft-map="' . esc_attr( wp_json_encode( $config ) ) . '" role="region" aria-label="' . esc_attr__( 'Map', 'wp-smart-property' ) . '"></div>';
		$html .= '<p class="estatecraft-map__note">' . esc_html( trim( View::address( $id ) . ( '' !== $note ? '. ' . $note : '' ) ) );
		if ( $osm ) {
			$html .= ' <a href="' . esc_url( $osm ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open in OpenStreetMap', 'wp-smart-property' ) . '</a>';
		}
		$html .= '</p>';
		return self::wrap( $html, 'estatecraft-map', 'section' );
	}

	/**
	 * Upcoming open house times.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function open_houses( $attributes, $content, $block ) {
		$id   = View::property_id( $block );
		$now  = current_time( 'Y-m-d H:i' );
		$html = '';
		foreach ( $id ? Fields::get( $id, 'open_houses' ) : array() as $slot ) {
			$end = '' !== $slot['end'] ? $slot['end'] : $slot['start'];
			if ( $end < $now ) {
				continue;
			}
			$start = strtotime( $slot['start'] );
			$text  = date_i18n( 'l j F', $start ) . ', ' . date_i18n( get_option( 'time_format' ), $start );
			if ( '' !== $slot['end'] ) {
				$finish = strtotime( $slot['end'] );
				$text  .= ' – ' . ( substr( $slot['end'], 0, 10 ) === substr( $slot['start'], 0, 10 ) ? date_i18n( get_option( 'time_format' ), $finish ) : date_i18n( 'l j F', $finish ) . ', ' . date_i18n( get_option( 'time_format' ), $finish ) );
			}
			$html .= '<li>' . Icons::get( 'clock' ) . '<time datetime="' . esc_attr( str_replace( ' ', 'T', $slot['start'] ) ) . '">' . esc_html( $text ) . '</time></li>';
		}
		if ( '' === $html ) {
			return self::empty_preview( __( 'No upcoming open houses.', 'wp-smart-property' ) );
		}
		return self::wrap( View::heading( $attributes, __( 'Open house', 'wp-smart-property' ) ) . '<ul class="estatecraft-open-houses__list">' . $html . '</ul>', 'estatecraft-open-houses', 'section' );
	}

	/**
	 * Agent card.
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $heading  Name heading tag.
	 * @return string
	 */
	public static function agent_card( $agent_id, $heading = 'h3' ) {
		$agent = get_post( $agent_id );
		if ( ! $agent || 'publish' !== $agent->post_status || ! in_array( $agent->post_type, array( Agent_Types::AGENT, Agent_Types::AGENCY ), true ) ) {
			return '';
		}
		$meta   = static function ( $key ) use ( $agent ) {
			return (string) get_post_meta( $agent->ID, '_estatecraft_' . $key, true );
		};
		$html   = '<div class="estatecraft-agent">';
		$html  .= '<a class="estatecraft-agent__photo" href="' . esc_url( get_permalink( $agent ) ) . '" tabindex="-1" aria-hidden="true">';
		$html  .= has_post_thumbnail( $agent ) ? get_the_post_thumbnail( $agent, 'thumbnail', array( 'alt' => '' ) ) : '<span class="estatecraft-agent__initials">' . esc_html( self::initials( get_the_title( $agent ) ) ) . '</span>';
		$html  .= '</a><div class="estatecraft-agent__body">';
		$html  .= '<' . $heading . ' class="estatecraft-agent__name"><a href="' . esc_url( get_permalink( $agent ) ) . '">' . esc_html( get_the_title( $agent ) ) . '</a></' . $heading . '>';
		$agency = (int) $meta( 'agency' );
		if ( $agency && 'publish' === get_post_status( $agency ) ) {
			$html .= '<p class="estatecraft-agent__agency"><a href="' . esc_url( get_permalink( $agency ) ) . '">' . esc_html( get_the_title( $agency ) ) . '</a></p>';
		}
		$html .= self::contact_list( $agent->ID, array( 'phone', 'mobile', 'email' ) );
		return $html . '</div></div>';
	}

	/**
	 * Initials for agents without a photo.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function initials( $name ) {
		$letters = '';
		foreach ( array_slice( preg_split( '/\s+/', trim( wp_strip_all_tags( $name ) ) ), 0, 2 ) as $word ) {
			$letters .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
		}
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letters ) : strtoupper( $letters );
	}

	/**
	 * Contact links of an agent or agency.
	 *
	 * @param int      $post_id Agent or agency.
	 * @param string[] $keys    Which details.
	 * @return string
	 */
	public static function contact_list( $post_id, array $keys ) {
		$labels = array(
			'phone'     => array( 'phone', __( 'Phone', 'wp-smart-property' ) ),
			'mobile'    => array( 'phone', __( 'Mobile', 'wp-smart-property' ) ),
			'email'     => array( 'mail', __( 'Email', 'wp-smart-property' ) ),
			'website'   => array( 'globe', __( 'Website', 'wp-smart-property' ) ),
			'license'   => array( 'license', __( 'Licence', 'wp-smart-property' ) ),
			'languages' => array( 'language', __( 'Languages', 'wp-smart-property' ) ),
			'address'   => array( 'pin', __( 'Address', 'wp-smart-property' ) ),
		);
		$html   = '';
		foreach ( $keys as $key ) {
			$value = trim( (string) get_post_meta( $post_id, '_estatecraft_' . $key, true ) );
			if ( '' === $value || ! isset( $labels[ $key ] ) ) {
				continue;
			}
			switch ( $key ) {
				case 'phone':
				case 'mobile':
					$text = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $value ) ) . '">' . esc_html( $value ) . '</a>';
					break;
				case 'email':
					// Encoded so simple address harvesters do not read it.
					$text = '<a href="' . esc_attr( 'mailto:' . antispambot( $value ) ) . '">' . esc_html( antispambot( $value ) ) . '</a>';
					break;
				case 'website':
					$text = '<a href="' . esc_url( $value ) . '" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?|/$#', '', $value ) ) . '</a>';
					break;
				default:
					$text = nl2br( esc_html( $value ) );
			}
			$html .= '<li class="is-' . esc_attr( $key ) . '">' . Icons::get( $labels[ $key ][0] ) . '<span class="screen-reader-text">' . esc_html( $labels[ $key ][1] ) . ': </span>' . $text . '</li>';
		}
		return '' === $html ? '' : '<ul class="estatecraft-contact">' . $html . '</ul>';
	}

	/**
	 * Agents of the property.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function agents( $attributes, $content, $block ) {
		$id   = View::property_id( $block );
		$html = '';
		foreach ( $id ? Fields::get( $id, 'agents' ) : array() as $agent ) {
			$html .= self::agent_card( $agent );
		}
		if ( '' === $html ) {
			return self::empty_preview( __( 'No agent is linked to this property yet.', 'wp-smart-property' ) );
		}
		return self::wrap( View::heading( $attributes, __( 'Contact', 'wp-smart-property' ) ) . $html, 'estatecraft-property-agents', 'section' );
	}

	/**
	 * Share and print buttons.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function actions( $attributes, $content, $block ) {
		$id = View::property_id( $block );
		if ( ! $id ) {
			return '';
		}
		Assets::script();
		// Shown by the script, which provides the behaviour.
		$html  = '<button type="button" class="estatecraft-button is-secondary" data-estatecraft-share data-url="' . esc_url( get_permalink( $id ) ) . '" data-title="' . esc_attr( get_the_title( $id ) ) . '" hidden>' . Icons::get( 'share' ) . esc_html__( 'Share', 'wp-smart-property' ) . '</button>';
		$html .= '<button type="button" class="estatecraft-button is-secondary" data-estatecraft-print hidden>' . Icons::get( 'print' ) . esc_html__( 'Print', 'wp-smart-property' ) . '</button>';
		$html .= '<span class="estatecraft-actions__status" role="status" aria-live="polite"></span>';
		return self::wrap( $html, 'estatecraft-actions' );
	}
}
