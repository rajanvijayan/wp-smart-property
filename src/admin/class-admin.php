<?php
/**
 * Admin screens: settings page, dashboard widget and notices.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Admin;

use EstateCraft\Inquiries\Inquiry_Type;
use EstateCraft\Legacy\Migrator;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;

/**
 * Admin entry point.
 */
final class Admin {

	const SETTINGS_SLUG = 'estatecraft-settings';

	/**
	 * Hooks.
	 */
	public static function register() {
		Property_Editor::register();
		Agent_Editor::register();
		List_Table::register();
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_dashboard_setup', array( self::class, 'dashboard' ) );
		add_action( 'admin_notices', array( self::class, 'migration_notice' ) );
		add_action( 'admin_post_estatecraft_dismiss_migration', array( self::class, 'dismiss_migration' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ESTATECRAFT_FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * Settings page under Properties.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Property_Type::POST_TYPE,
			__( 'EstateCraft settings', 'wp-smart-property' ),
			__( 'Settings', 'wp-smart-property' ),
			'manage_estatecraft',
			self::SETTINGS_SLUG,
			array( self::class, 'settings_page' )
		);
	}

	/**
	 * Settings page markup (the React app mounts here).
	 */
	public static function settings_page() {
		echo '<div class="wrap"><div id="estatecraft-settings"></div></div>';
	}

	/**
	 * Settings app scripts.
	 *
	 * @param string $hook Admin page.
	 */
	public static function enqueue( $hook ) {
		if ( Property_Type::POST_TYPE . '_page_' . self::SETTINGS_SLUG !== $hook ) {
			return;
		}
		$asset = Assets::asset( 'settings' );
		wp_enqueue_script( 'estatecraft-settings', ESTATECRAFT_URL . 'build/settings.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'estatecraft-settings', ESTATECRAFT_URL . 'build/settings.css', array( 'wp-components' ), $asset['version'] );
		wp_set_script_translations( 'estatecraft-settings', 'wp-smart-property' );
		$pages = array();
		foreach ( get_pages( array( 'number' => 500 ) ) as $page ) {
			$pages[] = array(
				'id'    => $page->ID,
				'title' => $page->post_title,
			);
		}
		wp_localize_script(
			'estatecraft-settings',
			'estateCraftSettings',
			array(
				'pages'   => $pages,
				'version' => ESTATECRAFT_VERSION,
			)
		);
	}

	/**
	 * Dashboard widget for people who manage listings.
	 */
	public static function dashboard() {
		if ( current_user_can( 'edit_others_estate_properties' ) ) {
			wp_add_dashboard_widget( 'estatecraft_overview', __( 'Properties', 'wp-smart-property' ), array( self::class, 'dashboard_widget' ) );
		}
	}

	/**
	 * Dashboard widget content.
	 */
	public static function dashboard_widget() {
		$counts   = wp_count_posts( Property_Type::POST_TYPE );
		$inquiry  = wp_count_posts( Inquiry_Type::POST_TYPE );
		$expiring = get_posts(
			array(
				'post_type'   => Property_Type::POST_TYPE,
				'post_status' => 'publish',
				'fields'      => 'ids',
				'numberposts' => 50,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small dashboard query.
					array(
						'key'     => Fields::PREFIX . 'expires',
						'value'   => array( current_time( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+14 days', current_time( 'timestamp' ) ) ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local date range.
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);
		$rows     = array(
			array( __( 'Published', 'wp-smart-property' ), (int) $counts->publish, admin_url( 'edit.php?post_type=property&post_status=publish' ) ),
			array( __( 'Waiting for review', 'wp-smart-property' ), (int) $counts->pending, admin_url( 'edit.php?post_type=property&post_status=pending' ) ),
			array( __( 'Expiring in 14 days', 'wp-smart-property' ), count( $expiring ), admin_url( 'edit.php?post_type=property' ) ),
			array( __( 'Inquiries', 'wp-smart-property' ), isset( $inquiry->private ) ? (int) $inquiry->private + (int) $inquiry->publish : 0, admin_url( 'edit.php?post_type=' . Inquiry_Type::POST_TYPE ) ),
		);
		echo '<ul class="estatecraft-dashboard" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0">';
		foreach ( $rows as $row ) {
			echo '<li style="margin:0;padding:12px;border:1px solid #dcdcde;border-radius:4px"><a href="' . esc_url( $row[2] ) . '" style="text-decoration:none"><strong style="display:block;font-size:22px;line-height:1.2">' . esc_html( number_format_i18n( $row[1] ) ) . '</strong>' . esc_html( $row[0] ) . '</a></li>';
		}
		echo '</ul>';
	}

	/**
	 * One-time notice after the 2.0 upgrade.
	 */
	public static function migration_notice() {
		if ( ! get_option( 'estatecraft_migration_notice' ) || ! current_user_can( 'manage_estatecraft' ) ) {
			return;
		}
		$report  = Migrator::state()['report'];
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=estatecraft_dismiss_migration' ), 'estatecraft_dismiss_migration' );
		echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'WP Smart Property is now EstateCraft.', 'wp-smart-property' ) . '</strong> ';
		echo esc_html(
			sprintf(
				/* translators: %d: number of properties */
				_n( '%d property was moved to the new property details. Your property pages and their addresses stay the same.', '%d properties were moved to the new property details. Your property pages and their addresses stay the same.', $report['properties'], 'wp-smart-property' ),
				$report['properties']
			)
		);
		echo '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=property&page=' . self::SETTINGS_SLUG . '#tools' ) ) . '">' . esc_html__( 'See what moved', 'wp-smart-property' ) . '</a> <a class="button" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'wp-smart-property' ) . '</a></p></div>';
	}

	/**
	 * Dismiss the upgrade notice.
	 */
	public static function dismiss_migration() {
		check_admin_referer( 'estatecraft_dismiss_migration' );
		if ( current_user_can( 'manage_estatecraft' ) ) {
			delete_option( 'estatecraft_migration_notice' );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=property&page=' . self::SETTINGS_SLUG ) ) . '">' . esc_html__( 'Settings', 'wp-smart-property' ) . '</a>' );
		return $links;
	}
}
