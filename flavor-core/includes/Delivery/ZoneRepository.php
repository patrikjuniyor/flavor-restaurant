<?php
/**
 * Delivery zones per branch.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Delivery;

use FlavorCore\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class ZoneRepository
 */
class ZoneRepository {

	public const TYPES = array( 'radius', 'neighborhoods', 'polygon' );

	/**
	 * Table name.
	 */
	public static function table(): string {
		return Schema::table( 'flavor_delivery_zones' );
	}

	/**
	 * Zones of a branch.
	 *
	 * @param int  $branch_id Branch.
	 * @param bool $active_only Only active.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_branch( int $branch_id, bool $active_only = false ): array {
		global $wpdb;
		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE branch_id = %d";
		$args  = array( $branch_id );
		if ( $active_only ) {
			$sql .= ' AND is_active = 1';
		}
		$sql .= ' ORDER BY sort_order ASC, id ASC';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Find by id.
	 *
	 * @param int $id Zone id.
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
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Insert.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int|\WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$row = self::prepare_row( $data, $now, true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::table(), $row );
		if ( ! $ok ) {
			return new \WP_Error( 'flavor_zone_insert', __( 'ثبت منطقه ارسال انجام نشد.', 'flavor-core' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update.
	 *
	 * @param int                  $id   Zone.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$row = self::prepare_row( $data, current_time( 'mysql' ), false );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::table(), $row, array( 'id' => $id ) );
		return true;
	}

	/**
	 * Delete.
	 */
	public static function delete( int $id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @param array<string, mixed> $row Raw.
	 * @return array<string, mixed>
	 */
	public static function hydrate( array $row ): array {
		$hoods = array();
		if ( ! empty( $row['neighborhoods_json'] ) ) {
			$decoded = json_decode( (string) $row['neighborhoods_json'], true );
			$hoods   = is_array( $decoded ) ? $decoded : array();
		}
		$row['neighborhoods'] = $hoods;
		$row['id']            = (int) $row['id'];
		$row['branch_id']     = (int) $row['branch_id'];
		$row['delivery_fee']  = (int) $row['delivery_fee'];
		$row['min_order']     = (int) $row['min_order'];
		$row['is_active']     = (int) $row['is_active'];
		return $row;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @param string               $now  Datetime.
	 * @param bool                 $is_new Insert.
	 * @return array<string, mixed>
	 */
	private static function prepare_row( array $data, string $now, bool $is_new ): array {
		$type  = in_array( $data['zone_type'] ?? '', self::TYPES, true ) ? $data['zone_type'] : 'neighborhoods';
		$hoods = $data['neighborhoods'] ?? array();
		if ( is_string( $hoods ) ) {
			$hoods = preg_split( '/[\r\n,]+/', $hoods ) ?: array();
		}
		$hoods = array_values( array_filter( array_map( 'sanitize_text_field', (array) $hoods ) ) );

		$row = array(
			'name'               => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'zone_type'          => $type,
			'center_lat'         => isset( $data['center_lat'] ) && '' !== $data['center_lat'] ? (float) $data['center_lat'] : null,
			'center_lng'         => isset( $data['center_lng'] ) && '' !== $data['center_lng'] ? (float) $data['center_lng'] : null,
			'radius_km'          => isset( $data['radius_km'] ) && '' !== $data['radius_km'] ? (float) $data['radius_km'] : null,
			'neighborhoods_json' => wp_json_encode( $hoods ),
			'delivery_fee'       => (int) ( $data['delivery_fee'] ?? 0 ),
			'min_order'          => (int) ( $data['min_order'] ?? 0 ),
			'estimated_minutes'  => max( 5, (int) ( $data['estimated_minutes'] ?? 45 ) ),
			'is_active'          => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'sort_order'         => (int) ( $data['sort_order'] ?? 0 ),
			'updated_at'         => $now,
		);
		if ( $is_new ) {
			$row['branch_id']  = (int) $data['branch_id'];
			$row['created_at'] = $now;
		}
		return $row;
	}
}
