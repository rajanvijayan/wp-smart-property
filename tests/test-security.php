<?php
/**
 * Security fixes in 2.0.1.
 *
 * @package WP_Smart_Property
 */

/**
 * Grid AJAX actions and the property information box.
 *
 * @group ajax
 */
class Test_Security extends WP_Ajax_UnitTestCase {

	/**
	 * Run an AJAX action and return its output (empty when it stops early).
	 *
	 * @param string $action Action.
	 * @return string
	 */
	private function ajax( $action ) {
		$this->_last_response = '';
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
		}
		return $this->_last_response;
	}

	public function test_deleting_an_export_only_accepts_an_export_file_name() {
		$this->_setRole( 'administrator' );
		$uploads = wp_upload_dir();
		wp_mkdir_p( $uploads['basedir'] . '/post-grid' );
		$export = $uploads['basedir'] . '/post-grid/export-layout-content-2026-10-16-01-1792120000.txt';
		$victim = $uploads['basedir'] . '/victim.txt';
		file_put_contents( $export, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $victim, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// The 2.0 request (absolute path, no token) does nothing.
		$_POST = array( 'file_url' => $victim );
		$this->ajax( 'post_grid_ajax_remove_export_content_layout' );
		$this->assertFileExists( $victim );

		// A path instead of a file name is refused, even with a valid token.
		$_POST = array(
			'_ajax_nonce' => wp_create_nonce( 'post_grid_admin' ),
			'file_name'   => '../victim.txt',
		);
		$this->ajax( 'post_grid_ajax_remove_export_content_layout' );
		$this->assertFileExists( $victim );

		// An export file name with a valid token is deleted.
		$_POST = array(
			'_ajax_nonce' => wp_create_nonce( 'post_grid_admin' ),
			'file_name'   => basename( $export ),
		);
		$this->ajax( 'post_grid_ajax_remove_export_content_layout' );
		$this->assertFileDoesNotExist( $export );
		$this->assertFileExists( $victim );
		unlink( $victim ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function test_deleting_an_export_needs_an_administrator() {
		$this->_setRole( 'editor' );
		$uploads = wp_upload_dir();
		wp_mkdir_p( $uploads['basedir'] . '/post-grid' );
		$export = $uploads['basedir'] . '/post-grid/export-layout-content-2026-10-16-01-1792120001.txt';
		file_put_contents( $export, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$_POST = array(
			'_ajax_nonce' => wp_create_nonce( 'post_grid_admin' ),
			'file_name'   => basename( $export ),
		);
		$this->ajax( 'post_grid_ajax_remove_export_content_layout' );
		$this->assertFileExists( $export );
		unlink( $export ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function test_grid_admin_actions_need_the_security_token() {
		$this->_setRole( 'administrator' );
		update_option( 'post_grid_layout_content', array( 'flat' => array( 'x' ) ) );

		$_POST = array( 'layout_id' => 'flat' );
		$this->ajax( 'post_grid_remove_content_layout_ajax' );
		$this->assertArrayHasKey( 'flat', get_option( 'post_grid_layout_content' ), 'Removed without a token.' );

		$_POST = array(
			'_ajax_nonce' => wp_create_nonce( 'post_grid_admin' ),
			'layout_id'   => 'flat',
		);
		$this->ajax( 'post_grid_remove_content_layout_ajax' );
		$this->assertArrayNotHasKey( 'flat', get_option( 'post_grid_layout_content' ) );
	}

	public function test_public_grid_actions_ignore_unpublished_grids() {
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'post_grid',
				'post_status' => 'draft',
			)
		);
		wp_set_current_user( 0 );
		$_POST = array(
			'grid_id' => $draft,
			'keyword' => 'home',
		);
		$this->assertSame( '', $this->ajax( 'post_grid_ajax_search' ) );
		$_POST = array(
			'grid_id' => $draft,
			'paged'   => 2,
		);
		$this->assertSame( '', $this->ajax( 'post_grid_ajax_load_more' ) );
	}

	public function test_visitors_never_get_unpublished_posts_from_a_grid() {
		$grid = self::factory()->post->create(
			array(
				'post_type'   => 'post_grid',
				'post_status' => 'publish',
			)
		);
		update_post_meta(
			$grid,
			'post_grid_meta_options',
			array(
				'post_types'  => array( 'property' ),
				'post_status' => array( 'publish', 'private', 'draft' ),
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_status' => 'private',
				'post_title'  => 'Secret home',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'property',
				'post_status' => 'publish',
				'post_title'  => 'Open home',
			)
		);

		$seen = null;
		add_action(
			'pre_get_posts',
			static function ( $query ) use ( &$seen ) {
				if ( 'property' === $query->get( 'post_type' ) || array( 'property' ) === $query->get( 'post_type' ) ) {
					$seen = $query->get( 'post_status' );
				}
			}
		);
		wp_set_current_user( 0 );
		$_POST = array(
			'grid_id' => $grid,
			'keyword' => 'home',
		);
		$this->ajax( 'post_grid_ajax_search' );
		$this->assertSame( 'publish', $seen );
	}
}
