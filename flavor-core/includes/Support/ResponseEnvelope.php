<?php
/**
 * Wire-level error-envelope normaliser for the flavor REST namespaces.
 *
 * BaseApiController::respond_error() is the canonical error shape:
 *
 *   { success: false, data: null, meta: { timestamp }, errors: [ { code, message, details? } ] }
 *
 * Route callbacks reach that shape directly, but two families of responses
 * are still born in WordPress's native WP_Error wire shape
 * ( { code, message, data: { status } } ):
 *
 *   1. permission_callback refusals — the WP protocol requires returning
 *      true|WP_Error there, and core serialises the WP_Error itself, so a
 *      controller can never answer with its own envelope;
 *   2. core-generated route errors (rest_invalid_param, invalid nonce,
 *      rest_no_route, …) produced before/around the callback.
 *
 * This middleware rewrites those into the canonical envelope after dispatch
 * (rest_post_dispatch), so every flavor/v1 + flavor/v2 client — the Flutter
 * app, the theme JS, kitchen dashboards — sees one error structure with the
 * same HTTP status. set_data() is used deliberately: status and headers
 * (including the SecurityHeaders middleware output) stay untouched. The
 * rewrite is idempotent, which matters because core applies the filter both
 * in serve_request() and in dispatch().
 *
 * Non-flavor namespaces are never touched: WooCommerce, core and third-party
 * routes keep the native shape their own clients expect.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class ResponseEnvelope
 */
class ResponseEnvelope {

	/**
	 * Register the middleware.
	 */
	public function hooks(): void {
		add_filter( 'rest_post_dispatch', array( $this, 'on_rest_dispatch' ), 10, 3 );
	}

	/**
	 * REST middleware entry (rest_post_dispatch, 3 args).
	 *
	 * @param mixed $result  Response (WP_REST_Response) or error/other value.
	 * @param mixed $server  WP_REST_Server instance (unused).
	 * @param mixed $request WP_REST_Request.
	 * @return mixed
	 */
	public function on_rest_dispatch( $result, $server, $request ) {
		if ( ! $result instanceof \WP_REST_Response ) {
			return $result;
		}
		if ( ! self::is_flavor_request( $request ) ) {
			return $result;
		}
		if ( $result->get_status() < 400 ) {
			return $result;
		}
		$data = $result->get_data();
		if ( ! is_array( $data ) || isset( $data['success'] ) || ! isset( $data['code'], $data['message'] ) ) {
			// Already enveloped (idempotence) or not a native error payload.
			return $result;
		}
		$result->set_data(
			self::envelope(
				(string) $data['code'],
				(string) $data['message'],
				$data['data'] ?? null
			)
		);
		return $result;
	}

	/**
	 * Canonical error envelope for a single code/message pair.
	 *
	 * Mirrors BaseApiController::respond_error() exactly; kept static so the
	 * middleware and any non-controller caller produce byte-identical shapes.
	 *
	 * @param string $code     Machine error code.
	 * @param string $message  Human-readable message.
	 * @param mixed  $raw_data Native WP_Error data; 'status' is stripped and
	 *                         the rest becomes details.
	 * @return array<string, mixed>
	 */
	public static function envelope( string $code, string $message, $raw_data = null ): array {
		$details = array();
		if ( is_array( $raw_data ) ) {
			$details = array_diff_key( $raw_data, array( 'status' => true ) );
		}
		$entry = array(
			'code'    => $code,
			'message' => $message,
		);
		if ( ! empty( $details ) ) {
			$entry['details'] = $details;
		}
		return array(
			'success' => false,
			'data'    => null,
			'meta'    => array(
				'timestamp' => time(),
			),
			'errors'  => array( $entry ),
		);
	}

	/**
	 * Whether the request targets a flavor namespace (v1 or v2).
	 *
	 * @param mixed $request WP_REST_Request (or mock).
	 * @return bool
	 */
	private static function is_flavor_request( $request ): bool {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return false;
		}
		return 0 === strpos( (string) $request->get_route(), '/flavor/' );
	}
}
