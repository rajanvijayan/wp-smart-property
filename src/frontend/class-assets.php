<?php
/**
 * Front-end styles and scripts, loaded only where a property block renders.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

use EstateCraft\Admin\Assets as Built;
use EstateCraft\Settings;

/**
 * Handles are registered early; blocks enqueue what they need when they render. Block themes render
 * the template before wp_head, so styles still land in the head; classic themes are covered by
 * enqueue_for_request().
 */
final class Assets {

	const STYLE  = 'estatecraft-frontend';
	const SCRIPT = 'estatecraft-frontend';
	const MAP    = 'estatecraft-map';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_handles' ), 9 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_for_request' ) );
	}

	/**
	 * Register the handles.
	 */
	public static function register_handles() {
		$front = Built::asset( 'frontend' );
		wp_register_style( self::STYLE, ESTATECRAFT_URL . 'build/frontend.css', array(), $front['version'] );
		wp_style_add_data( self::STYLE, 'rtl', 'replace' );
		wp_register_script(
			self::SCRIPT,
			ESTATECRAFT_URL . 'build/frontend.js',
			$front['dependencies'],
			$front['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_set_script_translations( self::SCRIPT, 'wp-smart-property' );

		$map = Built::asset( 'property-map' );
		wp_register_style( self::MAP, ESTATECRAFT_URL . 'build/property-map.css', array(), $map['version'] );
		wp_register_script(
			self::MAP,
			ESTATECRAFT_URL . 'build/property-map.js',
			$map['dependencies'],
			$map['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_set_script_translations( self::MAP, 'wp-smart-property' );
	}

	/**
	 * Styles for pages EstateCraft renders, enqueued before wp_head in classic themes.
	 */
	public static function enqueue_for_request() {
		$post = is_singular() ? get_queried_object() : null;
		if ( Templates::current() || ( $post instanceof \WP_Post && false !== strpos( $post->post_content, '<!-- wp:estatecraft/' ) ) ) {
			self::style();
		}
	}

	/**
	 * Base styles.
	 */
	public static function style() {
		wp_enqueue_style( self::STYLE );
	}

	/**
	 * Gallery lightbox and share/print buttons.
	 */
	public static function script() {
		self::style();
		wp_enqueue_script( self::SCRIPT );
	}

	/**
	 * Map script (Leaflet loads when the map scrolls into view).
	 */
	public static function map() {
		self::style();
		wp_enqueue_style( self::MAP );
		wp_enqueue_script( self::MAP );
	}

	/**
	 * Map tiles settings for the page.
	 *
	 * @return array
	 */
	public static function tiles() {
		$maps = Settings::get( 'maps' );
		return array(
			'tiles'       => $maps['tiles'],
			'attribution' => $maps['attribution'],
		);
	}
}
