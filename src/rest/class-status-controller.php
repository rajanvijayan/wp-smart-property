<?php
/**
 * Status and maintenance routes for the admin tools.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Rest;

use EstateCraft\Legacy\Migrator;
use EstateCraft\Search\Geocode_Queue;
use EstateCraft\Search\Index;
use WP_REST_Request;
use WP_REST_Server;

/**
 * GET /status: index size and migration state. POST /index/rebuild: rebuild in batches.
 */
final class Status_Controller extends Rest_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/geocode/queue',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'queue_geocoding' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/index/rebuild',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rebuild' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'offset' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Status.
	 *
	 * @return array
	 */
	public function status() {
		$migration = Migrator::state();
		return array(
			'version'   => ESTATECRAFT_VERSION,
			'indexed'   => Index::count(),
			'geocoding' => array(
				'pending' => Geocode_Queue::pending_count(),
				'next'    => wp_next_scheduled( Geocode_Queue::CRON ) ? gmdate( 'c', wp_next_scheduled( Geocode_Queue::CRON ) ) : null,
			),
			'migration' => array(
				'done'   => ! Migrator::pending(),
				'stage'  => $migration['stage'],
				'report' => array_merge( $migration['report'], array( 'features' => array_keys( $migration['report']['features'] ) ) ),
			),
		);
	}

	/**
	 * Queue every address without a pin for lookup.
	 *
	 * @return array
	 */
	public function queue_geocoding() {
		return array(
			'queued'  => Geocode_Queue::queue_missing(),
			'pending' => Geocode_Queue::pending_count(),
		);
	}

	/**
	 * One rebuild batch.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function rebuild( WP_REST_Request $request ) {
		$offset = (int) $request['offset'];
		$done   = Index::rebuild( $offset, 200 );
		return array(
			'next'     => $offset + $done,
			'complete' => $done < 200,
		);
	}
}
