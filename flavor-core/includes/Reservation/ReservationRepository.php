<?php
/**
 * Reservation persistence.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reservation;

use FlavorCore\Database\Schema;
use FlavorCore\Support\Jalali;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReservationRepository
 */
class ReservationRepository {

	public const STATUSES = array( 'pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show' );

	/**
	 * Table name.
	 */
	public static function table(): string {
		return Schema::table( 'flavor_reservations' );
	}

	/**
	 * Find by id.
	 *
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
	 * Reservations for a branch on a Gregorian date.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_date( int $branch_id, string $date, bool $active_only = true ): array {
		global $wpdb;
		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE branch_id = %d AND reservation_date = %s";
		$args  = array( $branch_id, $date );
		if ( $active_only ) {
			$sql .= " AND status IN ('pending','confirmed','seated')";
		}
		$sql .= ' ORDER BY reservation_time ASC';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Range query for the admin calendar.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function in_range( int $branch_id, string $from, string $to ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE branch_id = %d AND reservation_date BETWEEN %s AND %s ORDER BY reservation_date ASC, reservation_time ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$branch_id,
				$from,
				$to
			),
			ARRAY_A
		);
		return array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() );
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
		$row = array(
			'branch_id'         => (int) $data['branch_id'],
			'table_id'          => ! empty( $data['table_id'] ) ? (int) $data['table_id'] : null,
			'section'           => isset( $data['section'] ) ? sanitize_key( (string) $data['section'] ) : null,
			'reservation_date'  => sanitize_text_field( (string) $data['reservation_date'] ),
			'reservation_time'  => sanitize_text_field( (string) $data['reservation_time'] ),
			'duration_minutes'  => max( 30, (int) ( $data['duration_minutes'] ?? 90 ) ),
			'party_size'        => max( 1, min( 30, (int) $data['party_size'] ) ),
			'customer_id'       => ! empty( $data['customer_id'] ) ? (int) $data['customer_id'] : null,
			'customer_name'     => sanitize_text_field( (string) $data['customer_name'] ),
			'customer_mobile'   => sanitize_text_field( (string) $data['customer_mobile'] ),
			'status'            => in_array( $data['status'] ?? 'pending', self::STATUSES, true ) ? $data['status'] : 'pending',
			'special_requests'  => isset( $data['special_requests'] ) ? sanitize_textarea_field( (string) $data['special_requests'] ) : null,
			'source'            => sanitize_key( (string) ( $data['source'] ?? 'online' ) ),
			'reminder_sent_at'  => null,
			'created_at'        => $now,
			'updated_at'        => $now,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::table(), $row );
		if ( ! $ok ) {
			return new \WP_Error( 'flavor_res_insert', __( 'ثبت رزرو انجام نشد.', 'flavor-core' ) );
		}
		$id = (int) $wpdb->insert_id;
		/**
		 * @param int                  $id  Id.
		 * @param array<string, mixed> $row Row.
		 */
		do_action( 'flavor_core_reservation_created', $id, $row );
		return $id;
	}

	/**
	 * Status transition.
	 */
	public static function set_status( int $id, string $status ): bool {
		$status = sanitize_key( $status );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
		do_action( 'flavor_core_reservation_status_changed', $id, $status );
		return true;
	}

	/**
	 * Mark reminder sent.
	 */
	public static function mark_reminded( int $id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			self::table(),
			array( 'reminder_sent_at' => current_time( 'mysql' ) ),
			array( 'id' => $id )
		);
	}

	/**
	 * Due reminders (confirmed, 2h window, not yet sent).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function due_reminders(): array {
		global $wpdb;
		$table = self::table();
		$from  = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$to    = gmdate( 'Y-m-d H:i:s', time() + ( 3 * HOUR_IN_SECONDS ) );
		// Compare local date+time as datetime. Site timezone via current_time offset is approximate; we filter in PHP too.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'confirmed' AND reminder_sent_at IS NULL AND reservation_date >= CURDATE()", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		$out = array();
		$now = time();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$ts = strtotime( $row['reservation_date'] . ' ' . $row['reservation_time'] );
			if ( ! $ts ) {
				continue;
			}
			$delta = $ts - $now;
			if ( $delta > 90 * MINUTE_IN_SECONDS && $delta <= 150 * MINUTE_IN_SECONDS ) {
				$out[] = self::hydrate( $row );
			}
		}
		unset( $from, $to );
		return $out;
	}

	/**
	 * No-show count for a mobile.
	 */
	public static function no_show_count( string $mobile ): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE customer_mobile = %s AND status = 'no_show'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$mobile
			)
		);
	}

	/**
	 * @param array<string, mixed> $row Raw.
	 * @return array<string, mixed>
	 */
	public static function hydrate( array $row ): array {
		$row['id']                = (int) $row['id'];
		$row['branch_id']         = (int) $row['branch_id'];
		$row['party_size']        = (int) $row['party_size'];
		$row['duration_minutes']  = (int) $row['duration_minutes'];
		$row['jalali']            = Jalali::parse_gregorian( (string) $row['reservation_date'] );
		$row['jalali_label']      = Jalali::format( (string) $row['reservation_date'], true );
		return $row;
	}
}
