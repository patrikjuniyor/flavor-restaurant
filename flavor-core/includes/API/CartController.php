<?php
/**
 * REST API: Cart controller for Mobile & Web clients.
 * Supports Bearer auth and guest sessions, item modifications, coupon codes,
 * and loyalty point redemptions.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Loyalty\DiscountManager;
use FlavorCore\Loyalty\PointsManager;
use FlavorCore\WooCommerce\CartSession;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class CartController
 */
class CartController extends BaseApiController {

	/**
	 * Register cart routes.
	 */
	public function register(): void {
		$ns = FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/cart',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_cart' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'clear_cart' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$ns,
			'/cart/items',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_item' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'quantity'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'modifier_ids' => array(
						'type'    => 'array',
						'default' => array(),
					),
					'instructions' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/cart/items/(?P<key>[a-zA-Z0-9_]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'quantity' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'remove_item' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$ns,
			'/cart/coupon',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'apply_coupon' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'code' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'remove_coupon' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$ns,
			'/cart/loyalty',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'apply_loyalty' ),
				'permission_callback' => array( $this, 'require_authenticated' ),
				'args'                => array(
					'points' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * GET /cart
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_cart( \WP_REST_Request $request ): \WP_REST_Response {
		$this->resolve_user( $request );
		CartSession::ensure();

		$payload = $this->build_cart_payload();
		return $this->respond_success( $payload, array(), 200, array( 'Cache-Control' => 'private, no-cache' ) );
	}

	/**
	 * POST /cart/items
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function add_item( \WP_REST_Request $request ): \WP_REST_Response {
		$this->resolve_user( $request );
		CartSession::ensure();

		$product_id = (int) $request->get_param( 'product_id' );
		$qty        = max( 1, min( 20, (int) $request->get_param( 'quantity' ) ) );
		$modifiers  = (array) $request->get_param( 'modifier_ids' );
		$notes      = (string) $request->get_param( 'instructions' );

		$key = CartSession::add(
			$product_id,
			$qty,
			array(
				'ids'          => $modifiers,
				'instructions' => $notes,
			)
		);

		if ( is_wp_error( $key ) ) {
			return $this->respond_error( $key->get_error_code(), $key->get_error_message(), 400 );
		}

		$payload             = $this->build_cart_payload();
		$payload['last_key'] = $key;

		return $this->respond_success( $payload, array( 'message' => __( 'آیتم به سبد اضافه شد.', 'flavor-core' ) ) );
	}

	/**
	 * PUT /cart/items/{key}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_item( \WP_REST_Request $request ): \WP_REST_Response {
		$this->resolve_user( $request );
		CartSession::ensure();

		$key = sanitize_text_field( (string) $request->get_param( 'key' ) );
		$qty = (int) $request->get_param( 'quantity' );

		if ( ! $key || ! WC()->cart || ! WC()->cart->get_cart_item( $key ) ) {
			return $this->respond_error( 'invalid_cart_item', __( 'آیتم مورد نظر در سبد خرید یافت نشد.', 'flavor-core' ), 404 );
		}

		if ( $qty <= 0 ) {
			WC()->cart->remove_cart_item( $key );
		} else {
			WC()->cart->set_quantity( $key, min( 20, $qty ) );
		}

		return $this->respond_success( $this->build_cart_payload() );
	}

	/**
	 * DELETE /cart/items/{key}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function remove_item( \WP_REST_Request $request ): \WP_REST_Response {
		$this->resolve_user( $request );
		CartSession::ensure();

		$key = sanitize_text_field( (string) $request->get_param( 'key' ) );
		if ( ! $key || ! WC()->cart || ! WC()->cart->get_cart_item( $key ) ) {
			return $this->respond_error( 'invalid_cart_item', __( 'آیتم مورد نظر در سبد خرید یافت نشد.', 'flavor-core' ), 404 );
		}

		WC()->cart->remove_cart_item( $key );
		return $this->respond_success( $this->build_cart_payload(), array( 'message' => __( 'آیتم از سبد حذف شد.', 'flavor-core' ) ) );
	}

	/**
	 * DELETE /cart
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function clear_cart( \WP_REST_Request $request ): \WP_REST_Response {
		$this->resolve_user( $request );
		CartSession::ensure();

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return $this->respond_success( $this->build_cart_payload(), array( 'message' => __( 'سبد خرید خالی شد.', 'flavor-core' ) ) );
	}

	/**
	 * POST /cart/coupon
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function apply_coupon( \WP_REST_Request $request ): \WP_REST_Response {
		$this->resolve_user( $request );
		CartSession::ensure();

		$code = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$res  = DiscountManager::apply( $code );

		if ( is_wp_error( $res ) ) {
			return $this->respond_error( $res->get_error_code(), $res->get_error_message(), 400 );
		}

		return $this->respond_success(
			array_merge( $this->build_cart_payload(), array( 'applied_coupon' => $code ) ),
			array( 'message' => __( 'کد تخفیف با موفقیت اعمال شد.', 'flavor-core' ) )
		);
	}

	/**
	 * DELETE /cart/coupon
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function remove_coupon( \WP_REST_Request $request ): \WP_REST_Response {
		$this->resolve_user( $request );
		CartSession::ensure();

		if ( WC()->cart ) {
			$applied = WC()->cart->get_applied_coupons();
			foreach ( $applied as $c ) {
				WC()->cart->remove_coupon( $c );
			}
			WC()->cart->calculate_totals();
		}

		return $this->respond_success( $this->build_cart_payload(), array( 'message' => __( 'کد تخفیف حذف شد.', 'flavor-core' ) ) );
	}

	/**
	 * POST /cart/loyalty
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function apply_loyalty( \WP_REST_Request $request ): \WP_REST_Response {
		$user = $this->resolve_user( $request );
		if ( ! $user ) {
			return $this->respond_error( 'unauthorized', __( 'وارد حساب شوید.', 'flavor-core' ), 401 );
		}

		$points = absint( $request->get_param( 'points' ) );
		$summary = PointsManager::summary( $user->ID );

		if ( $points > (int) $summary['balance'] ) {
			return $this->respond_error( 'insufficient_points', __( 'موجودی امتیاز شما کافی نیست.', 'flavor-core' ), 400 );
		}

		return $this->respond_success(
			$this->build_cart_payload(),
			array( 'message' => __( 'امتیاز باشگاه مشتریان محاسبه شد.', 'flavor-core' ) )
		);
	}

	/**
	 * Build comprehensive cart payload with storage/display currency support.
	 *
	 * @return array<string, mixed>
	 */
	private function build_cart_payload(): array {
		$raw = CartSession::payload();
		$wc_code   = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRT';
		$from_unit = 'IRR' === $wc_code ? Currency::RIAL : Currency::TOMAN;

		$subtotal_stored = Currency::to_storage( (int) round( (float) ( $raw['subtotal'] ?? 0 ) ), $from_unit );
		$total_stored    = Currency::to_storage( (int) round( (float) ( $raw['total'] ?? 0 ) ), $from_unit );

		$points_to_earn = PointsManager::estimate_points( $total_stored );

		return array(
			'items'            => $raw['items'] ?? array(),
			'count'            => (int) ( $raw['count'] ?? 0 ),
			'subtotal'         => $subtotal_stored,
			'subtotal_html'    => Currency::format( $subtotal_stored ),
			'total'            => $total_stored,
			'total_html'       => Currency::format( $total_stored ),
			'fees'             => $raw['fees'] ?? array(),
			'coupons'          => function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_applied_coupons() : array(),
			'points_to_earn'   => $points_to_earn,
			'needs_payment'    => (bool) ( $raw['needs_payment'] ?? true ),
		);
	}
}
