<?php
/**
 * Base for the estatecraft/v1 REST controllers.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Rest;

use WP_REST_Controller;

/**
 * Shared namespace and permission checks.
 */
abstract class Rest_Controller extends WP_REST_Controller {

	const NS = 'estatecraft/v1';

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = self::NS;

	/**
	 * Site settings and tools.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_estatecraft' );
	}
}
