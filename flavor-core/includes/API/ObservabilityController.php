<?php
/**
 * REST API: System Observability & Health Controller.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Database\Schema;
use FlavorCore\Observability\HealthCheck;

defined( 'ABSPATH' ) || exit;

/**
 * Class ObservabilityController
 */
class ObservabilityController extends BaseApiController {

	/**
	 * Register routes.
	 */
	public function register(): void {
		$ns = FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/system/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => '__return_true', // Health endpoint for monitoring agents
			)
		);

		register_rest_route(
			$ns,
			'/system/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'require_admin' ),
			)
		);
	}

	public function require_admin( \WP_REST_Request $request ) {
		return $this->require_capability( $request, 'manage_options' );
	}

	public function get_health(): \WP_REST_Response {
		$health = HealthCheck::check();
		$status_code = ( 'unhealthy' === $health['status'] ) ? 503 : 200;

		$response = $this->respond_success( $health, array(), $status_code );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate' );
		return $response;
	}

	public function get_logs( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$limit   = min( 100, max( 1, (int) ( $request->get_param( 'limit' ) ?: 50 ) ) );
		$level   = sanitize_key( (string) $request->get_param( 'level' ) );
		$channel = sanitize_key( (string) $request->get_param( 'channel' ) );

		$where_clauses = array();
		$params        = array();

		if ( ! empty( $level ) ) {
			$where_clauses[] = 'level = %s';
			$params[]        = $level;
		}
		if ( ! empty( $channel ) ) {
			$where_clauses[] = 'channel = %s';
			$params[]        = $channel;
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$tbl       = Schema::table( 'flavor_system_logs' );
		$params[]  = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tbl} {$where_sql} ORDER BY id DESC LIMIT %d",
				...$params
			),
			ARRAY_A
		);

		$items = array_map(
			static function( $r ) {
				$r['context'] = json_decode( (string) $r['context_json'], true ) ?: array();
				unset( $r['context_json'] );
				return $r;
			},
			$rows
		);

		return $this->respond_success( $items );
	}
}
