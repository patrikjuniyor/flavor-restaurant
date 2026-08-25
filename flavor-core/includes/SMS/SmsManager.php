<?php
/**
 * SMS facade: pick a provider, send, log.
 *
 * @package FlavorCore
 */

namespace FlavorCore\SMS;

use FlavorCore\Database\Schema;
use FlavorCore\SMS\Providers\DevProvider;
use FlavorCore\SMS\Providers\FarazProvider;
use FlavorCore\SMS\Providers\KavenegarProvider;
use FlavorCore\SMS\Providers\MelipayamakProvider;
use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SmsManager
 */
class SmsManager {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'flavor_core_kitchen_status_changed', array( $this, 'on_kitchen_status' ), 20, 3 );
		add_action( 'flavor_core_kitchen_ticket_created', array( $this, 'on_ticket_created' ), 20, 2 );
	}

	/**
	 * Registered drivers.
	 *
	 * @return ProviderInterface[]
	 */
	public static function providers(): array {
		$list = array(
			new DevProvider(),
			new MelipayamakProvider(),
			new FarazProvider(),
			new KavenegarProvider(),
		);
		/**
		 * @param ProviderInterface[] $list Drivers.
		 */
		return apply_filters( 'flavor_core_sms_providers', $list );
	}

	/**
	 * Active driver (falls back to Dev).
	 */
	public static function active(): ProviderInterface {
		$slug = (string) Settings::get( 'sms_provider', 'dev' );
		foreach ( self::providers() as $p ) {
			if ( $p->slug() === $slug && $p->is_available() ) {
				return $p;
			}
		}
		return new DevProvider();
	}

	/**
	 * Send a templated event.
	 *
	 * @param string                $event   Template key.
	 * @param string                $mobile  Mobile.
	 * @param array<string, string> $vars    Placeholders.
	 * @param array<string, mixed>  $related related_type / related_id.
	 * @return array{ok:bool,id:?string,error:?string}
	 */
	public static function send_event( string $event, string $mobile, array $vars, array $related = array() ): array {
		$body = Templates::render( $event, $vars );
		return self::send( $mobile, $body, $event, $related );
	}

	/**
	 * Send raw text and persist a log row.
	 *
	 * @param string               $mobile  Mobile.
	 * @param string               $message Body.
	 * @param string               $event   Event slug.
	 * @param array<string, mixed> $related Meta.
	 * @return array{ok:bool,id:?string,error:?string}
	 */
	public static function send( string $mobile, string $message, string $event = 'custom', array $related = array() ): array {
		$provider = self::active();
		$result   = $provider->send( $mobile, $message, array( 'event' => $event ) );

		/**
		 * After an SMS attempt.
		 *
		 * @param array<string, mixed> $result   Result.
		 * @param string               $mobile   Mobile.
		 * @param string               $message  Body.
		 * @param string               $event    Event.
		 */
		$result = apply_filters( 'flavor_core_sms_sent', $result, $mobile, $message, $event );

		self::log(
			array(
				'provider'            => $provider->slug(),
				'event'               => $event,
				'recipient'           => $mobile,
				'template'            => $event,
				'body'                => $message,
				'status'              => $result['ok'] ? ( 'dev' === $provider->slug() ? 'dev' : 'sent' ) : 'failed',
				'provider_message_id' => $result['id'] ?? null,
				'related_type'        => $related['related_type'] ?? null,
				'related_id'          => isset( $related['related_id'] ) ? (int) $related['related_id'] : null,
			)
		);

		return $result;
	}

	/**
	 * Confirm SMS after a new ticket.
	 *
	 * @param int                  $ticket_id Ticket.
	 * @param array<string, mixed> $row       Row.
	 */
	public function on_ticket_created( int $ticket_id, array $row ): void {
		$mobile = (string) ( $row['customer_mobile'] ?? '' );
		if ( ! $mobile ) {
			return;
		}
		self::send_event(
			'order_confirmation',
			$mobile,
			array(
				'customer_name' => (string) ( $row['customer_name'] ?? '' ),
				'order_number'  => (string) ( $row['order_number'] ?? '' ),
				'total'         => (string) ( $row['total'] ?? '' ),
			),
			array(
				'related_type' => 'ticket',
				'related_id'   => $ticket_id,
			)
		);
	}

	/**
	 * Status SMS.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param string $from      From.
	 * @param string $to        To.
	 */
	public function on_kitchen_status( int $ticket_id, string $from, string $to ): void {
		unset( $from );
		$ticket = \FlavorCore\Order\KitchenTicketRepository::find( $ticket_id );
		if ( ! $ticket || empty( $ticket['customer_mobile'] ) ) {
			return;
		}
		$event = 'ready' === $to ? 'order_ready' : 'order_status';
		self::send_event(
			$event,
			(string) $ticket['customer_mobile'],
			array(
				'order_number' => (string) $ticket['order_number'],
				'status'       => $to,
			),
			array(
				'related_type' => 'ticket',
				'related_id'   => $ticket_id,
			)
		);
	}

	/**
	 * Persist a log row.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	public static function log( array $row ): void {
		global $wpdb;
		$row['created_at'] = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( Schema::table( 'flavor_sms_log' ), $row );
	}

	/**
	 * Latest SMS for a mobile (dev OTP preview).
	 *
	 * @param string $mobile Mobile.
	 * @return array<string, mixed>|null
	 */
	public static function last_for( string $mobile ): ?array {
		global $wpdb;
		$table = Schema::table( 'flavor_sms_log' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE recipient = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$mobile
			),
			ARRAY_A
		);
		return $row ?: null;
	}
}
