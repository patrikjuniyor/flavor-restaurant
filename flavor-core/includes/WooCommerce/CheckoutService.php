<?php
/**
 * Place an order through official WooCommerce checkout + gateway APIs.
 *
 * The UI is custom (cart drawer / mobile app). The money path is standard WooCommerce.
 * Secures guest orders with cryptographic guest tokens to prevent IDOR.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce;

use FlavorCore\Delivery\ZoneChecker;
use FlavorCore\Menu\AvailabilityManager;
use FlavorCore\Menu\MenuScheduler;
use FlavorCore\Order\KitchenTicketSync;
use FlavorCore\Order\OrderModes;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\GuestToken;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Settings;
use FlavorCore\Table\TableRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutService
 */
class CheckoutService {

	/**
	 * How long a replay is treated as the same checkout attempt for
	 * automatically derived idempotency keys (double-click / retry dedupe).
	 */
	public const AUTO_IDEMPOTENCY_WINDOW = 120;

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
	 * Guarantees:
	 *  - The guest cart (cart_token) is deleted only after the gateway confirmed
	 *    success and the order is fully persisted.
	 *  - The same idempotency key never creates two orders.
	 *  - Branch, table, zone and product availability are revalidated server-side
	 *    before any order is created.
	 *
	 * @param array<string, mixed> $payload Posted JSON.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function place( array $payload ) {
		// Restore guest cart if cart token provided and session empty.
		$cart_token = sanitize_text_field( (string) ( $payload['cart_token'] ?? '' ) );
		if ( ! empty( $cart_token ) && ( ! WC()->cart || WC()->cart->is_empty() ) ) {
			CartTokenService::restore_into_session( $cart_token );
		}

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

		// Delivery always needs a concrete, valid branch; other modes validate
		// the branch when one was provided (backward compatible with branch-less
		// classic flows).
		if ( 'delivery' === $mode && $branch_id <= 0 ) {
			return new \WP_Error( 'flavor_branch', __( 'شعبه برای ارسال انتخاب نشده است.', 'flavor-core' ), array( 'status' => 400 ) );
		}
		if ( $branch_id > 0 && ! self::is_valid_branch( $branch_id ) ) {
			return new \WP_Error( 'flavor_branch', __( 'شعبه انتخاب‌شده معتبر نیست.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		// Table resolution: strict for dine-in, best-effort for other modes.
		$table_id     = (int) ( $payload['table_id'] ?? $ctx['table_id'] ?? 0 );
		$table_number = sanitize_text_field( (string) ( $payload['table_number'] ?? $ctx['table_number'] ?? '' ) );
		if ( 'dine_in' === $mode ) {
			$table = self::resolve_table( $branch_id, $table_id, $table_number );
			if ( is_wp_error( $table ) ) {
				return $table;
			}
			if ( ! $table ) {
				return new \WP_Error( 'flavor_table', __( 'برای سفارش حضوری، میز معتبر را انتخاب کنید.', 'flavor-core' ), array( 'status' => 400 ) );
			}
			$table_id     = (int) $table['id'];
			$table_number = (string) $table['table_number'];
		} else {
			$table = self::resolve_table( $branch_id, $table_id, $table_number );
			if ( is_wp_error( $table ) ) {
				// Non dine-in flows tolerate a missing table; never persist an
				// unverified reference though.
				$table_id     = 0;
				$table_number = '';
			} elseif ( $table ) {
				$table_id     = (int) $table['id'];
				$table_number = (string) $table['table_number'];
			} else {
				$table_id     = 0;
				$table_number = '';
			}
		}

		$zone    = null;
		$address = isset( $payload['address'] ) && is_array( $payload['address'] ) ? $payload['address'] : array();
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
		}

		// Idempotency: an explicit client key never creates two orders. When the
		// client sends no key, one is derived from the cart/user/mode so rapid
		// double-submits (double tap, network retry) within a short window are
		// deduplicated as well.
		$idempotency_key = self::sanitize_idempotency_key( (string) ( $payload['idempotency_key'] ?? '' ) );
		$is_auto_key     = '' === $idempotency_key;
		if ( $is_auto_key ) {
			$idempotency_key = 'auto_' . self::cart_fingerprint( $cart, $cart_token, $mode, $branch_id, $table_id );
		}
		$previous = self::find_by_idempotency_key( $idempotency_key );
		if ( $previous && ( ! $is_auto_key || self::within_auto_window( $previous ) ) ) {
			$verified = self::can_replay( $previous, $cart_token );
			if ( ! $verified ) {
				return new \WP_Error(
					'flavor_idempotency_conflict',
					__( 'این سفارش قبلاً با نشانی دیگری ثبت شده است.', 'flavor-core' ),
					array( 'status' => 409 )
				);
			}
			return self::replay_response( $previous, true );
		}

		// Revalidate every line against the server-side catalog before money is
		// involved (availability, menu schedule, price, modifiers).
		$invalid = self::revalidate_cart( $cart, $branch_id );
		if ( is_wp_error( $invalid ) ) {
			return $invalid;
		}

		if ( 'delivery' === $mode && $zone ) {
			self::apply_delivery_fee( $zone );
		}

		$ctx['branch_id']    = $branch_id;
		$ctx['table_id']     = $table_id;
		$ctx['table_number'] = $table_number;
		$ctx['order_mode']   = $mode;
		OrderModes::set( $ctx );

		$method = sanitize_key( (string) ( $payload['payment_method'] ?? 'flavor_pay_at_counter' ) );

		// Gateway must exist and be available BEFORE any order row is created.
		$gateways_obj = WC()->payment_gateways();
		$gateways     = $gateways_obj ? $gateways_obj->get_available_payment_gateways() : array();
		if ( empty( $gateways[ $method ] ) || 'yes' !== (string) ( $gateways[ $method ]->enabled ?? 'yes' ) ) {
			return new \WP_Error( 'flavor_gateway', __( 'درگاه پرداخت پیدا نشد.', 'flavor-core' ), array( 'status' => 400 ) );
		}
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

		// Race fix: WC_Checkout::create_order() fires
		// woocommerce_checkout_order_processed inside itself, but our flavor
		// meta (branch/table/mode/...) is only attached after the call returns.
		// Suspend the legacy hook listener while we create the order; the
		// kitchen ticket is materialized explicitly below, once meta is saved.
		KitchenTicketSync::suspend( true );
		try {
			$order_id = $checkout->create_order( $posted );
		} finally {
			KitchenTicketSync::suspend( false );
		}
		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'flavor_order', __( 'ساخت سفارش ووکامرس ناموفق بود.', 'flavor-core' ), array( 'status' => 500 ) );
		}

		// Generate Guest Token if order placed by unauthenticated guest.
		$guest_token = '';
		if ( ! $order->get_customer_id() || 0 === (int) $order->get_customer_id() ) {
			$guest_token = GuestToken::generate();
			$order->update_meta_data( GuestToken::META_GUEST_TOKEN, $guest_token );
		}

		$order->set_payment_method( $method );
		$order->update_meta_data( '_flavor_branch_id', $branch_id );
		$order->update_meta_data( '_flavor_table_id', $table_id );
		$order->update_meta_data( '_flavor_table_number', $table_number );
		$order->update_meta_data( '_flavor_order_mode', $mode );
		$order->update_meta_data( '_flavor_mobile', $mobile );
		$source = sanitize_key( (string) ( $payload['source'] ?? 'online' ) );
		if ( ! in_array( $source, array( 'online', 'phone', 'walk_in' ), true ) ) {
			$source = 'online';
		}
		$order->update_meta_data( '_flavor_source', $source );
		if ( $zone ) {
			$order->update_meta_data( '_flavor_zone_id', (int) $zone['id'] );
			$order->update_meta_data( '_flavor_zone_name', (string) $zone['name'] );
		}
		if ( ! in_array( $method, KitchenTicketSync::OFFLINE_METHODS, true ) ) {
			$order->update_meta_data( '_flavor_awaiting_online', 'yes' );
		}

		// Idempotency + lifecycle bookkeeping (must be persisted before the
		// ticket snapshot reads the order).
		$order->update_meta_data( '_flavor_idempotency_key', $idempotency_key );
		$order->update_meta_data( '_flavor_placed_at', time() );
		if ( '' !== $cart_token ) {
			// Store only a hash — enough to bind replays to the same cart owner.
			$order->update_meta_data( '_flavor_cart_token_hash', hash( 'sha256', $cart_token ) );
		}
		$order->save();

		try {
			$result = $gateways[ $method ]->process_payment( $order_id );
		} catch ( \Throwable $e ) {
			$result = null;
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'Flavor checkout gateway exception: ' . $e->getMessage(),
					array( 'source' => 'flavor-checkout' )
				);
			}
		}

		if ( ! is_array( $result ) || 'success' !== ( $result['result'] ?? '' ) ) {
			// Failed payment: keep the cart (session + stored guest cart) so the
			// customer can retry with another method. The order stays persisted
			// for audit/repayment; replaying the same idempotency key returns
			// this order instead of creating a duplicate.
			$order->update_meta_data( '_flavor_payment_ok', 'no' );
			$order->save();
			$message = is_array( $result ) && ! empty( $result['messages'] ) ? wp_strip_all_tags( (string) $result['messages'] ) : __( 'پرداخت آغاز نشد.', 'flavor-core' );
			return new \WP_Error(
				'flavor_pay_failed',
				$message,
				array(
					'status'      => 400,
					'recoverable' => true,
					'order_id'    => (int) $order_id,
				)
			);
		}

		// Payment confirmed: persist success bookkeeping before any side effects.
		$order->update_meta_data( '_flavor_payment_ok', 'yes' );
		if ( ! empty( $result['redirect'] ) && is_string( $result['redirect'] ) ) {
			$order->update_meta_data( '_flavor_payment_redirect', (string) $result['redirect'] );
		}
		$order->save();

		// Offline methods are settled money-wise: create the kitchen ticket now,
		// with all flavor metadata already persisted on the order. Online
		// methods create their ticket on woocommerce_payment_complete.
		if ( in_array( $method, KitchenTicketSync::OFFLINE_METHODS, true ) ) {
			( new KitchenTicketSync() )->ensure_ticket( $order );
		}

		// The gateway confirmed and the order is fully persisted — only now the
		// stored guest cart may be discarded.
		if ( ! empty( $cart_token ) ) {
			CartTokenService::delete_cart( $cart_token );
		}

		$out = array(
			'ok'           => true,
			'order_id'     => (int) $order_id,
			'order_number' => $order->get_order_number(),
			'redirect'     => $result['redirect'] ?? '',
			'payment'      => $method,
			'mode'         => $mode,
		);

		if ( ! empty( $guest_token ) ) {
			$out['guest_token'] = $guest_token;
		}

		return $out;
	}

	/**
	 * Branch must exist, be a branch post and be published.
	 */
	private static function is_valid_branch( int $branch_id ): bool {
		$post = get_post( $branch_id );
		if ( ! $post ) {
			return false;
		}
		if ( BranchPostType::POST_TYPE !== get_post_type( $branch_id ) ) {
			return false;
		}
		return 'publish' === get_post_status( $branch_id );
	}

	/**
	 * Resolve + validate a table against the selected branch.
	 *
	 * @param int    $branch_id    Branch.
	 * @param int    $table_id     Requested table id (0 = by number).
	 * @param string $table_number Requested table number (optional).
	 * @return array<string, mixed>|\WP_Error|null Table row, error when an
	 *                                             explicit reference is invalid,
	 *                                             null when nothing was requested.
	 */
	private static function resolve_table( int $branch_id, int $table_id, string $table_number ) {
		if ( $table_id > 0 ) {
			$row = TableRepository::find( $table_id );
			if ( ! $row ) {
				return new \WP_Error( 'flavor_table', __( 'میز پیدا نشد.', 'flavor-core' ), array( 'status' => 400 ) );
			}
			if ( $branch_id > 0 && (int) $row['branch_id'] !== $branch_id ) {
				return new \WP_Error( 'flavor_table', __( 'این میز متعلق به شعبه انتخاب‌شده نیست.', 'flavor-core' ), array( 'status' => 400 ) );
			}
			if ( empty( $row['is_active'] ) ) {
				return new \WP_Error( 'flavor_table', __( 'این میز فعلاً غیرفعال است.', 'flavor-core' ), array( 'status' => 400 ) );
			}
			return $row;
		}

		if ( '' !== $table_number && $branch_id > 0 ) {
			foreach ( TableRepository::for_branch( $branch_id ) as $t ) {
				if ( (string) $t['table_number'] === $table_number ) {
					if ( empty( $t['is_active'] ) ) {
						return new \WP_Error( 'flavor_table', __( 'این میز فعلاً غیرفعال است.', 'flavor-core' ), array( 'status' => 400 ) );
					}
					return $t;
				}
			}
			return new \WP_Error( 'flavor_table', __( 'شماره میز واردشده برای این شعبه معتبر نیست.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		if ( '' !== $table_number ) {
			// No branch context: an arbitrary number cannot be verified.
			return new \WP_Error( 'flavor_table', __( 'شماره میز بدون شعبه معتبر قابل بررسی نیست.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		return null;
	}

	/**
	 * Server-side revalidation of cart lines: availability, menu schedule,
	 * price and modifier selection.
	 *
	 * @param \WC_Cart $cart      Cart.
	 * @param int      $branch_id Branch.
	 * @return true|\WP_Error
	 */
	private static function revalidate_cart( $cart, int $branch_id ) {
		foreach ( (array) $cart->get_cart() as $cart_item ) {
			$product_id = (int) ( $cart_item['product_id'] ?? 0 );
			$product    = $cart_item['data'] ?? ( $product_id ? wc_get_product( $product_id ) : null );
			if ( $product_id <= 0 || ! $product || ! is_object( $product ) ) {
				return new \WP_Error( 'flavor_unavailable', __( 'یکی از آیتم‌های سبد دیگر وجود ندارد.', 'flavor-core' ), array( 'status' => 400 ) );
			}
			$label = method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '#' . $product_id;
			if ( ( method_exists( $product, 'is_purchasable' ) && ! $product->is_purchasable() )
				|| ( method_exists( $product, 'get_status' ) && 'publish' !== $product->get_status() ) ) {
				return new \WP_Error(
					'flavor_unavailable',
					/* translators: product name */
					sprintf( __( '«%s» دیگر قابل سفارش نیست.', 'flavor-core' ), $label ),
					array( 'status' => 400 )
				);
			}
			if ( ! AvailabilityManager::is_available( $branch_id, $product_id ) ) {
				return new \WP_Error(
					'flavor_unavailable',
					/* translators: product name */
					sprintf( __( '«%s» در این شعبه موقتاً ناموجود است.', 'flavor-core' ), $label ),
					array( 'status' => 400 )
				);
			}
			$state = MenuScheduler::product_state( $branch_id, $product_id );
			if ( ! empty( $state ) && empty( $state['visible'] ) ) {
				return new \WP_Error(
					'flavor_unavailable',
					/* translators: product name */
					sprintf( __( '«%s» در این بازه زمانی سرو نمی‌شود.', 'flavor-core' ), $label ),
					array( 'status' => 400 )
				);
			}

			// Price sanity: the price must come from the current product catalog,
			// never from client input. Modifier selections are re-sanitized so
			// removed options cannot slip a false price into the order.
			$current_price = (float) $product->get_price();
			if ( $current_price <= 0 ) {
				return new \WP_Error(
					'flavor_price_changed',
					/* translators: product name */
					sprintf( __( 'قیمت «%s» معتبر نیست؛ سبد را به‌روزرسانی کنید.', 'flavor-core' ), $label ),
					array( 'status' => 400 )
				);
			}

			$selected_modifiers = is_array( $cart_item['flavor_modifiers'] ?? null ) ? (array) $cart_item['flavor_modifiers'] : array();
			$modifier_ids       = is_array( $selected_modifiers['ids'] ?? null ) ? array_map( 'strval', $selected_modifiers['ids'] ) : array();
			if ( $modifier_ids ) {
				$sanitized = ProductModifiers::sanitize_selection( $product_id, array( 'ids' => $modifier_ids ) );
				if ( count( $sanitized ) !== count( array_unique( $modifier_ids ) ) ) {
					return new \WP_Error(
						'flavor_modifiers',
						/* translators: product name */
						sprintf( __( 'گزینه‌های انتخابی «%s» دیگر معتبر نیستند؛ آیتم را دوباره انتخاب کنید.', 'flavor-core' ), $label ),
						array( 'status' => 400 )
					);
				}
			}

			// Drift detection for plain lines (without modifiers the server can
			// derive the expected line total exactly): a stored total that no
			// longer matches the catalog price means the cart is stale.
			if ( ! $modifier_ids && isset( $cart_item['line_total'] ) ) {
				$qty      = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );
				$expected = $qty * $current_price;
				$actual   = (float) $cart_item['line_total'];
				if ( abs( $expected - $actual ) > max( 1.0, $expected * 0.01 ) ) {
					return new \WP_Error(
						'flavor_price_changed',
						/* translators: product name */
						sprintf( __( 'قیمت «%s» تغییر کرده است؛ سبد را به‌روزرسانی کنید.', 'flavor-core' ), $label ),
						array( 'status' => 400 )
					);
				}
			}
		}
		return true;
	}

	/**
	 * Normalize a client supplied idempotency key.
	 */
	private static function sanitize_idempotency_key( string $raw ): string {
		$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', $raw );
		return substr( (string) $key, 0, 64 );
	}

	/**
	 * Fingerprint of the current checkout attempt for auto idempotency.
	 *
	 * @param \WC_Cart $cart       Cart.
	 * @param string   $cart_token Guest cart token (may be empty).
	 * @param string   $mode       Order mode.
	 * @param int      $branch_id  Branch.
	 * @param int      $table_id   Table.
	 */
	private static function cart_fingerprint( $cart, string $cart_token, string $mode, int $branch_id, int $table_id ): string {
		$items = array();
		foreach ( (array) $cart->get_cart() as $cart_item ) {
			$mods = is_array( $cart_item['flavor_modifiers'] ?? null ) ? (array) ( $cart_item['flavor_modifiers']['ids'] ?? array() ) : array();
			$ids  = array_map( 'strval', $mods );
			sort( $ids );
			$items[] = array(
				'p' => (int) ( $cart_item['product_id'] ?? 0 ),
				'q' => (int) ( $cart_item['quantity'] ?? 0 ),
				'm' => $ids,
			);
		}
		usort(
			$items,
			static function ( array $a, array $b ): int {
				return $a['p'] <=> $b['p'];
			}
		);
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'u'  => get_current_user_id(),
					'ct' => '' !== $cart_token ? hash( 'sha256', $cart_token ) : '',
					'md' => $mode,
					'b'  => $branch_id,
					't'  => $table_id,
					'i'  => $items,
				)
			)
		);
	}

	/**
	 * Most recent order carrying this idempotency key (HPOS-safe lookup).
	 *
	 * @param string $key Key.
	 * @return \WC_Order|null
	 */
	private static function find_by_idempotency_key( string $key ) {
		if ( '' === $key || ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}
		$orders = wc_get_orders(
			array(
				'meta_key'   => '_flavor_idempotency_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'limit'      => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);
		if ( ! is_array( $orders ) || empty( $orders[0] ) || ! is_object( $orders[0] ) ) {
			return null;
		}
		return $orders[0];
	}

	/**
	 * Auto-derived keys only dedupe within a short window — the same cart
	 * placed again minutes later is a legitimate new order.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function within_auto_window( $order ): bool {
		$placed_at = (int) $order->get_meta( '_flavor_placed_at' );
		if ( $placed_at <= 0 ) {
			return true;
		}
		return ( time() - $placed_at ) <= self::AUTO_IDEMPOTENCY_WINDOW;
	}

	/**
	 * A replay may only be answered to the same cart/customer that placed the
	 * original order (keys are not a capability).
	 *
	 * @param \WC_Order $order      Order.
	 * @param string    $cart_token Plain guest cart token from this request.
	 */
	private static function can_replay( $order, string $cart_token ): bool {
		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id > 0 ) {
			return get_current_user_id() === $customer_id;
		}
		$stored = (string) $order->get_meta( '_flavor_cart_token_hash' );
		if ( '' !== $stored ) {
			return '' !== $cart_token && hash_equals( $stored, hash( 'sha256', $cart_token ) );
		}
		return true;
	}

	/**
	 * Response for a replayed (already created) order.
	 *
	 * @param \WC_Order $order    Order.
	 * @param bool      $verified Binding was verified, guest token may be exposed.
	 * @return array<string, mixed>
	 */
	private static function replay_response( $order, bool $verified ): array {
		$paid   = 'yes' === (string) $order->get_meta( '_flavor_payment_ok' );
		$status = (string) $order->get_status();
		// Legacy orders predate the bookkeeping flag: offline methods that are no
		// longer pending are considered settled.
		if ( 'yes' !== (string) $order->get_meta( '_flavor_payment_ok' )
			&& 'no' !== (string) $order->get_meta( '_flavor_payment_ok' )
			&& in_array( (string) $order->get_payment_method(), KitchenTicketSync::OFFLINE_METHODS, true )
			&& ! in_array( $status, array( 'pending', 'failed', 'cancelled' ), true ) ) {
			$paid = true;
		}

		$out = array(
			'ok'           => $paid,
			'replay'       => true,
			'order_id'     => (int) $order->get_id(),
			'order_number' => $order->get_order_number(),
			'redirect'     => $paid ? (string) ( $order->get_meta( '_flavor_payment_redirect' ) ?: '' ) : '',
			'payment'      => (string) $order->get_payment_method(),
			'mode'         => (string) ( $order->get_meta( '_flavor_order_mode' ) ?: '' ),
		);
		if ( ! $paid ) {
			$out['payment_status'] = 'failed';
			$out['recoverable']    = true;
		}
		if ( $verified ) {
			$guest_token = (string) $order->get_meta( GuestToken::META_GUEST_TOKEN );
			if ( '' !== $guest_token ) {
				$out['guest_token'] = $guest_token;
			}
		}
		return $out;
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
