<?php
/**
 * Search criteria, queries, markers, the REST route and the geocoding queue.
 *
 * @package EstateCraft
 */

use EstateCraft\Frontend\View;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Taxonomies;
use EstateCraft\Search\Geocode_Queue;
use EstateCraft\Search\Geocoder;
use EstateCraft\Search\Index;
use EstateCraft\Search\Search;
use EstateCraft\Settings;

/**
 * Search.
 */
class Test_Search extends EstateCraft_TestCase {

	/**
	 * Properties by name.
	 *
	 * @var int[]
	 */
	private $p = array();

	/**
	 * Geocoding requests made.
	 *
	 * @var string[]
	 */
	private $requests = array();

	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION );
		delete_option( Geocoder::LAST_REQUEST );
		Settings::flush();
		Taxonomies::install_defaults();
		$this->requests = array();
		$uk     = wp_insert_term( 'United Kingdom', 'property_location' );
		$london = wp_insert_term( 'London', 'property_location', array( 'parent' => $uk['term_id'] ) );
		$homes  = array(
			// name => type, status, price, beds, area, lat, lng, features.
			'cheap'  => array( 'apartment', 'for-sale', 200000, 1, 500, 51.50, -0.10, array() ),
			'family' => array( 'house', 'for-sale', 600000, 4, 1600, 51.52, -0.12, array( 'Garden', 'Garage' ) ),
			'rental' => array( 'apartment', 'for-rent', 2000, 2, 700, 53.48, -2.24, array( 'Garden' ) ),
			'quote'  => array( 'house', 'for-sale', 0, 3, 1200, 0, 0, array() ),
		);
		$i = 0;
		foreach ( $homes as $name => $h ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'property',
					'post_status' => 'publish',
					'post_title'  => ucfirst( $name ) . ' home',
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '2024-01-01' ) + ( $i++ ) * DAY_IN_SECONDS ),
				)
			);
			wp_set_object_terms( $id, $h[0], 'property_type' );
			wp_set_object_terms( $id, $h[1], 'property_status' );
			wp_set_object_terms( $id, $h[7], 'property_feature' );
			wp_set_object_terms( $id, array( 'rental' === $name ? $uk['term_id'] : $london['term_id'] ), 'property_location' );
			Fields::set( $id, 'price', $h[2] );
			Fields::set( $id, 'bedrooms', $h[3] );
			Fields::set( $id, 'area', $h[4] );
			Fields::set( $id, 'lat', $h[5] );
			Fields::set( $id, 'lng', $h[6] );
			$this->p[ $name ] = $id;
		}
		Fields::set( $this->p['family'], 'reference', 'EC-42' );
		Index::flush();
	}

	public function tear_down() {
		Settings::flush();
		parent::tear_down();
	}

	/**
	 * Names of the properties a search finds.
	 *
	 * @param array $params URL parameters.
	 * @return string[]
	 */
	private function found( array $params ) {
		$ids = wp_list_pluck( Search::run( Search::normalize( $params ) )->posts, 'ID' );
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = array_search( $id, $this->p, true );
		}
		sort( $out );
		return $out;
	}

	public function test_criteria_are_cleaned() {
		$criteria = Search::normalize(
			array(
				'keyword'   => '  <b>garden</b> ',
				'type'      => 'house,Apartment, ,',
				'feature'   => array( 'garden', 'garage' ),
				'price_min' => '100,000',
				'price_max' => 'lots',
				'bedrooms'  => '3',
				'baths'     => '1.4',
				'bounds'    => '51,-1,52,1',
				'sort'      => 'price-asc',
				'view'      => 'map',
				'pg'        => '3',
				'evil'      => 'x',
			)
		);
		$this->assertSame( 'garden', $criteria['keyword'] );
		$this->assertSame( array( 'house', 'apartment' ), $criteria['type'] );
		$this->assertSame( array( 'garden', 'garage' ), $criteria['feature'] );
		$this->assertSame( 100000.0, $criteria['price_min'] );
		$this->assertArrayNotHasKey( 'price_max', $criteria );
		$this->assertSame( 3, $criteria['beds'], 'The 2.0 redirect name works.' );
		$this->assertSame( 1.5, $criteria['baths'] );
		$this->assertSame( array( 51.0, -1.0, 52.0, 1.0 ), $criteria['bounds'] );
		$this->assertSame( 3, $criteria['pg'] );
		$this->assertArrayNotHasKey( 'evil', $criteria );

		$this->assertArrayNotHasKey( 'bounds', Search::normalize( array( 'bounds' => '52,0,51,1' ) ), 'South must be below north.' );
		$this->assertArrayNotHasKey( 'sort', Search::normalize( array( 'sort' => 'featured' ) ), 'Defaults stay out of the URL.' );
		$this->assertSame( array( 'type' => 'house,apartment' ), Search::to_params( array( 'type' => array( 'house', 'apartment' ) ) ) );
	}

	public function test_filters() {
		$this->assertSame( array( 'cheap', 'family', 'quote', 'rental' ), $this->found( array() ) );
		$this->assertSame( array( 'family', 'quote' ), $this->found( array( 'type' => 'house' ) ) );
		$this->assertSame( array( 'rental' ), $this->found( array( 'status' => 'for-rent' ) ) );
		$this->assertSame( array( 'family', 'quote' ), $this->found( array( 'beds' => 3 ) ) );
		$this->assertSame( array( 'cheap', 'family' ), $this->found( array( 'price_min' => 100000 ) ), 'A price range leaves out "price on request".' );
		$this->assertSame( array( 'cheap', 'rental' ), $this->found( array( 'price_max' => 300000 ) ) );
		$this->assertSame( array( 'family', 'quote' ), $this->found( array( 'area_min' => 1000 ) ) );
		$this->assertSame( array( 'family', 'rental' ), $this->found( array( 'feature' => 'garden' ) ) );
		$this->assertSame( array( 'family' ), $this->found( array( 'feature' => 'garden,garage' ) ), 'All features must be present.' );
		$this->assertSame( array( 'cheap', 'family', 'quote', 'rental' ), $this->found( array( 'location' => 'united-kingdom' ) ), 'A location includes the places in it.' );
		$this->assertSame( array( 'cheap', 'family', 'quote' ), $this->found( array( 'location' => 'london' ) ) );
		$this->assertSame( array( 'family' ), $this->found( array( 'keyword' => 'Family' ) ) );
		$this->assertSame( array( 'family' ), $this->found( array( 'keyword' => 'EC-42' ) ), 'The reference is searched too.' );
		$this->assertSame( array( 'cheap', 'family' ), $this->found( array( 'bounds' => '51.4,-0.2,51.6,0' ) ) );

		$sorted = wp_list_pluck( Search::run( Search::normalize( array( 'sort' => 'price-desc' ) ) )->posts, 'ID' );
		$this->assertSame( $this->p['family'], $sorted[0] );
	}

	public function test_custom_fields_marked_as_filters_can_be_searched() {
		Settings::save(
			array(
				'fields' => array(
					array(
						'label'   => 'Heating',
						'type'    => 'select',
						'options' => array( 'Gas', 'Heat pump' ),
						'search'  => true,
					),
					array(
						'label' => 'Secret',
						'type'  => 'text',
					),
				),
			)
		);
		Fields::set( $this->p['cheap'], 'cf_heating', 'Heat pump' );
		Fields::set( $this->p['cheap'], 'cf_secret', 'x' );
		$this->assertSame( array( 'cheap' ), $this->found( array( 'cf_heating' => 'Heat pump' ) ) );
		$this->assertArrayNotHasKey( 'cf_heating', Search::normalize( array( 'cf_heating' => 'Coal' ) ), 'Only listed choices.' );
		$this->assertArrayNotHasKey( 'cf_secret', Search::normalize( array( 'cf_secret' => 'x' ) ), 'Not a filter.' );
	}

	public function test_archive_pages_use_the_search_parameters() {
		// go_to() fills $_GET from the URL, as a real request would.
		$this->go_to( add_query_arg( array( 'type' => 'apartment' ), get_post_type_archive_link( 'property' ) ) );
		$this->assertEqualSets( array( $this->p['cheap'], $this->p['rental'] ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		$this->assertTrue( is_post_type_archive( 'property' ) );
		$this->go_to( add_query_arg( array( 'keyword' => 'home' ), get_post_type_archive_link( 'property' ) ) );
		$this->assertCount( 4, $GLOBALS['wp_query']->posts );
		$this->assertFalse( is_search(), 'A keyword does not turn the archive into WordPress search.' );
	}

	public function test_markers_hide_exact_positions() {
		Fields::set( $this->p['family'], 'hide_address', true );
		Index::flush();
		$markers = Search::markers( array() );
		$this->assertSame( 3, $markers['total'], 'Properties without a pin are not on the map.' );
		$byid = array_column( $markers['markers'], null, 'id' );
		$this->assertTrue( $byid[ $this->p['family'] ]['approximate'] );
		$this->assertNotEquals( 51.52, $byid[ $this->p['family'] ]['lat'] );
		$this->assertEquals( View::map_point( $this->p['family'] )['lat'], $byid[ $this->p['family'] ]['lat'] );
		$this->assertSame( '$600k', $byid[ $this->p['family'] ]['label'] );
	}

	public function test_rest_search() {
		$request = new WP_REST_Request( 'GET', '/estatecraft/v1/search' );
		$request->set_query_params(
			array(
				'status' => 'for-sale',
				'base'   => home_url( '/find/' ),
			)
		);
		$data = rest_get_server()->dispatch( $request )->get_data();
		$this->assertSame( 3, $data['total'] );
		$this->assertSame( '3 properties', $data['summary'] );
		$this->assertSame( 3, substr_count( $data['html'], 'estatecraft-card ' ) );
		$this->assertStringStartsWith( home_url( '/find/' ), $data['url'] );

		// Page links keep the criteria and only point at this site.
		add_filter(
			'estatecraft_archive_per_page',
			static function () {
				return 1;
			}
		);
		$request->set_query_params(
			array(
				'status' => 'for-sale',
				'base'   => 'https://evil.example/',
			)
		);
		$data = rest_get_server()->dispatch( $request )->get_data();
		$this->assertStringContainsString( 'status=for-sale', $data['pagination'] );
		$this->assertStringContainsString( 'pg=2', $data['pagination'] );
		$this->assertStringNotContainsString( 'evil.example', $data['pagination'] );
		$this->assertStringNotContainsString( 'fields=', $data['pagination'], 'The API parameters stay out of page links.' );

		$request = new WP_REST_Request( 'GET', '/estatecraft/v1/search' );
		$request->set_query_params( array( 'fields' => 'markers' ) );
		$this->assertCount( 3, rest_get_server()->dispatch( $request )->get_data()['markers'] );
	}

	public function test_short_prices() {
		$id = $this->p['cheap'];
		foreach ( array(
			390000  => '$390k',
			1250000 => '$1.25M',
			2000000 => '$2M',
			2400    => '$2.4k',
			950     => '$950',
		) as $price => $label ) {
			Fields::set( $id, 'price', $price );
			$this->assertSame( $label, View::short_price( $id ) );
		}
		Fields::set( $id, 'price_on_request', true );
		$this->assertSame( '', View::short_price( $id ) );
	}

	public function test_geocoding_queue() {
		add_filter( 'pre_http_request', array( $this, 'fake_nominatim' ), 10, 3 );
		$wait = static function () {
			delete_option( Geocoder::LAST_REQUEST );
		};

		$new = self::factory()->post->create( array( 'post_type' => 'property' ) );
		Fields::set( $new, 'street', '1 Found Street' );
		Geocode_Queue::on_save( $new );
		$this->assertSame( 'pending', Fields::get( $new, 'geocode' ), 'An address without a pin is queued.' );

		$lost = self::factory()->post->create( array( 'post_type' => 'property' ) );
		Fields::set( $lost, 'street', 'Nowhere Lane' );
		Fields::set( $lost, 'geocode', 'pending' );

		$manual = self::factory()->post->create( array( 'post_type' => 'property' ) );
		Fields::set( $manual, 'street', '2 Found Street' );
		Fields::set( $manual, 'lat', 50 );
		Fields::set( $manual, 'lng', 1 );
		Fields::set( $manual, 'geocode', 'manual' );

		$result = Geocode_Queue::run( $wait );
		$this->assertSame( 1, $result['done'] );
		$this->assertSame( 1, $result['failed'] );
		$this->assertSame( 0, $result['left'] );
		$this->assertEquals( 48.85, Fields::get( $new, 'lat' ) );
		$this->assertSame( 'done', Fields::get( $new, 'geocode' ) );
		$this->assertSame( 'failed', Fields::get( $lost, 'geocode' ) );
		$this->assertEquals( 50, Fields::get( $manual, 'lat' ), 'Hand-placed pins are kept.' );
		$this->assertCount( 2, $this->requests );

		// "Find pins for all addresses" queues failed ones again, not hand-placed pins.
		$this->assertSame( 1, Geocode_Queue::queue_missing() );
		$this->assertSame( 'pending', Fields::get( $lost, 'geocode' ) );

		// When the service is busy the run stops and keeps the rest for later.
		update_option( Geocoder::LAST_REQUEST, microtime( true ) );
		$result = Geocode_Queue::run( $wait );
		$this->assertSame( 0, $result['done'] + $result['failed'] );
		$this->assertSame( 1, $result['left'] );
	}

	public function test_geocoding_tools_are_for_site_managers() {
		$request = new WP_REST_Request( 'POST', '/estatecraft/v1/geocode/queue' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
		$status = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/estatecraft/v1/status' ) )->get_data();
		$this->assertArrayHasKey( 'pending', $status['geocoding'] );
	}

	/**
	 * Answer Nominatim requests.
	 *
	 * @param false  $pre  Short-circuit.
	 * @param array  $args Arguments.
	 * @param string $url  URL.
	 * @return array
	 */
	public function fake_nominatim( $pre, $args, $url ) {
		$this->requests[] = $url;
		$body             = false !== strpos( $url, 'Nowhere' ) ? array() : array(
			array(
				'lat'          => '48.85',
				'lon'          => '2.35',
				'display_name' => 'Found Street',
			),
		);
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}
}
