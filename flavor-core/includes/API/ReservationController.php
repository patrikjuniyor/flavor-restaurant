<?php
/**
 * REST API: Reservations controller for Mobile & Web clients.
 * Handles table reservation slots calculation, Jalali calendar grids,
 * customer booking submissions, and reservation cancellation.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Reservation\ReservationRepository;
use FlavorCore\Reservation\ReservationService;
use FlavorCore\Reservation\SlotCalculator;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\RateLimit;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReservationController
 */
class ReservationController extends BaseApiController {

	/**
	 * Register reservation routes.
	 */
	public function register(): void {
		$ns = FLAVOR_CORE_REST_NAMESPACE;

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
				'year'     => $jy,
				'month'    => $jm,
				'month_name' => Jalali::MONTHS[ $jm ] ?? '',
				'today'    => $today,
				'days'     => $days,
				'weekdays' => array_values( Jalali::WEEKDAYS ),
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

		return $this->respond_success(
			$res,
			array( 'message' => __( 'درخواست رزرو میز شما با موفقیت ثبت شد.', 'flavor-core' ) ),
			201,
			array( 'Cache-Control' => 'no-store' )
		);
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

		$user = $this->resolve_user( $request );
		if ( ! current_user_can( 'manage_options' ) ) {
			if ( $user && ! empty( $row['customer_id'] ) && (int) $user->ID !== (int) $row['customer_id'] ) {
				return $this->respond_error( 'forbidden', __( 'دسترسی به این رزرو مجاز نیست.', 'flavor-core' ), 403 );
			}
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
		$date   = sanitize_text_field( (string) $request->get_param( 'date' ) ) ?: current_time( 'Y-m-d' );

		$rows = ReservationRepository::for_date( $branch, $date, false );
		return $this->respond_success( $rows );
	}
}
