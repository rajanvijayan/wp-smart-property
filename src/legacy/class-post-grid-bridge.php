<?php
/**
 * The property grid from 2.0 (a modified copy of the Post Grid plugin).
 *
 * @package EstateCraft
 */

namespace EstateCraft\Legacy;

/**
 * Keeps [post_grid id] pages working until the listings renderer takes the shortcode over. The copy
 * is not loaded when the Post Grid plugin itself is active (it provides the same post type and
 * shortcode).
 */
final class Post_Grid_Bridge {

	/**
	 * Whether the Post Grid plugin is active.
	 *
	 * @return bool
	 */
	public static function plugin_active() {
		return class_exists( 'PostGrid' )
			|| in_array( 'post-grid/post-grid.php', (array) get_option( 'active_plugins', array() ), true )
			|| ( is_multisite() && array_key_exists( 'post-grid/post-grid.php', (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	/**
	 * Load the bundled grid, or explain why it is not loaded.
	 */
	public static function load() {
		if ( self::plugin_active() ) {
			add_action( 'admin_notices', array( self::class, 'notice' ) );
			return;
		}
		require_once ESTATECRAFT_DIR . 'post-grid/post-grid.php';
	}

	/**
	 * Admin notice when Post Grid provides the grids.
	 */
	public static function notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( current_user_can( 'activate_plugins' ) && $screen && 'plugins' === $screen->id ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'EstateCraft: the Post Grid plugin is active, so it provides the [post_grid] shortcode.', 'wp-smart-property' ) . '</p></div>';
		}
	}
}
