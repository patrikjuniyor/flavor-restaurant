<?php
/**
 * Transient-backed rate limiter for public REST.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class RateLimit
 */
class RateLimit {

	/**
	 * Whether the bucket still has room. Increments on success.
	 *
	 * @param string $bucket Logical name.
	 * @param int    $max    Hits allowed.
	 * @param int    $window Seconds.
	 */
	public static function allow( string $bucket, int $max, int $window ): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$key = 'flavor_rl_' . md5( $bucket . '|' . $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return false;
		}
		set_transient( $key, $n + 1, $window );
		return true;
	}

	/**
	 * WP_Error when limited.
	 *
	 * @return true|\WP_Error
	 */
	public static function guard( string $bucket, int $max, int $window ) {
		if ( self::allow( $bucket, $max, $window ) ) {
			return true;
		}
		return new \WP_Error(
			'flavor_rate',
			__( 'تعداد درخواست بیش از حد است. کمی بعد دوباره تلاش کنید.', 'flavor-core' ),
			array( 'status' => 429 )
		);
	}
}
