<?php
/**
 * Time-of-day menu windows (breakfast / lunch / dinner / late night).
 *
 * @package FlavorCore
 */

namespace FlavorCore\Menu;

use FlavorCore\Database\Schema;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\Settings;
use FlavorCore\WooCommerce\ProductModifiers;

defined( 'ABSPATH' ) || exit;

/**
 * Class MenuScheduler
 */
class MenuScheduler {

	/**
	 * Active schedule slugs for a branch at $timestamp (site time).
	 *
	 * @return string[]
	 */
	public static function active_slugs( int $branch_id, ?int $timestamp = null ): array {
		$ts   = $timestamp ?: current_time( 'timestamp' );
		$tod  = (int) date( 'H', $ts ) * 60 + (int) date( 'i', $ts ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$dow  = Jalali::iran_dow( date( 'Y-m-d', $ts ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$out  = array();
		foreach ( self::schedules( $branch_id ) as $row ) {
			if ( ! (int) $row['is_active'] ) {
				continue;
			}
			$days = json_decode( (string) $row['days_json'], true );
			if ( is_array( $days ) && ! in_array( $dow, array_map( 'intval', $days ), true ) ) {
				continue;
			}
			$start = self::tod( (string) $row['start_time'] );
			$end   = self::tod( (string) $row['end_time'] );
			if ( self::in_window( $tod, $start, $end ) ) {
				$out[] = (string) $row['slug'];
			}
		}
		return $out;
	}

	/**
	 * Whether a product should appear now.
	 *
	 * @return array{visible:bool,slugs:string[],next:string}
	 */
	public static function product_state( int $branch_id, int $product_id, ?int $timestamp = null ): array {
		$assigned = get_post_meta( $product_id, ProductModifiers::META_SCHEDULE, true );
		$assigned = is_array( $assigned ) ? $assigned : array();
		$active   = self::active_slugs( $branch_id, $timestamp );
		if ( empty( $assigned ) ) {
			return array(
				'visible' => true,
				'slugs'   => $active,
				'next'    => '',
			);
		}
		$hit = array_intersect( $assigned, $active );
		$next = '';
		if ( empty( $hit ) ) {
			$next = self::next_label( $branch_id, $assigned );
		}
		$hide = 'yes' === Settings::get( 'hide_offschedule', 'yes' );
		return array(
			'visible' => ! empty( $hit ) || ! $hide,
			'slugs'   => $assigned,
			'next'    => $next,
			'now'     => ! empty( $hit ),
		);
	}

	/**
	 * Schedules: branch-specific rows override global (branch_id=0) of the same slug.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function schedules( int $branch_id ): array {
		global $wpdb;
		$table = Schema::table( 'flavor_menu_schedules' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE branch_id IN (0, %d) ORDER BY branch_id ASC, start_time ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$branch_id
			),
			ARRAY_A
		);
		$map = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ (string) $row['slug'] ] = $row;
		}
		return array_values( $map );
	}

	/**
	 * Upsert a schedule row.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public static function save( array $data ): int {
		global $wpdb;
		$now  = current_time( 'mysql' );
		$slug = sanitize_key( (string) $data['slug'] );
		$row  = array(
			'branch_id'  => (int) ( $data['branch_id'] ?? 0 ),
			'name'       => sanitize_text_field( (string) ( $data['name'] ?? $slug ) ),
			'slug'       => $slug,
			'start_time' => sanitize_text_field( (string) $data['start_time'] ),
			'end_time'   => sanitize_text_field( (string) $data['end_time'] ),
			'days_json'  => wp_json_encode( isset( $data['days'] ) ? array_map( 'intval', (array) $data['days'] ) : array( 0, 1, 2, 3, 4, 5, 6 ) ),
			'is_active'  => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'updated_at' => $now,
		);
		$table = Schema::table( 'flavor_menu_schedules' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE branch_id = %d AND slug = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$row['branch_id'],
				$slug
			)
		);
		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $row, array( 'id' => (int) $existing ) );
			return (int) $existing;
		}
		$row['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Overnight-aware window.
	 */
	public static function in_window( int $tod, int $start, int $end ): bool {
		if ( $end > $start ) {
			return $tod >= $start && $tod < $end;
		}
		// Overnight (e.g. 23:00–02:00).
		return $tod >= $start || $tod < $end;
	}

	/**
	 * Human next-available label.
	 *
	 * @param string[] $slugs Assigned slugs.
	 */
	private static function next_label( int $branch_id, array $slugs ): string {
		foreach ( self::schedules( $branch_id ) as $row ) {
			if ( in_array( (string) $row['slug'], $slugs, true ) ) {
				return sprintf(
					/* translators: 1: name, 2: start */
					__( 'از ساعت %2$s (%1$s)', 'flavor-core' ),
					$row['name'],
					substr( (string) $row['start_time'], 0, 5 )
				);
			}
		}
		return '';
	}

	/**
	 * TIME → minutes.
	 */
	private static function tod( string $time ): int {
		$p = array_map( 'intval', explode( ':', $time ) );
		return ( ( $p[0] ?? 0 ) * 60 ) + ( $p[1] ?? 0 );
	}
}
