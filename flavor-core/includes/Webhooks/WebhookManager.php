<?php
/**
 * Outgoing Webhooks Dispatch & Event Delivery Manager.
 *
 * Supports secure HMAC-SHA256 signatures, queued asynchronous delivery and
 * delivery audit logs.
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
	 * Internal-only event used by the admin "test" endpoint. It is not
	 * subscribable and may never be emitted by application code.
	 */
	public const EVENT_SYSTEM_PING = 'system.ping';

	/**
	 * Cron hook that drains the queued deliveries.
	 */
	public const QUEUE_HOOK = 'flavor_core_webhook_process_queue';

	/**
	 * Maximum deliveries processed per queue run.
	 */
	public const QUEUE_BATCH_LIMIT = 20;

	/**
	 * Retry policy for failed deliveries: exponential backoff. Delivery k+1
	 * becomes due at created_at + sum over i=1..k of min(BASE * 2^(i-1),
	 * CAP). There is no attempt counter in the table beyond attempt_count,
	 * so the schedule is derived from the audit row itself.
	 */
	public const MAX_ATTEMPTS       = 5;
	public const RETRY_BASE_SECONDS = 30;
	public const RETRY_CAP_SECONDS  = 3600;

	/**
	 * Delivery statuses stored in the audit table.
	 */
	public const STATUS_QUEUED    = 'queued';
	public const STATUS_DELIVERED = 'delivered';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_SKIPPED   = 'skipped';

	/**
	 * Supported events list — the explicit allowlist. Event names contain dots
	 * and must never pass through sanitize_key().
	 *
	 * @return array<string, string>
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
	 * Supported event names only.
	 *
	 * @return string[]
	 */
	public static function allowed_event_names(): array {
		return array_keys( self::supported_events() );
	}

	/**
	 * Validate + normalize a subscription event list against the allowlist.
	 * Names are case/dot sensitive and are never passed through sanitize_key().
	 *
	 * @param array<int, mixed> $events Raw events from the request.
	 * @return string[]|\WP_Error Normalized unique list or error.
	 */
	public static function validate_events( array $events ) {
		if ( empty( $events ) ) {
			return new \WP_Error(
				'flavor_webhook_events',
				__( 'حداقل یک رویداد انتخاب کنید.', 'flavor-core' ),
				array( 'status' => 400 )
			);
		}

		$allowed = self::allowed_event_names();
		$clean   = array();
		foreach ( $events as $event ) {
			if ( ! is_string( $event ) ) {
				continue;
			}
			$event = trim( $event );
			if ( '' === $event ) {
				continue;
			}
			if ( '*' === $event ) {
				$clean[] = '*';
				continue;
			}
			if ( ! in_array( $event, $allowed, true ) ) {
				return new \WP_Error(
					'flavor_webhook_events',
					sprintf(
						/* translators: event name */
						__( 'رویداد «%s» پشتیبانی نمی‌شود.', 'flavor-core' ),
						$event
					),
					array(
						'status' => 400,
						'event'  => $event,
					)
				);
			}
			$clean[] = $event;
		}

		$clean = array_values( array_unique( $clean ) );
		if ( in_array( '*', $clean, true ) ) {
			$clean = array( '*' );
		}
		if ( empty( $clean ) ) {
			return new \WP_Error(
				'flavor_webhook_events',
				__( 'حداقل یک رویداد معتبر انتخاب کنید.', 'flavor-core' ),
				array( 'status' => 400 )
			);
		}
		return $clean;
	}

	/**
	 * Canonical wire signature of a delivery body: raw 64-hex HMAC-SHA256,
	 * the exact format build-mobile.yml emits (openssl dgst -hmac) and
	 * BuildManager verifies for CI callbacks. Single source of truth.
	 *
	 * @param string $json_body Exact bytes of the request body.
	 * @param string $secret    Shared webhook secret.
	 */
	public static function sign_body( string $json_body, string $secret ): string {
		return hash_hmac( 'sha256', $json_body, $secret );
	}

	/**
	 * Timing-safe verification of an X-Flavor-Signature against the raw body.
	 * Receivers MUST use this instead of == or === (signature comparison is a
	 * classic timing oracle). A `sha256=` prefix is tolerated, casing is
	 * normalized; anything else that is not exactly 64 hex chars is rejected
	 * before any comparison happens, and an empty secret never verifies.
	 *
	 * @param string $json_body Exact bytes of the received body.
	 * @param string $secret    Shared webhook secret.
	 * @param string $signature Header value to verify.
	 */
	public static function verify_signature( string $json_body, string $secret, string $signature ): bool {
		$signature = strtolower( trim( $signature ) );
		if ( 0 === strpos( $signature, 'sha256=' ) ) {
			$signature = substr( $signature, 7 );
		}
		if ( '' === $secret || ! preg_match( '/^[0-9a-f]{64}$/', $signature ) ) {
			return false;
		}
		return hash_equals( self::sign_body( $json_body, $secret ), $signature );
	}

	/**
	 * A secret is dispatch-ready only when it is long enough for HMAC to be
	 * meaningful; empty or stub secrets produce signatures anyone can brute
	 * force, so they are refused at the sender edge (fail closed).
	 *
	 * @param string $secret Candidate secret.
	 */
	public static function signing_ready( string $secret ): bool {
		return strlen( trim( $secret ) ) >= 16;
	}

	/**
	 * Backoff schedule helper: total seconds after created_at at which the
	 * delivery becomes eligible for its next attempt, given how many
	 * attempts already ran. attempt_count 0 means "due immediately".
	 *
	 * @param int $attempt_count Completed attempts so far.
	 */
	public static function next_attempt_offset( int $attempt_count ): int {
		$offset = 0;
		for ( $i = 1; $i <= $attempt_count; $i++ ) {
			$offset += min( self::RETRY_BASE_SECONDS * ( 2 ** ( $i - 1 ) ), self::RETRY_CAP_SECONDS );
		}
		return $offset;
	}

	/**
	 * Eligibility of a queued row right now under the backoff schedule.
	 *
	 * @param int      $created_unix created_at of the row as a unix stamp.
	 * @param int      $attempt_count Completed attempts so far.
	 * @param int|null $now          Current unix time (injectable for tests).
	 */
	public static function delivery_is_due( int $created_unix, int $attempt_count, ?int $now = null ): bool {
		$now = null === $now ? time() : $now;
		return $now - $created_unix >= self::next_attempt_offset( $attempt_count );
	}

	/**
	 * Whether an outcome ends the delivery's lifecycle: success always, or a
	 * failure once the attempt budget is spent. Extracted so the retry
	 * policy is unit-testable without a database or transport.
	 *
	 * @param bool $success       This attempt's result.
	 * @param int  $attempts_done Attempts completed including this one.
	 */
	public static function is_terminal( bool $success, int $attempts_done ): bool {
		return $success || $attempts_done >= self::MAX_ATTEMPTS;
	}

	/**
	 * A webhook target must be an external http(s) URL — never localhost, a
	 * private/reserved IP literal, a host resolving to one, or a URL carrying
	 * credentials. DNS-derived checks defer to wp_http_validate_url() and the
	 * safe remote transport when the name cannot be resolved locally.
	 *
	 * @param string $url Target URL.
	 */
	public static function is_safe_target_url( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url || strlen( $url ) > 2048 ) {
			return false;
		}

		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		if ( 'https' !== $scheme ) {
			// Cleartext dispatch is refused outright (prompt 42: https only).
			// Local development may opt in explicitly by defining
			// FLAVOR_WEBHOOK_ALLOW_HTTP as true in wp-config — and even then
			// only public hosts pass: loopback/private/reserved ranges below
			// stay blocked regardless of scheme (the opt-in relaxes wire
			// encryption, never the SSRF fence).
			$dev_http = defined( 'FLAVOR_WEBHOOK_ALLOW_HTTP' ) && FLAVOR_WEBHOOK_ALLOW_HTTP;
			if ( 'http' !== $scheme || ! $dev_http ) {
				return false;
			}
		}

		$host = strtolower( trim( (string) ( $parts['host'] ?? '' ) ) );
		if ( '' === $host ) {
			return false;
		}

		// user:pass@host is a classical smuggling vector.
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}

		if ( 'localhost' === $host
			|| substr( $host, -10 ) === '.localhost'
			|| substr( $host, -6 ) === '.local'
			|| substr( $host, -9 ) === '.internal' ) {
			return false;
		}

		$ip = trim( $host, '[]' );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		// A hostname: when resolution is possible, every A record must be public.
		if ( function_exists( 'gethostbynamel' ) ) {
			$ips = gethostbynamel( $host );
			if ( is_array( $ips ) ) {
				if ( empty( $ips ) ) {
					return false;
				}
				foreach ( $ips as $resolved ) {
					if ( ! filter_var( $resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return false;
					}
				}
			}
			// Unresolvable names defer to wp_http_validate_url() / the safe
			// transport layer (request-time re-check, including redirect hops).
		}

		if ( function_exists( 'wp_http_validate_url' ) && false === wp_http_validate_url( $url ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hooks for internal system events.
	 *
	 * The listened-to hook names are exactly the do_action() calls the
	 * application actually emits (`flavor_core_*`), found via
	 * KitchenTicketRepository, ReservationRepository, OtpAuth and
	 * PointsManager. Do not invent parallel names — drift means silent
	 * webhook death.
	 */
	public function hooks(): void {
		add_action( 'flavor_core_kitchen_ticket_created', array( $this, 'on_ticket_created' ), 10, 2 );
		add_action( 'flavor_core_kitchen_status_changed', array( $this, 'on_ticket_status_change' ), 10, 3 );
		add_action( 'flavor_core_reservation_created', array( $this, 'on_reservation_created' ), 10, 2 );
		add_action( 'flavor_core_reservation_status_changed', array( $this, 'on_reservation_updated' ), 10, 2 );
		add_action( 'flavor_core_otp_verified', array( $this, 'on_customer_registered' ), 10, 2 );
		add_action( 'flavor_core_loyalty_points_awarded', array( $this, 'on_loyalty_points' ), 10, 3 );
		add_action( self::QUEUE_HOOK, array( self::class, 'process_queue' ) );
	}

	/**
	 * Queue event deliveries for all subscribed webhooks.
	 *
	 * No HTTP happens here: deliveries are persisted with status `queued` and a
	 * single cron task is scheduled, so the checkout/reservation code path that
	 * emitted the event never blocks on remote networks.
	 *
	 * @param string               $event   Event name (allowlist enforced).
	 * @param array<string, mixed> $payload Event data.
	 */
	public static function dispatch( string $event, array $payload ): void {
		if ( ! in_array( $event, self::allowed_event_names(), true ) ) {
			return;
		}

		global $wpdb;
		$webhooks_tbl = Schema::table( 'flavor_webhooks' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, events_json FROM {$webhooks_tbl} WHERE is_active = 1", ARRAY_A );
		if ( empty( $rows ) ) {
			return;
		}

		$wrapped_payload = array(
			'event'     => $event,
			'timestamp' => gmdate( 'c' ),
			'data'      => $payload,
		);

		$deliveries_tbl = Schema::table( 'flavor_webhook_deliveries' );
		$queued         = 0;
		foreach ( $rows as $wh ) {
			$events = json_decode( (string) $wh['events_json'], true ) ?: array();
			if ( ! in_array( $event, $events, true ) && ! in_array( '*', $events, true ) ) {
				continue;
			}
			$wrapped_payload['delivery_id'] = wp_generate_uuid4();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->insert(
				$deliveries_tbl,
				array(
					'webhook_id'    => (int) $wh['id'],
					'event'         => $event,
					'payload_json'  => (string) wp_json_encode( $wrapped_payload ),
					'response_code' => null,
					'response_body' => null,
					'duration_ms'   => null,
					'attempt_count' => 0,
					'status'        => self::STATUS_QUEUED,
					'error_message' => null,
					'created_at'    => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			if ( false !== $ok ) {
				++$queued;
			}
		}

		if ( $queued > 0 ) {
			self::schedule_queue();
		}
	}

	/**
	 * Schedule the queue processor (immediately, via WP-Cron).
	 */
	private static function schedule_queue( int $at = 0 ): void {
		if ( function_exists( 'wp_schedule_single_event' ) ) {
			wp_schedule_single_event( $at > 0 ? $at : time(), self::QUEUE_HOOK );
		}
	}

	/**
	 * Drain queued deliveries with one HTTP attempt each.
	 *
	 * @param int $limit Max rows per run.
	 * @return int Number of attempted deliveries.
	 */
	public static function process_queue( int $limit = self::QUEUE_BATCH_LIMIT ): int {
		global $wpdb;
		$deliveries_tbl = Schema::table( 'flavor_webhook_deliveries' );
		$webhooks_tbl   = Schema::table( 'flavor_webhooks' );

		// Single pass over one page of the queue. Failed attempts stay queued
		// (status is only made terminal when the retry budget is exhausted),
		// so a while-loop re-querying would hammer the same rows; the cron
		// follow-up below re-arms at the earliest backoff due-time instead.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pending = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$deliveries_tbl} WHERE status = %s ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::STATUS_QUEUED,
				$limit
			),
			ARRAY_A
		);
		if ( empty( $pending ) ) {
			return 0;
		}

		$attempted = 0;
		$next_due  = 0;
		foreach ( $pending as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wh = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, target_url, secret, is_active FROM {$webhooks_tbl} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $row['webhook_id']
				),
				ARRAY_A
			);

			if ( ! $wh || ! (int) $wh['is_active'] ) {
				self::finalize_row(
					(int) $row['id'],
					array(
						'status'        => self::STATUS_SKIPPED,
						'error_message' => 'Webhook inactive or deleted',
					)
				);
				continue;
			}

			$attempts = (int) ( $row['attempt_count'] ?? 0 );
			$created  = strtotime( (string) ( $row['created_at'] ?? '' ) . ' UTC' );
			if ( false !== $created && ! self::delivery_is_due( $created, $attempts ) ) {
				// Backoff window still open: leave it queued, remember the wake
				// time so the next cron run can be armed precisely.
				$due      = $created + self::next_attempt_offset( $attempts );
				$next_due = $next_due > 0 ? min( $next_due, $due ) : $due;
				continue;
			}

			$payload  = json_decode( (string) $row['payload_json'], true );
			$payload  = is_array( $payload ) ? $payload : array();
			$event    = (string) $row['event'];
			$delivery = self::attempt(
				(string) $wh['target_url'],
				(string) $wh['secret'],
				$event,
				(string) $row['payload_json'],
				(string) ( $payload['delivery_id'] ?? wp_generate_uuid4() )
			);

			$terminal = self::is_terminal( (bool) $delivery['success'], $attempts + 1 );
			$status   = $delivery['success']
				? self::STATUS_DELIVERED
				: ( $terminal ? self::STATUS_FAILED : self::STATUS_QUEUED );

			self::finalize_row(
				(int) $row['id'],
				array(
					'status'        => $status,
					'response_code' => $delivery['status_code'],
					'response_body' => $delivery['response_body'],
					'duration_ms'   => $delivery['duration_ms'],
					'error_message' => $terminal
						? $delivery['error']
						: 'Retry scheduled after attempt ' . ( $attempts + 1 ) . ': ' . $delivery['error'],
				)
			);
			if ( $terminal ) {
				self::record_webhook_outcome( (int) $wh['id'], $delivery['success'] );
			}
			++$attempted;
		}

		if ( $next_due > 0 ) {
			self::schedule_queue( max( time(), $next_due ) );
		}

		return $attempted;
	}

	/**
	 * Finalize a delivery audit row after an attempt.
	 *
	 * @param int                  $row_id Delivery row id.
	 * @param array<string, mixed> $data   Columns to update (attempt_count is incremented).
	 */
	private static function finalize_row( int $row_id, array $data ): void {
		global $wpdb;
		$deliveries_tbl = Schema::table( 'flavor_webhook_deliveries' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$deliveries_tbl} SET attempt_count = attempt_count + 1 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$row_id
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $deliveries_tbl, $data, array( 'id' => $row_id ) );
	}

	/**
	 * Single HTTP attempt with HMAC signature. No redirect following — a 3xx
	 * counts as failure, so a signed delivery can never be bounced across
	 * hosts (SSRF via open redirect).
	 *
	 * @param string $target_url  URL.
	 * @param string $secret      HMAC secret.
	 * @param string $event       Event name.
	 * @param string $json_body   Exact body being signed.
	 * @param string $delivery_id Delivery UUID.
	 * @return array{success: bool, status_code: int, response_body: string, duration_ms: int, error: string}
	 */
	private static function attempt( string $target_url, string $secret, string $event, string $json_body, string $delivery_id ): array {
		if ( ! self::signing_ready( $secret ) ) {
			return array(
				'success'       => false,
				'status_code'   => 0,
				'response_body' => '',
				'duration_ms'   => 0,
				'error'         => __( 'رمز وب‌هوک برای امضای HMAC معتبر نیست؛ ارسال بدونِ امضا انجام نمی‌شود.', 'flavor-core' ),
			);
		}
		if ( ! self::is_safe_target_url( $target_url ) ) {
			return array(
				'success'       => false,
				'status_code'   => 0,
				'response_body' => '',
				'duration_ms'   => 0,
				'error'         => __( 'آدرس مقصد وب‌هوک امن نیست (SSRF).', 'flavor-core' ),
			);
		}

		$args  = array(
			'timeout'     => 5,
			'redirection' => 0,
			'httpversion' => '1.1',
			'headers'     => array(
				'Content-Type'         => 'application/json',
				'User-Agent'           => 'Flavor-Webhook-Dispatcher/2.0',
				'X-Flavor-Signature'   => self::sign_body( $json_body, $secret ),
				'X-Flavor-Event'       => $event,
				'X-Flavor-Delivery-ID' => $delivery_id,
			),
			'body'        => $json_body,
		);
		$start = microtime( true );
		// Prefer the SSRF-safe transport (blocks internal IP hops) when present.
		$response = function_exists( 'wp_safe_remote_post' )
			? wp_safe_remote_post( $target_url, $args )
			: wp_remote_post( $target_url, $args );
		$duration_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		$status_code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$body        = is_wp_error( $response ) ? '' : substr( (string) wp_remote_retrieve_body( $response ), 0, 1000 );
		$error       = is_wp_error( $response ) ? $response->get_error_message() : '';
		$success     = $status_code >= 200 && $status_code < 300;
		if ( ! $success && '' === $error && ! is_wp_error( $response ) ) {
			$error = 'HTTP ' . $status_code;
		}

		return array(
			'success'       => $success,
			'status_code'   => $status_code,
			'response_body' => $body,
			'duration_ms'   => $duration_ms,
			'error'         => $error,
		);
	}

	/**
	 * Update webhook stats after an outcome.
	 *
	 * @param int  $webhook_id Webhook id.
	 * @param bool $success    Outcome.
	 */
	private static function record_webhook_outcome( int $webhook_id, bool $success ): void {
		global $wpdb;
		$webhooks_tbl = Schema::table( 'flavor_webhooks' );
		if ( $success ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$webhooks_tbl,
				array(
					'last_triggered_at' => gmdate( 'Y-m-d H:i:s' ),
					'failure_count'     => 0,
				),
				array( 'id' => $webhook_id )
			);
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$webhooks_tbl} SET failure_count = failure_count + 1 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$webhook_id
			)
		);
	}

	/**
	 * Synchronous single delivery + audit row (kept for the admin "test"
	 * endpoint; the event pipeline itself is asynchronous).
	 *
	 * @param int                  $webhook_id Webhook DB ID.
	 * @param string               $target_url Endpoint URL.
	 * @param string               $secret     Shared HMAC secret.
	 * @param string               $event      Event name.
	 * @param array<string, mixed> $payload    Wrapped payload.
	 * @return array<string, mixed>
	 */
	public static function send_delivery( int $webhook_id, string $target_url, string $secret, string $event, array $payload ): array {
		global $wpdb;

		$payload['delivery_id'] = isset( $payload['delivery_id'] ) && is_string( $payload['delivery_id'] ) ? $payload['delivery_id'] : wp_generate_uuid4();
		$json_body              = (string) wp_json_encode( $payload );

		$delivery = self::attempt( $target_url, $secret, $event, $json_body, (string) $payload['delivery_id'] );
		$status   = $delivery['success'] ? self::STATUS_DELIVERED : self::STATUS_FAILED;

		$deliveries_tbl = Schema::table( 'flavor_webhook_deliveries' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$deliveries_tbl,
			array(
				'webhook_id'    => $webhook_id,
				'event'         => $event,
				'payload_json'  => $json_body,
				'response_code' => $delivery['status_code'],
				'response_body' => $delivery['response_body'],
				'duration_ms'   => $delivery['duration_ms'],
				'attempt_count' => 1,
				'status'        => $status,
				'error_message' => '' !== $delivery['error'] ? $delivery['error'] : null,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		self::record_webhook_outcome( $webhook_id, $delivery['success'] );

		return array(
			'status'      => $status,
			'status_code' => $delivery['status_code'],
			'duration_ms' => $delivery['duration_ms'],
			'delivery_id' => (string) $payload['delivery_id'],
			'error'       => $delivery['error'],
		);
	}

	/**
	 * Kitchen ticket created ⇒ order.created.
	 *
	 * @param int                  $ticket_id Ticket.
	 * @param array<string, mixed> $row       Ticket row.
	 */
	public function on_ticket_created( int $ticket_id, array $row ): void {
		self::dispatch(
			self::EVENT_ORDER_CREATED,
			self::ticket_payload(
				$ticket_id,
				$row,
				array(
					'order_id'      => (int) ( $row['order_id'] ?? 0 ),
					'order_number'  => (string) ( $row['order_number'] ?? '' ),
					'branch_id'     => (int) ( $row['branch_id'] ?? 0 ),
					'order_mode'    => (string) ( $row['order_mode'] ?? '' ),
					'table_number'  => (string) ( $row['table_number'] ?? '' ),
					'payment_method' => (string) ( $row['payment_method'] ?? '' ),
					'total'         => (int) ( $row['total'] ?? 0 ),
					'source'        => (string) ( $row['source'] ?? '' ),
					'placed_at'     => (string) ( $row['placed_at'] ?? '' ),
				)
			)
		);
	}

	/**
	 * Kitchen ticket status change ⇒ order.updated / completed / cancelled.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param string $from      From.
	 * @param string $to        To.
	 */
	public function on_ticket_status_change( int $ticket_id, string $from, string $to ): void {
		$event = self::EVENT_ORDER_UPDATED;
		if ( 'completed' === $to ) {
			$event = self::EVENT_ORDER_COMPLETED;
		} elseif ( 'cancelled' === $to ) {
			$event = self::EVENT_ORDER_CANCELLED;
		}
		self::dispatch(
			$event,
			array(
				'ticket_id'  => $ticket_id,
				'old_status' => $from,
				'new_status' => $to,
			)
		);
	}

	/**
	 * Reservation created (hook: flavor_core_reservation_created).
	 *
	 * @param int                  $id  Reservation id.
	 * @param array<string, mixed> $row Row.
	 */
	public function on_reservation_created( int $id, array $row = array() ): void {
		$row = is_array( $row ) ? $row : array();
		self::dispatch(
			self::EVENT_RESERVATION_CREATED,
			array(
				'reservation_id' => $id,
				'branch_id'      => (int) ( $row['branch_id'] ?? 0 ),
				'table_id'       => (int) ( $row['table_id'] ?? 0 ),
				'party_size'     => (int) ( $row['party_size'] ?? 0 ),
				'date'           => (string) ( $row['reservation_date'] ?? $row['date'] ?? '' ),
				'time'           => (string) ( $row['reservation_time'] ?? $row['time_slot'] ?? '' ),
				'status'         => (string) ( $row['status'] ?? '' ),
			)
		);
	}

	/**
	 * Reservation status change (hook: flavor_core_reservation_status_changed).
	 *
	 * @param int    $id     Reservation id.
	 * @param string $status New status.
	 */
	public function on_reservation_updated( int $id, string $status ): void {
		self::dispatch(
			self::EVENT_RESERVATION_UPDATED,
			array(
				'reservation_id' => $id,
				'status'         => $status,
			)
		);
	}

	/**
	 * OTP verified (hook: flavor_core_otp_verified) ⇒ customer.created, but
	 * only for accounts that were just created in this very flow.
	 *
	 * @param \WP_User $user   User.
	 * @param string   $mobile Mobile.
	 */
	public function on_customer_registered( $user, string $mobile ): void {
		$user_id = is_object( $user ) && ! empty( $user->ID ) ? (int) $user->ID : (int) $user;
		if ( $user_id <= 0 ) {
			return;
		}
		if ( ! get_user_meta( $user_id, '_flavor_just_created', true ) ) {
			return;
		}
		self::dispatch(
			self::EVENT_CUSTOMER_CREATED,
			array(
				'user_id' => $user_id,
				'mobile'  => $mobile,
			)
		);
	}

	/**
	 * Loyalty points awarded (hook: flavor_core_loyalty_points_awarded).
	 *
	 * @param int    $customer_id  Customer.
	 * @param int    $points_delta Delta (positive).
	 * @param string $reason       Reason.
	 */
	public function on_loyalty_points( int $customer_id, int $points_delta, string $reason ): void {
		if ( $points_delta <= 0 ) {
			return;
		}
		self::dispatch(
			self::EVENT_LOYALTY_POINTS,
			array(
				'customer_id'  => $customer_id,
				'points_delta' => $points_delta,
				'reason'       => $reason,
			)
		);
	}

	/**
	 * Ticket row payload helper.
	 *
	 * @param int                  $ticket_id Ticket.
	 * @param array<string, mixed> $row       Row.
	 * @param array<string, mixed> $fields    Field subset.
	 * @return array<string, mixed>
	 */
	private static function ticket_payload( int $ticket_id, array $row, array $fields ): array {
		unset( $row );
		return array_merge( array( 'ticket_id' => $ticket_id ), $fields );
	}
}
