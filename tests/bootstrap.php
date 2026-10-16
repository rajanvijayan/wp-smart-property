<?php
/**
 * PHPUnit bootstrap. Set WP_TESTS_DIR to the wordpress-develop tests/phpunit folder
 * (bin/install-wp-tests.sh does this).
 *
 * @package EstateCraft
 */

$estatecraft_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $estatecraft_tests_dir ) {
	$estatecraft_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
if ( ! file_exists( $estatecraft_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $estatecraft_tests_dir/includes/functions.php\n"; // phpcs:ignore
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $estatecraft_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/init.php';
	}
);
// Create the index table and roles once, before tests start their transactions.
tests_add_filter(
	'init',
	static function () {
		EstateCraft\Search\Index::install();
		EstateCraft\Capabilities::install();
	},
	30
);

require $estatecraft_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-testcase.php';
