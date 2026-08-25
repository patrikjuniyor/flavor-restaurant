<?php
/**
 * Storefront REST: cart, checkout, OTP, zones, kitchen extras.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\Delivery\ZoneChecker;
use FlavorCore\Menu\AvailabilityManager;
use FlavorCore\Order\KitchenTicketRepository;
use FlavorCore\Order\OrderModes;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Roles;
use FlavorCore\Table\TableRepository;
use FlavorCore\WooCommerce\CartSession;
use FlavorCore\WooCommerce\CheckoutService;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestStore
 */
class RestStore {

	/**
	 * Register store routes on the shared namespace.
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
			)
		);

		register_rest_route(
			$ns,
			'/cart/add',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_cart' ),
				'permission_callback' => array( $this, 'nonce' ),
			)
		);

		register_rest_route(
			$ns,
			'/cart/item',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_cart_item' ),
				'permission_callback' => array( $this, 'nonce' ),
			)
		);

		register_rest_route(
			$ns,
			'/checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'checkout' ),
				'permission_callback' => array( $this, 'nonce' ),
			)
		);

		register_rest_route(
			$ns,
			'/checkout/options',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'checkout_options' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/auth/otp/request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'otp_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/auth/otp/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'otp_verify' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/zones/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'zone_check' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/tables',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'tables' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/kitchen/tickets/(?P<id>\d+)/item',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'kitchen_item' ),
				'permission_callback' => array( $this, 'kitchen' ),
			)
		);

		register_rest_route(
			$ns,
			'/kitchen/availability',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'availability' ),
				'permission_callback' => array( $this, 'kitchen' ),
			)
		);

		register_rest_route(
			$ns,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'me' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Store nonce.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function nonce( \WP_REST_Request $request ): bool {
		return (bool) wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
	}

	/**
	 * Kitchen cap.
	 */
	public function kitchen(): bool {
		return current_user_can( 'flavor_manage_kitchen' );
	}

	/**
	 * GET /cart
	 */
	public function get_cart() {
		$response = rest_ensure_response( CartSession::payload() );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * POST /cart/add
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function add_cart( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$key  = CartSession::add(
			(int) ( $body['product_id'] ?? 0 ),
			(int) ( $body['quantity'] ?? 1 ),
			array(
				'ids'          => isset( $body['modifier_ids'] ) ? (array) $body['modifier_ids'] : array(),
				'instructions' => (string) ( $body['instructions'] ?? '' ),
			)
		);
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$payload        = CartSession::payload();
		$payload['key'] = $key;
		$response       = rest_ensure_response( $payload );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * POST /cart/item  {key, quantity} quantity=0 removes.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function update_cart_item( \WP_REST_Request $request ) {
		CartSession::ensure();
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$key  = sanitize_text_field( (string) ( $body['key'] ?? '' ) );
		$qty  = (int) ( $body['quantity'] ?? 0 );
		if ( ! $key || ! WC()->cart ) {
			return new \WP_Error( 'flavor_cart', __( 'آیتم سبد نامعتبر است.', 'flavor-core' ), array( 'status' => 400 ) );
		}
		if ( $qty <= 0 ) {
			WC()->cart->remove_cart_item( $key );
		} else {
			WC()->cart->set_quantity( $key, min( 20, $qty ) );
		}
		$response = rest_ensure_response( CartSession::payload() );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * GET /checkout/options
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function checkout_options( \WP_REST_Request $request ) {
		$mode = sanitize_key( (string) $request->get_param( 'mode' ) );
		if ( ! in_array( $mode, array( 'dine_in', 'takeaway', 'delivery' ), true ) ) {
			$mode = (string) ( OrderModes::get()['order_mode'] ?? 'takeaway' );
		}
		$branch_id = (int) $request->get_param( 'branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = (int) ( OrderModes::get()['branch_id'] ?? 0 ) ?: BranchPostType::default_id();
		}
		return rest_ensure_response(
			array(
				'mode'      => $mode,
				'branch_id' => $branch_id,
				'methods'   => CheckoutService::methods_for_mode( $mode ),
				'tables'    => 'dine_in' === $mode ? TableRepository::for_branch( $branch_id ) : array(),
				'provinces' => \FlavorCore\Support\Iran::provinces(),
			)
		);
	}

	/**
	 * POST /checkout
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function checkout( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$out  = CheckoutService::place( $body );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$response = rest_ensure_response( $out );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * POST /auth/otp/request
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function otp_request( \WP_REST_Request $request ) {
		$limited = \FlavorCore\Support\RateLimit::guard( 'otp', 8, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$out  = OtpAuth::request( (string) ( $body['mobile'] ?? '' ) );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$response = rest_ensure_response( $out );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * POST /auth/otp/verify
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function otp_verify( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$out  = OtpAuth::verify(
			(string) ( $body['mobile'] ?? '' ),
			(string) ( $body['code'] ?? '' ),
			(string) ( $body['name'] ?? '' )
		);
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$response = rest_ensure_response( $out );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * POST /zones/check
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function zone_check( \WP_REST_Request $request ) {
		$body      = $request->get_json_params();
		$body      = is_array( $body ) ? $body : array();
		$branch_id = (int) ( $body['branch_id'] ?? 0 );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}
		$zone = ZoneChecker::match( $branch_id, $body );
		if ( ! $zone ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => __( 'خارج از محدوده ارسال.', 'flavor-core' ),
				)
			);
		}
		return rest_ensure_response(
			array(
				'ok'                 => true,
				'zone_id'            => (int) $zone['id'],
				'name'               => $zone['name'],
				'delivery_fee'       => (int) $zone['delivery_fee'],
				'delivery_fee_html'  => Currency::format( (int) $zone['delivery_fee'] ),
				'min_order'          => (int) $zone['min_order'],
				'min_order_html'     => Currency::format( (int) $zone['min_order'] ),
				'estimated_minutes'  => (int) $zone['estimated_minutes'],
			)
		);
	}

	/**
	 * GET /tables?branch_id=
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function tables( \WP_REST_Request $request ) {
		$branch_id = (int) $request->get_param( 'branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}
		$rows = array();
		foreach ( TableRepository::for_branch( $branch_id ) as $t ) {
			if ( ! (int) $t['is_active'] ) {
				continue;
			}
			$rows[] = array(
				'id'           => (int) $t['id'],
				'table_number' => $t['table_number'],
				'label'        => $t['label'],
				'capacity'     => (int) $t['capacity'],
				'section'      => $t['section'],
			);
		}
		return rest_ensure_response( $rows );
	}

	/**
	 * POST kitchen item ready.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function kitchen_item( \WP_REST_Request $request ) {
		$ticket_id = (int) $request['id'];
		$ticket    = KitchenTicketRepository::find( $ticket_id );
		if ( ! $ticket ) {
			return new \WP_Error( 'flavor_not_found', __( 'تیکت پیدا نشد.', 'flavor-core' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! Roles::can_access_branch( get_current_user_id(), (int) $ticket['branch_id'] ) ) {
			return new \WP_Error( 'flavor_forbidden', __( 'دسترسی ندارید.', 'flavor-core' ), array( 'status' => 403 ) );
		}
		$body   = $request->get_json_params();
		$item   = (int) ( $body['item_id'] ?? 0 );
		$status = sanitize_key( (string) ( $body['status'] ?? 'ready' ) );
		if ( $item ) {
			KitchenTicketRepository::set_item_status( $item, $status );
		}
		$fresh = KitchenTicketRepository::find( $ticket_id );
		return rest_ensure_response( KitchenTicketRepository::to_card( $fresh ?? $ticket ) );
	}

	/**
	 * POST availability toggle.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function availability( \WP_REST_Request $request ) {
		$body      = $request->get_json_params();
		$body      = is_array( $body ) ? $body : array();
		$branch_id = (int) ( $body['branch_id'] ?? 0 );
		$product   = (int) ( $body['product_id'] ?? 0 );
		if ( ! $branch_id || ! $product ) {
			return new \WP_Error( 'flavor_bad', __( 'شعبه و محصول لازم است.', 'flavor-core' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! Roles::can_access_branch( get_current_user_id(), $branch_id ) ) {
			return new \WP_Error( 'flavor_forbidden', __( 'دسترسی ندارید.', 'flavor-core' ), array( 'status' => 403 ) );
		}
		$available = ! empty( $body['available'] );
		$until     = ! empty( $body['until'] ) ? sanitize_text_field( (string) $body['until'] ) : null;
		AvailabilityManager::set( $branch_id, $product, $available, $until, get_current_user_id(), sanitize_text_field( (string) ( $body['reason'] ?? '' ) ) );
		return rest_ensure_response(
			array(
				'ok'           => true,
				'available'    => $available,
				'menu_version' => \FlavorCore\Support\Settings::menu_version( $branch_id ),
			)
		);
	}

	/**
	 * GET /me
	 */
	public function me() {
		if ( ! is_user_logged_in() ) {
			return rest_ensure_response( array( 'logged_in' => false ) );
		}
		$user = wp_get_current_user();
		return rest_ensure_response(
			array(
				'logged_in' => true,
				'id'        => $user->ID,
				'name'      => $user->display_name,
				'mobile'    => (string) get_user_meta( $user->ID, OtpAuth::META_MOBILE, true ),
				'loyalty'   => \FlavorCore\Loyalty\PointsManager::summary( $user->ID ),
			)
		);
	}
}
