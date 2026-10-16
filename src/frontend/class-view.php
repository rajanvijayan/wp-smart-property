<?php
/**
 * Property data prepared for display, and the markup pieces blocks and templates share.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Format;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Settings;
use WP_Post;

/**
 * Everything here escapes its own output.
 */
final class View {

	/**
	 * The property a block is about: block context, the global post, or (in editor previews) the
	 * latest property so templates can be designed before any property is open.
	 *
	 * @param \WP_Block|null $block Block.
	 * @return int Property ID or 0.
	 */
	public static function property_id( $block = null ) {
		$id = $block && ! empty( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		if ( $id && Property_Type::POST_TYPE === get_post_type( $id ) ) {
			return $id;
		}
		return self::is_preview() ? self::preview_id( Property_Type::POST_TYPE ) : 0;
	}

	/**
	 * Whether this is a block preview in the editor.
	 *
	 * @return bool
	 */
	public static function is_preview() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_posts' );
	}

	/**
	 * Latest published post of a type, for previews.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	public static function preview_id( $post_type ) {
		$ids = get_posts(
			array(
				'post_type'   => $post_type,
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Images of a property: the featured image first, then the gallery.
	 *
	 * @param int $post_id Property ID.
	 * @return int[] Attachment IDs.
	 */
	public static function images( $post_id ) {
		$ids = Fields::get( $post_id, 'gallery' );
		$ids = is_array( $ids ) ? $ids : array();
		$top = (int) get_post_thumbnail_id( $post_id );
		if ( $top ) {
			array_unshift( $ids, $top );
		}
		$out = array();
		foreach ( array_unique( $ids ) as $id ) {
			if ( wp_attachment_is_image( $id ) ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	/**
	 * Main image (featured image, or the first gallery photo).
	 *
	 * @param int $post_id Property ID.
	 * @return int Attachment ID or 0.
	 */
	public static function main_image( $post_id ) {
		$images = self::images( $post_id );
		return $images ? $images[0] : 0;
	}

	/**
	 * Terms of a property in one taxonomy.
	 *
	 * @param int    $post_id  Property ID.
	 * @param string $taxonomy Taxonomy.
	 * @return \WP_Term[]
	 */
	public static function terms( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * The most specific location with its parents, e.g. "London, United Kingdom".
	 *
	 * @param int $post_id Property ID.
	 * @return string
	 */
	public static function location( $post_id ) {
		$best  = null;
		$depth = -1;
		foreach ( self::terms( $post_id, 'property_location' ) as $term ) {
			$ancestors = get_ancestors( $term->term_id, 'property_location', 'taxonomy' );
			if ( count( $ancestors ) > $depth ) {
				$best  = $term;
				$depth = count( $ancestors );
			}
		}
		if ( ! $best ) {
			return '';
		}
		$names = array( $best->name );
		foreach ( get_ancestors( $best->term_id, 'property_location', 'taxonomy' ) as $id ) {
			$term = get_term( $id, 'property_location' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return implode( ', ', $names );
	}

	/**
	 * Address shown to visitors. With "hide the exact address" only the area is shown.
	 *
	 * @param int $post_id Property ID.
	 * @return string
	 */
	public static function address( $post_id ) {
		$parts = array();
		if ( ! Fields::get( $post_id, 'hide_address' ) ) {
			$parts[] = Fields::get( $post_id, 'street' );
			$parts[] = Fields::get( $post_id, 'postcode' );
		}
		$parts = array_filter( array_map( 'trim', $parts ) );
		$text  = implode( ', ', $parts );
		// Add the location names the street does not already mention ("London N1 7AB" has London).
		foreach ( array_filter( explode( ', ', self::location( $post_id ) ) ) as $name ) {
			if ( false === stripos( $text, $name ) ) {
				$parts[] = $name;
				$text   .= ', ' . $name;
			}
		}
		return implode( ', ', $parts );
	}

	/**
	 * Point to show on a map: the exact pin, or an approximate one (stable per property) when the
	 * address is hidden. The exact coordinates never reach the page in that case.
	 *
	 * @param int $post_id Property ID.
	 * @return array|null { lat, lng, approximate, radius (metres) }
	 */
	public static function map_point( $post_id ) {
		$lat = (float) Fields::get( $post_id, 'lat' );
		$lng = (float) Fields::get( $post_id, 'lng' );
		if ( ! $lat && ! $lng ) {
			return null;
		}
		if ( ! Fields::get( $post_id, 'hide_address' ) ) {
			return array(
				'lat'         => $lat,
				'lng'         => $lng,
				'approximate' => false,
				'radius'      => 0,
			);
		}
		// Move the centre up to ~250 m in a direction derived from the post and site, then draw a
		// 500 m circle: the property is inside it, but its position cannot be read back.
		$hash  = hexdec( substr( md5( $post_id . wp_salt( 'nonce' ) ), 0, 8 ) );
		$angle = ( $hash % 360 ) * M_PI / 180;
		$dist  = 100 + ( $hash % 150 );
		$dlat  = ( $dist * cos( $angle ) ) / 111320;
		$dlng  = ( $dist * sin( $angle ) ) / ( 111320 * max( 0.01, cos( deg2rad( $lat ) ) ) );
		return array(
			'lat'         => round( $lat + $dlat, 4 ),
			'lng'         => round( $lng + $dlng, 4 ),
			'approximate' => true,
			'radius'      => 500,
		);
	}

	/**
	 * Price text.
	 *
	 * @param int $post_id Property ID.
	 * @return string
	 */
	public static function price( $post_id ) {
		return Format::price( Fields::get( $post_id, 'price' ), Fields::get( $post_id, 'price_period' ), Fields::get( $post_id, 'price_on_request' ) );
	}

	/**
	 * Short price for map pins: "$475k", "£1.25M", "€2.4k".
	 *
	 * @param int $post_id Property ID.
	 * @return string
	 */
	public static function short_price( $post_id ) {
		$amount = (float) Fields::get( $post_id, 'price' );
		if ( ! $amount || Fields::get( $post_id, 'price_on_request' ) ) {
			return '';
		}
		$g     = Settings::get( 'general' );
		$point = '' !== $g['decimal_point'] ? $g['decimal_point'] : '.';
		// 1.50 -> 1.5 and 2.00 -> 2, but 390 stays 390.
		$short = static function ( $value, $decimals ) use ( $point ) {
			$text = number_format( $value, $decimals, $point, '' );
			return $decimals ? rtrim( rtrim( $text, '0' ), $point ) : $text;
		};
		if ( $amount >= 1000000 ) {
			$number = $short( $amount / 1000000, 2 ) . __( 'M', 'wp-smart-property' );
		} elseif ( $amount >= 1000 ) {
			$number = $short( $amount / 1000, $amount < 10000 ? 1 : 0 ) . __( 'k', 'wp-smart-property' );
		} else {
			$number = number_format( $amount, 0, $point, $g['thousands'] );
		}
		return $g['symbol_after'] ? $number . ' ' . $g['symbol'] : $g['symbol'] . $number;
	}

	/**
	 * A number without needless decimals (2.5, 3).
	 *
	 * @param float $value Number.
	 * @return string
	 */
	public static function number( $value ) {
		$value = (float) $value;
		return number_format_i18n( $value, floor( $value ) == $value ? 0 : 1 ); // phpcs:ignore Universal.Operators.StrictComparisons -- comparing a float to its floor.
	}

	/**
	 * Key facts.
	 *
	 * @param int  $post_id Property ID.
	 * @param bool $compact Only rooms and living area (cards).
	 * @return array[] { key, icon, value, label }
	 */
	public static function facts( $post_id, $compact = false ) {
		$facts = array();
		$beds  = Fields::get( $post_id, 'bedrooms' );
		$baths = Fields::get( $post_id, 'bathrooms' );
		if ( $beds ) {
			$facts[] = array( 'bedrooms', 'bed', self::number( $beds ), _n( 'bedroom', 'bedrooms', $beds, 'wp-smart-property' ) );
		}
		if ( $baths ) {
			$facts[] = array( 'bathrooms', 'bath', self::number( $baths ), _n( 'bathroom', 'bathrooms', $baths > 1 ? 2 : 1, 'wp-smart-property' ) );
		}
		if ( Fields::get( $post_id, 'area' ) ) {
			$facts[] = array( 'area', 'area', Format::area( Fields::get( $post_id, 'area' ) ), __( 'living area', 'wp-smart-property' ) );
		}
		if ( ! $compact ) {
			if ( Fields::get( $post_id, 'land_area' ) ) {
				$facts[] = array( 'land_area', 'land', Format::area( Fields::get( $post_id, 'land_area' ) ), __( 'land', 'wp-smart-property' ) );
			}
			$garages = Fields::get( $post_id, 'garages' );
			if ( $garages ) {
				$facts[] = array( 'garages', 'garage', self::number( $garages ), _n( 'parking space', 'parking spaces', $garages, 'wp-smart-property' ) );
			}
			if ( Fields::get( $post_id, 'year_built' ) ) {
				$facts[] = array( 'year_built', 'calendar', (string) Fields::get( $post_id, 'year_built' ), __( 'built', 'wp-smart-property' ) );
			}
		}
		$out = array();
		foreach ( $facts as $fact ) {
			$out[] = array_combine( array( 'key', 'icon', 'value', 'label' ), $fact );
		}
		return $out;
	}

	/**
	 * Facts list markup.
	 *
	 * @param int  $post_id Property ID.
	 * @param bool $compact Card style.
	 * @return string
	 */
	public static function facts_html( $post_id, $compact = false ) {
		$facts = self::facts( $post_id, $compact );
		if ( ! $facts ) {
			return '';
		}
		$html = '<ul class="estatecraft-facts' . ( $compact ? ' is-compact' : '' ) . '">';
		foreach ( $facts as $fact ) {
			$html .= '<li class="estatecraft-facts__item is-' . esc_attr( $fact['key'] ) . '">' . Icons::get( $fact['icon'] )
				. '<span class="estatecraft-facts__value">' . esc_html( $fact['value'] ) . '</span> <span class="estatecraft-facts__label">' . esc_html( $fact['label'] ) . '</span></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Status, featured and category badges.
	 *
	 * @param int $post_id Property ID.
	 * @return string
	 */
	public static function badges( $post_id ) {
		$html = '';
		foreach ( self::terms( $post_id, 'property_status' ) as $term ) {
			$html .= '<span class="estatecraft-badge is-status is-' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</span>';
		}
		$featured = (bool) Fields::get( $post_id, 'featured' );
		if ( $featured ) {
			$html .= '<span class="estatecraft-badge is-featured">' . esc_html__( 'Featured', 'wp-smart-property' ) . '</span>';
		}
		foreach ( self::terms( $post_id, 'property_category' ) as $term ) {
			// A "Featured" category (common on 2.0 sites) would repeat the featured badge.
			if ( $featured && 'featured' === $term->slug ) {
				continue;
			}
			$html .= '<span class="estatecraft-badge is-label">' . esc_html( $term->name ) . '</span>';
		}
		return '' === $html ? '' : '<div class="estatecraft-badges">' . $html . '</div>';
	}

	/**
	 * Property card.
	 *
	 * @param int    $post_id Property ID.
	 * @param string $heading Heading tag (h2 or h3).
	 * @param string $layout  grid or list.
	 * @return string
	 */
	public static function card( $post_id, $heading = 'h3', $layout = 'grid' ) {
		$heading = in_array( $heading, array( 'h2', 'h3', 'h4' ), true ) ? $heading : 'h3';
		$link    = get_permalink( $post_id );
		$title   = get_the_title( $post_id );
		$image   = self::main_image( $post_id );
		$classes = array( 'estatecraft-card', 'is-' . ( 'list' === $layout ? 'list' : 'grid' ) );
		if ( Fields::get( $post_id, 'featured' ) ) {
			$classes[] = 'is-featured';
		}

		$html = '<article class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		// The image link repeats the title link, so it is hidden from assistive technology.
		$html .= '<a class="estatecraft-card__media" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">';
		if ( $image ) {
			$html .= wp_get_attachment_image(
				$image,
				'medium_large',
				false,
				array(
					'class' => 'estatecraft-card__image',
					'alt'   => '',
					'sizes' => 'list' === $layout ? '(max-width: 600px) 100vw, 320px' : '(max-width: 600px) 100vw, (max-width: 1000px) 50vw, 400px',
				)
			);
		} else {
			$html .= '<span class="estatecraft-card__placeholder">' . Icons::get( 'home' ) . '</span>';
		}
		$html   .= '</a>';
		$html   .= self::badges( $post_id );
		$html   .= '<div class="estatecraft-card__body">';
		$html   .= '<p class="estatecraft-card__price">' . esc_html( self::price( $post_id ) ) . '</p>';
		$html   .= '<' . $heading . ' class="estatecraft-card__title"><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></' . $heading . '>';
		$address = self::address( $post_id );
		if ( '' !== $address ) {
			$html .= '<p class="estatecraft-card__address">' . esc_html( $address ) . '</p>';
		}
		$html .= self::facts_html( $post_id, true );
		$html .= '</div></article>';

		/**
		 * Filters a property card.
		 *
		 * @param string $html    Card markup.
		 * @param int    $post_id Property ID.
		 * @param string $layout  grid or list.
		 */
		return apply_filters( 'estatecraft_property_card', $html, $post_id, $layout );
	}

	/**
	 * Values of the custom fields shown on the property page.
	 *
	 * @param int $post_id Property ID.
	 * @return array[] { key, label, value }
	 */
	public static function custom_values( $post_id ) {
		$out = array();
		foreach ( Settings::get( 'fields' ) as $field ) {
			if ( empty( $field['show'] ) ) {
				continue;
			}
			$value = Fields::get( $post_id, 'cf_' . $field['key'] );
			if ( 'checkbox' === $field['type'] ) {
				$value = $value ? __( 'Yes', 'wp-smart-property' ) : '';
			} elseif ( 'number' === $field['type'] ) {
				$value = $value ? self::number( $value ) : '';
			} elseif ( 'date' === $field['type'] && $value ) {
				$value = date_i18n( get_option( 'date_format' ), strtotime( $value ) );
			}
			if ( '' !== (string) $value ) {
				$out[] = array(
					'key'   => 'cf_' . $field['key'],
					'label' => $field['label'],
					'value' => (string) $value,
				);
			}
		}
		return $out;
	}

	/**
	 * Section heading used by the property blocks.
	 *
	 * @param array  $attributes Block attributes (showHeading, heading).
	 * @param string $fallback   Default heading.
	 * @return string
	 */
	public static function heading( array $attributes, $fallback ) {
		if ( isset( $attributes['showHeading'] ) && ! $attributes['showHeading'] ) {
			return '';
		}
		$text = isset( $attributes['heading'] ) && '' !== trim( $attributes['heading'] ) ? $attributes['heading'] : $fallback;
		return '<h2 class="estatecraft-heading">' . esc_html( $text ) . '</h2>';
	}
}
