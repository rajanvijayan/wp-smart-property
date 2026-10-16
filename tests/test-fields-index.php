<?php
/**
 * Property fields and the search index.
 *
 * @package EstateCraft
 */

use EstateCraft\Properties\Fields;
use EstateCraft\Search\Index;

/**
 * Fields are sanitized by type and private ones stay out of REST; the index follows the posts.
 */
class Test_Fields_Index extends EstateCraft_TestCase {

	public function test_values_are_sanitized_by_type() {
		$this->assertSame( 3, Fields::sanitize( '3 bedrooms', 'int' ) );
		$this->assertSame( 0, Fields::sanitize( -2, 'int' ) );
		$this->assertSame( 2.5, Fields::sanitize( '2.4', 'half' ) );
		$this->assertSame( 90.0, Fields::sanitize( 123, 'lat' ) );
		$this->assertSame( -0.1275, Fields::sanitize( '-0.1275', 'lng' ) );
		$this->assertSame( '', Fields::sanitize( 'javascript:alert(1)', 'url' ) );
		$this->assertSame( 'month', Fields::sanitize( 'month', array( 'enum', Fields::PERIODS ) ) );
		$this->assertSame( '', Fields::sanitize( 'fortnight', array( 'enum', Fields::PERIODS ) ) );
		$this->assertSame( array( 4, 7 ), Fields::sanitize( array( '4', 'x', 7, 4 ), 'ids' ) );
		$this->assertSame( '2026-11-01 14:00', Fields::sanitize( '2026-11-01 14:00', 'datetime' ) );
		$this->assertSame( '', Fields::sanitize( 'tomorrow', 'datetime' ) );
		$plans = Fields::sanitize( array( array( 'title' => '<b>Ground</b>', 'area' => '62.456', 'bedrooms' => '2' ), 'junk' ), 'plans' );
		$this->assertCount( 1, $plans );
		$this->assertSame( 'Ground', $plans[0]['title'] );
		$this->assertSame( 62.46, $plans[0]['area'] );
	}

	public function test_private_fields_are_not_in_rest() {
		$keys = array_keys( get_registered_meta_keys( 'post', 'property' ) );
		$this->assertContains( '_estatecraft_price', $keys );
		$property = self::factory()->post->create( array( 'post_type' => 'property' ) );
		Fields::set( $property, 'private_notes', 'Keys with neighbour' );
		Fields::set( $property, 'price', 450000 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/properties/' . $property );
		$response = rest_get_server()->dispatch( $request );
		$meta     = $response->get_data()['meta'];
		$this->assertEquals( 450000, $meta['_estatecraft_price'] );
		$this->assertArrayNotHasKey( '_estatecraft_private_notes', $meta );
	}

	public function test_the_index_follows_the_property() {
		global $wpdb;
		$property = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_status' => 'publish',
			)
		);
		Fields::set( $property, 'price', '1250 000' );
		Fields::set( $property, 'price', 1250000 );
		Fields::set( $property, 'bedrooms', 4 );
		Fields::set( $property, 'bathrooms', 2.5 );
		Fields::set( $property, 'lat', 51.5072 );
		Fields::set( $property, 'lng', -0.1276 );
		Fields::set( $property, 'featured', true );
		wp_set_object_terms( $property, 'for-sale', 'property_status' );
		Index::flush();

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Index::table() . ' WHERE post_id = %d', $property ), ARRAY_A ); // phpcs:ignore
		$this->assertSame( 'publish', $row['post_status'] );
		$this->assertSame( 'for-sale', $row['listing'] );
		$this->assertEquals( 1250000, $row['price'] );
		$this->assertEquals( 4, $row['bedrooms'] );
		$this->assertEquals( 2.5, $row['bathrooms'] );
		$this->assertEquals( 51.5072, $row['lat'] );
		$this->assertEquals( 1, $row['featured'] );

		wp_trash_post( $property );
		Index::flush();
		$this->assertSame( 'trash', $wpdb->get_var( $wpdb->prepare( 'SELECT post_status FROM ' . Index::table() . ' WHERE post_id = %d', $property ) ) ); // phpcs:ignore

		wp_delete_post( $property, true );
		Index::flush();
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . Index::table() . ' WHERE post_id = %d', $property ) ) ); // phpcs:ignore
	}

	public function test_the_index_can_be_rebuilt() {
		self::factory()->post->create_many( 3, array( 'post_type' => 'property' ) );
		self::factory()->post->create( array( 'post_type' => 'post' ) );
		Index::rebuild( 0, 200 );
		$this->assertSame( 3, Index::count() );
	}
}
