<?php
/**
 * Site settings, stored in one option.
 *
 * @package EstateCraft
 */

namespace EstateCraft;

/**
 * Settings are read through get() (defaults merged in) and written through save(), which sanitizes
 * every value against the defaults' types.
 */
final class Settings {

	const OPTION = 'estatecraft_settings';

	const CUSTOM_TYPES = array( 'text', 'number', 'select', 'checkbox', 'date' );

	/**
	 * Cached settings for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'general' => array(
				'currency'      => 'USD',
				'symbol'        => '$',
				'symbol_after'  => false,
				'decimals'      => 0,
				'thousands'     => ',',
				'decimal_point' => '.',
				'area_unit'     => 'sqft',
			),
			'pages'   => array(
				'search'    => 0,
				'dashboard' => 0,
				'submit'    => 0,
			),
			'maps'    => array(
				'tiles'       => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
				'lat'         => 51.5072,
				'lng'         => -0.1276,
				'zoom'        => 11,
				'cluster'     => true,
				'geocoding'   => true,
			),
			'emails'  => array(
				'from_name'        => '',
				'from_email'       => '',
				'inquiry_subject'  => __( 'New inquiry about {property}', 'wp-smart-property' ),
				'inquiry_body'     => __( "{name} ({email}, {phone}) wrote about {property}:\n\n{message}\n\n{property_url}", 'wp-smart-property' ),
				'approved_subject' => __( 'Your listing "{property}" is live', 'wp-smart-property' ),
				'approved_body'    => __( "Good news: {property} has been approved and is now published.\n\n{property_url}", 'wp-smart-property' ),
			),
			'portal'  => array(
				'registration'   => false,
				'approve_agents' => true,
				'agents_publish' => false,
				'duration'       => 90,
				'max_images'     => 30,
			),
			'privacy' => array(
				'consent'        => __( 'I agree that my details are used to answer my inquiry.', 'wp-smart-property' ),
				'retention_days' => 365,
			),
			'fields'  => array(),
		);
	}

	/**
	 * All settings, or one section.
	 *
	 * @param string $section Section key (optional).
	 * @return array
	 */
	public static function get( $section = '' ) {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $stored ) ? $stored : array() );
		}
		if ( '' === $section ) {
			return self::$cache;
		}
		return isset( self::$cache[ $section ] ) ? self::$cache[ $section ] : array();
	}

	/**
	 * Save settings (sanitized).
	 *
	 * @param array $settings Settings (any subset of sections).
	 * @return array Saved settings.
	 */
	public static function save( array $settings ) {
		$clean = self::sanitize( array_replace_recursive( self::get(), $settings ) );
		if ( isset( $settings['fields'] ) ) {
			// Lists replace, they do not merge by index.
			$clean['fields'] = self::sanitize_fields( $settings['fields'] );
		}
		update_option( self::OPTION, $clean );
		self::$cache = null;
		return self::get();
	}

	/**
	 * Forget the cached copy (tests, after direct option changes).
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitize against the defaults: unknown keys are dropped, values take the default's type.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function sanitize( array $settings ) {
		$out = array();
		foreach ( self::defaults() as $section => $values ) {
			if ( 'fields' === $section ) {
				$out['fields'] = self::sanitize_fields( isset( $settings['fields'] ) ? $settings['fields'] : array() );
				continue;
			}
			$given = isset( $settings[ $section ] ) && is_array( $settings[ $section ] ) ? $settings[ $section ] : array();
			foreach ( $values as $key => $default ) {
				$out[ $section ][ $key ] = array_key_exists( $key, $given ) ? self::value( $section, $key, $given[ $key ], $default ) : $default;
			}
		}
		return $out;
	}

	/**
	 * One sanitized value.
	 *
	 * @param string $section Section.
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param mixed  $fallback Default (gives the type).
	 * @return mixed
	 */
	private static function value( $section, $key, $value, $fallback ) {
		if ( is_bool( $fallback ) ) {
			return (bool) rest_sanitize_boolean( $value );
		}
		if ( is_int( $fallback ) ) {
			$value = (int) $value;
			$range = array(
				'decimals'       => array( 0, 4 ),
				'zoom'           => array( 1, 19 ),
				'duration'       => array( 0, 3650 ),
				'max_images'     => array( 1, 100 ),
				'retention_days' => array( 0, 3650 ),
			);
			if ( isset( $range[ $key ] ) ) {
				$value = max( $range[ $key ][0], min( $range[ $key ][1], $value ) );
			}
			return 'pages' === $section ? absint( $value ) : $value;
		}
		if ( is_float( $fallback ) ) {
			$value = is_numeric( $value ) ? (float) $value : $fallback;
			return (float) ( 'lat' === $key ? max( -90, min( 90, $value ) ) : max( -180, min( 180, $value ) ) );
		}
		$value = is_scalar( $value ) ? (string) $value : '';
		switch ( $key ) {
			case 'currency':
				$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', $value ) );
				return 3 === strlen( $value ) ? $value : $fallback;
			case 'area_unit':
				return in_array( $value, array( 'sqft', 'm2' ), true ) ? $value : $fallback;
			case 'thousands':
			case 'decimal_point':
				return in_array( $value, array( ',', '.', ' ', "'", '' ), true ) ? $value : $fallback;
			case 'tiles':
				// A tile URL template: https only, with the {z}/{x}/{y} placeholders.
				if ( 0 !== strpos( $value, 'https://' ) || false === strpos( $value, '{z}' ) ) {
					return $fallback;
				}
				// esc_url_raw() would encode the {s}/{z}/{x}/{y} placeholders, so allow URL characters only.
				return preg_replace( '/[^A-Za-z0-9\-._~:\/?#@!$&()*+,;=%{}]/', '', $value );
			case 'attribution':
				return wp_kses(
					$value,
					array(
						'a' => array( 'href' => true ),
					)
				);
			case 'from_email':
				return sanitize_email( $value );
			case 'inquiry_body':
			case 'approved_body':
			case 'consent':
				return sanitize_textarea_field( $value );
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Custom field definitions.
	 *
	 * @param mixed $fields Field list.
	 * @return array[] { key, label, type, options, search, show }
	 */
	public static function sanitize_fields( $fields ) {
		$out  = array();
		$seen = array();
		foreach ( is_array( $fields ) ? array_slice( $fields, 0, 50 ) : array() as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$label = isset( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : '';
			$key   = isset( $field['key'] ) && is_scalar( $field['key'] ) && '' !== $field['key'] ? (string) $field['key'] : $label;
			// "Pool type" and "Has-Lift" become pool_type and has_lift (the settings app does the same).
			$key = substr( trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( remove_accents( $key ) ) ), '_' ), 0, 40 );
			if ( '' === $key || '' === $label || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$type         = isset( $field['type'] ) && in_array( $field['type'], self::CUSTOM_TYPES, true ) ? $field['type'] : 'text';
			$options      = array();
			if ( 'select' === $type ) {
				$raw     = isset( $field['options'] ) ? $field['options'] : array();
				$raw     = is_array( $raw ) ? $raw : explode( "\n", (string) $raw );
				$options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $raw ) ) ) ) );
			}
			$out[] = array(
				'key'     => $key,
				'label'   => $label,
				'type'    => $type,
				'options' => $options,
				'search'  => ! empty( $field['search'] ),
				'show'    => ! isset( $field['show'] ) || ! empty( $field['show'] ),
			);
		}
		return $out;
	}

	/**
	 * Add the custom fields to the property field definitions.
	 *
	 * @param array[] $fields Fields.
	 * @return array[]
	 */
	public static function custom_fields( array $fields ) {
		$types = array(
			'text'     => 'text',
			'number'   => 'number',
			'checkbox' => 'bool',
			'date'     => 'datetime',
		);
		foreach ( self::get( 'fields' ) as $field ) {
			if ( 'select' === $field['type'] ) {
				$type = array( 'enum', array_merge( array( '' ), $field['options'] ) );
			} else {
				$type = $types[ $field['type'] ];
			}
			$default                         = 'checkbox' === $field['type'] ? false : ( 'number' === $field['type'] ? 0 : '' );
			$fields[ 'cf_' . $field['key'] ] = array( $type, $default, true );
		}
		return $fields;
	}
}
