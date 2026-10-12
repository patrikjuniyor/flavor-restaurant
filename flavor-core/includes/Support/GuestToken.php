<?php
/**
 * Guest Ownership Token Service for securing guest orders and reservations against IDOR.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class GuestToken
 */
class GuestToken {

	public const META_GUEST_TOKEN = '_flavor_guest_token';

	/**
	 * Generate a cryptographically secure 64-character hex token.
	 *
	 * @return string
	 */
	public static function generate(): string {
		// 256 bits straight from the CSPRNG. There is deliberately no
		// uniqid/microtime fallback: a token that indexes guest ORDER
		// ownership must never be derivable from wall-clock time, and
		// "CSPRNG unavailable" is a fatal server condition — failing the
		// request (500) is correct, minting a bruteable token is not.
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Compute SHA-256 hash of a token for secure database indexing.
	 *
	 * @param string $token Plain token.
	 * @return string 64-char hash.
	 */
	public static function hash( string $token ): string {
		return hash( 'sha256', trim( $token ) );
	}

	/**
	 * Constant-time string comparison.
	 *
	 * @param string $known_token  Known token stored on server.
	 * @param string $user_token   Token submitted by client.
	 * @return bool
	 */
	public static function verify( string $known_token, string $user_token ): bool {
		$known = trim( $known_token );
		$user  = trim( $user_token );

		if ( '' === $known || '' === $user ) {
			return false;
		}

		return hash_equals( $known, $user );
	}

	/**
	 * Extract guest token from request headers or query/body parameters.
	 *
	 * @param \WP_REST_Request $request REST Request.
	 * @return string
	 */
	public static function extract_from_request( \WP_REST_Request $request ): string {
		// 1. Header: X-Guest-Token
		$header = $request->get_header( 'X-Guest-Token' );
		if ( ! empty( $header ) ) {
			return sanitize_text_field( (string) $header );
		}

		// 2. Query / Body parameter: guest_token
		$param = $request->get_param( 'guest_token' );
		if ( ! empty( $param ) && is_string( $param ) ) {
			return sanitize_text_field( $param );
		}

		return '';
	}
}
