<?php
/**
 * Ensure WooCommerce cart/session exist during REST.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class CartSession
 */
class CartSession {

	/**
	 * Load frontend cart if missing.
	 */
	public static function ensure(): void {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return;
		}
		if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}
		}
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * Serialize the current cart for the drawer.
	 *
	 * @return array<string, mixed>
	 */
	public static function payload(): array {
		self::ensure();
		$cart = WC()->cart;
		if ( ! $cart ) {
			return array(
				'items'    => array(),
				'count'    => 0,
				'subtotal' => 0,
				'total'    => 0,
				'fees'     => array(),
			);
		}

		$items = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			$product = $item['data'] ?? null;
			$items[] = array(
				'key'          => $key,
				'product_id'   => (int) $item['product_id'],
				'name'         => $product ? $product->get_name() : '',
				'quantity'     => (int) $item['quantity'],
				'price_html'   => $product ? wc_price( $product->get_price() ) : '',
				'line_html'    => isset( $item['line_total'] ) ? wc_price( $item['line_total'] ) : '',
				'modifiers'    => $item['flavor_modifiers'] ?? array(),
				'instructions' => $item['flavor_instructions'] ?? '',
				'image'        => $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '',
			);
		}

		return array(
			'items'        => $items,
			'count'        => $cart->get_cart_contents_count(),
			'subtotal'     => (float) $cart->get_subtotal(),
			'total'        => (float) $cart->get_total( 'edit' ),
			'subtotal_html'=> wc_price( $cart->get_subtotal() ),
			'total_html'   => $cart->get_total(),
			'fees'         => array_values(
				array_map(
					static function ( $fee ) {
						return array(
							'name'  => $fee->name,
							'total' => (float) $fee->amount,
							'html'  => wc_price( $fee->amount ),
						);
					},
					$cart->get_fees()
				)
			),
			'needs_payment'=> $cart->needs_payment(),
		);
	}

	/**
	 * Add a food item with modifiers.
	 *
	 * @param int                  $product_id Product.
	 * @param int                  $qty        Qty.
	 * @param array<string, mixed> $selection  {ids:[], instructions:''}.
	 * @return string|\WP_Error Cart item key.
	 */
	public static function add( int $product_id, int $qty, array $selection ) {
		self::ensure();
		if ( ! WC()->cart ) {
			return new \WP_Error( 'flavor_no_cart', __( 'سبد در دسترس نیست.', 'flavor-core' ), array( 'status' => 500 ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() ) {
			return new \WP_Error( 'flavor_bad_product', __( 'این آیتم قابل سفارش نیست.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$qty = max( 1, min( 20, $qty ) );
		$_REQUEST['flavor_modifiers']    = array( 'ids' => $selection['ids'] ?? array() );
		$_REQUEST['flavor_instructions'] = isset( $selection['instructions'] ) ? (string) $selection['instructions'] : '';

		$key = WC()->cart->add_to_cart( $product_id, $qty );
		unset( $_REQUEST['flavor_modifiers'], $_REQUEST['flavor_instructions'] );

		if ( ! $key ) {
			return new \WP_Error( 'flavor_add_failed', __( 'افزودن به سبد انجام نشد.', 'flavor-core' ), array( 'status' => 400 ) );
		}
		return $key;
	}
}
