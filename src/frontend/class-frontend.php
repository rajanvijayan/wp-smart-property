<?php
/**
 * Front-end entry point.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use WP_Post;
use WP_REST_Response;

/**
 * Wires the front-end parts together.
 */
final class Frontend {

	/**
	 * Hooks.
	 */
	public static function register() {
		Assets::register();
		Blocks::register();
		Templates::register();
		Query::register();
		Schema::register();
		Patterns::register();
		add_filter( 'rest_prepare_' . Property_Type::POST_TYPE, array( self::class, 'rest_privacy' ), 10, 2 );
	}

	/**
	 * When the exact address is hidden, the REST API does not reveal it to visitors either.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_Post          $post     Property.
	 * @return WP_REST_Response
	 */
	public static function rest_privacy( $response, $post ) {
		if ( ! Fields::get( $post->ID, 'hide_address' ) || current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( array(
				'street'   => '',
				'postcode' => '',
				'lat'      => 0,
				'lng'      => 0,
			) as $key => $empty ) {
				if ( array_key_exists( Fields::PREFIX . $key, $data['meta'] ) ) {
					$data['meta'][ Fields::PREFIX . $key ] = $empty;
				}
			}
			$response->set_data( $data );
		}
		return $response;
	}
}
