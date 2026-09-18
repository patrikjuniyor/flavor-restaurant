<?php
/**
 * REST API: Branches controller.
 * Serves branch metadata, dining tables, delivery zones, and shift schedules.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Delivery\ZoneRepository;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Table\TableRepository;
use FlavorCore\Support\Settings;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class BranchController
 */
class BranchController extends BaseApiController {

	/**
	 * Register branch routes.
	 */
	public function register(): void {
		$ns = FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/branches',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_branches' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/branches/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_branch' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/branches/(?P<id>\d+)/tables',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_tables' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/branches/(?P<id>\d+)/zones',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_zones' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/branches/(?P<id>\d+)/schedule',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_schedule' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /branches
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_branches( \WP_REST_Request $request ): \WP_REST_Response {
		$ids = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$items = array();
		foreach ( $ids as $id ) {
			$branch = $this->format_branch_card( (int) $id );
			if ( $branch ) {
				$items[] = $branch;
			}
		}

		$meta = array(
			'count'      => count( $items ),
			'default_id' => BranchPostType::default_id(),
		);

		return $this->respond_success(
			$items,
			$meta,
			200,
			array( 'Cache-Control' => 'public, max-age=60' )
		);
	}

	/**
	 * GET /branches/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_branch( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		if ( $id <= 0 ) {
			$id = BranchPostType::default_id();
		}

		$data = $this->format_branch_detail( $id );
		if ( ! $data ) {
			return $this->respond_error( 'branch_not_found', __( 'شعبه مورد نظر یافت نشد.', 'flavor-core' ), 404 );
		}

		return $this->respond_success(
			$data,
			array( 'menu_version' => Settings::menu_version( $id ) ),
			200,
			array( 'Cache-Control' => 'public, max-age=60' )
		);
	}

	/**
	 * GET /branches/{id}/tables
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_tables( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id = (int) $request->get_param( 'id' );
		$tables    = TableRepository::for_branch( $branch_id );

		$active = array();
		foreach ( $tables as $table ) {
			if ( empty( $table['is_active'] ) ) {
				continue;
			}
			$active[] = array(
				'id'           => (int) $table['id'],
				'table_number' => (string) $table['table_number'],
				'label'        => (string) ( $table['label'] ?? '' ),
				'capacity'     => (int) $table['capacity'],
				'section'      => (string) ( $table['section'] ?? 'indoor' ),
				'qr_token'     => (string) $table['qr_token'],
				'qr_url'       => TableRepository::public_url( $table ),
			);
		}

		return $this->respond_success(
			$active,
			array( 'branch_id' => $branch_id, 'count' => count( $active ) ),
			200,
			array( 'Cache-Control' => 'public, max-age=120' )
		);
	}

	/**
	 * GET /branches/{id}/zones
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_zones( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id = (int) $request->get_param( 'id' );
		$zones     = ZoneRepository::for_branch( $branch_id, true );

		$formatted = array();
		foreach ( $zones as $zone ) {
			$fee = (int) $zone['delivery_fee'];
			$min = (int) $zone['min_order'];

			$formatted[] = array(
				'id'                => (int) $zone['id'],
				'name'              => $zone['name'],
				'zone_type'         => $zone['zone_type'],
				'radius_km'         => $zone['radius_km'],
				'neighborhoods'     => (array) $zone['neighborhoods'],
				'delivery_fee'      => $fee,
				'delivery_fee_html' => Currency::format( $fee ),
				'min_order'         => $min,
				'min_order_html'    => Currency::format( $min ),
				'estimated_minutes' => (int) $zone['estimated_minutes'],
			);
		}

		return $this->respond_success(
			$formatted,
			array( 'branch_id' => $branch_id, 'count' => count( $formatted ) ),
			200,
			array( 'Cache-Control' => 'public, max-age=120' )
		);
	}

	/**
	 * GET /branches/{id}/schedule
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_schedule( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id = (int) $request->get_param( 'id' );
		$shifts    = \FlavorCore\Menu\ScheduleAdmin::for_branch( $branch_id );

		return $this->respond_success(
			$shifts,
			array( 'branch_id' => $branch_id ),
			200,
			array( 'Cache-Control' => 'public, max-age=120' )
		);
	}

	/**
	 * Helper: format branch card.
	 *
	 * @param int $branch_id Branch ID.
	 * @return array<string, mixed>|null
	 */
	private function format_branch_card( int $branch_id ): ?array {
		$row = BranchPostType::to_array( $branch_id );
		if ( ! $row ) {
			return null;
		}

		$thumbnail = get_the_post_thumbnail_url( $branch_id, 'medium' ) ?: '';

		return array_merge(
			$row,
			array(
				'thumbnail' => $thumbnail,
				'is_open'   => true,
			)
		);
	}

	/**
	 * Helper: format full branch detail.
	 *
	 * @param int $branch_id Branch ID.
	 * @return array<string, mixed>|null
	 */
	private function format_branch_detail( int $branch_id ): ?array {
		$post = get_post( $branch_id );
		if ( ! $post || BranchPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$card  = $this->format_branch_card( $branch_id );
		$zones = ZoneRepository::for_branch( $branch_id, true );

		return array_merge(
			(array) $card,
			array(
				'description'  => apply_filters( 'the_content', $post->post_content ),
				'zones_count'  => count( $zones ),
				'opening_hours' => array(
					'regular' => '11:30 - 23:30',
					'weekend' => '12:00 - 00:00',
				),
			)
		);
	}
}
