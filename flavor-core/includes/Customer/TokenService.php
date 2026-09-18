<?php
/**
 * Stateless Bearer and Refresh Token Manager for Mobile and API clients.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Customer;

use FlavorCore\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class TokenService
 */
class TokenService {

	/**
	 * Access token lifetime in seconds (2 hours).
	 */
	public const ACCESS_TTL = 7200;

	/**
	 * Refresh token lifetime in seconds (60 days).
	 */
	public const REFRESH_TTL = 5184000;

	/**
	 * Table name.
	 */
	public static function table(): string {
		return Schema::table( 'flavor_auth_tokens' );
	}

	/**
	 * Issue a new token pair for a user.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $device_id   Device identifier.
	 * @param string $device_name Device label (e.g. "Samsung S24", "iPhone 15").
	 * @return array<string, mixed>
	 */
	public static function issue( int $user_id, string $device_id = '', string $device_name = '' ): array {
		$access_token  = wp_generate_password( 64, false, false );
		$refresh_token = wp_generate_password( 64, false, false );

		$access_hash  = hash( 'sha256', $access_token );
		$refresh_hash = hash( 'sha256', $refresh_token );

		$now         = current_time( 'mysql' );
		$now_ts      = strtotime( $now );
		$exp_access  = date( 'Y-m-d H:i:s', $now_ts + self::ACCESS_TTL ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$exp_refresh = date( 'Y-m-d H:i:s', $now_ts + self::REFRESH_TTL ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			self::table(),
			array(
				'user_id'            => $user_id,
				'token_hash'         => $access_hash,
				'refresh_token_hash' => $refresh_hash,
				'device_id'          => sanitize_text_field( $device_id ),
				'device_name'        => sanitize_text_field( $device_name ),
				'ip'                 => self::client_ip(),
				'user_agent'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'expires_at'         => $exp_access,
				'revoked_at'         => null,
				'created_at'         => $now,
				'updated_at'         => $now,
			)
		);

		return array(
			'token_type'    => 'Bearer',
			'access_token'  => $access_token,
			'refresh_token' => $refresh_token,
			'expires_in'    => self::ACCESS_TTL,
			'expires_at'    => $exp_access,
			'user_id'       => $user_id,
		);
	}

	/**
	 * Validate a Bearer token and return the associated user ID.
	 *
	 * @param string $raw_token Token from Authorization header.
	 * @return int|null User ID or null if invalid/expired.
	 */
	public static function validate( string $raw_token ): ?int {
		$raw_token = trim( $raw_token );
		if ( empty( $raw_token ) ) {
			return null;
		}

		$hash = hash( 'sha256', $raw_token );
		$now  = current_time( 'mysql' );

		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM {$table} WHERE token_hash = %s AND revoked_at IS NULL AND expires_at >= %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hash,
				$now
			),
			ARRAY_A
		);

		return $row ? (int) $row['user_id'] : null;
	}

	/**
	 * Exchange a refresh token for a fresh token pair (Rotation).
	 *
	 * @param string $raw_refresh_token Refresh token.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function refresh( string $raw_refresh_token ) {
		$raw_refresh_token = trim( $raw_refresh_token );
		if ( empty( $raw_refresh_token ) ) {
			return new \WP_Error( 'flavor_bad_token', __( 'توکن بازنشانی نامعتبر است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$hash = hash( 'sha256', $raw_refresh_token );
		$now  = current_time( 'mysql' );

		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE refresh_token_hash = %s AND revoked_at IS NULL LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hash
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new \WP_Error( 'flavor_token_expired', __( 'توکن منقضی یا نامعتبر شده است. لطفاً مجدداً وارد شوید.', 'flavor-core' ), array( 'status' => 401 ) );
		}

		// Revoke the old token row
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array( 'revoked_at' => $now ),
			array( 'id' => (int) $row['id'] )
		);

		// Issue new pair
		return self::issue( (int) $row['user_id'], (string) $row['device_id'], (string) $row['device_name'] );
	}

	/**
	 * Revoke a specific token.
	 *
	 * @param string $raw_token Raw access token.
	 */
	public static function revoke( string $raw_token ): bool {
		$hash = hash( 'sha256', trim( $raw_token ) );
		$now  = current_time( 'mysql' );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$affected = $wpdb->update(
			self::table(),
			array( 'revoked_at' => $now ),
			array( 'token_hash' => $hash )
		);

		return $affected > 0;
	}

	/**
	 * Revoke all tokens for a specific user (Logout Everywhere).
	 *
	 * @param int $user_id User ID.
	 */
	public static function revoke_all_user_tokens( int $user_id ): void {
		$now = current_time( 'mysql' );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			self::table(),
			array( 'revoked_at' => $now ),
			array(
				'user_id'    => $user_id,
				'revoked_at' => null,
			)
		);
	}

	/**
	 * Clean up expired and revoked tokens older than 30 days.
	 */
	public static function purge_expired(): int {
		$threshold = date( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - ( 30 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s OR (revoked_at IS NOT NULL AND revoked_at < %s)", $threshold, $threshold ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Get client remote IP.
	 */
	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
