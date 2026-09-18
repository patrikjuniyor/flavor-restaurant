<?php
/**
 * REST API: Mobile Provisioning and CI/CD Build Controller.
 * Powers White-label configuration delivery, build triggers, and CI webhook callbacks.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Mobile\BuildManager;
use FlavorCore\Mobile\MobileConfigManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class MobileProvisionController
 */
class MobileProvisionController extends BaseApiController {

	/**
	 * Register routes.
	 */
	public function register(): void {
		$ns = FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/mobile/config',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_config' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_config' ),
					'permission_callback' => array( $this, 'require_admin' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mobile/builds/trigger',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'trigger_build' ),
				'permission_callback' => array( $this, 'require_admin' ),
				'args'                => array(
					'platform'    => array(
						'type'    => 'string',
						'default' => 'all',
					),
					'environment' => array(
						'type'    => 'string',
						'default' => 'prod',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mobile/builds/callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'build_callback' ),
				'permission_callback' => '__return_true', // Protected by HMAC signature header
			)
		);

		register_rest_route(
			$ns,
			'/mobile/builds',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_builds' ),
				'permission_callback' => array( $this, 'require_admin' ),
			)
		);

		register_rest_route(
			$ns,
			'/mobile/builds/(?P<uuid>[a-zA-Z0-9\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_build_single' ),
				'permission_callback' => array( $this, 'require_admin' ),
			)
		);
	}

	/**
	 * Require admin capability.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function require_admin( \WP_REST_Request $request ) {
		return $this->require_capability( $request, 'manage_options' );
	}

	/**
	 * GET /mobile/config
	 * Returns full white-label branding payload for CI or App.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_config(): \WP_REST_Response {
		$payload = MobileConfigManager::get_ci_provision_payload();
		return $this->respond_success(
			$payload,
			array( 'readiness' => MobileConfigManager::get_readiness_status() ),
			200,
			array( 'Cache-Control' => 'public, max-age=60' )
		);
	}

	/**
	 * POST /mobile/config
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_config( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params() ?: array();
		MobileConfigManager::update( $body );

		return $this->respond_success(
			MobileConfigManager::get_all(),
			array( 'message' => __( 'تنظیمات اپلیکیشن با موفقیت ذخیره شد.', 'flavor-core' ) )
		);
	}

	/**
	 * POST /mobile/builds/trigger
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function trigger_build( \WP_REST_Request $request ): \WP_REST_Response {
		$platform = sanitize_key( (string) $request->get_param( 'platform' ) );
		$env      = sanitize_key( (string) $request->get_param( 'environment' ) );

		$res = BuildManager::trigger_build( $platform, $env, get_current_user_id() );
		if ( is_wp_error( $res ) ) {
			return $this->respond_error( $res->get_error_code(), $res->get_error_message(), 400 );
		}

		return $this->respond_success(
			$res,
			array( 'message' => __( 'درخواست بیلد اپلیکیشن با موفقیت ثبت شد و به سرور کامپایل ارسال گردید.', 'flavor-core' ) ),
			201
		);
	}

	/**
	 * POST /mobile/builds/callback
	 * Handled by remote CI with HMAC signature verification.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function build_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$sig  = $request->get_header( 'X-Flavor-Signature' ) ?: '';
		$body = $request->get_json_params() ?: array();
		$uuid = sanitize_text_field( (string) ( $body['build_uuid'] ?? '' ) );

		if ( empty( $uuid ) ) {
			return $this->respond_error( 'missing_uuid', __( 'شناسه بیلد ارسال نشده است.', 'flavor-core' ), 400 );
		}

		$res = BuildManager::update_build_from_ci( $uuid, $body, $sig );
		if ( is_wp_error( $res ) ) {
			return $this->respond_error( $res->get_error_code(), $res->get_error_message(), (int) ( $res->get_error_data()['status'] ?? 400 ) );
		}

		return $this->respond_success( array( 'updated' => true ) );
	}

	/**
	 * GET /mobile/builds
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_builds( \WP_REST_Request $request ): \WP_REST_Response {
		$limit  = min( 50, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$page   = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$offset = ( $page - 1 ) * $limit;

		$history = BuildManager::get_history( $limit, $offset );
		return $this->respond_success( $history );
	}

	/**
	 * GET /mobile/builds/{uuid}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_build_single( \WP_REST_Request $request ): \WP_REST_Response {
		$uuid = sanitize_text_field( (string) $request->get_param( 'uuid' ) );
		$row  = BuildManager::get_build_by_uuid( $uuid );

		if ( ! $row ) {
			return $this->respond_error( 'build_not_found', __( 'اطلاعات بیلد مورد نظر یافت نشد.', 'flavor-core' ), 404 );
		}

		return $this->respond_success( $row );
	}
}
