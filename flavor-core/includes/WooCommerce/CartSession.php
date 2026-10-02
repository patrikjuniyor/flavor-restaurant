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
				// Authoritative numeric amounts, in the WooCommerce catalog unit.
				'unit_price_raw' => $product ? (float) $product->get_price() : 0,
				'line_total_raw' => (float) ( $item['line_total'] ?? 0 ),
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
			'discount_total' => method_exists( $cart, 'get_discount_total' ) ? (float) $cart->get_discount_total() : 0,
			'tax_total' => method_exists( $cart, 'get_total_tax' ) ? (float) $cart->get_total_tax() : 0,
			'shipping_total' => method_exists( $cart, 'get_shipping_total' ) ? (float) $cart->get_shipping_total() : 0,
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
	 * Atomically edit one owned line. Never delete the original before validation.
	 * Woo's key includes selection/notes: regenerate it so later adds/restores
	 * cannot merge different choices. Unrelated plugin item data is preserved.
	 *
	 * @return string|\WP_Error Updated (possibly merged) cart key.
	 */
	public static function update_selection( string $key, int $qty, array $selection ) {
		self::ensure(); $cart = WC()->cart;
		$item = $cart ? $cart->get_cart_item( $key ) : array();
		if ( ! $item ) { return new \WP_Error( 'invalid_cart_item', __( 'آیتم در سبد شما یافت نشد.', 'flavor-core' ) ); }
		$product_id = (int) $item['product_id'];
		$product = wc_get_product( ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : $product_id );
		if ( ! $product || ! $product->is_purchasable() || 'publish' !== $product->get_status() ) {
			return new \WP_Error( 'flavor_bad_product', __( 'این محصول دیگر قابل سفارش نیست؛ انتخاب قبلی شما حذف نشده است.', 'flavor-core' ) );
		}
		$ids = $selection['ids'] ?? array();
		if ( ! is_array( $ids ) ) { return new \WP_Error( 'flavor_modifiers', __( 'گزینه‌های انتخابی معتبر نیستند.', 'flavor-core' ) ); }
		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) && ! is_int( $id ) ) { return new \WP_Error( 'flavor_modifiers', __( 'شناسهٔ گزینه معتبر نیست.', 'flavor-core' ) ); }
		}
		$ids = array_values( array_unique( array_map( 'strval', $ids ) ) );
		$selected = ProductModifiers::sanitize_selection( $product_id, array( 'ids' => $ids ) );
		if ( count( $selected ) !== count( $ids ) ) {
			return new \WP_Error( 'flavor_modifiers', __( 'یکی از گزینه‌ها دیگر در منو وجود ندارد. انتخاب‌های فعلی سبد تغییر نکرده‌اند.', 'flavor-core' ) );
		}
		$types = array_count_values( array_column( $selected, 'type' ) );
		foreach ( array( 'size', 'cook', 'removal' ) as $type ) {
			if ( ( $types[ $type ] ?? 0 ) > 1 ) { return new \WP_Error( 'flavor_modifiers', __( 'در گروه‌های تک‌انتخابی فقط یک گزینه انتخاب کنید.', 'flavor-core' ) ); }
		}
		if ( in_array( 'size', array_column( ProductModifiers::get_modifiers( $product_id ), 'type' ), true ) && empty( $types['size'] ) ) {
			return new \WP_Error( 'flavor_modifiers', __( 'اندازهٔ این غذا را انتخاب کنید.', 'flavor-core' ) );
		}
		$notes = ProductModifiers::clean_instructions( (string) ( $selection['instructions'] ?? '' ) );
		$extra_data = $item;
		foreach ( array( 'key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash', 'line_tax_data', 'line_subtotal', 'line_subtotal_tax', 'line_total', 'line_tax' ) as $builtin ) { unset( $extra_data[ $builtin ] ); }
		$extra_data['flavor_modifiers'] = $selected;
		$extra_data['flavor_instructions'] = $notes;
		$extra_data['flavor_extra'] = ProductModifiers::selection_extra( $selected );
		$extra_data['unique_key'] = md5( wp_json_encode( array( $product_id, $selected, $notes ) ) );
		$new_key = $cart->generate_cart_id( $product_id, (int) ( $item['variation_id'] ?? 0 ), (array) ( $item['variation'] ?? array() ), $extra_data );
		$other = $new_key !== $key ? $cart->get_cart_item( $new_key ) : array();
		$qty = max( 1, min( 20, $qty ) );
		$merged_qty = $qty + (int) ( $other['quantity'] ?? 0 );
		if ( $merged_qty > 20 ) {
			return new \WP_Error( 'flavor_quantity_limit', __( 'این ویرایش بیش از ۲۰ واحد از یک انتخاب در سبد می‌سازد. ابتدا تعداد را کم کنید؛ سبد قبلی محفوظ است.', 'flavor-core' ) );
		}
		// Respect native stock, individually-sold products and update validation.
		$stock_id = $product->get_stock_managed_by_id(); $stock_qty = $qty;
		foreach ( $cart->get_cart() as $existing_key => $existing ) {
			if ( $existing_key !== $key && isset( $existing['data'] ) && $existing['data']->get_stock_managed_by_id() === $stock_id ) { $stock_qty += (int) $existing['quantity']; }
		}
		if ( ! $product->is_in_stock() || ! $product->has_enough_stock( $stock_qty ) || ( $product->is_sold_individually() && $stock_qty > 1 ) || ! apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, $qty ) ) {
			return new \WP_Error( 'flavor_cart_validation', __( 'این تعداد یا ویرایش توسط فروشگاه پذیرفته نشد؛ انتخاب قبلی شما محفوظ است.', 'flavor-core' ) );
		}
		$before = $cart->get_cart(); $before_totals = $cart->get_totals();
		foreach ( $before as &$saved_line ) { if ( isset( $saved_line['data'] ) && is_object( $saved_line['data'] ) ) { $saved_line['data'] = clone $saved_line['data']; } }
		unset( $saved_line );
		try {
			$updated = array_replace( $item, $extra_data );
			$updated['key'] = $new_key;
			// Fresh data also removes an old paid add-on when the new extra is zero.
			$updated['data'] = clone $product;
			$updated['quantity'] = (int) ( $other['quantity'] ?? $item['quantity'] );
			unset( $cart->cart_contents[ $key ] );
			$cart->cart_contents[ $new_key ] = $updated;
			$cart->set_quantity( $new_key, $merged_qty );
			return $new_key;
		} catch ( \Throwable $error ) {
			$cart->cart_contents = $before;
			$cart->set_totals( $before_totals );
			$cart->set_session();
			return new \WP_Error( 'flavor_cart_update', __( 'ویرایش انجام نشد. انتخاب‌های قبلی سبد محفوظ‌اند؛ دوباره تلاش کنید.', 'flavor-core' ) );
		}
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
		// ProductModifiers expects a native (slashed) WordPress request field.
		$notes = (string) ( $selection['instructions'] ?? '' );
		$_REQUEST['flavor_instructions'] = function_exists( 'wp_slash' ) ? wp_slash( $notes ) : $notes;

		$key = WC()->cart->add_to_cart( $product_id, $qty );
		unset( $_REQUEST['flavor_modifiers'], $_REQUEST['flavor_instructions'] );

		if ( ! $key ) {
			return new \WP_Error( 'flavor_add_failed', __( 'افزودن به سبد انجام نشد.', 'flavor-core' ), array( 'status' => 400 ) );
		}
		return $key;
	}
}
