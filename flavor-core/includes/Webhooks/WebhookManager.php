<?php
/**
 * Outgoing Webhooks Dispatch & Event Delivery Manager.
 * Supports secure HMAC-SHA256 signatures, async event hooks, and delivery audit logs.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Webhooks;

use FlavorCore\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookManager
 */
class WebhookManager {

	public const EVENT_ORDER_CREATED       = 'order.created';
	public const EVENT_ORDER_UPDATED       = 'order.updated';
	public const EVENT_ORDER_COMPLETED     = 'order.completed';
	public const EVENT_ORDER_CANCELLED     = 'order.cancelled';
	public const EVENT_RESERVATION_CREATED = 'reservation.created';
	public const EVENT_RESERVATION_UPDATED = 'reservation.updated';
	public const EVENT_CUSTOMER_CREATED    = 'customer.created';
	public const EVENT_LOYALTY_POINTS      = 'loyalty.points_awarded';

	/**
	 * Supported events list.
	 *
	 * @return string[]
	 */
	public static function supported_events(): array {
		return array(
			self::EVENT_ORDER_CREATED       => __( 'سفارش جدید ثبت شد (order.created)', 'flavor-core' ),
			self::EVENT_ORDER_UPDATED       => __( 'وضعیت سفارش تغییر کرد (order.updated)', 'flavor-core' ),
			self::EVENT_ORDER_COMPLETED     => __( 'سفارش تحویل/تکمیل شد (order.completed)', 'flavor-core' ),
			self::EVENT_ORDER_CANCELLED     => __( 'سفارش لغو شد (order.cancelled)', 'flavor-core' ),
			self::EVENT_RESERVATION_CREATED => __( 'رزرو میز جدید ثبت شد (reservation.created)', 'flavor-core' ),
			self::EVENT_RESERVATION_UPDATED => __( 'وضعیت رزرو به‌روز شد (reservation.updated)', 'flavor-core' ),
			self::EVENT_CUSTOMER_CREATED    => __( 'مشتری جدید ثبت‌نام کرد (customer.created)', 'flavor-core' ),
			self::EVENT_LOYALTY_POINTS      => __( 'امتیاز باشگاه به مشتری تعلق گرفت (loyalty.points_awarded)', 'flavor-core' ),
		);
	}

	/**
	 * Hooks for internal system events.
	 */
	public function hooks(): void {
		add_action( 'flavor_order_placed', array( $this, 'on_order_placed' ), 10, 2 );
		add_action( 'flavor_kitchen_status_transition', array( $this, 'on_ticket_status_change' ), 10, 3 );
		add_action( 'flavor_reservation_created', array( $this, 'on_reservation_created' ), 10, 1 );
		add_action( 'flavor_reservation_status_updated', array( $this, 'on_reservation_updated' ), 10, 2 );
		add_action( 'flavor_customer_registered', array( $this, 'on_customer_registered' ), 10, 1 );
		add_action( 'flavor_loyalty_points_added', array( $this, 'on_loyalty_points' ), 10, 3 );
	}

	/**
	 * Dispatch event to all subscribed webhooks.
	 *
	 * @param string               $event Event name.
	 * @param array<string, mixed> $payload Event data.
	 */
	public static function dispatch( string $event, array $payload ): void {
		global $wpdb;

		$webhooks_tbl = Schema::table( 'flavor_webhooks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$webhooks_tbl} WHERE is_active = 1"
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return;
		}

		$wrapped_payload = array(
			'event'     => $event,
			'timestamp' => gmdate( 'c' ),
			'data'      => $payload,
		);

		foreach ( $rows as $wh ) {
			$events = json_decode( (string) $wh['events_json'], true ) ?: array();
			if ( in_array( $event, $events, true ) || in_array( '*', $events, true ) ) {
				self::send_delivery( (int) $wh['id'], $wh['target_url'], $wh['secret'], $event, $wrapped_payload );
			}
		}
	}

	/**
	 * Send HTTP POST delivery and log result.
	 *
	 * @param int                  $webhook_id Webhook DB ID.
	 * @param string               $target_url Endpoint URL.
	 * @param string               $secret Shared HMAC secret.
	 * @param string               $event Event name.
	 * @param array<string, mixed> $payload Wrapped payload.
	 * @return array<string, mixed>
	 */
	public static function send_delivery( int $webhook_id, string $target_url, string $secret, string $event, array $payload ): array {
		global $wpdb;

		$json_body = wp_json_encode( $payload );
		$sig       = hash_hmac( 'sha256', (string) $json_body, $secret );
		$delivery_id = wp_generate_uuid4();

		$start_ts = microtime( true );
		$response = wp_remote_post(
			$target_url,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'httpversion' => '1.1',
				'headers'     => array(
					'Content-Type'          => 'application/json',
					'User-Agent'            => 'Flavor-Webhook-Dispatcher/2.0',
					'X-Flavor-Signature'    => $sig,
					'X-Flavor-Event'        => $event,
					'X-Flavor-Delivery-ID'  => $delivery_id,
				),
				'body'        => $json_body,
			)
		);
		$duration_ms = (int) round( ( microtime( true ) - $start_ts ) * 1000 );

		$status_code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$resp_body   = is_wp_error( $response ) ? $response->get_error_message() : substr( wp_remote_retrieve_body( $response ), 0, 1000 );
		$is_success  = ( $status_code >= 200 && $status_code < 300 );
		$status_str  = $is_success ? 'delivered' : 'failed';

		// Insert delivery log
		$deliveries_tbl = Schema::table( 'flavor_webhook_deliveries' );
		$wpdb->insert(
			$deliveries_tbl,
			array(
				'webhook_id'    => $webhook_id,
				'event'         => $event,
				'payload_json'  => $json_body,
				'response_code' => $status_code,
				'response_body' => $resp_body,
				'duration_ms'   => $duration_ms,
				'attempt_count' => 1,
				'status'        => $status_str,
				'error_message' => is_wp_error( $response ) ? $response->get_error_message() : null,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		// Update webhook stats
		$webhooks_tbl = Schema::table( 'flavor_webhooks' );
		if ( $is_success ) {
			$wpdb->update(
				$webhooks_tbl,
				array( 'last_triggered_at' => gmdate( 'Y-m-d H:i:s' ), 'failure_count' => 0 ),
				array( 'id' => $webhook_id )
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "UPDATE {$webhooks_tbl} SET failure_count = failure_count + 1 WHERE id = %d", $webhook_id ) );
		}

		return array(
			'status'        => $status_str,
			'status_code'   => $status_code,
			'duration_ms'   => $duration_ms,
			'delivery_id'   => $delivery_id,
		);
	}

	public function on_order_placed( int $ticket_id, array $order_data ): void {
		self::dispatch( self::EVENT_ORDER_CREATED, array_merge( array( 'ticket_id' => $ticket_id ), $order_data ) );
	}

	public function on_ticket_status_change( int $ticket_id, string $old_status, string $new_status ): void {
		$event = ( 'completed' === $new_status ) ? self::EVENT_ORDER_COMPLETED : ( ( 'cancelled' === $new_status ) ? self::EVENT_ORDER_CANCELLED : self::EVENT_ORDER_UPDATED );
		self::dispatch( $event, array( 'ticket_id' => $ticket_id, 'old_status' => $old_status, 'new_status' => $new_status ) );
	}

	public function on_reservation_created( int $reservation_id ): void {
		self::dispatch( self::EVENT_RESERVATION_CREATED, array( 'reservation_id' => $reservation_id ) );
	}

	public function on_reservation_updated( int $reservation_id, string $status ): void {
		self::dispatch( self::EVENT_RESERVATION_UPDATED, array( 'reservation_id' => $reservation_id, 'status' => $status ) );
	}

	public function on_customer_registered( int $user_id ): void {
		self::dispatch( self::EVENT_CUSTOMER_CREATED, array( 'user_id' => $user_id ) );
	}

	public function on_loyalty_points( int $customer_id, int $points_delta, string $reason ): void {
		self::dispatch( self::EVENT_LOYALTY_POINTS, array( 'customer_id' => $customer_id, 'points_delta' => $points_delta, 'reason' => $reason ) );
	}
}
