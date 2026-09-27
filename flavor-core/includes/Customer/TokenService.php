<?php
/**
 * Stateless Bearer and Refresh Token Manager for Mobile and API clients.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Customer;

use FlavorCore\Database\Schema;
use FlavorCore\Observability\StructuredLogger;

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
	 * @return array<string, mixed>|\WP_Error Token pair payload, or WP_Error when persistence fails.
	 */
	public static function issue( int $user_id, string $device_id = '', string $device_name = '' ) {
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
		$inserted = $wpdb->insert(
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
				'refresh_expires_at' => $exp_refresh,
				'revoked_at'         => null,
				'created_at'         => $now,
				'updated_at'         => $now,
			)
		);

		if ( false === $inserted ) {
			self::log_write_failure( 'issue', $user_id );
			return new \WP_Error(
				'flavor_token_persist_failed',
				__( 'خطا در صدور توکن. لطفاً دوباره تلاش کنید.', 'flavor-core' ),
				array( 'status' => 500 )
			);
		}

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

		// Enforce the refresh-token TTL. Rows written before the refresh_expires_at
		// column existed (schema < 1.5.0) fall back to created_at + REFRESH_TTL so
		// a refresh credential can never outlive its configured lifetime.
		$refresh_expires = isset( $row['refresh_expires_at'] ) && $row['refresh_expires_at']
			? (string) $row['refresh_expires_at']
			: date( 'Y-m-d H:i:s', strtotime( (string) $row['created_at'] ) + self::REFRESH_TTL ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		if ( strtotime( $refresh_expires ) < strtotime( $now ) ) {
			// Best-effort cleanup of the dead row before rejecting.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array( 'revoked_at' => $now ),
				array( 'id' => (int) $row['id'] )
			);
			return new \WP_Error( 'flavor_token_expired', __( 'توکن منقضی یا نامعتبر شده است. لطفاً مجدداً وارد شوید.', 'flavor-core' ), array( 'status' => 401 ) );
		}

		// Revoke the old token row (single-use rotation).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$revoked = $wpdb->update(
			$table,
			array( 'revoked_at' => $now ),
			array( 'id' => (int) $row['id'] )
		);

		if ( false === $revoked ) {
			// Rotating while the old refresh credential cannot be invalidated
			// would mint a second live refresh token — refuse instead.
			self::log_write_failure( 'refresh-revoke', (int) $row['user_id'] );
			return new \WP_Error(
				'flavor_token_revoke_failed',
				__( 'خطا در بازنشانی توکن. لطفاً دوباره تلاش کنید.', 'flavor-core' ),
				array( 'status' => 500 )
			);
		}

		// Issue new pair (may itself return WP_Error on persistence failure).
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

		if ( false === $affected ) {
			self::log_write_failure( 'revoke', null );
			return false;
		}

		return $affected > 0;
	}

	/**
	 * Revoke all tokens for a specific user (Logout Everywhere).
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success, false when the database write failed.
	 */
	public static function revoke_all_user_tokens( int $user_id ): bool {
		$now = current_time( 'mysql' );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			self::table(),
			array( 'revoked_at' => $now ),
			array(
				'user_id'    => $user_id,
				'revoked_at' => null,
			)
		);

		if ( false === $result ) {
			self::log_write_failure( 'revoke-all', $user_id );
			return false;
		}

		return true;
	}

	/**
	 * Clean up dead token rows.
	 *
	 * A row is purged when the session was revoked more than 30 days ago, or when
	 * its refresh credential is definitively expired (refresh_expires_at passed,
	 * or — for legacy rows without the column — created_at is older than
	 * REFRESH_TTL). Purging must never delete a row whose refresh token is still
	 * usable for rotation, so access-token expiry alone is not a criterion.
	 */
	public static function purge_expired(): int {
		$now_ts        = strtotime( current_time( 'mysql' ) );
		$now           = date( 'Y-m-d H:i:s', $now_ts ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$revoke_cutoff = date( 'Y-m-d H:i:s', $now_ts - ( 30 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$refresh_cut   = date( 'Y-m-d H:i:s', $now_ts - self::REFRESH_TTL ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE (revoked_at IS NOT NULL AND revoked_at < %s) OR (refresh_expires_at IS NOT NULL AND refresh_expires_at < %s) OR (refresh_expires_at IS NULL AND created_at < %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$revoke_cutoff,
				$now,
				$refresh_cut
			)
		);

		if ( false === $deleted ) {
			self::log_write_failure( 'purge', null );
			return 0;
		}

		return (int) $deleted;
	}

	/**
	 * Get client remote IP.
	 */
	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Make a database write failure observable without breaking auth flows.
	 *
	 * @param string   $operation Operation slug (issue/refresh-revoke/revoke/revoke-all/purge).
	 * @param int|null $user_id   Related user ID, when known.
	 */
	private static function log_write_failure( string $operation, ?int $user_id ): void {
		global $wpdb;

		$db_error = '';
		if ( isset( $wpdb ) && is_object( $wpdb ) && property_exists( $wpdb, 'last_error' ) ) {
			$db_error = (string) $wpdb->last_error;
		}

		try {
			StructuredLogger::error(
				'auth',
				'Auth token database write failed: ' . $operation,
				array(
					'user_id'  => $user_id,
					'db_error' => $db_error,
				)
			);
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Observability must never break the auth flow itself.
		}
	}
}
