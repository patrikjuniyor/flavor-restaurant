<?php
/**
 * Response security headers (R4-6 / پرامپت ۳۹).
 *
 * REST middleware: every flavor/v1 response carries nosniff + a sane
 * Referrer-Policy, and defaults to `Cache-Control: no-store` so that any
 * payload containing customer data (orders, reservations, auth tokens,
 * carts) can never be stored by a shared cache or the browser. Controllers
 * that intentionally serve public, cacheable data (menu, search) already
 * set their own `Cache-Control` and are left untouched — default-deny,
 * explicit opt-in.
 *
 * Front-end HTML: nosniff + Referrer-Policy plus a SOFT Report-Only CSP.
 * Report-Only never blocks a resource, so Elementor markup, inline theme
 * payloads and CDN assets keep working while violations stay observable
 * in the browser console. There is deliberately no report-uri sink: adding
 * one would mean a new anonymous REST route for no security gain. Site
 * owners can tune or silence the policy via the `flavor_csp_report_only`
 * filter (empty string disables the header).
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class SecurityHeaders
 */
class SecurityHeaders {

	/**
	 * Header value shared by REST and HTML responses.
	 */
	public const REFERRER_POLICY = 'strict-origin-when-cross-origin';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'rest_post_dispatch', array( $this, 'on_rest_dispatch' ), 10, 3 );
		add_filter( 'wp_headers', array( $this, 'on_wp_headers' ) );
	}

	/**
	 * Compute the hardened header map for a flavor REST response.
	 *
	 * Pure array logic so it can be asserted offline (run-pure suite).
	 *
	 * @param array<string, string> $headers   Headers already set by the controller.
	 * @param string                $route_namespace Route namespace, e.g. 'flavor/v1'.
	 * @return array<string, string>
	 */
	public static function rest_headers( array $headers, string $route_namespace ): array {
		if ( 0 !== strpos( $route_namespace, 'flavor/' ) ) {
			return $headers;
		}
		$headers['X-Content-Type-Options'] = 'nosniff';
		$headers['Referrer-Policy']        = self::REFERRER_POLICY;
		if ( ! isset( $headers['Cache-Control'] ) ) {
			// Customer-data default: never cacheable. Public cacheable
			// endpoints opt in by setting Cache-Control themselves.
			$headers['Cache-Control'] = 'no-store, must-revalidate';
		}
		return $headers;
	}

	/**
	 * Compute front-end HTML security headers.
	 *
	 * @param array<string, string> $headers Existing wp_headers map.
	 * @param mixed                 $csp     Filtered CSP policy (string) or null/false/'' to omit.
	 * @return array<string, string>
	 */
	public static function html_headers( array $headers, $csp ): array {
		$headers['X-Content-Type-Options'] = 'nosniff';
		$headers['Referrer-Policy']        = self::REFERRER_POLICY;
		if ( is_string( $csp ) && '' !== trim( $csp ) ) {
			$headers['Content-Security-Policy-Report-Only'] = $csp;
		}
		return $headers;
	}

	/**
	 * The soft, deliberately permissive report-only policy.
	 *
	 * @return string
	 */
	public static function csp_report_only(): string {
		return "default-src 'self'; "
			. "img-src 'self' data: blob: https:; "
			. "style-src 'self' 'unsafe-inline' https:; "
			. "script-src 'self' 'unsafe-inline' https:; "
			. "font-src 'self' data: https:; "
			. "connect-src 'self' https:; "
			. "media-src 'self' https: data:; "
			. "frame-src 'self'; "
			. "object-src 'none'; "
			. "base-uri 'self'; "
			. "form-action 'self'";
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
		$existing = $result->get_headers();
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$wanted = self::rest_headers( $existing, self::namespace_of( $request ) );
		foreach ( $wanted as $key => $value ) {
			if ( ( $existing[ $key ] ?? null ) !== $value ) {
				$result->header( $key, $value );
			}
		}
		return $result;
	}

	/**
	 * Front-end HTML headers (wp_headers filter).
	 *
	 * @param array<string, string> $headers Headers map.
	 * @return array<string, string>
	 */
	public function on_wp_headers( array $headers ): array {
		if ( is_admin() ) {
			// wp-admin owns its own headers (and its inline-script volume
			// would make even report-only violations pure noise).
			return $headers;
		}
		return self::html_headers( $headers, apply_filters( 'flavor_csp_report_only', self::csp_report_only() ) );
	}

	/**
	 * Best-effort namespace extraction, tolerant of stripped requests.
	 *
	 * @param mixed $request WP_REST_Request (or mock).
	 * @return string
	 */
	private static function namespace_of( $request ): string {
		if ( ! is_object( $request ) ) {
			return '';
		}
		if ( method_exists( $request, 'get_namespace' ) ) {
			return (string) $request->get_namespace();
		}
		if ( method_exists( $request, 'get_route' ) ) {
			$segments = explode( '/', trim( (string) $request->get_route(), '/' ) );
			return count( $segments ) >= 2 ? $segments[0] . '/' . $segments[1] : '';
		}
		return '';
	}
}
