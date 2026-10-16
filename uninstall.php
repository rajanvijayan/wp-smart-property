<?php
/**
 * Uninstall: removes EstateCraft's settings, roles, capabilities and index table. Properties,
 * agents, inquiries and terms are content and are kept.
 *
 * @package EstateCraft
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

EstateCraft\Capabilities::uninstall();
EstateCraft\Search\Index::uninstall();
foreach ( array( 'estatecraft_migration', 'estatecraft_legacy_terms', 'estatecraft_migrating', 'estatecraft_migration_notice' ) as $estatecraft_option ) {
	delete_option( $estatecraft_option );
}
wp_clear_scheduled_hook( 'estatecraft_migrate' );
