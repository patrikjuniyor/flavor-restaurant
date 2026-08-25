<?php
/**
 * Dining tables per branch.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Table;

use FlavorCore\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class TableRepository
 */
class TableRepository {

	public const SECTIONS = array( 'indoor', 'outdoor', 'bar', 'window' );

	/**
	 * Table name.
	 */
	public static function table(): string {
		return Schema::table( 'flavor_tables' );
	}

	/**
	 * All tables of a branch.
	 *
	 * @param int $branch_id Branch.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_branch( int $branch_id ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE branch_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$branch_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find by public QR token.
	 *
	 * @param string $token Token.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_token( string $token ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE qr_token = %s", $token ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Find by id.
	 *
	 * @param int $id Table id.
	 * @return array<string, mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Insert a table.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int|\WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );

		$row = array(
			'branch_id'    => (int) $data['branch_id'],
			'table_number' => sanitize_text_field( (string) $data['table_number'] ),
			'label'        => isset( $data['label'] ) ? sanitize_text_field( (string) $data['label'] ) : null,
			'capacity'     => isset( $data['capacity'] ) ? max( 1, min( 50, (int) $data['capacity'] ) ) : 4,
			'section'      => in_array( $data['section'] ?? 'indoor', self::SECTIONS, true ) ? $data['section'] : 'indoor',
			'qr_token'     => self::fresh_token(),
			'is_active'    => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'sort_order'   => (int) ( $data['sort_order'] ?? 0 ),
			'created_at'   => $now,
			'updated_at'   => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::table(), $row );
		if ( ! $ok ) {
			return new \WP_Error( 'flavor_table_insert', __( 'ثبت میز انجام نشد. شماره میز در این شعبه تکراری است؟', 'flavor-core' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Bulk create numbered tables.
	 *
	 * @param int $branch_id Branch.
	 * @param int $from      Start number.
	 * @param int $to        End number.
	 * @param int $capacity  Seats.
	 * @return int How many inserted.
	 */
	public static function bulk_create( int $branch_id, int $from, int $to, int $capacity = 4 ): int {
		$from = max( 1, $from );
		$to   = min( 200, max( $from, $to ) );
		$n    = 0;
		for ( $i = $from; $i <= $to; $i++ ) {
			$result = self::create(
				array(
					'branch_id'    => $branch_id,
					'table_number' => (string) $i,
					'capacity'     => $capacity,
					'sort_order'   => $i,
				)
			);
			if ( ! is_wp_error( $result ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Public URL the QR should encode. PNG/SVG generation is Phase 2.
	 *
	 * @param array<string, mixed> $table Table row.
	 */
	public static function public_url( array $table ): string {
		$branch = get_post( (int) $table['branch_id'] );
		$slug   = $branch ? $branch->post_name : (string) $table['branch_id'];
		return home_url( '/branch/' . $slug . '/table/' . rawurlencode( (string) $table['table_number'] ) . '/' );
	}

	/**
	 * Cryptographically random 32-char hex token.
	 */
	public static function fresh_token(): string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			return md5( uniqid( 'flavor', true ) );
		}
	}
}
