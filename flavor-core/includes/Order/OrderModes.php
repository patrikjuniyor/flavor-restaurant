<?php
/**
 * Session-level order mode, branch and table binding.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderModes
 */
class OrderModes {

	public const SESSION_KEY = 'flavor_context';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'maybe_start_session' ), 1 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_order' ), 10, 2 );
	}

	/**
	 * WooCommerce session is enough once WC has booted; cookie fallback for QR landing.
	 */
	public function maybe_start_session(): void {
		if ( headers_sent() ) {
			return;
		}
		$branch = isset( $_GET['flavor_branch'] ) ? absint( wp_unslash( $_GET['flavor_branch'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$table  = isset( $_GET['flavor_table'] ) ? sanitize_text_field( wp_unslash( $_GET['flavor_table'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mode   = isset( $_GET['flavor_mode'] ) ? sanitize_key( wp_unslash( $_GET['flavor_mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $branch && ! $table && ! $mode ) {
			return;
		}

		$ctx = self::get();
		if ( $branch ) {
			$ctx['branch_id'] = $branch;
		}
		if ( $table ) {
			$ctx['table_token'] = $table;
			$ctx['order_mode']  = 'dine_in';
		}
		if ( in_array( $mode, array( 'dine_in', 'takeaway', 'delivery' ), true ) ) {
			$ctx['order_mode'] = $mode;
		}
		self::set( $ctx );
	}

	/**
	 * Current context.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$defaults = array(
			'branch_id'    => 0,
			'table_id'     => 0,
			'table_token'  => '',
			'table_number' => '',
			'order_mode'   => '',
		);

		if ( function_exists( 'WC' ) && WC()->session ) {
			$stored = WC()->session->get( self::SESSION_KEY );
			if ( is_array( $stored ) ) {
				return wp_parse_args( $stored, $defaults );
			}
		}

		$cookie = isset( $_COOKIE['flavor_ctx'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['flavor_ctx'] ) ) : '';
		if ( $cookie ) {
			$decoded = json_decode( $cookie, true );
			if ( is_array( $decoded ) ) {
				return wp_parse_args( $decoded, $defaults );
			}
		}

		return $defaults;
	}

	/**
	 * Persist context.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function set( array $ctx ): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $ctx );
		}
		if ( ! headers_sent() ) {
			$payload = wp_json_encode(
				array(
					'branch_id'    => (int) ( $ctx['branch_id'] ?? 0 ),
					'table_id'     => (int) ( $ctx['table_id'] ?? 0 ),
					'table_token'  => (string) ( $ctx['table_token'] ?? '' ),
					'table_number' => (string) ( $ctx['table_number'] ?? '' ),
					'order_mode'   => (string) ( $ctx['order_mode'] ?? '' ),
				)
			);
			setcookie( 'flavor_ctx', (string) $payload, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), false );
		}
	}

	/**
	 * Copy context onto a new WC order.
	 *
	 * @param \WC_Order            $order Order.
	 * @param array<string, mixed> $data  Posted checkout data.
	 */
	public function stamp_order( $order, array $data ): void {
		unset( $data );
		$ctx = self::get();
		if ( ! empty( $ctx['branch_id'] ) ) {
			$order->update_meta_data( '_flavor_branch_id', (int) $ctx['branch_id'] );
		}
		if ( ! empty( $ctx['table_id'] ) ) {
			$order->update_meta_data( '_flavor_table_id', (int) $ctx['table_id'] );
		}
		if ( ! empty( $ctx['table_number'] ) ) {
			$order->update_meta_data( '_flavor_table_number', sanitize_text_field( (string) $ctx['table_number'] ) );
		}
		if ( ! empty( $ctx['order_mode'] ) ) {
			$order->update_meta_data( '_flavor_order_mode', sanitize_key( (string) $ctx['order_mode'] ) );
		}
		$order->update_meta_data( '_flavor_source', 'online' );
	}
}
