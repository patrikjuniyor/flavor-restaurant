<?php
/**
 * Place an order through official WooCommerce checkout + gateway APIs.
 *
 * The UI is custom (cart drawer). The money path is not.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce;

use FlavorCore\Delivery\ZoneChecker;
use FlavorCore\Order\OrderModes;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Settings;
use FlavorCore\Table\TableRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutService
 */
class CheckoutService {

	/**
	 * Available payment methods for the current mode.
	 *
	 * @param string $mode dine_in|takeaway|delivery.
	 * @return array<int, array<string, string>>
	 */
	public static function methods_for_mode( string $mode ): array {
		CartSession::ensure();
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
		$out      = array();
		foreach ( $gateways as $id => $gw ) {
			if ( ! $gw || 'yes' !== $gw->enabled ) {
				continue;
			}
			if ( 'dine_in' === $mode && in_array( $id, array( 'flavor_cod', 'flavor_card_on_delivery' ), true ) ) {
				continue;
			}
			if ( 'takeaway' === $mode && in_array( $id, array( 'flavor_cod', 'flavor_card_on_delivery' ), true ) ) {
				continue;
			}
			if ( 'delivery' === $mode && 'flavor_pay_at_counter' === $id ) {
				continue;
			}
			$out[] = array(
				'id'          => $id,
				'title'       => $gw->get_title(),
				'description' => wp_strip_all_tags( (string) $gw->get_description() ),
			);
		}
		return $out;
	}

	/**
	 * Create a WC order and run process_payment().
	 *
	 * @param array<string, mixed> $payload Posted JSON.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function place( array $payload ) {
		CartSession::ensure();
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return new \WP_Error( 'flavor_empty_cart', __( 'سبد خالی است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$mode = sanitize_key( (string) ( $payload['order_mode'] ?? '' ) );
		if ( ! in_array( $mode, array( 'dine_in', 'takeaway', 'delivery' ), true ) ) {
			return new \WP_Error( 'flavor_mode', __( 'حالت سفارش را انتخاب کنید.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$mobile = Iran::normalize_mobile( (string) ( $payload['mobile'] ?? '' ) );
		$name   = sanitize_text_field( (string) ( $payload['name'] ?? '' ) );
		if ( '' === $mobile ) {
			return new \WP_Error( 'flavor_mobile', __( 'شماره موبایل برای پیگیری سفارش لازم است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$ctx       = OrderModes::get();
		$branch_id = (int) ( $payload['branch_id'] ?? $ctx['branch_id'] ?? 0 );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}

		$table_id     = (int) ( $payload['table_id'] ?? $ctx['table_id'] ?? 0 );
		$table_number = sanitize_text_field( (string) ( $payload['table_number'] ?? $ctx['table_number'] ?? '' ) );
		if ( 'dine_in' === $mode && ! $table_id && $table_number && $branch_id ) {
			foreach ( TableRepository::for_branch( $branch_id ) as $t ) {
				if ( (string) $t['table_number'] === $table_number ) {
					$table_id = (int) $t['id'];
					break;
				}
			}
		}

		$zone     = null;
		$address  = isset( $payload['address'] ) && is_array( $payload['address'] ) ? $payload['address'] : array();
		if ( 'delivery' === $mode ) {
			$zone = ZoneChecker::match( $branch_id, $address );
			if ( ! $zone ) {
				return new \WP_Error( 'flavor_zone', __( 'این نشانی در محدوده ارسال این شعبه نیست.', 'flavor-core' ), array( 'status' => 400 ) );
			}
			$subtotal_storage = self::cart_subtotal_storage();
			if ( ! ZoneChecker::meets_minimum( $zone, $subtotal_storage ) ) {
				return new \WP_Error(
					'flavor_min_order',
					sprintf(
						/* translators: money */
						__( 'حداقل سفارش این منطقه %s است.', 'flavor-core' ),
						Currency::format( (int) $zone['min_order'] )
					),
					array( 'status' => 400 )
				);
			}
			self::apply_delivery_fee( $zone );
		}

		$ctx['branch_id']    = $branch_id;
		$ctx['table_id']     = $table_id;
		$ctx['table_number'] = $table_number;
		$ctx['order_mode']   = $mode;
		OrderModes::set( $ctx );

		$method = sanitize_key( (string) ( $payload['payment_method'] ?? 'flavor_pay_at_counter' ) );
		$allowed = wp_list_pluck( self::methods_for_mode( $mode ), 'id' );
		if ( ! in_array( $method, $allowed, true ) ) {
			return new \WP_Error( 'flavor_pay', __( 'روش پرداخت برای این حالت سفارش مجاز نیست.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$posted = array(
			'billing_first_name' => $name ?: $mobile,
			'billing_last_name'  => '',
			'billing_phone'      => $mobile,
			'billing_email'      => $mobile . '@otp.flavor.local',
			'billing_country'    => 'IR',
			'billing_state'      => sanitize_text_field( (string) ( $address['province'] ?? '' ) ),
			'billing_city'       => sanitize_text_field( (string) ( $address['city'] ?? '' ) ),
			'billing_address_1'  => sanitize_text_field( (string) ( $address['line'] ?? $address['neighborhood'] ?? '' ) ),
			'billing_postcode'   => sanitize_text_field( (string) ( $address['postal_code'] ?? '' ) ),
			'payment_method'     => $method,
			'order_comments'     => sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
		);

		// Mirror into $_POST so WC_Checkout and Iranian gateways see classic fields.
		foreach ( $posted as $k => $v ) {
			$_POST[ $k ] = $v;
		}

		if ( ! is_user_logged_in() && 'yes' !== Settings::get( 'guest_checkout', 'yes' ) ) {
			return new \WP_Error( 'flavor_auth', __( 'برای ثبت سفارش وارد شوید.', 'flavor-core' ), array( 'status' => 401 ) );
		}

		$checkout = WC()->checkout();
		$order_id = $checkout->create_order( $posted );
		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'flavor_order', __( 'ساخت سفارش ووکامرس ناموفق بود.', 'flavor-core' ), array( 'status' => 500 ) );
		}

		$order->set_payment_method( $method );
		$order->update_meta_data( '_flavor_branch_id', $branch_id );
		$order->update_meta_data( '_flavor_table_id', $table_id );
		$order->update_meta_data( '_flavor_table_number', $table_number );
		$order->update_meta_data( '_flavor_order_mode', $mode );
		$order->update_meta_data( '_flavor_mobile', $mobile );
		$order->update_meta_data( '_flavor_source', 'online' );
		if ( $zone ) {
			$order->update_meta_data( '_flavor_zone_id', (int) $zone['id'] );
			$order->update_meta_data( '_flavor_zone_name', (string) $zone['name'] );
		}
		if ( ! in_array( $method, array( 'flavor_pay_at_counter', 'flavor_cod', 'flavor_card_on_delivery', 'cod' ), true ) ) {
			$order->update_meta_data( '_flavor_awaiting_online', 'yes' );
		}
		$order->save();

		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( empty( $gateways[ $method ] ) ) {
			return new \WP_Error( 'flavor_gateway', __( 'درگاه پرداخت پیدا نشد.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$result = $gateways[ $method ]->process_payment( $order_id );
		if ( ! is_array( $result ) || 'success' !== ( $result['result'] ?? '' ) ) {
			$message = is_array( $result ) && ! empty( $result['messages'] ) ? wp_strip_all_tags( (string) $result['messages'] ) : __( 'پرداخت آغاز نشد.', 'flavor-core' );
			return new \WP_Error( 'flavor_pay_failed', $message, array( 'status' => 400 ) );
		}

		return array(
			'ok'           => true,
			'order_id'     => (int) $order_id,
			'order_number' => $order->get_order_number(),
			'redirect'     => $result['redirect'] ?? '',
			'payment'      => $method,
			'mode'         => $mode,
		);
	}

	/**
	 * Cart subtotal converted to storage units.
	 */
	private static function cart_subtotal_storage(): int {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return 0;
		}
		$wc_code   = strtoupper( (string) get_woocommerce_currency() );
		$from_unit = 'IRR' === $wc_code ? Currency::RIAL : Currency::TOMAN;
		return Currency::to_storage( (int) round( (float) $cart->get_subtotal() ), $from_unit );
	}

	/**
	 * Add / replace a cart fee for the delivery zone.
	 *
	 * @param array<string, mixed> $zone Zone.
	 */
	private static function apply_delivery_fee( array $zone ): void {
		$fee_storage = ZoneChecker::fee( $zone );
		if ( $fee_storage <= 0 ) {
			return;
		}
		$wc_amount = ProductModifiers::storage_to_wc( $fee_storage );
		$cart      = WC()->cart;
		if ( ! $cart ) {
			return;
		}
		$cart->add_fee( __( 'هزینه ارسال', 'flavor-core' ), $wc_amount, false );
		$cart->calculate_totals();
	}
}
