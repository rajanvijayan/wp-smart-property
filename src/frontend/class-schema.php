<?php
/**
 * Structured data, Open Graph tags and sitemaps.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Agents\Agent_Types;
use EstateCraft\Legacy\Legacy_Taxonomies;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Settings;

/**
 * Prints schema.org RealEstateListing on property pages and RealEstateAgent on agent pages, with
 * breadcrumbs. Open Graph tags only when no SEO plugin prints them.
 */
final class Schema {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'wp_head', array( self::class, 'print_json_ld' ), 20 );
		add_action( 'wp_head', array( self::class, 'print_open_graph' ), 5 );
		add_filter( 'wp_sitemaps_taxonomies', array( self::class, 'sitemap_taxonomies' ) );
	}

	/**
	 * The 2.0 taxonomies only redirect; keep them out of the sitemap.
	 *
	 * @param \WP_Taxonomy[] $taxonomies Taxonomies.
	 * @return \WP_Taxonomy[]
	 */
	public static function sitemap_taxonomies( $taxonomies ) {
		foreach ( array_keys( Legacy_Taxonomies::all() ) as $taxonomy ) {
			unset( $taxonomies[ $taxonomy ] );
		}
		return $taxonomies;
	}

	/**
	 * Whether an SEO plugin that prints its own tags is active.
	 *
	 * @return bool
	 */
	private static function seo_plugin() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
	}

	/**
	 * Schema.org type of a property, from its property type.
	 *
	 * @param int $id Property.
	 * @return string
	 */
	private static function place_type( $id ) {
		$map = array(
			'house'      => 'SingleFamilyResidence',
			'apartment'  => 'Apartment',
			'flat'       => 'Apartment',
			'condo'      => 'Apartment',
			'commercial' => 'Place',
			'land'       => 'Place',
		);
		foreach ( View::terms( $id, 'property_type' ) as $term ) {
			if ( isset( $map[ $term->slug ] ) ) {
				return $map[ $term->slug ];
			}
		}
		return 'Accommodation';
	}

	/**
	 * Listing data for a property.
	 *
	 * @param int $id Property.
	 * @return array
	 */
	public static function property( $id ) {
		$url     = get_permalink( $id );
		$hidden  = (bool) Fields::get( $id, 'hide_address' );
		$area    = 'm2' === Settings::get( 'general' )['area_unit'] ? 'MTK' : 'FTK';
		$place   = array(
			'@type' => self::place_type( $id ),
			'name'  => get_the_title( $id ),
		);
		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $hidden ? '' : Fields::get( $id, 'street' ),
				'postalCode'      => $hidden ? '' : Fields::get( $id, 'postcode' ),
				'addressLocality' => View::location( $id ),
			)
		);
		if ( count( $address ) > 1 ) {
			$place['address'] = $address;
		}
		$point = View::map_point( $id );
		if ( $point && ! $point['approximate'] ) {
			$place['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $point['lat'],
				'longitude' => $point['lng'],
			);
		}
		foreach ( array(
			'numberOfBedrooms'       => 'bedrooms',
			'numberOfBathroomsTotal' => 'bathrooms',
			'yearBuilt'              => 'year_built',
		) as $prop => $field ) {
			if ( Fields::get( $id, $field ) ) {
				$place[ $prop ] = Fields::get( $id, $field );
			}
		}
		if ( Fields::get( $id, 'area' ) ) {
			$place['floorSize'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => Fields::get( $id, 'area' ),
				'unitCode' => $area,
			);
		}
		$features = View::terms( $id, 'property_feature' );
		if ( $features ) {
			$place['amenityFeature'] = array();
			foreach ( $features as $term ) {
				$place['amenityFeature'][] = array(
					'@type' => 'LocationFeatureSpecification',
					'name'  => $term->name,
					'value' => true,
				);
			}
		}

		$images = array();
		foreach ( array_slice( View::images( $id ), 0, 10 ) as $image ) {
			$src = wp_get_attachment_image_url( $image, 'large' );
			if ( $src ) {
				$images[] = $src;
			}
		}

		$data        = array(
			'@type'        => 'RealEstateListing',
			'@id'          => $url . '#listing',
			'url'          => $url,
			'name'         => get_the_title( $id ),
			'datePosted'   => get_the_date( 'c', $id ),
			'dateModified' => get_the_modified_date( 'c', $id ),
			'about'        => $place,
		);
		$description = wp_strip_all_tags( get_the_excerpt( $id ) );
		if ( '' !== $description ) {
			$data['description'] = $description;
		}
		if ( $images ) {
			$data['image'] = $images;
		}
		$price = Fields::get( $id, 'price' );
		if ( $price && ! Fields::get( $id, 'price_on_request' ) ) {
			$statuses = wp_list_pluck( View::terms( $id, 'property_status' ), 'slug' );
			$offer    = array(
				'@type'            => 'Offer',
				'price'            => $price,
				'priceCurrency'    => Settings::get( 'general' )['currency'],
				'availability'     => array_intersect( $statuses, array( 'sold', 'rented' ) ) ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
				'businessFunction' => in_array( 'for-rent', $statuses, true ) || '' !== Fields::get( $id, 'price_period' ) ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
			);
			$period   = array(
				'day'   => 'DAY',
				'week'  => 'WEE',
				'month' => 'MON',
				'year'  => 'ANN',
			);
			if ( isset( $period[ Fields::get( $id, 'price_period' ) ] ) ) {
				$offer['priceSpecification'] = array(
					'@type'         => 'UnitPriceSpecification',
					'price'         => $price,
					'priceCurrency' => $offer['priceCurrency'],
					'unitCode'      => $period[ Fields::get( $id, 'price_period' ) ],
				);
			}
			$agents = Fields::get( $id, 'agents' );
			if ( $agents && 'publish' === get_post_status( $agents[0] ) ) {
				$offer['offeredBy'] = array( '@id' => get_permalink( $agents[0] ) . '#agent' );
			}
			$data['offers'] = $offer;
		}
		return $data;
	}

	/**
	 * Agent or agency data.
	 *
	 * @param int $id Agent or agency.
	 * @return array
	 */
	public static function agent( $id ) {
		$meta   = static function ( $key ) use ( $id ) {
			return (string) get_post_meta( $id, '_estatecraft_' . $key, true );
		};
		$data   = array_filter(
			array(
				'@type'     => 'RealEstateAgent',
				'@id'       => get_permalink( $id ) . '#agent',
				'name'      => get_the_title( $id ),
				'url'       => get_permalink( $id ),
				'telephone' => '' !== $meta( 'phone' ) ? $meta( 'phone' ) : $meta( 'mobile' ),
				'email'     => $meta( 'email' ),
				'image'     => (string) get_the_post_thumbnail_url( $id, 'medium' ),
				'sameAs'    => $meta( 'website' ),
				'address'   => $meta( 'address' ),
			)
		);
		$agency = Agent_Types::AGENT === get_post_type( $id ) ? (int) $meta( 'agency' ) : 0;
		if ( $agency && 'publish' === get_post_status( $agency ) ) {
			$data['parentOrganization'] = array(
				'@type' => 'RealEstateAgent',
				'@id'   => get_permalink( $agency ) . '#agent',
				'name'  => get_the_title( $agency ),
				'url'   => get_permalink( $agency ),
			);
		}
		return $data;
	}

	/**
	 * Breadcrumbs: home, archive, location, page.
	 *
	 * @param int $id Post.
	 * @return array
	 */
	private static function breadcrumbs( $id ) {
		$type  = get_post_type( $id );
		$items = array( array( __( 'Home', 'wp-smart-property' ), home_url( '/' ) ) );
		$link  = get_post_type_archive_link( $type );
		if ( $link ) {
			$items[] = array( get_post_type_object( $type )->labels->name, $link );
		}
		if ( Property_Type::POST_TYPE === $type ) {
			$terms = View::terms( $id, 'property_location' );
			if ( $terms ) {
				$term = $terms[0];
				foreach ( array_reverse( get_ancestors( $term->term_id, 'property_location', 'taxonomy' ) ) as $parent ) {
					$parent  = get_term( $parent, 'property_location' );
					$items[] = array( $parent->name, get_term_link( $parent ) );
				}
				$items[] = array( $term->name, get_term_link( $term ) );
			}
		}
		$items[] = array( get_the_title( $id ), get_permalink( $id ) );

		$list = array();
		foreach ( $items as $position => $item ) {
			if ( is_wp_error( $item[1] ) ) {
				continue;
			}
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => count( $list ) + 1,
				'name'     => wp_strip_all_tags( $item[0] ),
				'item'     => $item[1],
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list,
		);
	}

	/**
	 * Graph for the current page.
	 *
	 * @return array
	 */
	public static function graph() {
		if ( ! is_singular( array( Property_Type::POST_TYPE, Agent_Types::AGENT, Agent_Types::AGENCY ) ) ) {
			return array();
		}
		$id    = get_queried_object_id();
		$graph = array();
		if ( Property_Type::POST_TYPE === get_post_type( $id ) ) {
			$graph[] = self::property( $id );
			$agents  = Fields::get( $id, 'agents' );
			if ( $agents && 'publish' === get_post_status( $agents[0] ) ) {
				$graph[] = self::agent( $agents[0] );
			}
		} else {
			$graph[] = self::agent( $id );
		}
		// SEO plugins print their own breadcrumbs.
		if ( ! self::seo_plugin() ) {
			$graph[] = self::breadcrumbs( $id );
		}
		/**
		 * Filters the structured data of a property, agent or agency page. Return an empty array to
		 * print none.
		 *
		 * @param array $graph Graph nodes.
		 * @param int   $id    Post ID.
		 */
		return apply_filters( 'estatecraft_schema', $graph, $id );
	}

	/**
	 * Print JSON-LD.
	 */
	public static function print_json_ld() {
		$graph = self::graph();
		if ( ! $graph ) {
			return;
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( $graph ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);
		echo "<script type=\"application/ld+json\" class=\"estatecraft-schema\">{$json}</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and & hex-escaped.
	}

	/**
	 * Open Graph tags for property pages (share previews), unless an SEO plugin prints them.
	 */
	public static function print_open_graph() {
		if ( ! is_singular( Property_Type::POST_TYPE ) || self::seo_plugin() ) {
			return;
		}
		$id    = get_queried_object_id();
		$image = View::main_image( $id );
		$tags  = array(
			'og:type'        => 'website',
			'og:title'       => get_the_title( $id ) . ' – ' . View::price( $id ),
			'og:url'         => get_permalink( $id ),
			'og:description' => wp_strip_all_tags( get_the_excerpt( $id ) ),
			'og:site_name'   => get_bloginfo( 'name' ),
		);
		if ( $image ) {
			$src = wp_get_attachment_image_src( $image, 'large' );
			if ( $src ) {
				$tags['og:image']        = $src[0];
				$tags['og:image:width']  = $src[1];
				$tags['og:image:height'] = $src[2];
			}
		}
		foreach ( array_filter( $tags ) as $property => $content ) {
			echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '" />' . "\n";
		}
	}
}
