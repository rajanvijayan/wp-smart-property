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
$estatecraft_options = array(
	'estatecraft_migration',
	'estatecraft_legacy_terms',
	'estatecraft_migrating',
	'estatecraft_migration_notice',
	'estatecraft_settings',
	'estatecraft_geocode_last',
	'estatecraft_default_terms',
	'estatecraft_agent_links',
);
foreach ( $estatecraft_options as $estatecraft_option ) {
	delete_option( $estatecraft_option );
}
wp_clear_scheduled_hook( 'estatecraft_migrate' );
wp_clear_scheduled_hook( 'estatecraft_reindex' );
wp_clear_scheduled_hook( 'estatecraft_geocode' );
