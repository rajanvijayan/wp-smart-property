<?php
/**
 * Upgrade from WP Smart Property 2.0.
 *
 * @package EstateCraft
 */

use EstateCraft\Legacy\Legacy_Taxonomies;
use EstateCraft\Legacy\Migrator;
use EstateCraft\Properties\Fields;

/**
 * Seeds 2.0 data the way 2.0 stored it, then migrates.
 */
class Test_Migrator extends EstateCraft_TestCase {

	/**
	 * Property IDs.
	 *
	 * @var int[]
	 */
	private $ids = array();

	public function set_up() {
		parent::set_up();
		delete_option( Migrator::OPTION );
		delete_option( Migrator::MAP );

		$homes = array(
			'house'   => array( 'House', 'Featured', 'United Kingdom', 'London', '3', '2', "12 Rose Lane\nLondon N1 7AB" ),
			'flat'    => array( 'Apartment', 'New', 'United Kingdom', 'London', '2 Bedrooms', '1.5', '45 King Street' ),
			'shared'  => array( 'House', '', 'United States', 'Springfield', 'Three', 'Garden flat', '' ),
			'shared2' => array( 'House', '', 'United Kingdom', 'Springfield', 'Garden flat', '1', '' ),
		);
		foreach ( $homes as $key => $h ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'property',
					'post_status' => 'publish',
					'post_title'  => $key,
				)
			);
			wp_set_object_terms( $id, $h[0], Legacy_Taxonomies::TYPE );
			if ( $h[1] ) {
				wp_set_object_terms( $id, $h[1], Legacy_Taxonomies::CATEGORY );
			}
			wp_set_object_terms( $id, $h[2], Legacy_Taxonomies::COUNTRY );
			wp_set_object_terms( $id, $h[3], Legacy_Taxonomies::CITY );
			wp_set_object_terms( $id, $h[4], Legacy_Taxonomies::BEDS );
			wp_set_object_terms( $id, $h[5], Legacy_Taxonomies::BATHS );
			if ( $h[6] ) {
				update_post_meta( $id, 'property_address', $h[6] );
				update_post_meta( $id, 'admin_notes', 'Keys with neighbour' );
			}
			$this->ids[ $key ] = $id;
		}
	}

	public function test_terms_fields_and_locations_are_migrated() {
		$this->assertTrue( Migrator::pending() );
		$this->assertTrue( Migrator::has_legacy_data() );
		Migrator::run_all();
		$this->assertFalse( Migrator::pending() );

		$house = $this->ids['house'];
		$this->assertSame( array( 'house' ), wp_get_object_terms( $house, 'property_type', array( 'fields' => 'slugs' ) ) );
		$this->assertSame( array( 'featured' ), wp_get_object_terms( $house, 'property_category', array( 'fields' => 'slugs' ) ) );

		// London is only in the UK, so it sits under United Kingdom.
		$london = get_term_by( 'slug', 'london', 'property_location' );
		$uk     = get_term_by( 'slug', 'united-kingdom', 'property_location' );
		$this->assertSame( $uk->term_id, $london->parent );
		// Springfield properties are in two countries, so it stays top level.
		$this->assertSame( 0, get_term_by( 'slug', 'springfield', 'property_location' )->parent );

		$this->assertSame( 3, Fields::get( $house, 'bedrooms' ) );
		$this->assertEquals( 2, Fields::get( $house, 'bathrooms' ) );
		$this->assertSame( 2, Fields::get( $this->ids['flat'], 'bedrooms' ) );
		$this->assertEquals( 1.5, Fields::get( $this->ids['flat'], 'bathrooms' ) );
		$this->assertSame( 3, Fields::get( $this->ids['shared'], 'bedrooms' ) );
		// Names that are not numbers become features.
		$this->assertSame( array( 'Garden flat' ), wp_get_object_terms( $this->ids['shared'], 'property_feature', array( 'fields' => 'names' ) ) );
		$this->assertSame( array( 'Garden flat' ), wp_get_object_terms( $this->ids['shared2'], 'property_feature', array( 'fields' => 'names' ) ) );

		$this->assertSame( '12 Rose Lane, London N1 7AB', Fields::get( $house, 'street' ) );
		$this->assertSame( 'pending', Fields::get( $house, 'geocode' ) );
		$this->assertSame( 'Keys with neighbour', Fields::get( $house, 'private_notes' ) );

		// The 2.0 data is untouched.
		$this->assertSame( "12 Rose Lane\nLondon N1 7AB", get_post_meta( $house, 'property_address', true ) );
		$this->assertSame( array( 'House' ), wp_get_object_terms( $house, Legacy_Taxonomies::TYPE, array( 'fields' => 'names' ) ) );

		$report = Migrator::state()['report'];
		$this->assertSame( 4, $report['properties'] );
		$this->assertSame( 2, $report['addresses'] );
	}

	public function test_running_again_changes_nothing() {
		Migrator::run_all();
		$count = wp_count_terms( array( 'taxonomy' => 'property_location' ) );
		delete_option( Migrator::OPTION );
		Migrator::run_all();
		$this->assertSame( $count, wp_count_terms( array( 'taxonomy' => 'property_location' ) ) );
		$this->assertCount( 1, wp_get_object_terms( $this->ids['house'], 'property_type' ) );
	}

	public function test_sites_without_2_0_data_finish_at_once() {
		foreach ( $this->ids as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( array_keys( Legacy_Taxonomies::all() ) as $taxonomy ) {
			foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
		}
		$this->assertFalse( Migrator::has_legacy_data() );
		Migrator::maybe_run();
		$this->assertFalse( Migrator::pending() );
	}

	public function test_old_archive_urls_point_to_the_new_ones() {
		Migrator::run_all();
		$city = get_term_by( 'slug', 'london', Legacy_Taxonomies::CITY );
		$this->assertSame( get_term_link( get_term_by( 'slug', 'london', 'property_location' ) ), Legacy_Taxonomies::new_url( $city ) );
		$beds = get_term_by( 'name', '2 Bedrooms', Legacy_Taxonomies::BEDS );
		$this->assertStringContainsString( 'bedrooms=2', Legacy_Taxonomies::new_url( $beds ) );
	}

	public function test_counts_are_parsed_from_term_names() {
		$this->assertSame( 3, Migrator::parse_count( '3' ) );
		$this->assertSame( 4, Migrator::parse_count( '4+ Bedrooms' ) );
		$this->assertSame( 2, Migrator::parse_count( 'Two' ) );
		$this->assertSame( 1.5, Migrator::parse_count( '1,5 baths', true ) );
		$this->assertSame( 1, Migrator::parse_count( '1.5', false ) );
		$this->assertSame( 0, Migrator::parse_count( 'Studio' ) );
		$this->assertNull( Migrator::parse_count( 'Garden flat' ) );
	}
}
