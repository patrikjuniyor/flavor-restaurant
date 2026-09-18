<?php
/**
 * REST API: Reservations controller for Mobile & Web clients.
 * Handles table reservation slots calculation, Jalali calendar grids,
 * customer booking submissions, details retrieval, and reservation cancellation.
 * Fully secured against IDOR with Cryptographic Guest Tokens and Branch Staff Isolation.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Reservation\ReservationRepository;
use FlavorCore\Reservation\ReservationService;
use FlavorCore\Reservation\SlotCalculator;
use FlavorCore\Support\GuestToken;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\RateLimit;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReservationController
 */
class ReservationController extends BaseApiController {

	/**
	 * Register reservation routes for a namespace.
	 *
	 * @param string|null $namespace Namespace override (defaults to V1).
	 */
	public function register( ?string $namespace = null ): void {
		$ns = $namespace ?: FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/reservations/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_calendar' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'jy' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'jm' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/reservations/slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_slots' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'branch_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'date'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'party'     => array(
						'type'    => 'integer',
						'default' => 2,
					),
					'section'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/reservations',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'book_table' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_reservations' ),
					'permission_callback' => array( $this, 'require_authenticated' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/reservations/my',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'my_reservations' ),
				'permission_callback' => array( $this, 'require_authenticated' ),
			)
		);

		register_rest_route(
			$ns,
			'/reservations/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_reservation' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/reservations/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_reservation' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /reservations/calendar
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_calendar( \WP_REST_Request $request ): \WP_REST_Response {
		$today = Jalali::parse_gregorian( current_time( 'Y-m-d' ) );
		$jy    = (int) ( $request->get_param( 'jy' ) ?: $today['y'] );
		$jm    = (int) ( $request->get_param( 'jm' ) ?: $today['m'] );
		$len   = Jalali::month_length( $jy, $jm );

		$days = array();
		for ( $d = 1; $d <= $len; $d++ ) {
			list( $gy, $gm, $gd ) = Jalali::to_gregorian( $jy, $jm, $d );
			$g = sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
			$days[] = array(
				'day'       => $d,
				'gregorian' => $g,
				'dow'       => Jalali::iran_dow( $g ),
				'past'      => $g < current_time( 'Y-m-d' ),
				'is_today'  => $g === current_time( 'Y-m-d' ),
			);
		}

		return $this->respond_success(
			array(
				'year'       => $jy,
				'month'      => $jm,
				'month_name' => Jalali::MONTHS[ $jm ] ?? '',
				'today'      => $today,
				'days'       => $days,
				'weekdays'   => array_values( Jalali::WEEKDAYS ),
			),
			array(),
			200,
			array( 'Cache-Control' => 'public, max-age=3600' )
		);
	}

	/**
	 * GET /reservations/slots
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_slots( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id = (int) $request->get_param( 'branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}

		$date = sanitize_text_field( (string) $request->get_param( 'date' ) );
		if ( preg_match( '/^14\d{2}/', $date ) ) {
			$date = Jalali::jalali_iso_to_gregorian( $date );
		}

		$party   = max( 1, (int) $request->get_param( 'party' ) );
		$section = sanitize_key( (string) $request->get_param( 'section' ) );

		$slots = SlotCalculator::slots( $branch_id, $date, $party, $section );

		return $this->respond_success(
			array(
				'branch_id'    => $branch_id,
				'date'         => $date,
				'jalali'       => Jalali::parse_gregorian( $date ),
				'jalali_label' => Jalali::format( $date, true ),
				'slots'        => $slots,
			),
			array(),
			200,
			array( 'Cache-Control' => 'no-cache' )
		);
	}

	/**
	 * POST /reservations
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function book_table( \WP_REST_Request $request ): \WP_REST_Response {
		$rate = RateLimit::guard( 'res_book', 8, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate ) ) {
			return $this->respond_error( 'rate_limit_exceeded', $rate->get_error_message(), 429 );
		}

		$this->resolve_user( $request );
		$body = $request->get_json_params() ?: array();

		$res = ReservationService::book( $body );
		if ( is_wp_error( $res ) ) {
			return $this->respond_error( $res->get_error_code(), $res->get_error_message(), (int) ( $res->get_error_data()['status'] ?? 400 ) );
		}

		$headers = array( 'Cache-Control' => 'no-store' );
		if ( ! empty( $res['guest_token'] ) ) {
			$headers['X-Guest-Token'] = (string) $res['guest_token'];
		}

		return $this->respond_success(
			$res,
			array( 'message' => __( 'درخواست رزرو میز شما با موفقیت ثبت شد.', 'flavor-core' ) ),
			201,
			$headers
		);
	}

	/**
	 * GET /reservations/{id} (Single reservation lookup)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_reservation( \WP_REST_Request $request ): \WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$row = ReservationRepository::find( $id );

		if ( ! $row ) {
			return $this->respond_error( 'reservation_not_found', __( 'رزرو مورد نظر پیدا نشد.', 'flavor-core' ), 404 );
		}

		$auth_error = $this->guard_reservation_access( $row, $request );
		if ( is_wp_error( $auth_error ) ) {
			return $this->respond_error( $auth_error->get_error_code(), $auth_error->get_error_message(), (int) ( $auth_error->get_error_data()['status'] ?? 403 ) );
		}

		return $this->respond_success( $row );
	}

	/**
	 * GET /reservations/my
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function my_reservations( \WP_REST_Request $request ): \WP_REST_Response {
		$user = $this->resolve_user( $request );
		if ( ! $user ) {
			return $this->respond_error( 'unauthorized', __( 'وارد حساب شوید.', 'flavor-core' ), 401 );
		}

		$mobile = (string) get_user_meta( $user->ID, '_flavor_mobile', true );

		global $wpdb;
		$table = ReservationRepository::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d OR (customer_mobile = %s AND %s != '') ORDER BY reservation_date DESC, reservation_time DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user->ID,
				$mobile,
				$mobile
			),
			ARRAY_A
		);

		$items = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$items[] = ReservationRepository::hydrate( $r );
			}
		}

		return $this->respond_success( $items );
	}

	/**
	 * POST /reservations/{id}/cancel
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function cancel_reservation( \WP_REST_Request $request ): \WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$row = ReservationRepository::find( $id );

		if ( ! $row ) {
			return $this->respond_error( 'reservation_not_found', __( 'رزرو مورد نظر پیدا نشد.', 'flavor-core' ), 404 );
		}

		if ( 'cancelled' === $row['status'] ) {
			return $this->respond_success( array( 'cancelled' => true, 'message' => __( 'رزرو قبلاً لغو شده است.', 'flavor-core' ) ) );
		}

		$auth_error = $this->guard_reservation_access( $row, $request );
		if ( is_wp_error( $auth_error ) ) {
			return $this->respond_error( $auth_error->get_error_code(), $auth_error->get_error_message(), (int) ( $auth_error->get_error_data()['status'] ?? 403 ) );
		}

		ReservationRepository::set_status( $id, 'cancelled' );

		return $this->respond_success( array( 'cancelled' => true, 'message' => __( 'رزرو با موفقیت لغو شد.', 'flavor-core' ) ) );
	}

	/**
	 * GET /reservations (Staff & Admin list)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_reservations( \WP_REST_Request $request ): \WP_REST_Response {
		$perm = $this->require_capability( $request, 'flavor_manage_reservations' );
		if ( is_wp_error( $perm ) ) {
			return $this->respond_error( $perm->get_error_code(), $perm->get_error_message(), 403 );
		}

		$branch = (int) $request->get_param( 'branch_id' );
		if ( $branch > 0 ) {
			$branch_perm = $this->require_branch_access( $request, $branch );
			if ( is_wp_error( $branch_perm ) ) {
				return $this->respond_error( $branch_perm->get_error_code(), $branch_perm->get_error_message(), 403 );
			}
		}

		$date = sanitize_text_field( (string) $request->get_param( 'date' ) ) ?: current_time( 'Y-m-d' );
		$rows = ReservationRepository::for_date( $branch, $date, false );

		return $this->respond_success( $rows );
	}

	/**
	 * Guard reservation access against IDOR vulnerability.
	 *
	 * @param array<string, mixed> $row     Reservation row.
	 * @param \WP_REST_Request     $request REST Request.
	 * @return true|\WP_Error
	 */
	public function guard_reservation_access( array $row, \WP_REST_Request $request ) {
		// 1. Admin or Staff with reservation management
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$user      = $this->resolve_user( $request );
		$branch_id = (int) ( $row['branch_id'] ?? 0 );

		if ( $user && user_can( $user, 'flavor_manage_reservations' ) ) {
			if ( Roles::can_access_branch( $user->ID, $branch_id ) ) {
				return true;
			}
		}

		// 2. Authenticated customer ownership (User ID or phone)
		if ( $user && ! empty( $row['customer_id'] ) && (int) $user->ID === (int) $row['customer_id'] ) {
			return true;
		}
		if ( $user && ! empty( $row['customer_mobile'] ) ) {
			$user_mobile = (string) get_user_meta( $user->ID, OtpAuth::META_MOBILE, true );
			if ( ! empty( $user_mobile ) && Iran::normalize_mobile( $user_mobile ) === Iran::normalize_mobile( (string) $row['customer_mobile'] ) ) {
				return true;
			}
		}

		// 3. Guest Ownership Token
		$stored_guest_token = (string) ( $row['guest_token'] ?? '' );
		$req_guest_token    = GuestToken::extract_from_request( $request );

		if ( ! empty( $stored_guest_token ) && ! empty( $req_guest_token ) ) {
			if ( GuestToken::verify( $stored_guest_token, $req_guest_token ) ) {
				return true;
			}
		}

		if ( ! $user && empty( $req_guest_token ) ) {
			return new \WP_Error(
				'unauthorized',
				__( 'برای مشاهده یا مدیریت این رزرو باید وارد شوید یا توکن مهمان ارسال کنید.', 'flavor-core' ),
				array( 'status' => 401 )
			);
		}

		return new \WP_Error(
			'forbidden',
			__( 'دسترسی به این رزرو برای شما مجاز نیست.', 'flavor-core' ),
			array( 'status' => 403 )
		);
	}
}
