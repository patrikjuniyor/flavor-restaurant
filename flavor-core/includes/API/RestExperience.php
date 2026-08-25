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
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Reservation\ReservationRepository;
use FlavorCore\Reservation\ReservationService;
use FlavorCore\Reservation\SlotCalculator;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\Roles;
use FlavorCore\WooCommerce\CartSession;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestExperience
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
			'/reservations/slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'slots' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/reservations',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'book' ),
					'permission_callback' => array( $this, 'nonce' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_admin' ),
					'permission_callback' => array( $this, 'staff' ),
				),
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
	 * GET slots.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function slots( \WP_REST_Request $request ) {
		$branch = (int) $request->get_param( 'branch_id' );
		if ( $branch <= 0 ) {
			$branch = BranchPostType::default_id();
		}
		$date = sanitize_text_field( (string) $request->get_param( 'date' ) );
		if ( preg_match( '/^14\d{2}/', $date ) ) {
			$date = Jalali::jalali_iso_to_gregorian( $date );
		}
		$party   = max( 1, (int) $request->get_param( 'party' ) );
		$section = sanitize_key( (string) $request->get_param( 'section' ) );
		return rest_ensure_response(
			array(
				'branch_id' => $branch,
				'date'      => $date,
				'jalali'    => Jalali::parse_gregorian( $date ),
				'slots'     => SlotCalculator::slots( $branch, $date, $party, $section ),
			)
		);
	}

	/**
	 * POST book.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function book( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$out  = ReservationService::book( is_array( $body ) ? $body : array() );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$response = rest_ensure_response( $out );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * GET admin list.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function list_admin( \WP_REST_Request $request ) {
		$branch = (int) $request->get_param( 'branch_id' );
		$date   = sanitize_text_field( (string) $request->get_param( 'date' ) ) ?: current_time( 'Y-m-d' );
		if ( $branch && ! current_user_can( 'manage_options' ) && ! Roles::can_access_branch( get_current_user_id(), $branch ) ) {
			return new \WP_Error( 'flavor_forbidden', __( 'دسترسی ندارید.', 'flavor-core' ), array( 'status' => 403 ) );
		}
		return rest_ensure_response( ReservationRepository::for_date( $branch, $date, false ) );
	}

	/**
	 * Apply coupon.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function coupon( \WP_REST_Request $request ) {
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
