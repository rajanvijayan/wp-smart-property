<?php
/**
 * Who may manage properties.
 *
 * @package EstateCraft
 */

/**
 * Agents manage their own listings; authors and contributors keep their 2.0 abilities.
 */
class Test_Capabilities extends EstateCraft_TestCase {

	public function test_agents_edit_only_their_own_listings_and_do_not_publish() {
		$agent = self::factory()->user->create( array( 'role' => 'estate_agent' ) );
		$other = self::factory()->user->create( array( 'role' => 'estate_agent' ) );
		$own   = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_author' => $agent,
				'post_status' => 'pending',
			)
		);
		$theirs = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_author' => $other,
			)
		);
		wp_set_current_user( $agent );
		$this->assertTrue( current_user_can( 'edit_post', $own ) );
		$this->assertTrue( current_user_can( 'delete_post', $own ) );
		$this->assertFalse( current_user_can( 'edit_post', $theirs ) );
		$this->assertFalse( current_user_can( 'publish_estate_properties' ) );
		$this->assertFalse( current_user_can( 'manage_estate_terms' ) );
		$this->assertTrue( current_user_can( 'upload_files' ) );
	}

	public function test_roles_keep_what_they_could_do_in_2_0() {
		$property = self::factory()->post->create( array( 'post_type' => 'property' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( current_user_can( 'edit_post', $property ) );
		$this->assertTrue( current_user_can( 'manage_estate_terms' ) );
		$this->assertFalse( current_user_can( 'manage_estatecraft' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertTrue( current_user_can( 'publish_estate_properties' ) );
		$this->assertFalse( current_user_can( 'edit_post', $property ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertTrue( current_user_can( 'edit_estate_properties' ) );
		$this->assertFalse( current_user_can( 'publish_estate_properties' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( current_user_can( 'edit_estate_properties' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( current_user_can( 'manage_estatecraft' ) );
	}

	public function test_inquiries_are_private() {
		$object = get_post_type_object( 'estate_inquiry' );
		$this->assertFalse( $object->public );
		$this->assertFalse( $object->show_in_rest );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'estate_agent' ) ) );
		$this->assertFalse( current_user_can( $object->cap->edit_posts ) );
	}
}
