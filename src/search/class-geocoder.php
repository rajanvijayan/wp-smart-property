<?php
/**
 * Address lookup with OpenStreetMap Nominatim.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Search;

use EstateCraft\Settings;
use WP_Error;

/**
 * Follows the Nominatim usage policy: server-side only, at most one request per second for the
 * whole site, an identifying User-Agent, results cached, and no bulk lookups (the queue in M4 works
 * through pending properties one at a time).
 */
final class Geocoder {

	const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

	const LAST_REQUEST = 'estatecraft_geocode_last';

	/**
	 * Look up an address.
	 *
	 * @param string $query Address.
	 * @return array|WP_Error { lat, lng, label }
	 */
	public static function lookup( $query ) {
		$query = trim( sanitize_text_field( $query ) );
		if ( '' === $query ) {
			return new WP_Error( 'estatecraft_geocode_empty', __( 'Enter an address first.', 'wp-smart-property' ), array( 'status' => 400 ) );
		}
		if ( ! Settings::get( 'maps' )['geocoding'] ) {
			return new WP_Error( 'estatecraft_geocode_off', __( 'Address lookup is turned off in the settings.', 'wp-smart-property' ), array( 'status' => 403 ) );
		}

		$key    = 'estatecraft_geo_' . md5( strtolower( $query ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// One request per second for the whole site.
		$last = (float) get_option( self::LAST_REQUEST, 0 );
		if ( microtime( true ) - $last < 1 ) {
			return new WP_Error( 'estatecraft_geocode_busy', __( 'Address lookup is busy; try again in a moment.', 'wp-smart-property' ), array( 'status' => 429 ) );
		}
		update_option( self::LAST_REQUEST, microtime( true ), false );

		/**
		 * Filters the geocoding request URL (e.g. to use a self-hosted Nominatim).
		 *
		 * @param string $url   Request URL.
		 * @param string $query Address.
		 */
		$url      = apply_filters(
			'estatecraft_geocode_url',
			add_query_arg(
				array(
					'q'      => rawurlencode( $query ),
					'format' => 'jsonv2',
					'limit'  => 1,
				),
				self::ENDPOINT
			),
			$query
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'user-agent' => 'EstateCraft/' . ESTATECRAFT_VERSION . ' (WordPress; ' . home_url( '/' ) . ')',
				'headers'    => array( 'Accept-Language' => get_bloginfo( 'language' ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'estatecraft_geocode_failed', __( 'The address lookup service could not be reached.', 'wp-smart-property' ), array( 'status' => 502 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) ) {
			return new WP_Error( 'estatecraft_geocode_failed', __( 'The address lookup service did not answer.', 'wp-smart-property' ), array( 'status' => 502 ) );
		}
		if ( empty( $data[0]['lat'] ) ) {
			$result = new WP_Error( 'estatecraft_geocode_none', __( 'No place was found for that address.', 'wp-smart-property' ), array( 'status' => 404 ) );
			return $result;
		}
		$result = array(
			'lat'   => round( (float) $data[0]['lat'], 7 ),
			'lng'   => round( (float) $data[0]['lon'], 7 ),
			'label' => isset( $data[0]['display_name'] ) ? sanitize_text_field( $data[0]['display_name'] ) : '',
		);
		set_transient( $key, $result, 30 * DAY_IN_SECONDS );
		return $result;
	}
}
