<?php
/**
 * Phase 3 REST: reservations, coupons, loyalty, Jalali calendar.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\Loyalty\DiscountManager;
use FlavorCore\Loyalty\PointsManager;
use FlavorCore\Support\Jalali;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy V1-only compatibility endpoints (experience layer).
 *
 * @deprecated New clients must use the modular controllers registered by
 *             RestController for both namespaces. Only routes that never
 *             existed elsewhere may stay here; registering a route that a
 *             modular controller already handles is forbidden — route
 *             uniqueness is enforced by the e2e suite.
 */
class RestExperience {

	/**
	 * Register routes.
	 */
	public function register(): void {
		$ns = FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'calendar' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/coupon',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'coupon' ),
				'permission_callback' => array( $this, 'nonce' ),
			)
		);

		register_rest_route(
			$ns,
			'/staff/customer',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'customer' ),
				'permission_callback' => array( $this, 'phone_cap' ),
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
	 * Reservation staff.
	 */
	public function staff(): bool {
		return current_user_can( 'flavor_manage_reservations' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Phone desk.
	 */
	public function phone_cap(): bool {
		return current_user_can( 'flavor_create_phone_order' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Jalali month grid.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function calendar( \WP_REST_Request $request ) {
		$today = Jalali::parse_gregorian( current_time( 'Y-m-d' ) );
		$jy    = (int) ( $request->get_param( 'jy' ) ?: $today['y'] );
		$jm    = (int) ( $request->get_param( 'jm' ) ?: $today['m'] );
		$len   = Jalali::month_length( $jy, $jm );
		$days  = array();
		for ( $d = 1; $d <= $len; $d++ ) {
			list( $gy, $gm, $gd ) = Jalali::to_gregorian( $jy, $jm, $d );
			$g = sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
			$days[] = array(
				'jd'        => $d,
				'gregorian' => $g,
				'dow'       => Jalali::iran_dow( $g ),
				'past'      => $g < current_time( 'Y-m-d' ),
			);
		}
		return rest_ensure_response(
			array(
				'jy'     => $jy,
				'jm'     => $jm,
				'month'  => Jalali::MONTHS[ $jm ] ?? '',
				'today'  => $today,
				'days'   => $days,
				'weekdays' => array_values( Jalali::WEEKDAYS ),
			)
		);
	}

	/**
	 * Apply coupon.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function coupon( \WP_REST_Request $request ) {
		$limited = \FlavorCore\Support\RateLimit::guard( 'coupon', 20, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		$body = $request->get_json_params();
		$code = is_array( $body ) ? (string) ( $body['code'] ?? '' ) : '';
		$out  = DiscountManager::apply( $code );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$response = rest_ensure_response( $out );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * Staff customer lookup.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function customer( \WP_REST_Request $request ) {
		$mobile = \FlavorCore\Support\Iran::normalize_mobile( (string) $request->get_param( 'mobile' ) );
		if ( ! $mobile ) {
			return new \WP_Error( 'flavor_mobile', __( 'شماره نامعتبر است.', 'flavor-core' ), array( 'status' => 400 ) );
		}
		$users = get_users(
			array(
				'meta_key'   => OtpAuth::META_MOBILE,
				'meta_value' => $mobile,
				'number'     => 1,
			)
		);
		if ( empty( $users ) ) {
			return rest_ensure_response(
				array(
					'found'  => false,
					'mobile' => $mobile,
				)
			);
		}
		$user   = $users[0];
		$orders = wc_get_orders(
			array(
				'customer_id' => $user->ID,
				'limit'       => 5,
			)
		);
		$hist   = array();
		foreach ( $orders as $o ) {
			$hist[] = array(
				'id'     => $o->get_id(),
				'number' => $o->get_order_number(),
				'total'  => $o->get_formatted_order_total(),
				'status' => $o->get_status(),
			);
		}
		return rest_ensure_response(
			array(
				'found'   => true,
				'id'      => $user->ID,
				'name'    => $user->display_name,
				'mobile'  => $mobile,
				'loyalty' => PointsManager::summary( $user->ID ),
				'orders'  => $hist,
			)
		);
	}
}
