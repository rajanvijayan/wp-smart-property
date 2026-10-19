<?php
/**
 * Property and agent editors, and who may use the settings routes.
 *
 * @package EstateCraft
 */

use EstateCraft\Admin\Agent_Editor;
use EstateCraft\Admin\Property_Editor;
use EstateCraft\Properties\Fields;
use EstateCraft\Settings;

/**
 * Saving from wp-admin.
 */
class Test_Admin extends EstateCraft_TestCase {

	public function tear_down() {
		$_POST = array();
		Settings::flush();
		parent::tear_down();
	}

	/**
	 * Post the property details box.
	 *
	 * @param int   $post_id Property.
	 * @param array $values  Values.
	 */
	private function post_details( $post_id, array $values ) {
		$_POST = array(
			Property_Editor::NONCE . '_nonce' => wp_create_nonce( Property_Editor::NONCE ),
			'estatecraft_details'             => wp_slash( wp_json_encode( $values ) ),
		);
		Property_Editor::save( $post_id, get_post( $post_id ) );
	}

	public function test_details_are_saved_by_field_type() {
		$agent_user = self::factory()->user->create( array( 'role' => 'estate_agent' ) );
		$property   = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_author' => $agent_user,
			)
		);
		wp_set_current_user( $agent_user );
		$this->post_details(
			$property,
			array(
				'price'         => '450000.456',
				'bedrooms'      => '3',
				'bathrooms'     => '1.4',
				'street'        => '<b>12 Rose Lane</b>',
				'lat'           => 51.5,
				'lng'           => -0.12,
				'gallery'       => array( 5, '7', 5, 'x' ),
				'open_houses'   => array(
					array(
						'start' => '2026-11-01 10:00',
						'end'   => '2026-11-01 12:00',
					),
					array( 'start' => 'soon' ),
				),
				'private_notes' => "Keys\nwith neighbour",
				'expires'       => '2030-01-01',
				'not_a_field'   => 'ignored',
			)
		);
		$this->assertEquals( 450000.46, Fields::get( $property, 'price' ) );
		$this->assertSame( 3, Fields::get( $property, 'bedrooms' ) );
		$this->assertEquals( 1.5, Fields::get( $property, 'bathrooms' ) );
		$this->assertSame( '12 Rose Lane', Fields::get( $property, 'street' ) );
		$this->assertSame( array( 5, 7 ), Fields::get( $property, 'gallery' ) );
		$this->assertCount( 1, Fields::get( $property, 'open_houses' ) );
		$this->assertSame( "Keys\nwith neighbour", Fields::get( $property, 'private_notes' ) );
		// Agents do not set their own expiry.
		$this->assertSame( '', Fields::get( $property, 'expires' ) );
		$this->assertSame( '', get_post_meta( $property, Fields::PREFIX . 'not_a_field', true ) );
	}

	public function test_managers_set_expiry_and_a_hand_placed_pin_is_kept() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'estate_manager' ) ) );
		$property = self::factory()->post->create( array( 'post_type' => 'property' ) );
		Fields::set( $property, 'geocode', 'pending' );
		$this->post_details(
			$property,
			array(
				'expires' => '2030-01-01',
				'lat'     => 48.85,
				'lng'     => 2.35,
				'geocode' => 'pending',
			)
		);
		$this->assertSame( '2030-01-01', Fields::get( $property, 'expires' ) );
		$this->assertSame( 'manual', Fields::get( $property, 'geocode' ) );
	}

	public function test_agents_record_how_their_pin_was_placed() {
		$agent    = self::factory()->user->create( array( 'role' => 'estate_agent' ) );
		$property = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_author' => $agent,
			)
		);
		Fields::set( $property, 'geocode', 'pending' );
		wp_set_current_user( $agent );
		// "Find address on the map".
		$this->post_details(
			$property,
			array(
				'lat'     => 51.5,
				'lng'     => -0.1,
				'geocode' => 'done',
			)
		);
		$this->assertSame( 'done', Fields::get( $property, 'geocode' ) );
		// Without coordinates, or any other state, the change is ignored.
		$this->post_details( $property, array( 'geocode' => 'failed' ) );
		$this->assertSame( 'done', Fields::get( $property, 'geocode' ) );
		$this->post_details(
			$property,
			array(
				'lat'     => 0,
				'lng'     => 0,
				'geocode' => 'manual',
			)
		);
		$this->assertSame( 'done', Fields::get( $property, 'geocode' ), '"Placed by hand" needs a pin.' );
		// "Remove pin": the address can be looked up again.
		$this->post_details(
			$property,
			array(
				'lat'     => 0,
				'lng'     => 0,
				'geocode' => '',
			)
		);
		$this->assertSame( '', Fields::get( $property, 'geocode' ) );
	}

	public function test_details_need_a_valid_nonce_and_edit_rights() {
		$property = self::factory()->post->create( array( 'post_type' => 'property' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'estate_agent' ) ) );
		$this->post_details( $property, array( 'price' => 100 ) );
		$this->assertEquals( 0, Fields::get( $property, 'price' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST = array(
			Property_Editor::NONCE . '_nonce' => 'wrong',
			'estatecraft_details'             => wp_json_encode( array( 'price' => 100 ) ),
		);
		Property_Editor::save( $property, get_post( $property ) );
		$this->assertEquals( 0, Fields::get( $property, 'price' ) );
	}

	public function test_only_agent_managers_link_a_user_to_an_agent() {
		$person = self::factory()->user->create( array( 'role' => 'estate_agent' ) );
		$agent  = self::factory()->post->create(
			array(
				'post_type'   => 'estate_agent',
				'post_author' => $person,
			)
		);
		// Estate agents cannot edit agent profiles in wp-admin at all.
		wp_set_current_user( $person );
		$_POST = array(
			Agent_Editor::NONCE . '_nonce' => wp_create_nonce( Agent_Editor::NONCE ),
			'estatecraft_contact'          => array( 'email' => 'jo@example.com' ),
		);
		Agent_Editor::save( $agent );
		$this->assertEmpty( get_post_meta( $agent, '_estatecraft_email', true ) );

		// Someone who may edit their own profile, but not manage agents, cannot link a user.
		wp_get_current_user()->add_cap( 'edit_estate_agents' );
		wp_get_current_user()->add_cap( 'edit_published_estate_agents' );
		$_POST = array(
			Agent_Editor::NONCE . '_nonce' => wp_create_nonce( Agent_Editor::NONCE ),
			'estatecraft_contact'          => array(
				'email'   => 'jo@example.com',
				'user_id' => '1',
			),
		);
		Agent_Editor::save( $agent );
		$this->assertSame( 'jo@example.com', get_post_meta( $agent, '_estatecraft_email', true ) );
		$this->assertEmpty( get_post_meta( $agent, '_estatecraft_user_id', true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST[ Agent_Editor::NONCE . '_nonce' ] = wp_create_nonce( Agent_Editor::NONCE );
		$_POST['estatecraft_contact']['user_id'] = (string) $person;
		Agent_Editor::save( $agent );
		$this->assertEquals( $person, get_post_meta( $agent, '_estatecraft_user_id', true ) );
	}

	public function test_settings_routes_are_for_site_managers() {
		$server = rest_get_server();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $server->dispatch( new WP_REST_Request( 'GET', '/estatecraft/v1/settings' ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$response = $server->dispatch( new WP_REST_Request( 'GET', '/estatecraft/v1/settings' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'USD', $response->get_data()['general']['currency'] );

		$request = new WP_REST_Request( 'POST', '/estatecraft/v1/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'general' => array( 'currency' => 'gbp' ) ) ) );
		$response = $server->dispatch( $request );
		$this->assertSame( 'GBP', $response->get_data()['general']['currency'] );
	}

	public function test_address_lookup_is_for_people_who_edit_properties() {
		$server  = rest_get_server();
		$request = new WP_REST_Request( 'POST', '/estatecraft/v1/geocode' );
		$request->set_param( 'query', '' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'estate_agent' ) ) );
		$this->assertSame( 400, $server->dispatch( $request )->get_status() );
	}
}
