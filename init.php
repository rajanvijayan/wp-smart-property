<?php
/**
 * EstateCraft – Real Estate Listings & Portal.
 *
 * The file name is kept from WP Smart Property: WordPress records active plugins by path, so renaming
 * it would deactivate the plugin on update.
 *
 * @package EstateCraft
 *
 * @wordpress-plugin
 * Plugin Name:       EstateCraft – Real Estate Listings & Portal
 * Plugin URI:        https://wordpress.org/plugins/wp-smart-property/
 * Description:       Property listings with search, maps, agents and inquiries, and a front-end portal where agents submit and manage their listings.
 * Version:           3.0.0-dev
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Rajan V
 * Author URI:        https://www.wpsmartplugin.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-smart-property
 */

namespace EstateCraft;

defined( 'ABSPATH' ) || exit;

define( 'ESTATECRAFT_VERSION', '3.0.0-dev' );
define( 'ESTATECRAFT_FILE', __FILE__ );
define( 'ESTATECRAFT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ESTATECRAFT_URL', plugin_dir_url( __FILE__ ) );

if ( ! file_exists( ESTATECRAFT_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'EstateCraft is missing its vendor folder. Please reinstall the plugin.', 'wp-smart-property' ) . '</p></div>';
		}
	);
	return;
}
require_once ESTATECRAFT_DIR . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

Plugin::instance()->boot();
