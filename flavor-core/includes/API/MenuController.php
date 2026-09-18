<?php
/**
 * REST API: Menu, Categories, and Dishes controller.
 * Powers web, mobile app menus, item details, modifiers, and featured collections.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Database\Schema;
use FlavorCore\Menu\AvailabilityManager;
use FlavorCore\Menu\MenuScheduler;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Settings;
use FlavorCore\WooCommerce\Currency;
use FlavorCore\WooCommerce\ProductModifiers;

defined( 'ABSPATH' ) || exit;

/**
 * Class MenuController
 */
class MenuController extends BaseApiController {

	/**
	 * Register menu routes.
	 *
	 * @param string|null $namespace Namespace override (defaults to V1).
	 */
	public function register( ?string $namespace = null ): void {
		$ns = $namespace ?: FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_categories' ),
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
					'category'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'shift'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'search'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 30,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/dishes/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_dish' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'branch_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/dishes/featured',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_featured' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'branch_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'limit'     => array(
						'type'    => 'integer',
						'default' => 6,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/dishes/special-offers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_special_offers' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'branch_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'limit'     => array(
						'type'    => 'integer',
						'default' => 6,
					),
				),
			)
		);
	}

	/**
	 * GET /categories
	 *
	 * @return \WP_REST_Response
	 */
	public function get_categories(): \WP_REST_Response {
		$terms = array();
		if ( taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'orderby'    => 'menu_order',
					'order'      => 'ASC',
				)
			);
		}

		$categories = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! ( $term instanceof \WP_Term ) || 'uncategorized' === $term->slug ) {
					continue;
				}

				$thumb_id  = get_term_meta( $term->term_id, 'thumbnail_id', true );
				$image_url = $thumb_id ? wp_get_attachment_image_url( (int) $thumb_id, 'medium' ) : '';

				$categories[] = array(
					'id'          => (int) $term->term_id,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'count'       => (int) $term->count,
					'image'       => $image_url ?: '',
					'icon'        => (string) get_term_meta( $term->term_id, '_flavor_icon', true ),
				);
			}
		}

		return $this->respond_success(
			$categories,
			array( 'count' => count( $categories ) ),
			200,
			array( 'Cache-Control' => 'public, max-age=120' )
		);
	}

	/**
	 * GET /menu
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_menu( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id = (int) $request->get_param( 'branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$cat_id   = (int) $request->get_param( 'category' );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$shift    = sanitize_key( (string) $request->get_param( 'shift' ) );

		$args = array(
			'status'   => 'publish',
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'type'     => array( 'simple', 'variable' ),
		);

		if ( $cat_id > 0 ) {
			$args['category'] = array( $cat_id );
		}
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$wc_result = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : (object) array(
			'products' => array(),
			'total'    => 0,
		);

		$products = is_object( $wc_result ) && ! empty( $wc_result->products ) ? $wc_result->products : array();
		$total    = is_object( $wc_result ) ? (int) $wc_result->total : 0;

		$items = array();
		foreach ( $products as $prod ) {
			$prod_id = $prod->get_id();
			$state   = MenuScheduler::product_state( $branch_id, $prod_id );
			if ( empty( $state['visible'] ) ) {
				continue;
			}

			if ( ! empty( $shift ) ) {
				$schedules = (array) get_post_meta( $prod_id, ProductModifiers::META_SCHEDULE, true );
				if ( ! empty( $schedules ) && ! in_array( $shift, $schedules, true ) ) {
					continue;
				}
			}

			$dish    = $this->serialize_dish( $prod, $branch_id, $state );
			$items[] = $dish;
		}

		$meta = array(
			'branch_id'    => $branch_id,
			'menu_version' => Settings::menu_version( $branch_id ),
			'currency'     => array(
				'storage' => Currency::storage_unit(),
				'display' => Currency::display_unit(),
				'label'   => Currency::display_label(),
			),
		);

		$response = $this->respond_paginated( $items, $total, $page, $per_page, $meta );
		$response->header( 'Cache-Control', 'public, max-age=30' );
		return $response;
	}

	/**
	 * GET /dishes/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_dish( \WP_REST_Request $request ): \WP_REST_Response {
		$id        = (int) $request->get_param( 'id' );
		$branch_id = (int) $request->get_param( 'branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return $this->respond_error( 'dish_not_found', __( 'غذای مورد نظر یافت نشد.', 'flavor-core' ), 404 );
		}

		$state  = MenuScheduler::product_state( $branch_id, $id );
		$detail = $this->serialize_dish_detail( $product, $branch_id, $state );

		return $this->respond_success(
			$detail,
			array( 'branch_id' => $branch_id ),
			200,
			array( 'Cache-Control' => 'public, max-age=60' )
		);
	}

	/**
	 * GET /dishes/featured
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_featured( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id = (int) $request->get_param( 'branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}
		$limit = min( 20, max( 1, (int) $request->get_param( 'limit' ) ) );

		$args = array(
			'status'   => 'publish',
			'limit'    => $limit,
			'featured' => true,
		);

		$products = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();
		$items    = array();
		foreach ( $products as $prod ) {
			$state   = MenuScheduler::product_state( $branch_id, $prod->get_id() );
			$items[] = $this->serialize_dish( $prod, $branch_id, $state );
		}

		return $this->respond_success(
			$items,
			array( 'count' => count( $items ), 'branch_id' => $branch_id ),
			200,
			array( 'Cache-Control' => 'public, max-age=60' )
		);
	}

	/**
	 * GET /dishes/special-offers
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_special_offers( \WP_REST_Request $request ): \WP_REST_Response {
		$branch_id = (int) $request->get_param( 'branch_id' );
		if ( $branch_id <= 0 ) {
			$branch_id = BranchPostType::default_id();
		}
		$limit = min( 20, max( 1, (int) $request->get_param( 'limit' ) ) );

		$args = array(
			'status'  => 'publish',
			'limit'   => $limit,
			'on_sale' => true,
		);

		$products = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();
		$items    = array();
		foreach ( $products as $prod ) {
			$state   = MenuScheduler::product_state( $branch_id, $prod->get_id() );
			$items[] = $this->serialize_dish( $prod, $branch_id, $state );
		}

		return $this->respond_success(
			$items,
			array( 'count' => count( $items ), 'branch_id' => $branch_id ),
			200,
			array( 'Cache-Control' => 'public, max-age=60' )
		);
	}

	/**
	 * Serialize standard Dish Card for menus & lists.
	 *
	 * @param \WC_Product          $product   WC Product.
	 * @param int                  $branch_id Branch ID.
	 * @param array<string, mixed> $state     Scheduler state.
	 * @return array<string, mixed>
	 */
	private function serialize_dish( \WC_Product $product, int $branch_id, array $state ): array {
		$id        = $product->get_id();
		$image     = wp_get_attachment_image_url( $product->get_image_id(), 'medium' );
		$price     = (int) round( (float) $product->get_price() );
		$reg_price = (int) round( (float) $product->get_regular_price() );

		$wc_code   = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRT';
		$from_unit = 'IRR' === $wc_code ? Currency::RIAL : Currency::TOMAN;

		$stored_price = Currency::to_storage( $price, $from_unit );
		$stored_reg   = $reg_price > 0 ? Currency::to_storage( $reg_price, $from_unit ) : 0;

		$cats = array();
		$terms = get_the_terms( $id, 'product_cat' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				$cats[] = array(
					'id'   => $t->term_id,
					'name' => $t->name,
					'slug' => $t->slug,
				);
			}
		}

		$is_available = AvailabilityManager::is_available( $branch_id, $id );
		$dietary      = get_post_meta( $id, ProductModifiers::META_DIETARY, true ) ?: array();

		return array(
			'id'             => $id,
			'name'           => $product->get_name(),
			'slug'           => $product->get_slug(),
			'short_desc'     => wp_strip_all_tags( $product->get_short_description() ),
			'price'          => $stored_price,
			'price_html'     => Currency::format( $stored_price ),
			'regular_price'  => $stored_reg,
			'on_sale'        => $product->is_on_sale(),
			'discount_pct'   => ( $stored_reg > $stored_price && $stored_reg > 0 ) ? (int) round( ( 1 - ( $stored_price / $stored_reg ) ) * 100 ) : 0,
			'image'          => $image ?: '',
			'prep_time'      => (int) get_post_meta( $id, ProductModifiers::META_PREP, true ),
			'calories'       => (int) get_post_meta( $id, ProductModifiers::META_CALORIES, true ),
			'dietary'        => (array) $dietary,
			'rating_avg'     => (float) $product->get_average_rating(),
			'rating_count'   => (int) $product->get_rating_count(),
			'available'      => $is_available,
			'in_schedule'    => ! empty( $state['now'] ),
			'available_at'   => $state['next'] ?? '',
			'categories'     => $cats,
			'has_modifiers'  => ! empty( ProductModifiers::get_modifiers( $id ) ),
			'permalink'      => get_permalink( $id ),
		);
	}

	/**
	 * Serialize full Dish Detail including modifiers, gallery, reviews summary.
	 *
	 * @param \WC_Product          $product   WC Product.
	 * @param int                  $branch_id Branch ID.
	 * @param array<string, mixed> $state     Scheduler state.
	 * @return array<string, mixed>
	 */
	private function serialize_dish_detail( \WC_Product $product, int $branch_id, array $state ): array {
		$card = $this->serialize_dish( $product, $branch_id, $state );
		$id   = $product->get_id();

		$gallery = array();
		$attachment_ids = $product->get_gallery_image_ids();
		foreach ( $attachment_ids as $att_id ) {
			$url = wp_get_attachment_image_url( $att_id, 'large' );
			if ( $url ) {
				$gallery[] = $url;
			}
		}

		$raw_modifiers = ProductModifiers::get_modifiers( $id );
		$groups        = array();
		foreach ( $raw_modifiers as $mod ) {
			$type = $mod['type'] ?? 'topping';
			if ( ! isset( $groups[ $type ] ) ) {
				$groups[ $type ] = array(
					'type'       => $type,
					'title'      => ProductModifiers::type_label( $type ),
					'required'   => 'size' === $type,
					'multi'      => 'topping' === $type,
					'options'    => array(),
				);
			}

			$mod_price = (int) ( $mod['price'] ?? 0 );
			$groups[ $type ]['options'][] = array(
				'id'         => (string) $mod['id'],
				'name'       => (string) $mod['name'],
				'price'      => $mod_price,
				'price_html' => $mod_price > 0 ? '+' . Currency::format( $mod_price ) : __( 'رایگان', 'flavor-core' ),
				'is_default' => ! empty( $mod['is_default'] ),
			);
		}

		return array_merge(
			$card,
			array(
				'description'     => apply_filters( 'the_content', $product->get_description() ),
				'gallery'         => $gallery,
				'modifier_groups' => array_values( $groups ),
				'schedules'       => (array) ( get_post_meta( $id, ProductModifiers::META_SCHEDULE, true ) ?: array() ),
				'sku'             => $product->get_sku(),
			)
		);
	}
}
