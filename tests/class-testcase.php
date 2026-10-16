<?php
/**
 * Base test case.
 *
 * @package EstateCraft
 */

/**
 * The WordPress test case unregisters custom post types and taxonomies between tests; register
 * EstateCraft's again before each one.
 */
abstract class EstateCraft_TestCase extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		EstateCraft\Plugin::register_types();
	}
}
