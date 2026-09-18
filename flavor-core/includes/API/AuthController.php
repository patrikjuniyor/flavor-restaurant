<?php
/**
 * REST API: Auth controller for Mobile & Web clients.
 * Handles OTP login/registration, Bearer/Refresh token issuance & rotation,
 * user profile management, device registration, and guest cart migration on login.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\Customer\TokenService;
use FlavorCore\Database\Schema;
use FlavorCore\Loyalty\PointsManager;
use FlavorCore\Support\Iran;
use FlavorCore\Support\RateLimit;
use FlavorCore\WooCommerce\CartTokenService;

defined( 'ABSPATH' ) || exit;

/**
 * Class AuthController
 */
class AuthController extends BaseApiController {

	/**
	 * Register auth routes.
	 *
	 * @param string|null $namespace Namespace override (defaults to V1).
	 */
	public function register( ?string $namespace = null ): void {
		$ns = $namespace ?: FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/auth/otp/request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'otp_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'mobile' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/auth/otp/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'otp_verify' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'mobile' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'code'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'name'   => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'device_name' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/auth/token/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'token_refresh' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'refresh_token' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/auth/token/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'token_revoke' ),
				'permission_callback' => array( $this, 'require_authenticated' ),
				'args'                => array(
					'all_devices' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/auth/me',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'me' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_me' ),
					'permission_callback' => array( $this, 'require_authenticated' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/auth/device',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'register_device' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'unregister_device' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Request OTP SMS code.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function otp_request( \WP_REST_Request $request ): \WP_REST_Response {
		$rate = RateLimit::guard( 'otp_req', 8, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate ) ) {
			return $this->respond_error( 'rate_limit_exceeded', $rate->get_error_message(), 429 );
		}

		$mobile = sanitize_text_field( (string) $request->get_param( 'mobile' ) );
		$out    = OtpAuth::request( $mobile );
		if ( is_wp_error( $out ) ) {
			return $this->respond_error( $out->get_error_code(), $out->get_error_message(), (int) ( $out->get_error_data()['status'] ?? 400 ) );
		}

		return $this->respond_success( $out, array(), 200, array( 'Cache-Control' => 'no-store' ) );
	}

	/**
	 * Verify OTP Code and issue JWT/Bearer tokens.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function otp_verify( \WP_REST_Request $request ): \WP_REST_Response {
		$rate = RateLimit::guard( 'otp_ver', 15, 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate ) ) {
			return $this->respond_error( 'rate_limit_exceeded', $rate->get_error_message(), 429 );
		}

		$mobile      = sanitize_text_field( (string) $request->get_param( 'mobile' ) );
		$code        = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$name        = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$device_name = sanitize_text_field( (string) ( $request->get_param( 'device_name' ) ?: 'Mobile App' ) );

		$auth = OtpAuth::verify( $mobile, $code, $name );
		if ( is_wp_error( $auth ) ) {
			return $this->respond_error( $auth->get_error_code(), $auth->get_error_message(), (int) ( $auth->get_error_data()['status'] ?? 400 ) );
		}

		$user_id = (int) $auth['user_id'];
		$user    = get_userdata( $user_id );
		$tokens  = TokenService::issue( $user_id, $device_name );

		// Migrate guest cart if X-Cart-Token provided
		$cart_token = CartTokenService::extract_token( $request );
		if ( ! empty( $cart_token ) ) {
			CartTokenService::migrate_to_user( $cart_token, $user_id );
		}

		$profile = $this->format_user_profile( $user );

		$data = array(
			'user'         => $profile,
			'tokens'       => $tokens,
			'is_new_user'  => ! empty( $auth['new'] ),
		);

		return $this->respond_success( $data, array(), 200, array( 'Cache-Control' => 'no-store' ) );
	}

	/**
	 * Refresh access token using valid refresh token with rotation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function token_refresh( \WP_REST_Request $request ): \WP_REST_Response {
		$refresh_token = sanitize_text_field( (string) $request->get_param( 'refresh_token' ) );
		$tokens        = TokenService::refresh( $refresh_token );

		if ( is_wp_error( $tokens ) ) {
			return $this->respond_error( $tokens->get_error_code(), $tokens->get_error_message(), 401 );
		}

		return $this->respond_success( $tokens, array(), 200, array( 'Cache-Control' => 'no-store' ) );
	}

	/**
	 * Revoke current user session or all sessions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function token_revoke( \WP_REST_Request $request ): \WP_REST_Response {
		$user = $this->resolve_user( $request );
		if ( ! $user ) {
			return $this->respond_error( 'unauthorized', __( 'کاربر احراز هویت نشده است.', 'flavor-core' ), 401 );
		}

		$all = (bool) $request->get_param( 'all_devices' );
		if ( $all ) {
			TokenService::revoke_all_for_user( $user->ID );
		} else {
			$auth_header = $request->get_header( 'Authorization' );
			if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
				TokenService::revoke( $matches[1] );
			}
		}

		wp_logout();

		return $this->respond_success( array( 'revoked' => true ) );
	}

	/**
	 * Get profile of current logged-in user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function me( \WP_REST_Request $request ): \WP_REST_Response {
		$user = $this->resolve_user( $request );
		if ( ! $user || ! $user->exists() ) {
			return $this->respond_success(
				array(
					'logged_in' => false,
					'user'      => null,
				)
			);
		}

		$profile = $this->format_user_profile( $user );
		return $this->respond_success(
			array(
				'logged_in' => true,
				'user'      => $profile,
			),
			array(),
			200,
			array( 'Cache-Control' => 'private, no-cache' )
		);
	}

	/**
	 * Update user profile.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_me( \WP_REST_Request $request ): \WP_REST_Response {
		$user = $this->resolve_user( $request );
		if ( ! $user || ! $user->exists() ) {
			return $this->respond_error( 'unauthorized', __( 'کاربر یافت نشد.', 'flavor-core' ), 401 );
		}

		$body = $request->get_json_params() ?: array();
		$uid  = $user->ID;

		$update_data = array( 'ID' => $uid );
		if ( isset( $body['display_name'] ) && is_string( $body['display_name'] ) ) {
			$name = sanitize_text_field( $body['display_name'] );
			$update_data['display_name'] = $name;
			$update_data['nickname']     = $name;
		}

		if ( isset( $body['email'] ) && is_email( $body['email'] ) ) {
			$update_data['user_email'] = sanitize_email( $body['email'] );
		}

		if ( count( $update_data ) > 1 ) {
			$res = wp_update_user( $update_data );
			if ( is_wp_error( $res ) ) {
				return $this->respond_error( $res->get_error_code(), $res->get_error_message(), 400 );
			}
		}

		if ( isset( $body['dietary_preferences'] ) && is_array( $body['dietary_preferences'] ) ) {
			$clean_pref = array_map( 'sanitize_key', $body['dietary_preferences'] );
			update_user_meta( $uid, '_flavor_dietary_preferences', $clean_pref );
		}

		if ( isset( $body['saved_addresses'] ) && is_array( $body['saved_addresses'] ) ) {
			$clean_addresses = array();
			foreach ( $body['saved_addresses'] as $addr ) {
				if ( is_array( $addr ) && ! empty( $addr['address'] ) ) {
					$clean_addresses[] = array(
						'id'       => sanitize_text_field( (string) ( $addr['id'] ?? uniqid( 'addr_' ) ) ),
						'title'    => sanitize_text_field( (string) ( $addr['title'] ?? 'آدرس من' ) ),
						'address'  => sanitize_text_field( (string) $addr['address'] ),
						'province' => sanitize_text_field( (string) ( $addr['province'] ?? 'تهران' ) ),
						'lat'      => isset( $addr['lat'] ) ? (float) $addr['lat'] : null,
						'lng'      => isset( $addr['lng'] ) ? (float) $addr['lng'] : null,
						'unit'     => sanitize_text_field( (string) ( $addr['unit'] ?? '' ) ),
						'postal'   => sanitize_text_field( (string) ( $addr['postal'] ?? '' ) ),
					);
				}
			}
			update_user_meta( $uid, '_flavor_saved_addresses', $clean_addresses );
		}

		$fresh_user = get_userdata( $uid );
		return $this->respond_success(
			array(
				'user'    => $this->format_user_profile( $fresh_user ),
				'message' => __( 'پروفایل با موفقیت بروزرسانی شد.', 'flavor-core' ),
			)
		);
	}

	/**
	 * Register FCM / APNs device push token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function register_device( \WP_REST_Request $request ): \WP_REST_Response {
		$body         = $request->get_json_params() ?: array();
		$device_token = sanitize_text_field( (string) ( $body['device_token'] ?? '' ) );
		$platform     = sanitize_key( (string) ( $body['platform'] ?? 'android' ) );
		$app_version  = sanitize_text_field( (string) ( $body['app_version'] ?? '1.0.0' ) );

		if ( empty( $device_token ) ) {
			return $this->respond_error( 'missing_device_token', __( 'توکن دستگاه الزامی است.', 'flavor-core' ), 400 );
		}

		$user    = $this->resolve_user( $request );
		$user_id = $user ? $user->ID : null;

		global $wpdb;
		$table = Schema::table( 'flavor_device_tokens' );
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'user_id'      => $user_id,
				'device_token' => $device_token,
				'platform'     => in_array( $platform, array( 'ios', 'android', 'web' ), true ) ? $platform : 'android',
				'app_version'  => $app_version,
				'updated_at'   => $now,
				'created_at'   => $now,
			)
		);

		return $this->respond_success( array( 'registered' => true ) );
	}

	/**
	 * Unregister device push token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function unregister_device( \WP_REST_Request $request ): \WP_REST_Response {
		$body         = $request->get_json_params() ?: array();
		$device_token = sanitize_text_field( (string) ( $body['device_token'] ?? '' ) );

		if ( ! empty( $device_token ) ) {
			global $wpdb;
			$table = Schema::table( 'flavor_device_tokens' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'device_token' => $device_token ) );
		}

		return $this->respond_success( array( 'unregistered' => true ) );
	}

	/**
	 * Helper: format user profile payload.
	 *
	 * @param \WP_User $user User object.
	 * @return array<string, mixed>
	 */
	private function format_user_profile( \WP_User $user ): array {
		$uid     = $user->ID;
		$mobile  = (string) get_user_meta( $uid, OtpAuth::META_MOBILE, true );
		$loyalty = PointsManager::summary( $uid );
		$pref    = get_user_meta( $uid, '_flavor_dietary_preferences', true ) ?: array();
		$addrs   = get_user_meta( $uid, '_flavor_saved_addresses', true ) ?: array();

		return array(
			'id'                  => $uid,
			'display_name'        => $user->display_name,
			'email'               => str_ends_with( $user->user_email, '@otp.flavor.local' ) ? '' : $user->user_email,
			'mobile'              => $mobile,
			'avatar_url'          => get_avatar_url( $uid, array( 'size' => 128 ) ),
			'loyalty'             => $loyalty,
			'dietary_preferences' => (array) $pref,
			'saved_addresses'     => (array) $addrs,
			'capabilities'        => array(
				'is_admin'         => user_can( $uid, 'manage_options' ),
				'is_kitchen_staff' => user_can( $uid, 'flavor_manage_kitchen' ),
				'is_driver'        => user_can( $uid, 'flavor_driver' ),
			),
		);
	}
}
