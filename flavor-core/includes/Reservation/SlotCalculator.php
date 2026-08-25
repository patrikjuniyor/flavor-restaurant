<?php
/**
 * Available reservation slots. Capacity is pooled by section (not a hard table lock).
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reservation;

use FlavorCore\Database\Schema;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\Settings;
use FlavorCore\Table\TableRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class SlotCalculator
 */
class SlotCalculator {

	/**
	 * Slots for a Gregorian date.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function slots( int $branch_id, string $date, int $party, string $section = '' ): array {
		$party   = max( 1, $party );
		$hours   = self::hours_for( $branch_id, Jalali::iran_dow( $date ) );
		if ( empty( $hours ) || ! empty( $hours['is_closed'] ) ) {
			return array();
		}
		if ( self::is_closed( $branch_id, $date ) ) {
			return array();
		}

		$capacity = self::capacity( $branch_id, $section );
		if ( $capacity < $party ) {
			return array();
		}

		$buffer   = (int) Settings::get( 'reservation_buffer', 30 );
		$step     = max( 15, $buffer ?: 30 );
		$duration = (int) Settings::get( 'reservation_duration', 90 );
		$open     = self::minutes( (string) $hours['open_time'] );
		$close    = self::minutes( (string) $hours['close_time'] );
		if ( $close <= $open ) {
			$close += 24 * 60;
		}

		$existing = ReservationRepository::for_date( $branch_id, $date, true );
		$out      = array();
		$now      = current_time( 'timestamp' );

		for ( $t = $open; $t + $duration <= $close; $t += $step ) {
			$tod = $t % ( 24 * 60 );
			$hh  = str_pad( (string) intdiv( $tod, 60 ), 2, '0', STR_PAD_LEFT );
			$mm  = str_pad( (string) ( $tod % 60 ), 2, '0', STR_PAD_LEFT );
			$iso = $hh . ':' . $mm . ':00';
			$ts  = strtotime( $date . ' ' . $iso );
			if ( $ts && $ts < $now + ( 30 * MINUTE_IN_SECONDS ) ) {
				continue;
			}
			$used = 0;
			foreach ( $existing as $res ) {
				if ( $section && ! empty( $res['section'] ) && $res['section'] !== $section ) {
					continue;
				}
				if ( self::overlaps( $t, $duration, self::minutes( (string) $res['reservation_time'] ), (int) $res['duration_minutes'] ) ) {
					$used += (int) $res['party_size'];
				}
			}
			$free = $capacity - $used;
			$out[] = array(
				'time'      => $hh . ':' . $mm,
				'time_sql'  => $iso,
				'available' => $free >= $party,
				'free'      => max( 0, $free ),
			);
		}
		return $out;
	}

	/**
	 * Whether a concrete slot can still take this party.
	 */
	public static function can_book( int $branch_id, string $date, string $time, int $party, string $section = '' ): bool {
		foreach ( self::slots( $branch_id, $date, $party, $section ) as $slot ) {
			if ( $slot['time'] === substr( $time, 0, 5 ) && $slot['available'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Seats in a section (or the whole branch).
	 */
	public static function capacity( int $branch_id, string $section = '' ): int {
		$sum = 0;
		foreach ( TableRepository::for_branch( $branch_id ) as $t ) {
			if ( ! (int) $t['is_active'] ) {
				continue;
			}
			if ( $section && (string) $t['section'] !== $section ) {
				continue;
			}
			$sum += (int) $t['capacity'];
		}
		return $sum > 0 ? $sum : 20;
	}

	/**
	 * Working hours for a weekday. Falls back to 12:00–23:00.
	 *
	 * @return array<string, mixed>
	 */
	public static function hours_for( int $branch_id, int $dow ): array {
		global $wpdb;
		$table = Schema::table( 'flavor_branch_hours' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE branch_id = %d AND day_of_week = %d AND mode IN ('all','dine_in') ORDER BY FIELD(mode,'dine_in','all') LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$branch_id,
				$dow
			),
			ARRAY_A
		);
		if ( $row ) {
			return $row;
		}
		return array(
			'open_time'  => '12:00:00',
			'close_time' => '23:00:00',
			'is_closed'  => 0,
		);
	}

	/**
	 * Holiday row?
	 */
	public static function is_closed( int $branch_id, string $date ): bool {
		global $wpdb;
		$table = Schema::table( 'flavor_branch_closures' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE branch_id = %d AND closure_date = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$branch_id,
				$date
			)
		);
		return $n > 0;
	}

	/**
	 * HH:MM[:SS] → minutes from midnight.
	 */
	public static function minutes( string $time ): int {
		$p = array_map( 'intval', explode( ':', $time ) );
		return ( ( $p[0] ?? 0 ) * 60 ) + ( $p[1] ?? 0 );
	}

	/**
	 * Interval overlap (minutes from midnight).
	 */
	public static function overlaps( int $a0, int $adur, int $b0, int $bdur ): bool {
		$a1 = $a0 + $adur;
		$b1 = $b0 + $bdur;
		return $a0 < $b1 && $b0 < $a1;
	}
}
