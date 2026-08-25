<?php
/**
 * Create / update kitchen tickets from WooCommerce order lifecycle.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Order;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class KitchenTicketSync
 */
class KitchenTicketSync {

	/**
	 * Offline methods that should spawn a ticket immediately.
	 */
	public const OFFLINE_METHODS = array(
		'flavor_pay_at_counter',
		'flavor_cod',
		'flavor_card_on_delivery',
		'cod',
	);

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 20, 3 );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );
	}

	/**
	 * After checkout. Ticket is created now only for offline payment methods.
	 *
	 * @param int               $order_id    Order id.
	 * @param array<string,mixed> $posted    Posted data.
	 * @param \WC_Order|null    $order       Order.
	 */
	public function on_order_processed( int $order_id, $posted, $order = null ): void {
		unset( $posted );
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$method = (string) $order->get_payment_method();
		if ( in_array( $method, self::OFFLINE_METHODS, true ) || 'yes' !== $order->get_meta( '_flavor_awaiting_online' ) ) {
			if ( in_array( $method, self::OFFLINE_METHODS, true ) || ! $method ) {
				$this->ensure_ticket( $order );
			}
		}
	}

	/**
	 * Online gateways: spawn the ticket only after money is captured.
	 *
	 * @param int $order_id Order id.
	 */
	public function on_payment_complete( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->ensure_ticket( $order );
		}
	}

	/**
	 * Keep payment_status in sync; cancel ticket when the order is cancelled/refunded.
	 *
	 * @param int       $order_id   Order.
	 * @param string    $from       From.
	 * @param string    $to         To.
	 * @param \WC_Order $order      Order.
	 */
	public function on_status_changed( int $order_id, string $from, string $to, $order ): void {
		unset( $from );
		$ticket = KitchenTicketRepository::find_by_order( $order_id );
		if ( ! $ticket ) {
			if ( in_array( $to, array( 'processing', 'completed', 'on-hold' ), true ) && $order instanceof \WC_Order ) {
				$this->ensure_ticket( $order );
			}
			return;
		}

		global $wpdb;
		$update = array(
			'payment_status' => $to,
			'updated_at'     => current_time( 'mysql' ),
		);
		if ( in_array( $to, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			$update['kitchen_status'] = 'cancelled';
			$update['completed_at']   = current_time( 'mysql' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( KitchenTicketRepository::table(), $update, array( 'id' => (int) $ticket['id'] ) );
	}

	/**
	 * Idempotent ticket + items snapshot.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function ensure_ticket( $order ): void {
		$order_id = (int) $order->get_id();
		$existing = KitchenTicketRepository::find_by_order( $order_id );

		$branch_id = (int) $order->get_meta( '_flavor_branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}

		$mode = (string) $order->get_meta( '_flavor_order_mode' );
		if ( ! in_array( $mode, array( 'dine_in', 'takeaway', 'delivery' ), true ) ) {
			$mode = 'dine_in';
		}

		$storage_unit = Currency::storage_unit();
		$wc_code      = strtoupper( (string) $order->get_currency() );
		$from_unit    = 'IRR' === $wc_code ? Currency::RIAL : Currency::TOMAN;

		$to_storage = static function ( $amount ) use ( $from_unit, $storage_unit ): int {
			return Currency::convert( (int) round( (float) $amount ), $from_unit, $storage_unit );
		};

		if ( ! $existing ) {
			$ticket_id = KitchenTicketRepository::create_idempotent(
				array(
					'order_id'         => $order_id,
					'order_number'     => $order->get_order_number(),
					'branch_id'        => $branch_id,
					'table_id'         => (int) $order->get_meta( '_flavor_table_id' ) ?: null,
					'table_number'     => (string) $order->get_meta( '_flavor_table_number' ),
					'order_mode'       => $mode,
					'payment_status'   => $order->get_status(),
					'payment_method'   => $order->get_payment_method(),
					'customer_id'      => $order->get_customer_id() ?: null,
					'customer_name'    => $order->get_formatted_billing_full_name() ?: $order->get_billing_first_name(),
					'customer_mobile'  => $order->get_billing_phone() ?: (string) $order->get_meta( '_flavor_mobile' ),
					'delivery_address' => $order->get_formatted_billing_address(),
					'delivery_zone_id' => (int) $order->get_meta( '_flavor_zone_id' ) ?: null,
					'delivery_fee'     => $to_storage( $order->get_shipping_total() ),
					'subtotal'         => $to_storage( $order->get_subtotal() ),
					'discount_total'   => $to_storage( $order->get_discount_total() ),
					'total'            => $to_storage( $order->get_total() ),
					'special_notes'    => $order->get_customer_note(),
					'source'           => (string) $order->get_meta( '_flavor_source' ) ?: 'online',
					'placed_at'        => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' ),
				)
			);
		} else {
			$ticket_id = (int) $existing['id'];
		}

		if ( $ticket_id ) {
			KitchenTicketRepository::sync_items( $ticket_id, $order );
		}

		unset( $storage_unit );
	}
}
