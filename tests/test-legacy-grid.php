<?php
/**
 * The 2.0 [post_grid id] shortcode.
 *
 * @package EstateCraft
 */

use EstateCraft\Legacy\Grid_Compat;
use EstateCraft\Legacy\Legacy_Taxonomies;
use EstateCraft\Legacy\Migrator;
use EstateCraft\Properties\Fields;
use EstateCraft\Search\Index;

/**
 * Grids saved with the 2.0 grid builder keep showing their posts.
 */
class Test_Legacy_Grid extends EstateCraft_TestCase {

	/**
	 * Properties by name.
	 *
	 * @var int[]
	 */
	private $p = array();

	/**
	 * 2.0 term IDs by name.
	 *
	 * @var int[]
	 */
	private $old = array();

	public function set_up() {
		parent::set_up();
		Grid_Compat::register_type();
		delete_option( Migrator::MAP );

		$this->old['house'] = (int) wp_insert_term( 'House', Legacy_Taxonomies::TYPE )['term_id'];
		$this->old['flat']  = (int) wp_insert_term( 'Apartment', Legacy_Taxonomies::TYPE )['term_id'];
		$this->old['three'] = (int) wp_insert_term( '3 Bedrooms', Legacy_Taxonomies::BEDS )['term_id'];
		$house              = (int) $this->term( 'House', 'property_type' );
		$flat               = (int) $this->term( 'Apartment', 'property_type' );
		// What the 2.0 upgrade records: old term ID => new term ID.
		update_option(
			Migrator::MAP,
			array(
				$this->old['house'] => $house,
				$this->old['flat']  => $flat,
			)
		);

		foreach ( array(
			'big'   => array( $house, 4, 'publish' ),
			'small' => array( $house, 2, 'publish' ),
			'flat'  => array( $flat, 3, 'publish' ),
			'draft' => array( $house, 5, 'draft' ),
		) as $name => $h ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'property',
					'post_status' => $h[2],
					'post_title'  => ucfirst( $name ) . ' home',
				)
			);
			wp_set_object_terms( $id, array( $h[0] ), 'property_type' );
			Fields::set( $id, 'bedrooms', $h[1] );
			$this->p[ $name ] = $id;
		}
		Index::flush();
	}

	/**
	 * A term, created when it does not exist yet (types are installed by default).
	 *
	 * @param string $name     Name.
	 * @param string $taxonomy Taxonomy.
	 * @return int Term ID.
	 */
	private function term( $name, $taxonomy ) {
		$found = term_exists( $name, $taxonomy );
		return $found ? (int) $found['term_id'] : (int) wp_insert_term( $name, $taxonomy )['term_id'];
	}

	/**
	 * A grid as 2.0 saved it.
	 *
	 * @param array  $options Settings.
	 * @param string $status  Post status.
	 * @return int Grid ID.
	 */
	private function grid( array $options, $status = 'publish' ) {
		$id = self::factory()->post->create(
			array(
				'post_type'   => Grid_Compat::POST_TYPE,
				'post_status' => $status,
			)
		);
		update_post_meta( $id, Grid_Compat::META, $options );
		return $id;
	}

	/**
	 * Titles in a rendered grid.
	 *
	 * @param int $grid Grid ID.
	 * @return string[]
	 */
	private function titles( $grid ) {
		preg_match_all( '#class="estatecraft-card__title"><a [^>]+>([^<]+)</a>#', do_shortcode( '[post_grid id="' . $grid . '"]' ), $m );
		sort( $m[1] );
		return $m[1];
	}

	public function test_grid_terms_from_2_0_are_translated() {
		$grid = $this->grid(
			array(
				'post_types' => array( 'property' ),
				'categories' => array( Legacy_Taxonomies::TYPE . ',' . $this->old['house'] ),
			)
		);
		$this->assertSame( array( 'Big home', 'Small home' ), $this->titles( $grid ), 'Houses only, published only.' );
	}

	public function test_bedroom_terms_become_a_minimum() {
		$grid = $this->grid(
			array(
				'post_types' => array( 'property' ),
				'categories' => array( Legacy_Taxonomies::BEDS . ',' . $this->old['three'] ),
			)
		);
		$this->assertSame( array( 'Big home', 'Flat home' ), $this->titles( $grid ) );
	}

	public function test_grid_order_and_page_size_are_kept() {
		$grid = $this->grid(
			array(
				'post_types'     => array( 'property' ),
				'posts_per_page' => 2,
				'query_orderby'  => array( 'title' ),
				'query_order'    => 'ASC',
			)
		);
		$html = do_shortcode( '[post_grid id="' . $grid . '"]' );
		$this->assertSame( 2, substr_count( $html, 'estatecraft-card ' ) );
		$this->assertLessThan( strpos( $html, 'Flat home' ), strpos( $html, 'Big home' ), 'Title order.' );
		$this->assertStringContainsString( 'estatecraft-pagination', $html );
		$this->assertStringContainsString( 'id="post-grid-' . $grid . '"', $html, 'The 2.0 element ID stays, for custom CSS.' );
	}

	public function test_other_post_types_and_unpublished_grids() {
		self::factory()->post->create(
			array(
				'post_title'  => 'News item',
				'post_status' => 'publish',
			)
		);
		$posts = $this->grid( array( 'post_types' => array( 'post' ) ) );
		$this->assertSame( array( 'News item' ), $this->titles( $posts ) );

		$draft = $this->grid( array( 'post_types' => array( 'property' ) ), 'draft' );
		$this->assertSame( '', do_shortcode( '[post_grid id="' . $draft . '"]' ) );
		$this->assertSame( '', do_shortcode( '[post_grid id="' . $this->p['big'] . '"]' ), 'Only grids.' );

		$private = $this->grid(
			array(
				'post_types'  => array( 'property' ),
				'post_status' => array( 'draft', 'private' ),
			)
		);
		$this->assertNotContains( 'Draft home', $this->titles( $private ), 'Unpublished posts are never listed.' );
	}

	public function test_the_post_grid_plugin_keeps_its_shortcode() {
		$active = get_option( 'active_plugins' );
		update_option( 'active_plugins', array( 'post-grid/post-grid.php' ) );
		$this->assertTrue( Grid_Compat::plugin_active() );
		update_option( 'active_plugins', $active );
		$this->assertFalse( Grid_Compat::plugin_active() );
	}
}
