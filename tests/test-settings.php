<?php
/**
 * Settings, custom fields, formatting and address lookup.
 *
 * @package EstateCraft
 */

use EstateCraft\Format;
use EstateCraft\Properties\Fields;
use EstateCraft\Search\Geocoder;
use EstateCraft\Settings;

/**
 * Settings are sanitized against their defaults.
 */
class Test_Settings extends EstateCraft_TestCase {

	/**
	 * Geocoding requests made during a test.
	 *
	 * @var string[]
	 */
	private $requests = array();

	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION );
		delete_option( Geocoder::LAST_REQUEST );
		Settings::flush();
		$this->requests = array();
	}

	public function tear_down() {
		Settings::flush();
		parent::tear_down();
	}

	public function test_defaults_are_returned_without_saved_settings() {
		$this->assertSame( 'USD', Settings::get( 'general' )['currency'] );
		$this->assertTrue( Settings::get( 'maps' )['geocoding'] );
		$this->assertSame( array(), Settings::get( 'fields' ) );
		$this->assertSame( array(), Settings::get( 'unknown' ) );
	}

	public function test_values_are_sanitized_by_type_and_range() {
		$saved = Settings::save(
			array(
				'general' => array(
					'currency'  => 'eur!',
					'decimals'  => '9',
					'area_unit' => 'acres',
					'thousands' => '<b>',
					'symbol'    => '<script>x</script>€',
					'unknown'   => 'dropped',
				),
				'maps'    => array(
					'tiles'       => 'http://tiles.example.com/{z}/{x}/{y}.png',
					'attribution' => '<a href="https://example.com" onclick="x()">Tiles</a><script>alert(1)</script>',
					'lat'         => '123',
					'zoom'        => 0,
					'cluster'     => 'false',
				),
				'portal'  => array( 'max_images' => 500 ),
				'pages'   => array( 'search' => '-4' ),
			)
		);
		$this->assertSame( 'EUR', $saved['general']['currency'] );
		$this->assertSame( 4, $saved['general']['decimals'] );
		$this->assertSame( 'sqft', $saved['general']['area_unit'] );
		$this->assertSame( ',', $saved['general']['thousands'] );
		$this->assertSame( '€', $saved['general']['symbol'] );
		$this->assertArrayNotHasKey( 'unknown', $saved['general'] );
		// Tiles must be https: the default is kept.
		$this->assertSame( Settings::defaults()['maps']['tiles'], $saved['maps']['tiles'] );
		$this->assertSame( '<a href="https://example.com">Tiles</a>alert(1)', $saved['maps']['attribution'] );
		$this->assertSame( 90.0, $saved['maps']['lat'] );
		$this->assertSame( 1, $saved['maps']['zoom'] );
		$this->assertFalse( $saved['maps']['cluster'] );
		$this->assertSame( 100, $saved['portal']['max_images'] );
		$this->assertSame( 4, $saved['pages']['search'] );
		// Sections that were not sent keep their values.
		Settings::save( array( 'privacy' => array( 'retention_days' => 30 ) ) );
		$this->assertSame( 'EUR', Settings::get( 'general' )['currency'] );
	}

	public function test_tile_templates_keep_their_placeholders() {
		$tiles = 'https://{s}.tiles.example.com/{z}/{x}/{y}.png?key=abc';
		$saved = Settings::save( array( 'maps' => array( 'tiles' => $tiles ) ) );
		$this->assertSame( $tiles, $saved['maps']['tiles'] );
	}

	public function test_custom_fields_are_cleaned_and_become_property_fields() {
		$saved = Settings::save(
			array(
				'fields' => array(
					array(
						'label'   => 'Pool type',
						'type'    => 'select',
						'options' => "Indoor\nOutdoor\n\nIndoor",
						'search'  => true,
					),
					array(
						'label' => 'Pool type',
						'type'  => 'text',
					),
					array(
						'key'   => 'Has-Lift',
						'label' => 'Lift',
						'type'  => 'checkbox',
					),
					array(
						'label' => '',
						'type'  => 'text',
					),
					array(
						'label' => 'Orientation',
						'type'  => 'colour',
						'show'  => false,
					),
				),
			)
		);
		$fields = $saved['fields'];
		$this->assertCount( 3, $fields );
		$this->assertSame( 'pool_type', $fields[0]['key'] );
		$this->assertSame( array( 'Indoor', 'Outdoor' ), $fields[0]['options'] );
		$this->assertTrue( $fields[0]['search'] );
		$this->assertSame( 'has_lift', $fields[1]['key'] );
		$this->assertSame( 'text', $fields[2]['type'] );
		$this->assertFalse( $fields[2]['show'] );

		$all = Fields::all();
		$this->assertSame( array( 'enum', array( '', 'Indoor', 'Outdoor' ) ), $all['cf_pool_type'][0] );
		$this->assertSame( 'bool', $all['cf_has_lift'][0] );

		$property = self::factory()->post->create( array( 'post_type' => 'property' ) );
		Fields::set( $property, 'cf_pool_type', 'Rooftop' );
		$this->assertSame( '', Fields::get( $property, 'cf_pool_type' ) );
		Fields::set( $property, 'cf_pool_type', 'Outdoor' );
		$this->assertSame( 'Outdoor', Fields::get( $property, 'cf_pool_type' ) );

		// The field list is replaced, not merged by position.
		$saved = Settings::save( array( 'fields' => array( $fields[1] ) ) );
		$this->assertCount( 1, $saved['fields'] );
		$this->assertSame( 'has_lift', $saved['fields'][0]['key'] );
	}

	public function test_prices_and_areas_use_the_settings() {
		$this->assertSame( '$1,250,000', Format::price( 1250000 ) );
		$this->assertSame( 'Price on request', Format::price( 1250000, '', true ) );
		$this->assertSame( 'Price on request', Format::price( 0 ) );
		Settings::save(
			array(
				'general' => array(
					'symbol'        => '€',
					'symbol_after'  => true,
					'thousands'     => '.',
					'decimal_point' => ',',
					'decimals'      => 2,
					'area_unit'     => 'm2',
				),
			)
		);
		$this->assertSame( '1.450,50 € / month', Format::price( 1450.5, 'month' ) );
		$this->assertSame( '85 m²', Format::area( 85 ) );
		$this->assertSame( '1.200,5 m²', Format::area( 1200.5 ) );
	}

	public function test_geocoder_parses_caches_and_limits_requests() {
		add_filter( 'pre_http_request', array( $this, 'fake_nominatim' ), 10, 3 );

		$result = Geocoder::lookup( '12 Rose Lane, London' );
		$this->assertSame(
			array(
				'lat'   => 51.5,
				'lng'   => -0.12,
				'label' => '12 Rose Lane, London, United Kingdom',
			),
			$result
		);
		$this->assertCount( 1, $this->requests );
		$this->assertStringContainsString( 'format=jsonv2', $this->requests[0] );

		// Cached: no second request.
		$this->assertSame( $result, Geocoder::lookup( '12 rose lane, london' ) );
		$this->assertCount( 1, $this->requests );

		// A new address within a second of the last request waits.
		$busy = Geocoder::lookup( '1 Other Street' );
		$this->assertWPError( $busy );
		$this->assertSame( 'estatecraft_geocode_busy', $busy->get_error_code() );

		update_option( Geocoder::LAST_REQUEST, microtime( true ) - 5 );
		$none = Geocoder::lookup( 'Nowhere at all' );
		$this->assertSame( 'estatecraft_geocode_none', $none->get_error_code() );

		Settings::save( array( 'maps' => array( 'geocoding' => false ) ) );
		$this->assertSame( 'estatecraft_geocode_off', Geocoder::lookup( '12 Rose Lane' )->get_error_code() );
		$this->assertCount( 2, $this->requests );
	}

	/**
	 * Answer Nominatim requests.
	 *
	 * @param false  $pre  Short-circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url  URL.
	 * @return array
	 */
	public function fake_nominatim( $pre, $args, $url ) {
		$this->requests[] = $url;
		$this->assertStringStartsWith( 'EstateCraft/', $args['user-agent'] );
		$body = false !== strpos( $url, 'Nowhere' ) ? array() : array(
			array(
				'lat'          => '51.5',
				'lon'          => '-0.12',
				'display_name' => '12 Rose Lane, London, United Kingdom',
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
