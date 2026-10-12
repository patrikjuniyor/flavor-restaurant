<?php
/**
 * Base REST Controller providing consistent envelopes, dual-mode auth,
 * permission checks, pagination and error formatting.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Customer\TokenService;
use FlavorCore\Support\RateLimit;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class BaseApiController
 */
abstract class BaseApiController {

	/**
	 * Standard Success Envelope response.
	 *
	 * @param mixed                $data    Payload.
	 * @param array<string, mixed> $meta    Metadata (pagination, versions, server ts).
	 * @param int                  $status  HTTP Status Code.
	 * @param array<string, string>$headers Additional headers.
	 * @return \WP_REST_Response
	 */
	public function respond_success( $data = array(), array $meta = array(), int $status = 200, array $headers = array() ): \WP_REST_Response {
		$default_meta = array(
			'timestamp' => time(),
			'server_time' => current_time( 'mysql' ),
		);

		$payload = array(
			'success' => true,
			'data'    => $data,
			'meta'    => array_merge( $default_meta, $meta ),
			'errors'  => array(),
		);

		$response = new \WP_REST_Response( $payload, $status );
		foreach ( $headers as $key => $val ) {
			$response->header( $key, $val );
		}

		return $response;
	}

	/**
	 * Standard Error Envelope response.
	 *
	 * @param string               $code    Error code.
	 * @param string               $message Localized human-readable message.
	 * @param int                  $status  HTTP Status Code.
	 * @param array<int, mixed>    $details Detailed field validation errors.
	 * @return \WP_REST_Response
	 */
	public function respond_error( string $code, string $message, int $status = 400, array $details = array() ): \WP_REST_Response {
		$error_entry = array(
			'code'    => $code,
			'message' => $message,
		);
		if ( ! empty( $details ) ) {
			$error_entry['details'] = $details;
		}

		$payload = array(
			'success' => false,
			'data'    => null,
			'meta'    => array(
				'timestamp' => time(),
			),
			'errors'  => array( $error_entry ),
		);

		return new \WP_REST_Response( $payload, $status );
	}

	/**
	 * Paginated response builder.
	 *
	 * @param array<int, mixed>    $items      Current page items.
	 * @param int                  $total      Total items count.
	 * @param int                  $page       Current page number.
	 * @param int                  $per_page   Items per page.
	 * @param array<string, mixed> $extra_meta Extra meta attributes.
	 * @return \WP_REST_Response
	 */
	public function respond_paginated( array $items, int $total, int $page, int $per_page, array $extra_meta = array() ): \WP_REST_Response {
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		$pagination_meta = array(
			'pagination' => array(
				'total'        => $total,
				'count'        => count( $items ),
				'per_page'     => $per_page,
				'current_page' => $page,
				'total_pages'  => $total_pages,
				'has_next'     => $page < $total_pages,
				'has_prev'     => $page > 1,
			),
		);

		return $this->respond_success( $items, array_merge( $pagination_meta, $extra_meta ) );
	}

	/**
	 * Resolve current authenticated user across Cookie and Bearer Token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_User|null
	 */
	public function resolve_user( \WP_REST_Request $request ): ?\WP_User {
		$auth_header = $request->get_header( 'Authorization' );
		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			$user_id = TokenService::validate( $matches[1] );
			if ( $user_id ) {
				wp_set_current_user( $user_id );
				return get_userdata( $user_id ) ?: null;
			}
		}

		if ( is_user_logged_in() ) {
			return wp_get_current_user();
		}

		return null;
	}

	/**
	 * Permission check: Authenticated user required.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function require_authenticated( \WP_REST_Request $request ) {
		$user = $this->resolve_user( $request );
		if ( ! $user || ! $user->exists() ) {
			return new \WP_Error(
				'flavor_unauthorized',
				__( 'برای انجام این عملیات باید وارد حساب کاربری خود شوید.', 'flavor-core' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}

	/**
	 * Permission check: Capability or Admin.
	 *
	 * @param \WP_REST_Request $request    Request.
	 * @param string           $capability WordPress capability string.
	 * @return true|\WP_Error
	 */
	public function require_capability( \WP_REST_Request $request, string $capability ) {
		$auth = $this->require_authenticated( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'flavor_forbidden',
				__( 'شما دسترسی لازم برای این عملیات را ندارید.', 'flavor-core' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Branch scope for the current user (R4-2).
	 *
	 * Staff must never widen a query to "all branches" by omitting
	 * branch_id; only managers of the whole platform may see aggregate
	 * cross-branch data.
	 *
	 * @param \WP_REST_Request $request Request carrying an optional branch_id.
	 * @return int|null|\WP_Error int = forced branch, null = all allowed, error = deny.
	 */
	public function resolve_branch_scope( \WP_REST_Request $request ) {
		$requested = absint( $request->get_param( 'branch_id' ) );
		$global    = current_user_can( 'manage_options' ) || current_user_can( 'flavor_manage_all_branches' );
		if ( $global ) {
			return $requested > 0 ? $requested : null;
		}
		$managed = Roles::managed_branch_ids( get_current_user_id() );
		if ( $requested > 0 ) {
			if ( in_array( $requested, $managed, true ) ) {
				return $requested;
			}
			return new \WP_Error(
				'flavor_branch_forbidden',
				__( 'دسترسی به اطلاعات این شعبه برای شما مجاز نیست.', 'flavor-core' ),
				array( 'status' => 403 )
			);
		}
		if ( 1 === count( $managed ) ) {
			return $managed[0];
		}
		return new \WP_Error(
			'flavor_branch_required',
			__( 'حساب شما به چند شعبه دسترسی دارد؛ ارسال branch_id الزامی است.', 'flavor-core' ),
			array( 'status' => 400 )
		);
	}
}
