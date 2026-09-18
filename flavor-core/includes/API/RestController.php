<?php
/**
 * REST API: Master Controller for flavor/v1 and flavor/v2 namespaces.
 * Bootstraps modular REST API controllers powering Web, Android, iOS, and POS clients.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Menu\AvailabilityManager;
use FlavorCore\Order\KitchenTicketRepository;
use FlavorCore\Order\OrderModes;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Roles;
use FlavorCore\Support\Settings;
use FlavorCore\WooCommerce\Currency;
use FlavorCore\WooCommerce\ProductModifiers;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestController
 */
class RestController extends BaseApiController {

	/**
	 * Supported REST namespaces.
	 *
	 * @return string[]
	 */
	public static function namespaces(): array {
		return array(
			FLAVOR_CORE_REST_NAMESPACE,
			FLAVOR_CORE_REST_V2_NAMESPACE,
		);
	}

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register all modular API routes across V1 and V2 namespaces.
	 */
	public function register(): void {
		$auth_ctrl      = new AuthController();
		$branch_ctrl    = new BranchController();
		$menu_ctrl      = new MenuController();
		$cart_ctrl      = new CartController();
		$order_ctrl     = new OrderController();
		$res_ctrl       = new ReservationController();
		$review_ctrl    = new ReviewController();
		$settings_ctrl  = new SettingsController();
		$mobile_ctrl    = new MobileProvisionController();
		$analytics_ctrl = new AnalyticsController();
		$webhook_ctrl   = new WebhookController();
		$obs_ctrl       = new ObservabilityController();

		foreach ( self::namespaces() as $ns ) {
			// Register modular controllers for namespace
			$auth_ctrl->register( $ns );
			$branch_ctrl->register( $ns );
			$menu_ctrl->register( $ns );
			$cart_ctrl->register( $ns );
			$order_ctrl->register( $ns );
			$res_ctrl->register( $ns );
			$review_ctrl->register( $ns );
			$settings_ctrl->register( $ns );
			$mobile_ctrl->register( $ns );
			$analytics_ctrl->register( $ns );
			$webhook_ctrl->register( $ns );
			$obs_ctrl->register( $ns );

			// Context management
			register_rest_route(
				$ns,
				'/context',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_context' ),
						'permission_callback' => '__return_true',
					),
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'set_context' ),
						'permission_callback' => array( $this, 'require_store_nonce' ),
					),
				)
			);

			// Kitchen Display System endpoints
			register_rest_route(
				$ns,
				'/kitchen/tickets',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'kitchen_list' ),
					'permission_callback' => array( $this, 'require_kitchen' ),
				)
			);

			register_rest_route(
				$ns,
				'/kitchen/tickets/(?P<id>\d+)/status',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'kitchen_status' ),
					'permission_callback' => array( $this, 'require_kitchen' ),
				)
			);
		}

		// Backward-compatible V1 sub-routers
		( new RestStore() )->register();
		( new RestExperience() )->register();
	}

	/**
	 * Cookie nonce for storefront mutations (wp_rest).
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function require_store_nonce( \WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return (bool) wp_verify_nonce( (string) $nonce, 'wp_rest' );
	}

	/**
	 * Kitchen capability.
	 */
	public function require_kitchen(): bool {
		return current_user_can( 'flavor_manage_kitchen' ) || current_user_can( 'manage_options' );
	}

	/**
	 * GET /context
	 */
	public function get_context() {
		return rest_ensure_response( OrderModes::get() );
	}

	/**
	 * POST /context
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function set_context( \WP_REST_Request $request ) {
		$ctx  = OrderModes::get();
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		if ( isset( $body['branch_id'] ) ) {
			$ctx['branch_id'] = absint( $body['branch_id'] );
		}
		if ( isset( $body['order_mode'] ) && in_array( $body['order_mode'], array( 'dine_in', 'takeaway', 'delivery' ), true ) ) {
			$ctx['order_mode'] = $body['order_mode'];
		}
		if ( isset( $body['table_id'] ) ) {
			$ctx['table_id'] = absint( $body['table_id'] );
		}
		if ( isset( $body['table_number'] ) ) {
			$ctx['table_number'] = sanitize_text_field( (string) $body['table_number'] );
		}
		OrderModes::set( $ctx );
		return rest_ensure_response( $ctx );
	}

	/**
	 * GET /kitchen/tickets
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function kitchen_list( \WP_REST_Request $request ) {
		$branch_id = absint( $request->get_param( 'branch_id' ) );
		if ( ! $branch_id ) {
			$branch_id = BranchPostType::default_id();
		}
		if ( ! current_user_can( 'manage_options' ) && ! Roles::can_access_branch( get_current_user_id(), $branch_id ) ) {
			return new \WP_Error( 'flavor_forbidden', __( 'این شعبه در دسترس شما نیست.', 'flavor-core' ), array( 'status' => 403 ) );
		}

		$status = $request->get_param( 'status' );
		$status = is_string( $status ) && $status ? sanitize_key( $status ) : null;

		$rows = KitchenTicketRepository::for_kitchen( $branch_id, $status );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = KitchenTicketRepository::to_card( $row );
		}

		$response = rest_ensure_response(
			array(
				'branch_id' => $branch_id,
				'tickets'   => $out,
				'server_ts' => time(),
			)
		);
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * POST /kitchen/tickets/{id}/status
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function kitchen_status( \WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$body   = $request->get_json_params();
		$status = is_array( $body ) && isset( $body['status'] ) ? sanitize_key( (string) $body['status'] ) : '';

		$ticket = KitchenTicketRepository::find( $id );
		if ( ! $ticket ) {
			return new \WP_Error( 'flavor_not_found', __( 'تیکت پیدا نشد.', 'flavor-core' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! Roles::can_access_branch( get_current_user_id(), (int) $ticket['branch_id'] ) ) {
			return new \WP_Error( 'flavor_forbidden', __( 'دسترسی ندارید.', 'flavor-core' ), array( 'status' => 403 ) );
		}

		$ok = KitchenTicketRepository::transition( $id, $status );
		if ( ! $ok ) {
			return new \WP_Error( 'flavor_bad_transition', __( 'این تغییر وضعیت مجاز نیست.', 'flavor-core' ), array( 'status' => 409 ) );
		}

		$fresh = KitchenTicketRepository::find( $id );
		return rest_ensure_response( KitchenTicketRepository::to_card( $fresh ?? $ticket ) );
	}
}
