<?php
/**
 * Mobile Guest Cart Token Service.
 * Decouples mobile REST clients from PHP session cookies using token-based cart state persistence.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce;

use FlavorCore\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class CartTokenService
 */
class CartTokenService {

	public const HEADER_NAME = 'X-Cart-Token';
	public const TTL_SECONDS = 604800; // 7 days

	/**
	 * Database table name.
	 */
	public static function table(): string {
		return Schema::table( 'flavor_guest_carts' );
	}

	/**
	 * Generate a new random cart token.
	 */
	public static function generate_token(): string {
		try {
			return 'fcart_' . bin2hex( random_bytes( 24 ) );
		} catch ( \Exception $e ) {
			return 'fcart_' . hash( 'sha256', uniqid( (string) wp_rand(), true ) . microtime( true ) );
		}
	}

	/**
	 * Compute SHA-256 hash for database lookup.
	 */
	public static function hash_token( string $token ): string {
		return hash( 'sha256', trim( $token ) );
	}

	/**
	 * Extract cart token from request header or query parameter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	public static function extract_token( \WP_REST_Request $request ): string {
		$header = $request->get_header( 'X-Cart-Token' );
		if ( ! empty( $header ) && is_string( $header ) ) {
			return sanitize_text_field( $header );
		}

		$param = $request->get_param( 'cart_token' );
		if ( ! empty( $param ) && is_string( $param ) ) {
			return sanitize_text_field( $param );
		}

		return '';
	}

	/**
	 * Retrieve saved cart data from database.
	 *
	 * @param string $token Plain cart token.
	 * @return array<string, mixed>|null
	 */
	public static function get_cart( string $token ): ?array {
		$token = trim( $token );
		if ( '' === $token ) {
			return null;
		}

		global $wpdb;
		$table = self::table();
		$hash  = self::hash_token( $token );
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE cart_token_hash = %s AND expires_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hash,
				$now
			),
			ARRAY_A
		);

		if ( ! $row || empty( $row['cart_data'] ) ) {
			return null;
		}

		$data = json_decode( (string) $row['cart_data'], true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$data['branch_id'] = (int) ( $row['branch_id'] ?? 0 );
		return $data;
	}

	/**
	 * Persist cart snapshot into database.
	 *
	 * @param string               $token     Plain cart token.
	 * @param array<string, mixed> $cart_data Cart structure.
	 * @param int                  $branch_id Associated branch ID.
	 * @param int                  $ttl       Time to live in seconds.
	 * @return bool
	 */
	public static function save_cart( string $token, array $cart_data, int $branch_id = 0, int $ttl = self::TTL_SECONDS ): bool {
		$token = trim( $token );
		if ( '' === $token ) {
			return false;
		}

		global $wpdb;
		$table      = self::table();
		$hash       = self::hash_token( $token );
		$now        = current_time( 'mysql' );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + $ttl );
		$json       = wp_json_encode( $cart_data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE cart_token_hash = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hash
			)
		);

		if ( $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->update(
				$table,
				array(
					'cart_data'  => $json,
					'branch_id'  => $branch_id,
					'expires_at' => $expires_at,
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing_id )
			);
			return false !== $ok;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			$table,
			array(
				'cart_token_hash' => $hash,
				'cart_data'       => $json,
				'branch_id'       => $branch_id,
				'expires_at'      => $expires_at,
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);

		return false !== $ok;
	}

	/**
	 * Remove a guest cart after successful checkout or expiration.
	 *
	 * @param string $token Plain cart token.
	 * @return bool
	 */
	public static function delete_cart( string $token ): bool {
		$token = trim( $token );
		if ( '' === $token ) {
			return false;
		}

		global $wpdb;
		$table = self::table();
		$hash  = self::hash_token( $token );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->delete( $table, array( 'cart_token_hash' => $hash ), array( '%s' ) );
		return false !== $ok;
	}

	/**
	 * Restore database guest cart items into active WooCommerce cart session.
	 *
	 * @param string $token Plain cart token.
	 * @return bool
	 */
	public static function restore_into_session( string $token ): bool {
		$saved = self::get_cart( $token );
		if ( ! $saved || empty( $saved['items'] ) ) {
			return false;
		}

		CartSession::ensure();
		if ( ! WC()->cart ) {
			return false;
		}

		// Empty session cart first to avoid duplicate items on restore
		WC()->cart->empty_cart();

		foreach ( $saved['items'] as $item ) {
			$product_id   = (int) ( $item['product_id'] ?? 0 );
			$qty          = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$modifiers    = is_array( $item['modifiers'] ?? null ) ? $item['modifiers'] : array();
			$instructions = (string) ( $item['instructions'] ?? '' );

			if ( $product_id > 0 ) {
				$mod_ids = array();
				foreach ( $modifiers as $m ) {
					if ( is_array( $m ) && ! empty( $m['id'] ) ) {
						$mod_ids[] = (string) $m['id'];
					} elseif ( is_string( $m ) || is_int( $m ) ) {
						$mod_ids[] = (string) $m;
					}
				}
				CartSession::add( $product_id, $qty, array( 'ids' => $mod_ids, 'instructions' => $instructions ) );
			}
		}

		if ( ! empty( $saved['coupons'] ) && is_array( $saved['coupons'] ) ) {
			foreach ( $saved['coupons'] as $code ) {
				if ( is_string( $code ) && '' !== trim( $code ) ) {
					WC()->cart->apply_coupon( sanitize_text_field( $code ) );
				}
			}
		}

		WC()->cart->calculate_totals();
		return true;
	}

	/**
	 * Sync current active WooCommerce cart session back to guest cart table.
	 *
	 * @param string $token     Plain cart token.
	 * @param int    $branch_id Optional branch ID.
	 * @return bool
	 */
	public static function sync_session_to_database( string $token, int $branch_id = 0 ): bool {
		if ( '' === trim( $token ) ) {
			return false;
		}

		CartSession::ensure();
		if ( ! WC()->cart ) {
			return false;
		}

		$raw = CartSession::payload();
		$cart_data = array(
			'items'   => $raw['items'] ?? array(),
			'count'   => (int) ( $raw['count'] ?? 0 ),
			'coupons' => WC()->cart->get_applied_coupons(),
		);

		return self::save_cart( $token, $cart_data, $branch_id );
	}

	/**
	 * Migrate a guest cart to an authenticated user upon login.
	 *
	 * @param string $token   Plain cart token.
	 * @param int    $user_id Authenticated user ID.
	 * @return bool
	 */
	public static function migrate_to_user( string $token, int $user_id ): bool {
		if ( '' === trim( $token ) || $user_id <= 0 ) {
			return false;
		}

		$saved = self::get_cart( $token );
		if ( ! $saved || empty( $saved['items'] ) ) {
			self::delete_cart( $token );
			return true;
		}

		CartSession::ensure();
		if ( WC()->cart ) {
			foreach ( $saved['items'] as $item ) {
				$product_id   = (int) ( $item['product_id'] ?? 0 );
				$qty          = max( 1, (int) ( $item['quantity'] ?? 1 ) );
				$modifiers    = is_array( $item['modifiers'] ?? null ) ? $item['modifiers'] : array();
				$instructions = (string) ( $item['instructions'] ?? '' );

				if ( $product_id > 0 ) {
					$mod_ids = array();
					foreach ( $modifiers as $m ) {
						if ( is_array( $m ) && ! empty( $m['id'] ) ) {
							$mod_ids[] = (string) $m['id'];
						} elseif ( is_string( $m ) || is_int( $m ) ) {
							$mod_ids[] = (string) $m;
						}
					}
					CartSession::add( $product_id, $qty, array( 'ids' => $mod_ids, 'instructions' => $instructions ) );
				}
			}
			WC()->cart->calculate_totals();
		}

		self::delete_cart( $token );
		return true;
	}

	/**
	 * Cleanup expired guest carts.
	 *
	 * @return int Rows deleted.
	 */
	public static function purge_expired(): int {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE expires_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now
			)
		);

		return (int) $deleted;
	}
}
