<?php
/**
 * PHPUnit bootstrap. Set WP_TESTS_DIR to the wordpress-develop tests/phpunit folder
 * (bin/install-wp-tests.sh does this).
 *
 * @package WP_Smart_Property
 */

$wp_smart_property_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_smart_property_tests_dir ) {
	$wp_smart_property_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
if ( ! file_exists( $wp_smart_property_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $wp_smart_property_tests_dir/includes/functions.php\n"; // phpcs:ignore
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $wp_smart_property_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/init.php';
	}
);

require $wp_smart_property_tests_dir . '/includes/bootstrap.php';
