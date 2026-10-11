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
	 * @param string $scope  Optional extra key, e.g. a session token, for
	 *                       routes where one IP legitimately serves several
	 *                       independent clients (a shared office NAT, a
	 *                       family tablet). Never use it to loosen a
	 *                       credential endpoint: OTP and login stay IP-only.
	 */
	public static function allow( string $bucket, int $max, int $window, string $scope = '' ): bool {
		$key = self::key( $bucket, $scope );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return false;
		}
		set_transient( $key, $n + 1, $window );
		return true;
	}

	/**
	 * Empty one client's bucket.
	 *
	 * Tooling and tests only: an admin who unblocks a customer, or a suite
	 * that wants to measure the cap without inheriting the previous
	 * scenario's hits. It never raises the cap itself.
	 *
	 * @param string $bucket Logical name.
	 * @param string $scope  Same scope the guard was called with.
	 */
	public static function reset( string $bucket, string $scope = '' ): void {
		delete_transient( self::key( $bucket, $scope ) );
	}

	/**
	 * The transient name a client's bucket lives under.
	 *
	 * @param string $bucket Logical name.
	 * @param string $scope  Optional per-session scope.
	 * @return string
	 */
	private static function key( string $bucket, string $scope ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		return 'flavor_rl_' . md5( $bucket . '|' . $ip . ( '' !== $scope ? '|' . $scope : '' ) );
	}

	/**
	 * WP_Error when limited.
	 *
	 * @param string $bucket Logical name.
	 * @param int    $max    Hits allowed.
	 * @param int    $window Seconds.
	 * @param string $scope  See allow().
	 * @return true|\WP_Error
	 */
	public static function guard( string $bucket, int $max, int $window, string $scope = '' ) {
		if ( self::allow( $bucket, $max, $window, $scope ) ) {
			return true;
		}
		return new \WP_Error(
			'flavor_rate',
			__( 'تعداد درخواست بیش از حد است. کمی بعد دوباره تلاش کنید.', 'flavor-core' ),
			array( 'status' => 429 )
		);
	}
}
