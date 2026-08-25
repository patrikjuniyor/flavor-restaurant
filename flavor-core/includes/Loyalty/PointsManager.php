<?php
/**
 * Stamp-card + points ledger.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Loyalty;

use FlavorCore\Database\Schema;
use FlavorCore\Support\Settings;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class PointsManager
 */
class PointsManager {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'flavor_core_kitchen_status_changed', array( $this, 'on_kitchen' ), 30, 3 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_completed' ), 20, 1 );
	}

	/**
	 * Earn when kitchen marks completed (dine-in/takeaway often never hit WC completed).
	 *
	 * @param int    $ticket_id Ticket.
	 * @param string $from      From.
	 * @param string $to        To.
	 */
	public function on_kitchen( int $ticket_id, string $from, string $to ): void {
		unset( $from );
		if ( 'completed' !== $to ) {
			return;
		}
		$ticket = \FlavorCore\Order\KitchenTicketRepository::find( $ticket_id );
		if ( ! $ticket || empty( $ticket['order_id'] ) ) {
			return;
		}
		self::earn_from_order( (int) $ticket['order_id'] );
	}

	/**
	 * WC completed fallback.
	 */
	public function on_completed( int $order_id ): void {
		self::earn_from_order( $order_id );
	}

	/**
	 * Idempotent earn + stamp.
	 */
	public static function earn_from_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$uid = (int) $order->get_customer_id();
		if ( $uid <= 0 ) {
			$mobile = (string) $order->get_meta( '_flavor_mobile' );
			if ( $mobile ) {
				$users = get_users(
					array(
						'meta_key'   => \FlavorCore\Customer\OtpAuth::META_MOBILE,
						'meta_value' => $mobile,
						'number'     => 1,
					)
				);
				$uid = $users ? (int) $users[0]->ID : 0;
			}
		}
		if ( $uid <= 0 ) {
			return;
		}
		if ( self::already_earned( $uid, $order_id ) ) {
			return;
		}

		$rate   = max( 0, (int) Settings::get( 'loyalty_points_per_unit', 1 ) );
		$unit   = max( 1, (int) Settings::get( 'loyalty_unit_toman', 10000 ) );
		$total  = (int) round( (float) $order->get_total() );
		$wc     = strtoupper( (string) $order->get_currency() );
		$toman  = 'IRR' === $wc ? Currency::convert( $total, Currency::RIAL, Currency::TOMAN ) : $total;
		$points = $rate > 0 ? (int) floor( $toman / $unit ) * $rate : 0;

		if ( $points > 0 ) {
			self::adjust( $uid, $points, 'earn', $order_id, 'order' );
		}
		self::adjust( $uid, 1, 'stamp', $order_id, 'stamp' );

		$target = max( 2, (int) Settings::get( 'loyalty_stamp_target', 10 ) );
		$stamps = self::stamp_count( $uid );
		if ( $target > 0 && 0 === ( $stamps % $target ) && $stamps > 0 ) {
			update_user_meta( $uid, '_flavor_free_item', 1 );
		}
	}

	/**
	 * Current point balance.
	 */
	public static function balance( int $customer_id ): int {
		global $wpdb;
		$table = Schema::table( 'flavor_loyalty_ledger' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$val = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance_after FROM {$table} WHERE customer_id = %d AND reason IN ('earn','redeem','adjust','expire') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);
		return (int) $val;
	}

	/**
	 * Stamp count (sum of stamp deltas).
	 */
	public static function stamp_count( int $customer_id ): int {
		global $wpdb;
		$table = Schema::table( 'flavor_loyalty_ledger' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(points_delta),0) FROM {$table} WHERE customer_id = %d AND reason = 'stamp'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);
	}

	/**
	 * Redeem points for a discount amount in storage units. Returns discount or WP_Error.
	 */
	public static function redeem( int $customer_id, int $points ) {
		$points = max( 0, $points );
		if ( $points <= 0 ) {
			return 0;
		}
		$bal = self::balance( $customer_id );
		if ( $points > $bal ) {
			return new \WP_Error( 'flavor_points', __( 'امتیاز کافی نیست.', 'flavor-core' ) );
		}
		$per = max( 1, (int) Settings::get( 'loyalty_toman_per_point', 1000 ) );
		self::adjust( $customer_id, -$points, 'redeem', 0, 'redeem' );
		$toman = $points * $per;
		return Currency::to_storage( $toman, Currency::TOMAN );
	}

	/**
	 * Manual / system ledger write.
	 */
	public static function adjust( int $customer_id, int $delta, string $reason, int $order_id = 0, string $note = '' ): int {
		$reason  = sanitize_key( $reason );
		$current = ( 'stamp' === $reason ) ? self::stamp_count( $customer_id ) : self::balance( $customer_id );
		$after   = $current + $delta;
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			Schema::table( 'flavor_loyalty_ledger' ),
			array(
				'customer_id'   => $customer_id,
				'order_id'      => $order_id ?: null,
				'points_delta'  => $delta,
				'balance_after' => $after,
				'reason'        => $reason,
				'note'          => $note,
				'created_at'    => current_time( 'mysql' ),
			)
		);
		return $after;
	}

	/**
	 * Payload for /me and admin.
	 *
	 * @return array<string, mixed>
	 */
	public static function summary( int $customer_id ): array {
		$target = max( 2, (int) Settings::get( 'loyalty_stamp_target', 10 ) );
		$stamps = self::stamp_count( $customer_id );
		return array(
			'points'       => self::balance( $customer_id ),
			'stamps'       => $stamps,
			'stamp_target' => $target,
			'stamp_cycle'  => $target ? ( $stamps % $target ) : 0,
			'free_item'    => (bool) get_user_meta( $customer_id, '_flavor_free_item', true ),
		);
	}

	/**
	 * Already credited this order?
	 */
	private static function already_earned( int $customer_id, int $order_id ): bool {
		global $wpdb;
		$table = Schema::table( 'flavor_loyalty_ledger' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND order_id = %d AND reason IN ('earn','stamp')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id,
				$order_id
			)
		);
		return $n > 0;
	}
}
