<?php
/**
 * REST API: flavor/v1
 *
 * Route classes:
 *  - public + rate-limit: menu, branches
 *  - cookie nonce: context
 *  - capability: kitchen
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

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
class RestController {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register routes.
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
			'/menu',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_menu' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'branch_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'search'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'category'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

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
		return current_user_can( 'flavor_manage_kitchen' );
	}

	/**
	 * GET /branches
	 *
	 * @return \WP_REST_Response
	 */
	public function get_branches() {
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
		$out = array();
		foreach ( $ids as $id ) {
			$row = BranchPostType::to_array( (int) $id );
			if ( $row ) {
				$out[] = $row;
			}
		}
		return rest_ensure_response( $out );
	}

	/**
	 * GET /branches/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_branch( \WP_REST_Request $request ) {
		$row = BranchPostType::to_array( (int) $request['id'] );
		if ( ! $row ) {
			return new \WP_Error( 'flavor_not_found', __( 'شعبه پیدا نشد.', 'flavor-core' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	/**
	 * GET /menu
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_menu( \WP_REST_Request $request ) {
		$branch_id = (int) $request->get_param( 'branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}

		$args = array(
			'status'   => 'publish',
			'limit'    => min( 50, max( 1, (int) $request->get_param( 'per_page' ) ) ),
			'page'     => max( 1, (int) $request->get_param( 'page' ) ),
			'paginate' => true,
			'type'     => 'simple',
		);

		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( $search ) {
			$args['s'] = $search;
		}
		$cat = (int) $request->get_param( 'category' );
		if ( $cat ) {
			$args['category'] = array( $cat );
		}

		$result = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : (object) array(
			'products' => array(),
			'total'    => 0,
		);

		$items = array();
		if ( is_object( $result ) && ! empty( $result->products ) ) {
			foreach ( $result->products as $product ) {
				$items[] = $this->serialize_product( $product, $branch_id );
			}
		}

		$response = rest_ensure_response(
			array(
				'branch_id'    => $branch_id,
				'menu_version' => Settings::menu_version( $branch_id ),
				'currency'     => array(
					'storage' => Currency::storage_unit(),
					'display' => Currency::display_unit(),
					'label'   => Currency::display_label(),
				),
				'items'        => $items,
				'total'        => is_object( $result ) ? (int) $result->total : 0,
				'page'         => (int) $args['page'],
			)
		);

		$response->header( 'Cache-Control', 'public, max-age=30' );
		return $response;
	}

	/**
	 * Product → menu card.
	 *
	 * @param \WC_Product $product   Product.
	 * @param int         $branch_id Branch.
	 * @return array<string, mixed>
	 */
	private function serialize_product( $product, int $branch_id ): array {
		$id    = $product->get_id();
		$image = wp_get_attachment_image_url( $product->get_image_id(), 'medium' );
		$price = (int) round( (float) $product->get_price() );

		// Catalog price is in WC currency. Convert to storage then format.
		$wc_code   = strtoupper( (string) get_woocommerce_currency() );
		$from_unit = 'IRR' === $wc_code ? Currency::RIAL : Currency::TOMAN;
		$stored    = Currency::to_storage( $price, $from_unit );

		return array(
			'id'          => $id,
			'name'        => $product->get_name(),
			'slug'        => $product->get_slug(),
			'short'       => wp_strip_all_tags( $product->get_short_description() ),
			'price'       => $stored,
			'price_html'  => Currency::format( $stored ),
			'image'       => $image ?: '',
			'prep_time'   => (int) get_post_meta( $id, ProductModifiers::META_PREP, true ),
			'calories'    => (int) get_post_meta( $id, ProductModifiers::META_CALORIES, true ),
			'dietary'     => get_post_meta( $id, ProductModifiers::META_DIETARY, true ) ?: array(),
			'schedule'    => get_post_meta( $id, ProductModifiers::META_SCHEDULE, true ) ?: array(),
			'modifiers'   => ProductModifiers::get_modifiers( $id ),
			'permalink'   => get_permalink( $id ),
			'branch_id'   => $branch_id,
			'available'   => true,
		);
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
