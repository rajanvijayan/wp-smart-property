<?php
/**
 * Structured property details, stored as registered post meta.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Properties;

/**
 * One definition per field: type, default and whether it is public. Every value is sanitized by type
 * on save, whichever way it arrives (editor, REST, portal, import).
 */
final class Fields {

	/**
	 * Meta key prefix. Keys start with an underscore so they stay out of the Custom Fields box.
	 */
	const PREFIX = '_estatecraft_';

	const PERIODS = array( '', 'day', 'week', 'month', 'year' );

	const ENERGY = array( '', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' );

	/**
	 * Field definitions: type, default, public (shown on the site and in REST view context).
	 *
	 * @return array[]
	 */
	public static function all() {
		$fields = array(
			'price'            => array( 'number', 0, true ),
			'price_period'     => array( array( 'enum', self::PERIODS ), '', true ),
			'price_on_request' => array( 'bool', false, true ),
			'area'             => array( 'number', 0, true ),
			'land_area'        => array( 'number', 0, true ),
			'bedrooms'         => array( 'int', 0, true ),
			'bathrooms'        => array( 'half', 0, true ),
			'garages'          => array( 'int', 0, true ),
			'year_built'       => array( 'int', 0, true ),
			'street'           => array( 'text', '', true ),
			'postcode'         => array( 'text', '', true ),
			'lat'              => array( 'lat', 0, true ),
			'lng'              => array( 'lng', 0, true ),
			'hide_address'     => array( 'bool', false, true ),
			'gallery'          => array( 'ids', array(), true ),
			'floor_plans'      => array( 'plans', array(), true ),
			'video_url'        => array( 'url', '', true ),
			'tour_url'         => array( 'url', '', true ),
			'energy_class'     => array( array( 'enum', self::ENERGY ), '', true ),
			'reference'        => array( 'text', '', true ),
			'featured'         => array( 'bool', false, true ),
			'open_houses'      => array( 'slots', array(), true ),
			'agents'           => array( 'ids', array(), true ),
			'private_notes'    => array( 'textarea', '', false ),
			'expires'          => array( 'datetime', '', false ),
			'geocode'          => array( array( 'enum', array( '', 'pending', 'done', 'failed', 'manual' ) ), '', false ),
		);

		/**
		 * Filters the property field definitions (custom fields are added here).
		 *
		 * @param array[] $fields key => array( type, default, public ).
		 */
		return apply_filters( 'estatecraft_property_fields', $fields );
	}

	/**
	 * Register every field as post meta on the property post type.
	 */
	public static function register() {
		foreach ( self::all() as $key => $field ) {
			list( $type, $default, $public ) = $field;
			register_post_meta(
				Property_Type::POST_TYPE,
				self::PREFIX . $key,
				array(
					'type'              => self::rest_type( $type ),
					'single'            => true,
					'default'           => $default,
					'sanitize_callback' => static function ( $value ) use ( $type ) {
						return Fields::sanitize( $value, $type );
					},
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
					'show_in_rest'      => $public ? array( 'schema' => self::rest_schema( $type, $default ) ) : false,
				)
			);
		}
	}

	/**
	 * Read a field with its default.
	 *
	 * @param int    $post_id Property ID.
	 * @param string $key     Field key without prefix.
	 * @return mixed
	 */
	public static function get( $post_id, $key ) {
		$fields = self::all();
		if ( ! isset( $fields[ $key ] ) ) {
			return null;
		}
		$value = get_post_meta( $post_id, self::PREFIX . $key, true );
		if ( '' === $value && ! metadata_exists( 'post', $post_id, self::PREFIX . $key ) ) {
			return $fields[ $key ][1];
		}
		return self::sanitize( $value, $fields[ $key ][0] );
	}

	/**
	 * Save a field (sanitized by type).
	 *
	 * @param int    $post_id Property ID.
	 * @param string $key     Field key without prefix.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	public static function set( $post_id, $key, $value ) {
		$fields = self::all();
		if ( ! isset( $fields[ $key ] ) ) {
			return false;
		}
		return (bool) update_post_meta( $post_id, self::PREFIX . $key, self::sanitize( $value, $fields[ $key ][0] ) );
	}

	/**
	 * Sanitize a value for a field type.
	 *
	 * @param mixed        $value Value.
	 * @param string|array $type  Type, or array( 'enum', options ).
	 * @return mixed
	 */
	public static function sanitize( $value, $type ) {
		if ( is_array( $type ) ) {
			$value = is_scalar( $value ) ? (string) $value : '';
			return in_array( $value, $type[1], true ) ? $value : $type[1][0];
		}
		switch ( $type ) {
			case 'int':
				return max( 0, (int) $value );
			case 'number':
				return is_numeric( $value ) ? max( 0, round( (float) $value, 2 ) ) : 0;
			case 'half':
				return is_numeric( $value ) ? max( 0, round( (float) $value * 2 ) / 2 ) : 0;
			case 'lat':
				return is_numeric( $value ) ? round( max( -90, min( 90, (float) $value ) ), 7 ) : 0;
			case 'lng':
				return is_numeric( $value ) ? round( max( -180, min( 180, (float) $value ) ), 7 ) : 0;
			case 'bool':
				return (bool) rest_sanitize_boolean( $value );
			case 'text':
				return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
			case 'textarea':
				return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
			case 'url':
				return esc_url_raw( is_scalar( $value ) ? (string) $value : '', array( 'http', 'https' ) );
			case 'datetime':
				$value = is_scalar( $value ) ? (string) $value : '';
				return preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value ) ? $value : '';
			case 'ids':
				return array_values( array_unique( array_filter( array_map( 'absint', is_array( $value ) ? $value : array() ) ) ) );
			case 'plans':
				$out = array();
				foreach ( is_array( $value ) ? array_slice( $value, 0, 20 ) : array() as $plan ) {
					if ( ! is_array( $plan ) ) {
						continue;
					}
					$out[] = array(
						'title'     => sanitize_text_field( isset( $plan['title'] ) ? (string) $plan['title'] : '' ),
						'image_id'  => isset( $plan['image_id'] ) ? absint( $plan['image_id'] ) : 0,
						'area'      => isset( $plan['area'] ) && is_numeric( $plan['area'] ) ? max( 0, round( (float) $plan['area'], 2 ) ) : 0,
						'bedrooms'  => isset( $plan['bedrooms'] ) ? absint( $plan['bedrooms'] ) : 0,
						'bathrooms' => isset( $plan['bathrooms'] ) && is_numeric( $plan['bathrooms'] ) ? round( (float) $plan['bathrooms'] * 2 ) / 2 : 0,
					);
				}
				return $out;
			case 'slots':
				$out = array();
				foreach ( is_array( $value ) ? array_slice( $value, 0, 20 ) : array() as $slot ) {
					$start = isset( $slot['start'] ) ? self::sanitize( $slot['start'], 'datetime' ) : '';
					$end   = isset( $slot['end'] ) ? self::sanitize( $slot['end'], 'datetime' ) : '';
					if ( '' !== $start ) {
						$out[] = array(
							'start' => $start,
							'end'   => $end,
						);
					}
				}
				return $out;
		}
		return '';
	}

	/**
	 * Meta type for register_post_meta().
	 *
	 * @param string|array $type Field type.
	 * @return string
	 */
	private static function rest_type( $type ) {
		if ( is_array( $type ) ) {
			return 'string';
		}
		$map = array(
			'int'    => 'integer',
			'number' => 'number',
			'half'   => 'number',
			'lat'    => 'number',
			'lng'    => 'number',
			'bool'   => 'boolean',
			'ids'    => 'array',
			'plans'  => 'array',
			'slots'  => 'array',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : 'string';
	}

	/**
	 * REST schema for a field.
	 *
	 * @param string|array $type    Field type.
	 * @param mixed        $fallback Default.
	 * @return array
	 */
	private static function rest_schema( $type, $fallback ) {
		$schema = array(
			'type'    => self::rest_type( $type ),
			'default' => $fallback,
		);
		if ( is_array( $type ) ) {
			$schema['enum'] = $type[1];
		} elseif ( 'ids' === $type ) {
			$schema['items'] = array( 'type' => 'integer' );
		} elseif ( 'plans' === $type ) {
			$schema['items'] = array(
				'type'       => 'object',
				'properties' => array(
					'title'     => array( 'type' => 'string' ),
					'image_id'  => array( 'type' => 'integer' ),
					'area'      => array( 'type' => 'number' ),
					'bedrooms'  => array( 'type' => 'integer' ),
					'bathrooms' => array( 'type' => 'number' ),
				),
			);
		} elseif ( 'slots' === $type ) {
			$schema['items'] = array(
				'type'       => 'object',
				'properties' => array(
					'start' => array( 'type' => 'string' ),
					'end'   => array( 'type' => 'string' ),
				),
			);
		}
		return $schema;
	}
}
