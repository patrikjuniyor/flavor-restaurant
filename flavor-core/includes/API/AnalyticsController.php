<?php
/**
 * REST API: Restaurant Analytics & Reporting Controller.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Analytics\AnalyticsManager;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsController
 */
class AnalyticsController extends BaseApiController {

	/**
	 * Register routes.
	 *
	 * @param string|null $namespace Namespace override (defaults to V1).
	 */
	public function register( ?string $namespace = null ): void {
		$ns = $namespace ?: FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/analytics/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'require_analytics_access' ),
			)
		);

		register_rest_route(
			$ns,
			'/analytics/peak-hours',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_peak_hours' ),
				'permission_callback' => array( $this, 'require_analytics_access' ),
			)
		);

		register_rest_route(
			$ns,
			'/analytics/popular-dishes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_popular_dishes' ),
				'permission_callback' => array( $this, 'require_analytics_access' ),
			)
		);

		register_rest_route(
			$ns,
			'/analytics/branches-summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_branches_summary' ),
				'permission_callback' => array( $this, 'require_analytics_access' ),
			)
		);
	}

	/**
	 * Permission callback: requires user with analytics/reports capability.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function require_analytics_access( \WP_REST_Request $request ) {
		if ( current_user_can( 'flavor_view_analytics' ) || current_user_can( 'flavor_view_reports' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error(
			'flavor_forbidden',
			__( 'شما اجازه مشاهده گزارشات تحلیلی رستوران را ندارید.', 'flavor-core' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * GET /analytics/overview
	 */
	public function get_overview( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id  = $request->get_param( 'branch_id' ) ? (int) $request->get_param( 'branch_id' ) : null;
		$start_date = sanitize_text_field( (string) $request->get_param( 'start_date' ) );
		$end_date   = sanitize_text_field( (string) $request->get_param( 'end_date' ) );

		if ( $branch_id && ! Roles::can_access_branch( get_current_user_id(), $branch_id ) ) {
			return $this->respond_error( 'forbidden_branch', __( 'دسترسی به اطلاعات این شعبه محدود است.', 'flavor-core' ), 403 );
		}

		$data = AnalyticsManager::get_overview( $branch_id, $start_date, $end_date );
		return $this->respond_success( $data );
	}

	/**
	 * GET /analytics/peak-hours
	 */
	public function get_peak_hours( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id  = $request->get_param( 'branch_id' ) ? (int) $request->get_param( 'branch_id' ) : null;
		$start_date = sanitize_text_field( (string) $request->get_param( 'start_date' ) );
		$end_date   = sanitize_text_field( (string) $request->get_param( 'end_date' ) );

		$data = AnalyticsManager::get_peak_hours( $branch_id, $start_date, $end_date );
		return $this->respond_success( $data );
	}

	/**
	 * GET /analytics/popular-dishes
	 */
	public function get_popular_dishes( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id  = $request->get_param( 'branch_id' ) ? (int) $request->get_param( 'branch_id' ) : null;
		$limit      = min( 50, max( 1, (int) ( $request->get_param( 'limit' ) ?: 10 ) ) );
		$start_date = sanitize_text_field( (string) $request->get_param( 'start_date' ) );
		$end_date   = sanitize_text_field( (string) $request->get_param( 'end_date' ) );

		$data = AnalyticsManager::get_popular_dishes( $branch_id, $limit, $start_date, $end_date );
		return $this->respond_success( $data );
	}

	/**
	 * GET /analytics/branches-summary
	 */
	public function get_branches_summary( \WP_REST_Request $request ): \WP_REST_Response {
		$start_date = sanitize_text_field( (string) $request->get_param( 'start_date' ) );
		$end_date   = sanitize_text_field( (string) $request->get_param( 'end_date' ) );

		$data = AnalyticsManager::get_branches_summary( $start_date, $end_date );
		return $this->respond_success( $data );
	}
}
