<?php
/**
 * Settings and address lookup routes.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Rest;

use EstateCraft\Search\Geocoder;
use EstateCraft\Settings;
use WP_REST_Request;
use WP_REST_Server;

/**
 * GET/POST /settings (site managers); POST /geocode (anyone who can edit properties).
 */
final class Settings_Controller extends Rest_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function () {
						return Settings::get();
					},
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/geocode',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'geocode' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_estate_properties' );
				},
				'args'                => array(
					'query' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Save settings.
	 *
	 * @param WP_REST_Request $request Request whose JSON body holds settings sections.
	 * @return array
	 */
	public function save( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		return Settings::save( is_array( $data ) ? $data : array() );
	}

	/**
	 * Look up an address.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function geocode( WP_REST_Request $request ) {
		return Geocoder::lookup( (string) $request['query'] );
	}
}
