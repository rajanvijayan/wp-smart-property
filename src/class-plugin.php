<?php
/**
 * Plugin bootstrap.
 *
 * @package EstateCraft
 */

namespace EstateCraft;

use EstateCraft\Admin\Admin;
use EstateCraft\Agents\Agent_Types;
use EstateCraft\Inquiries\Inquiry_Type;
use EstateCraft\Legacy\Legacy_Taxonomies;
use EstateCraft\Legacy\Migrator;
use EstateCraft\Legacy\Post_Grid_Bridge;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Properties\Taxonomies;
use EstateCraft\Rest\Settings_Controller;
use EstateCraft\Rest\Status_Controller;
use EstateCraft\Search\Index;

/**
 * Wires the components together.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function boot() {
		add_action( 'init', array( self::class, 'register_types' ), 5 );
		add_action( 'init', array( self::class, 'upgrade' ), 20 );
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		Index::register();
		Migrator::register();
		Post_Grid_Bridge::load();
		add_filter( 'estatecraft_property_fields', array( Settings::class, 'custom_fields' ) );
		if ( is_admin() ) {
			Admin::register();
		}
	}

	/**
	 * Post types, taxonomies and fields.
	 */
	public static function register_types() {
		Property_Type::register();
		Taxonomies::register();
		Legacy_Taxonomies::register();
		Fields::register();
		Agent_Types::register();
		Inquiry_Type::register();
	}

	/**
	 * Schema, capabilities and data migration on sites updated in place (no activation).
	 */
	public static function upgrade() {
		Index::maybe_install();
		Capabilities::maybe_install();
		if ( Migrator::pending() ) {
			Migrator::maybe_run();
		}
	}

	/**
	 * REST routes.
	 */
	public static function register_routes() {
		( new Status_Controller() )->register_routes();
		( new Settings_Controller() )->register_routes();
	}

	/**
	 * Activation: create everything and migrate 2.0 data.
	 */
	public static function activate() {
		self::register_types();
		Index::install();
		Capabilities::install();
		Taxonomies::install_defaults();
		Migrator::run_all();
		Index::rebuild();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( Migrator::CRON );
		flush_rewrite_rules();
	}
}
