<?php
/**
 * Front end: display data, blocks, queries, templates, privacy and structured data.
 *
 * @package EstateCraft
 */

use EstateCraft\Frontend\Blocks;
use EstateCraft\Frontend\Query;
use EstateCraft\Frontend\Schema;
use EstateCraft\Frontend\Templates;
use EstateCraft\Frontend\View;
use EstateCraft\Properties\Agent_Link;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Taxonomies;
use EstateCraft\Search\Index;
use EstateCraft\Settings;

/**
 * Property pages and listings.
 */
class Test_Frontend extends EstateCraft_TestCase {

	/**
	 * A property with details.
	 *
	 * @var int
	 */
	private $house;

	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION );
		Settings::flush();
		Taxonomies::install_defaults();
		$this->house = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_title'  => 'Rose Cottage',
				'post_status' => 'publish',
			)
		);
		$uk     = wp_insert_term( 'United Kingdom', 'property_location' );
		$london = wp_insert_term( 'London', 'property_location', array( 'parent' => $uk['term_id'] ) );
		wp_set_object_terms( $this->house, array( $london['term_id'] ), 'property_location' );
		wp_set_object_terms( $this->house, 'for-sale', 'property_status' );
		wp_set_object_terms( $this->house, 'house', 'property_type' );
		foreach ( array(
			'price'     => 450000,
			'bedrooms'  => 3,
			'bathrooms' => 1.5,
			'area'      => 1200,
			'street'    => '12 Rose Lane, London',
			'postcode'  => 'N1 7AB',
			'lat'       => 51.5,
			'lng'       => -0.12,
		) as $key => $value ) {
			Fields::set( $this->house, $key, $value );
		}
		Index::flush();
	}

	public function tear_down() {
		Settings::flush();
		parent::tear_down();
	}

	public function test_address_skips_repeated_place_names_and_hides_the_street() {
		$this->assertSame( '12 Rose Lane, London, N1 7AB, United Kingdom', View::address( $this->house ) );
		Fields::set( $this->house, 'hide_address', true );
		$this->assertSame( 'London, United Kingdom', View::address( $this->house ) );
	}

	public function test_a_hidden_address_shows_only_an_area_on_the_map() {
		$exact = View::map_point( $this->house );
		$this->assertFalse( $exact['approximate'] );
		$this->assertSame( 51.5, $exact['lat'] );

		Fields::set( $this->house, 'hide_address', true );
		$area = View::map_point( $this->house );
		$this->assertTrue( $area['approximate'] );
		$this->assertNotEquals( 51.5, $area['lat'] );
		// The property is inside the circle drawn around the shifted centre.
		$metres = 111320 * sqrt( pow( $area['lat'] - 51.5, 2 ) + pow( ( $area['lng'] + 0.12 ) * cos( deg2rad( 51.5 ) ), 2 ) );
		$this->assertLessThan( $area['radius'], $metres );
		$this->assertGreaterThan( 50, $metres );
		$this->assertSame( $area, View::map_point( $this->house ), 'The area is stable between page views.' );

		$html = Blocks::map( array(), '', $this->block( 'property-map' ) );
		$this->assertStringNotContainsString( '51.5,', $html );
		$this->assertStringNotContainsString( 'openstreetmap.org/?mlat', $html );
	}

	public function test_rest_api_hides_the_exact_address_from_visitors() {
		Fields::set( $this->house, 'hide_address', true );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/properties/' . $this->house );
		$response = rest_get_server()->dispatch( $request );
		$meta     = $response->get_data()['meta'];
		$this->assertSame( 0, $meta['_estatecraft_lat'] );
		$this->assertSame( '', $meta['_estatecraft_street'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$meta = rest_get_server()->dispatch( $request )->get_data()['meta'];
		$this->assertEquals( 51.5, $meta['_estatecraft_lat'] );
	}

	public function test_images_start_with_the_featured_image_without_repeats() {
		$a = self::factory()->attachment->create_upload_object( $this->image( 'a' ) );
		$b = self::factory()->attachment->create_upload_object( $this->image( 'b' ) );
		Fields::set( $this->house, 'gallery', array( $a, $b ) );
		$this->assertSame( array( $a, $b ), View::images( $this->house ) );
		set_post_thumbnail( $this->house, $b );
		$this->assertSame( array( $b, $a ), View::images( $this->house ) );

		$html = Blocks::gallery( array(), '', $this->block( 'property-gallery' ) );
		$this->assertStringContainsString( 'fetchpriority="high"', $html );
		$this->assertStringContainsString( 'data-estatecraft-gallery', $html );
		$this->assertSame( 2, substr_count( $html, 'estatecraft-gallery__item' ) );
	}

	public function test_card_shows_price_title_place_and_rooms() {
		$card = View::card( $this->house );
		$this->assertStringContainsString( '$450,000', $card );
		$this->assertStringContainsString( 'Rose Cottage', $card );
		$this->assertStringContainsString( 'United Kingdom', $card );
		$this->assertStringContainsString( '1.5', $card );
		$this->assertStringContainsString( 'For sale', $card );

		// A 2.0 "Featured" category does not repeat the featured badge.
		Fields::set( $this->house, 'featured', true );
		wp_set_object_terms( $this->house, 'Featured', 'property_category' );
		$this->assertSame( 1, substr_count( View::badges( $this->house ), '>Featured<' ) );
	}

	public function test_details_include_shown_custom_fields() {
		Settings::save(
			array(
				'fields' => array(
					array(
						'label' => 'Pool',
						'type'  => 'checkbox',
					),
					array(
						'label' => 'Notes for staff',
						'type'  => 'text',
						'show'  => false,
					),
				),
			)
		);
		Fields::set( $this->house, 'cf_pool', true );
		Fields::set( $this->house, 'cf_notes_for_staff', 'secret' );
		$html = Blocks::details( array(), '', $this->block( 'property-details' ) );
		$this->assertStringContainsString( '<dt>Pool</dt><dd>Yes</dd>', $html );
		$this->assertStringNotContainsString( 'secret', $html );

		$only = Blocks::details( array( 'fields' => array( 'bedrooms' ) ), '', $this->block( 'property-details' ) );
		$this->assertStringContainsString( 'Bedrooms', $only );
		$this->assertStringNotContainsString( 'Price', $only );
	}

	public function test_agent_listings_follow_the_agents_field() {
		$jo  = self::factory()->post->create( array( 'post_type' => 'estate_agent' ) );
		$sam = self::factory()->post->create( array( 'post_type' => 'estate_agent' ) );
		Fields::set( $this->house, 'agents', array( $jo, $sam ) );
		$this->assertEqualSets( array( $jo, $sam ), array_map( 'intval', get_post_meta( $this->house, Agent_Link::KEY, false ) ) );

		$both = Query::listings( array( 'agents' => array( $jo, $sam ) ) );
		$this->assertSame( array( $this->house ), wp_list_pluck( $both->posts, 'ID' ), 'Listed once although both agents match.' );

		Fields::set( $this->house, 'agents', array( $sam ) );
		$this->assertSame( array(), Query::listings( array( 'agents' => array( $jo ) ) )->posts );
		$this->assertSame( array(), Query::listings( array( 'agents' => array() ) )->posts );
	}

	public function test_archives_show_featured_first_and_filter_rooms() {
		$small = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_status' => 'publish',
				'post_date'   => '2025-06-01 00:00:00',
			)
		);
		$big   = self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_status' => 'publish',
				'post_date'   => '2020-01-01 00:00:00',
			)
		);
		wp_update_post(
			array(
				'ID'            => $this->house,
				'post_date'     => '2019-01-01 00:00:00',
				'post_date_gmt' => '2019-01-01 00:00:00',
			)
		);
		Fields::set( $small, 'bedrooms', 1 );
		Fields::set( $big, 'bedrooms', 5 );
		Fields::set( $big, 'featured', true );
		Index::flush();

		$this->go_to( get_post_type_archive_link( 'property' ) );
		$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertSame( $big, $ids[0], 'Featured first.' );
		$this->assertSame( $small, $ids[1], 'Then newest.' );
		$this->assertSame( 'archive-property', Templates::current() );

		$this->go_to( add_query_arg( 'bedrooms', 3, get_post_type_archive_link( 'property' ) ) );
		$this->assertEqualSets( array( $big, $this->house ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );

		$cheap = Query::listings( array( 'order' => 'price-asc' ) );
		$this->assertSame( $this->house, $cheap->posts[0]->ID, 'Properties without a price come last.' );
	}

	public function test_templates_and_hierarchy() {
		foreach ( array_keys( Templates::all() ) as $slug ) {
			$names = $this->block_names( parse_blocks( Templates::block_template( $slug ) ) );
			$this->assertContains( 'core/template-part', $names, $slug );
		}
		$single = $this->block_names( parse_blocks( Templates::body( 'single-property' ) ) );
		foreach ( array( 'estatecraft/property-gallery', 'estatecraft/property-price', 'estatecraft/property-map', 'core/post-content' ) as $name ) {
			$this->assertContains( $name, $single );
		}

		$this->go_to( get_term_link( 'house', 'property_type' ) );
		$this->assertSame( array( 'taxonomy-property_type-house', 'taxonomy-property_type', 'archive-property', 'taxonomy' ), Templates::taxonomy_hierarchy( array( 'taxonomy-property_type-house', 'taxonomy-property_type', 'taxonomy' ) ) );

		// Classic themes: plugin template unless the theme has a specific one.
		$this->assertSame( ESTATECRAFT_DIR . 'templates/single-property.php', Templates::resolve( 'single-property', '/theme/single.php' ) );
		$this->assertSame( '/theme/single-property.php', Templates::resolve( 'single-property', '/theme/single-property.php' ) );
		$this->assertSame( '/theme/taxonomy-property_type.php', Templates::resolve( 'archive-property', '/theme/taxonomy-property_type.php' ) );
	}

	public function test_blocks_are_registered_with_server_rendering() {
		foreach ( array_keys( Blocks::all() ) as $folder ) {
			$type = WP_Block_Type_Registry::get_instance()->get_registered( 'estatecraft/' . $folder );
			$this->assertNotNull( $type, $folder );
			$this->assertTrue( $type->is_dynamic(), $folder );
		}
		$html = render_block(
			array(
				'blockName' => 'estatecraft/property-price',
				'attrs'     => array(),
			)
		);
		$this->assertSame( '', $html, 'Nothing outside a property.' );
	}

	public function test_structured_data() {
		$data = Schema::property( $this->house );
		$this->assertSame( 'RealEstateListing', $data['@type'] );
		$this->assertSame( 'SingleFamilyResidence', $data['about']['@type'] );
		$this->assertSame( 450000.0, (float) $data['offers']['price'] );
		$this->assertSame( 'USD', $data['offers']['priceCurrency'] );
		$this->assertSame( 51.5, $data['about']['geo']['latitude'] );
		$this->assertSame( 'FTK', $data['about']['floorSize']['unitCode'] );

		Fields::set( $this->house, 'hide_address', true );
		Fields::set( $this->house, 'price_on_request', true );
		$data = Schema::property( $this->house );
		$this->assertArrayNotHasKey( 'geo', $data['about'] );
		$this->assertArrayNotHasKey( 'streetAddress', $data['about']['address'] );
		$this->assertArrayNotHasKey( 'offers', $data );

		$this->go_to( get_permalink( $this->house ) );
		ob_start();
		Schema::print_json_ld();
		$out = ob_get_clean();
		$this->assertStringContainsString( '"BreadcrumbList"', $out );
		$this->assertStringNotContainsString( '</script><', substr( $out, 0, -10 ) );
	}

	public function test_legacy_taxonomies_stay_out_of_sitemaps() {
		$taxonomies = Schema::sitemap_taxonomies( get_taxonomies( array( 'public' => true ), 'objects' ) );
		$this->assertArrayNotHasKey( 'City', $taxonomies );
		$this->assertArrayHasKey( 'property_location', $taxonomies );
	}

	public function test_default_terms_are_created_on_updated_sites() {
		delete_option( Taxonomies::DEFAULTS );
		$sold = get_term_by( 'slug', 'sold', 'property_status' );
		wp_delete_term( $sold->term_id, 'property_status' );
		Taxonomies::maybe_install_defaults();
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'slug', 'sold', 'property_status' ) );
		$this->assertSame( '1', (string) get_option( Taxonomies::DEFAULTS ) );
	}

	/**
	 * A small JPEG to upload.
	 *
	 * @param string $name File name.
	 * @return string Path.
	 */
	private function image( $name ) {
		$file  = get_temp_dir() . 'estatecraft-' . $name . '.jpg';
		$image = imagecreatetruecolor( 640, 480 );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 40, 90, 160 ) );
		imagejpeg( $image, $file );
		return $file;
	}

	/**
	 * A block of ours with the house as context.
	 *
	 * @param string $name Block folder.
	 * @return WP_Block
	 */
	private function block( $name ) {
		return new WP_Block(
			array(
				'blockName' => 'estatecraft/' . $name,
				'attrs'     => array(),
			),
			array(
				'postId'   => $this->house,
				'postType' => 'property',
			)
		);
	}

	/**
	 * Names of all blocks, nested ones included.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return string[]
	 */
	private function block_names( array $blocks ) {
		$names = array();
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] ) {
				$names[] = $block['blockName'];
			}
			$names = array_merge( $names, $this->block_names( $block['innerBlocks'] ) );
		}
		return $names;
	}
}
