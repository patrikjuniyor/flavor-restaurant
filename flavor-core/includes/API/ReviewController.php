<?php
/**
 * REST API: Reviews and Rating controller for Dishes and Restaurant.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\Database\Schema;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\RateLimit;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewController
 */
class ReviewController extends BaseApiController {

	/**
	 * Register review routes.
	 *
	 * @param string|null $namespace Namespace override (defaults to V1).
	 */
	public function register( ?string $namespace = null ): void {
		$ns = $namespace ?: FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/dishes/(?P<id>\d+)/reviews',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_dish_reviews' ),
					'permission_callback' => '__return_true',
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
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'submit_dish_review' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'rating'  => array(
							'type'     => 'integer',
							'required' => true,
						),
						'comment' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'name'    => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/reviews/recent',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_recent_reviews' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 6,
					),
				),
			)
		);
	}

	/**
	 * GET /dishes/{id}/reviews
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_dish_reviews( \WP_REST_Request $request ): \WP_REST_Response {
		$dish_id  = (int) $request->get_param( 'id' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		global $wpdb;
		$table = Schema::table( 'flavor_reviews' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND is_approved = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$dish_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$avg_rating = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$table} WHERE product_id = %d AND is_approved = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$dish_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d AND is_approved = 1 ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$dish_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$items = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$items[] = $this->format_review( $r );
			}
		}

		$meta = array(
			'dish_id'      => $dish_id,
			'rating_avg'   => round( $avg_rating, 1 ),
			'rating_count' => $total,
		);

		return $this->respond_paginated( $items, $total, $page, $per_page, $meta );
	}

	/**
	 * POST /dishes/{id}/reviews
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function submit_dish_review( \WP_REST_Request $request ): \WP_REST_Response {
		$rate = RateLimit::guard( 'review_sub', 5, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate ) ) {
			return $this->respond_error( 'rate_limit_exceeded', $rate->get_error_message(), 429 );
		}

		$dish_id = (int) $request->get_param( 'id' );
		$rating  = min( 5, max( 1, (int) $request->get_param( 'rating' ) ) );
		$comment = sanitize_textarea_field( (string) $request->get_param( 'comment' ) );

		if ( empty( $comment ) ) {
			return $this->respond_error( 'empty_comment', __( 'لطفاً متن نظر خود را وارد کنید.', 'flavor-core' ), 400 );
		}

		$user        = $this->resolve_user( $request );
		$user_id     = $user ? $user->ID : null;
		$author_name = $user ? $user->display_name : sanitize_text_field( (string) ( $request->get_param( 'name' ) ?: 'مشتری' ) );

		// Check if verified buyer
		$is_verified = false;
		if ( $user_id ) {
			$is_verified = wc_customer_bought_product( $user->user_email, $user_id, $dish_id );
		}

		global $wpdb;
		$table = Schema::table( 'flavor_reviews' );
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'product_id'   => $dish_id,
				'user_id'      => $user_id,
				'author_name'  => $author_name,
				'rating'       => $rating,
				'comment'      => $comment,
				'is_verified'  => $is_verified ? 1 : 0,
				'is_approved'  => 1, // default auto-approve
				'created_at'   => $now,
			)
		);

		return $this->respond_success(
			array(
				'id'          => (int) $wpdb->insert_id,
				'is_verified' => $is_verified,
			),
			array( 'message' => __( 'دیدگاه شما با موفقیت ثبت شد.', 'flavor-core' ), 'status' => 201 ),
			201
		);
	}

	/**
	 * GET /reviews/recent
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_recent_reviews( \WP_REST_Request $request ): \WP_REST_Response {
		$limit = min( 20, max( 1, (int) $request->get_param( 'limit' ) ) );

		global $wpdb;
		$table = Schema::table( 'flavor_reviews' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_approved = 1 AND rating >= 4 ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		$items = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$item = $this->format_review( $r );
				$prod = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $r['product_id'] ) : null;
				if ( $prod ) {
					$item['dish_name']  = $prod->get_name();
					$item['dish_image'] = wp_get_attachment_image_url( $prod->get_image_id(), 'thumbnail' ) ?: '';
				}
				$items[] = $item;
			}
		}

		return $this->respond_success(
			$items,
			array( 'count' => count( $items ) ),
			200,
			array( 'Cache-Control' => 'public, max-age=120' )
		);
	}

	/**
	 * Format single review.
	 *
	 * @param array<string, mixed> $r Raw review row.
	 * @return array<string, mixed>
	 */
	private function format_review( array $r ): array {
		$created = (string) ( $r['created_at'] ?? '' );
		return array(
			'id'          => (int) $r['id'],
			'product_id'  => (int) $r['product_id'],
			'author_name' => (string) $r['author_name'],
			'rating'      => (int) $r['rating'],
			'comment'     => (string) $r['comment'],
			'is_verified' => ! empty( $r['is_verified'] ),
			'date'        => $created,
			'jalali_date' => $created ? Jalali::format_datetime( $created ) : '',
		);
	}
}
