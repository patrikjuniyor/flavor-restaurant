<?php
/**
 * REST API: Webhooks Management Controller.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Database\Schema;
use FlavorCore\Webhooks\WebhookManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookController
 */
class WebhookController extends BaseApiController {

	/**
	 * Register routes.
	 *
	 * @param string|null $namespace Namespace override (defaults to V1).
	 */
	public function register( ?string $namespace = null ): void {
		$ns = $namespace ?: FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/webhooks',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_webhooks' ),
					'permission_callback' => array( $this, 'require_webhook_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_webhook' ),
					'permission_callback' => array( $this, 'require_webhook_admin' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/webhooks/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_webhook' ),
					'permission_callback' => array( $this, 'require_webhook_admin' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_webhook' ),
					'permission_callback' => array( $this, 'require_webhook_admin' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_webhook' ),
					'permission_callback' => array( $this, 'require_webhook_admin' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/webhooks/(?P<id>\d+)/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_webhook' ),
				'permission_callback' => array( $this, 'require_webhook_admin' ),
			)
		);

		register_rest_route(
			$ns,
			'/webhooks/(?P<id>\d+)/deliveries',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_deliveries' ),
				'permission_callback' => array( $this, 'require_webhook_admin' ),
			)
		);
	}

	public function require_webhook_admin( \WP_REST_Request $request ) {
		if ( current_user_can( 'flavor_manage_webhooks' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error(
			'flavor_forbidden',
			__( 'دسترسی به مدیریت وب‌هوک‌ها محدود است.', 'flavor-core' ),
			array( 'status' => 403 )
		);
	}

	public function get_webhooks(): \WP_REST_Response {
		global $wpdb;
		$tbl  = Schema::table( 'flavor_webhooks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, name, target_url, events_json, is_active, failure_count, last_triggered_at, created_at FROM {$tbl} ORDER BY id DESC", ARRAY_A );

		$items = array_map(
			static function ( $r ) {
				$r['events'] = json_decode( (string) $r['events_json'], true ) ?: array();
				unset( $r['events_json'] );
				return $r;
			},
			$rows
		);

		return $this->respond_success(
			$items,
			array( 'supported_events' => WebhookManager::supported_events() )
		);
	}

	public function create_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$body   = $request->get_json_params() ?: array();
		$name   = sanitize_text_field( (string) ( $body['name'] ?? '' ) );
		$url    = esc_url_raw( (string) ( $body['target_url'] ?? '' ) );
		$secret = sanitize_text_field( (string) ( $body['secret'] ?? wp_generate_password( 24, false ) ) );
		$events = is_array( $body['events'] ?? null ) ? array_map( 'sanitize_key', $body['events'] ) : array( '*' );

		if ( empty( $name ) || empty( $url ) ) {
			return $this->respond_error( 'invalid_data', __( 'نام و آدرس وب‌هوک الزامی است.', 'flavor-core' ), 400 );
		}

		$tbl = Schema::table( 'flavor_webhooks' );
		$wpdb->insert(
			$tbl,
			array(
				'name'         => $name,
				'target_url'   => $url,
				'secret'       => $secret,
				'events_json'  => wp_json_encode( $events ),
				'is_active'    => 1,
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
				'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$new_id = (int) $wpdb->insert_id;
		return $this->respond_success(
			array(
				'id'         => $new_id,
				'name'       => $name,
				'target_url' => $url,
				'secret'     => $secret,
				'events'     => $events,
			),
			array( 'message' => __( 'وب‌هوک با موفقیت ایجاد گردید.', 'flavor-core' ) ),
			201
		);
	}

	public function get_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id  = (int) $request->get_param( 'id' );
		$tbl = Schema::table( 'flavor_webhooks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return $this->respond_error( 'not_found', __( 'وب‌هوک یافت نشد.', 'flavor-core' ), 404 );
		}

		$row['events'] = json_decode( (string) $row['events_json'], true ) ?: array();
		unset( $row['events_json'] );

		return $this->respond_success( $row );
	}

	public function update_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params() ?: array();
		$tbl  = Schema::table( 'flavor_webhooks' );

		$data = array( 'updated_at' => gmdate( 'Y-m-d H:i:s' ) );
		if ( isset( $body['name'] ) ) {
			$data['name'] = sanitize_text_field( (string) $body['name'] );
		}
		if ( isset( $body['target_url'] ) ) {
			$data['target_url'] = esc_url_raw( (string) $body['target_url'] );
		}
		if ( isset( $body['is_active'] ) ) {
			$data['is_active'] = ! empty( $body['is_active'] ) ? 1 : 0;
		}
		if ( isset( $body['events'] ) && is_array( $body['events'] ) ) {
			$data['events_json'] = wp_json_encode( array_map( 'sanitize_key', $body['events'] ) );
		}

		$wpdb->update( $tbl, $data, array( 'id' => $id ) );
		return $this->respond_success( array( 'updated' => true ) );
	}

	public function delete_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id  = (int) $request->get_param( 'id' );
		$tbl = Schema::table( 'flavor_webhooks' );
		$wpdb->delete( $tbl, array( 'id' => $id ), array( '%d' ) );

		return $this->respond_success( array( 'deleted' => true ) );
	}

	public function test_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id  = (int) $request->get_param( 'id' );
		$tbl = Schema::table( 'flavor_webhooks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return $this->respond_error( 'not_found', __( 'وب‌هوک یافت نشد.', 'flavor-core' ), 404 );
		}

		$payload = array(
			'event'     => 'system.ping',
			'timestamp' => gmdate( 'c' ),
			'data'      => array(
				'message'     => 'Flavor Webhook Ping Test',
				'server_time' => current_time( 'mysql' ),
				'webhook_id'  => $id,
			),
		);

		$result = WebhookManager::send_delivery( $id, $row['target_url'], $row['secret'], 'system.ping', $payload );
		return $this->respond_success( $result );
	}

	public function get_deliveries( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id    = (int) $request->get_param( 'id' );
		$limit = min( 50, max( 1, (int) ( $request->get_param( 'limit' ) ?: 20 ) ) );
		$tbl   = Schema::table( 'flavor_webhook_deliveries' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, webhook_id, event, response_code, duration_ms, attempt_count, status, error_message, created_at
				FROM {$tbl}
				WHERE webhook_id = %d
				ORDER BY id DESC
				LIMIT %d",
				$id,
				$limit
			),
			ARRAY_A
		);

		return $this->respond_success( $rows );
	}
}
