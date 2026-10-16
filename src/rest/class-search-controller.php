<?php
/**
 * Public search route.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Rest;

use EstateCraft\Frontend\Search_Blocks;
use EstateCraft\Search\Search;
use WP_REST_Request;
use WP_REST_Server;

/**
 * GET /search: result cards and page links (rendered like the page itself), or map markers.
 * Published properties only; the same parameters as the search URL.
 */
final class Search_Controller extends Rest_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'fields' => array(
						'type'    => 'string',
						'enum'    => array( 'results', 'markers' ),
						'default' => 'results',
					),
					'base'   => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'layout' => array(
						'type'    => 'string',
						'enum'    => array( 'grid', 'list' ),
						'default' => 'grid',
					),
				),
			)
		);
	}

	/**
	 * Search.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function search( WP_REST_Request $request ) {
		$criteria = Search::normalize( $request->get_query_params() );
		if ( 'markers' === $request['fields'] ) {
			$response = rest_ensure_response( Search::markers( $criteria ) );
		} else {
			// Page links point at the page the visitor is on (only on this site).
			$base     = wp_validate_redirect( (string) $request['base'], Search::results_url() );
			$query    = Search::run( $criteria );
			$response = rest_ensure_response(
				array(
					'total'      => (int) $query->found_posts,
					'pages'      => (int) $query->max_num_pages,
					'page'       => isset( $criteria['pg'] ) ? $criteria['pg'] : 1,
					'summary'    => Search_Blocks::summary( (int) $query->found_posts ),
					'html'       => Search_Blocks::cards( $query, 'list' === $request['layout'] ? 'list' : 'grid' ),
					'pagination' => Search_Blocks::pagination( $query, $criteria, strtok( $base, '?' ) ),
					'url'        => add_query_arg( Search::to_params( $criteria ), strtok( $base, '?' ) ),
				)
			);
		}
		// Results are the same for everyone; let browsers and proxies keep them briefly.
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	}
}
