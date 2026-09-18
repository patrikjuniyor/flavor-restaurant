<?php
/**
 * REST API: Orders controller for Mobile & Web clients.
 * Supports order placement, order history, live tracking, reordering, and cancellations.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\Order\KitchenTicketRepository;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Jalali;
use FlavorCore\WooCommerce\CartSession;
use FlavorCore\WooCommerce\CheckoutService;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderController
 */
class OrderController extends BaseApiController {

	/**
	 * Register order routes.
	 */
	public function register(): void {
		$ns = FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/orders',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_order' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_orders' ),
					'permission_callback' => array( $this, 'require_authenticated' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 10,
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_order' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)/track',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'track_order' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_order' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST /orders (Place new order)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_order( \WP_REST_Request $request ): \WP_REST_Response {
		$user = $this->resolve_user( $request );
		$body = $request->get_json_params() ?: array();

		if ( $user && empty( $body['mobile'] ) ) {
			$body['mobile'] = (string) get_user_meta( $user->ID, OtpAuth::META_MOBILE, true );
		}
		if ( $user && empty( $body['name'] ) ) {
			$body['name'] = $user->display_name;
		}

		$out = CheckoutService::place( $body );
		if ( is_wp_error( $out ) ) {
			return $this->respond_error( $out->get_error_code(), $out->get_error_message(), (int) ( $out->get_error_data()['status'] ?? 400 ) );
		}

		return $this->respond_success(
			$out,
			array( 'message' => __( 'سفارش با موفقیت ثبت شد.', 'flavor-core' ) ),
			201,
			array( 'Cache-Control' => 'no-store' )
		);
	}

	/**
	 * GET /orders (Customer order history)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_orders( \WP_REST_Request $request ): \WP_REST_Response {
		$user = $this->resolve_user( $request );
		if ( ! $user ) {
			return $this->respond_error( 'unauthorized', __( 'وارد حساب شوید.', 'flavor-core' ), 401 );
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ) );

		$args = array(
			'customer_id' => $user->ID,
			'page'        => $page,
			'limit'       => $per_page,
			'paginate'    => true,
			'orderby'     => 'date',
			'order'       => 'DESC',
		);

		$wc_orders = function_exists( 'wc_get_orders' ) ? wc_get_orders( $args ) : (object) array(
			'orders' => array(),
			'total'  => 0,
		);

		$orders_list = is_object( $wc_orders ) && ! empty( $wc_orders->orders ) ? $wc_orders->orders : array();
		$total       = is_object( $wc_orders ) ? (int) $wc_orders->total : 0;

		$items = array();
		foreach ( $orders_list as $o ) {
			$items[] = $this->format_order_summary( $o );
		}

		return $this->respond_paginated( $items, $total, $page, $per_page );
	}

	/**
	 * GET /orders/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_order( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;

		if ( ! $order ) {
			return $this->respond_error( 'order_not_found', __( 'سفارش مورد نظر یافت نشد.', 'flavor-core' ), 404 );
		}

		$user = $this->resolve_user( $request );
		$order_user_id = $order->get_customer_id();
		$order_mobile  = (string) $order->get_meta( '_flavor_mobile' );

		// Permission check: owner or admin or matching phone
		if ( ! current_user_can( 'manage_options' ) ) {
			if ( $user && $order_user_id && (int) $user->ID !== (int) $order_user_id ) {
				return $this->respond_error( 'forbidden', __( 'دسترسی به این سفارش مجاز نیست.', 'flavor-core' ), 403 );
			}
		}

		$detail = $this->format_order_detail( $order );
		return $this->respond_success( $detail );
	}

	/**
	 * GET /orders/{id}/track
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function track_order( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;

		if ( ! $order ) {
			return $this->respond_error( 'order_not_found', __( 'سفارش مورد نظر یافت نشد.', 'flavor-core' ), 404 );
		}

		$ticket = KitchenTicketRepository::find_by_order( $id );
		$status = $ticket ? $ticket['kitchen_status'] : 'new';

		$steps = array(
			array(
				'step'      => 'received',
				'title'     => __( 'دریافت سفارش', 'flavor-core' ),
				'completed' => true,
				'time'      => $order->get_date_created() ? $order->get_date_created()->date( 'H:i' ) : '',
			),
			array(
				'step'      => 'preparing',
				'title'     => __( 'در حال آماده‌سازی در آشپزخانه', 'flavor-core' ),
				'completed' => in_array( $status, array( 'preparing', 'ready', 'completed' ), true ),
				'time'      => $ticket && ! empty( $ticket['accepted_at'] ) ? date( 'H:i', strtotime( $ticket['accepted_at'] ) ) : '',
			),
			array(
				'step'      => 'ready',
				'title'     => 'delivery' === $order->get_meta( '_flavor_order_mode' ) ? __( 'تحویل به پیک', 'flavor-core' ) : __( 'آماده تحویل / سرو', 'flavor-core' ),
				'completed' => in_array( $status, array( 'ready', 'completed' ), true ),
				'time'      => $ticket && ! empty( $ticket['ready_at'] ) ? date( 'H:i', strtotime( $ticket['ready_at'] ) ) : '',
			),
			array(
				'step'      => 'completed',
				'title'     => __( 'تحویل داده شد', 'flavor-core' ),
				'completed' => 'completed' === $status,
				'time'      => $ticket && ! empty( $ticket['completed_at'] ) ? date( 'H:i', strtotime( $ticket['completed_at'] ) ) : '',
			),
		);

		$mode = (string) $order->get_meta( '_flavor_order_mode' );

		return $this->respond_success(
			array(
				'order_id'       => $id,
				'order_number'   => $order->get_order_number(),
				'current_status' => $status,
				'order_mode'     => $mode,
				'steps'          => $steps,
				'is_cancelled'   => 'cancelled' === $status,
				'branch_id'      => (int) $order->get_meta( '_flavor_branch_id' ),
				'estimated_time' => '30 - 45 ' . __( 'دقیقه', 'flavor-core' ),
			),
			array(),
			200,
			array( 'Cache-Control' => 'no-cache' )
		);
	}

	/**
	 * POST /orders/{id}/cancel
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function cancel_order( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;

		if ( ! $order ) {
			return $this->respond_error( 'order_not_found', __( 'سفارش مورد نظر یافت نشد.', 'flavor-core' ), 404 );
		}

		$ticket = KitchenTicketRepository::find_by_order( $id );
		if ( $ticket && in_array( $ticket['kitchen_status'], array( 'preparing', 'ready', 'completed' ), true ) ) {
			return $this->respond_error( 'cannot_cancel', __( 'سفارش در حال آماده‌سازی یا تحویل است و امکان لغو آن وجود ندارد.', 'flavor-core' ), 400 );
		}

		if ( $ticket ) {
			KitchenTicketRepository::transition( (int) $ticket['id'], 'cancelled' );
		}
		$order->update_status( 'cancelled', __( 'لغو شده توسط مشتری از طریق اپلیکیشن.', 'flavor-core' ) );

		return $this->respond_success( array( 'cancelled' => true, 'message' => __( 'سفارش لغو شد.', 'flavor-core' ) ) );
	}

	/**
	 * POST /orders/{id}/reorder
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function reorder( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;

		if ( ! $order ) {
			return $this->respond_error( 'order_not_found', __( 'سفارش مورد نظر یافت نشد.', 'flavor-core' ), 404 );
		}

		$this->resolve_user( $request );
		CartSession::ensure();

		$added_count = 0;
		foreach ( $order->get_items() as $item ) {
			if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
				continue;
			}
			$prod_id = $item->get_product_id();
			$qty     = $item->get_quantity();
			$mods    = $item->get_meta( '_flavor_modifiers' ) ?: array();
			$notes   = (string) $item->get_meta( '_flavor_instructions' );

			$mod_ids = array();
			if ( is_array( $mods ) ) {
				foreach ( $mods as $m ) {
					if ( ! empty( $m['id'] ) ) {
						$mod_ids[] = $m['id'];
					}
				}
			}

			$key = CartSession::add( $prod_id, $qty, array( 'ids' => $mod_ids, 'instructions' => $notes ) );
			if ( ! is_wp_error( $key ) ) {
				$added_count++;
			}
		}

		return $this->respond_success(
			array(
				'added_items' => $added_count,
				'cart'        => CartSession::payload(),
			),
			array( 'message' => sprintf( __( '%d قلم غذا به سبد خرید افزوده شد.', 'flavor-core' ), $added_count ) )
		);
	}

	/**
	 * Format order summary card.
	 *
	 * @param \WC_Order $order WC Order.
	 * @return array<string, mixed>
	 */
	private function format_order_summary( \WC_Order $order ): array {
		$date_created = $order->get_date_created();
		$g_date       = $date_created ? $date_created->date( 'Y-m-d H:i' ) : '';
		$jalali_date  = $g_date ? Jalali::format_datetime( $g_date ) : '';

		$total = (int) round( (float) $order->get_total() );
		$wc_code   = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRT';
		$from_unit = 'IRR' === $wc_code ? Currency::RIAL : Currency::TOMAN;
		$total_stored = Currency::to_storage( $total, $from_unit );

		$item_names = array();
		foreach ( $order->get_items() as $item ) {
			$item_names[] = $item->get_name() . ' (' . $item->get_quantity() . ')';
		}

		return array(
			'id'              => $order->get_id(),
			'order_number'    => $order->get_order_number(),
			'status'          => $order->get_status(),
			'status_label'    => wc_get_order_status_name( $order->get_status() ),
			'order_mode'      => (string) $order->get_meta( '_flavor_order_mode' ),
			'items_summary'   => implode( '، ', array_slice( $item_names, 0, 3 ) ) . ( count( $item_names ) > 3 ? '...' : '' ),
			'items_count'     => $order->get_item_count(),
			'total'           => $total_stored,
			'total_html'      => Currency::format( $total_stored ),
			'date'            => $g_date,
			'jalali_date'     => $jalali_date,
			'branch_id'       => (int) $order->get_meta( '_flavor_branch_id' ),
		);
	}

	/**
	 * Format comprehensive order details.
	 *
	 * @param \WC_Order $order WC Order.
	 * @return array<string, mixed>
	 */
	private function format_order_detail( \WC_Order $order ): array {
		$summary = $this->format_order_summary( $order );

		$items = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
				continue;
			}
			$prod = $item->get_product();
			$line_total = (int) round( (float) $item->get_total() );

			$items[] = array(
				'id'           => $item->get_id(),
				'product_id'   => $item->get_product_id(),
				'name'         => $item->get_name(),
				'quantity'     => $item->get_quantity(),
				'total'        => $line_total,
				'total_html'   => wc_price( $line_total ),
				'modifiers'    => (array) ( $item->get_meta( '_flavor_modifiers' ) ?: array() ),
				'instructions' => (string) $item->get_meta( '_flavor_instructions' ),
				'image'        => $prod ? wp_get_attachment_image_url( $prod->get_image_id(), 'thumbnail' ) : '',
			);
		}

		$ticket = KitchenTicketRepository::find_by_order( $order->get_id() );

		return array_merge(
			$summary,
			array(
				'items'            => $items,
				'payment_method'   => $order->get_payment_method_title(),
				'billing_address'  => array(
					'name'     => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'phone'    => $order->get_billing_phone(),
					'address'  => $order->get_billing_address_1(),
					'city'     => $order->get_billing_city(),
					'province' => $order->get_billing_state(),
				),
				'kitchen_status'   => $ticket ? $ticket['kitchen_status'] : 'new',
				'table_number'     => (string) $order->get_meta( '_flavor_table_number' ),
				'notes'            => $order->get_customer_note(),
			)
		);
	}
}
