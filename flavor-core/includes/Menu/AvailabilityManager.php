<?php
/**
 * Per-branch item availability.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Menu;

use FlavorCore\Database\Schema;
use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class AvailabilityManager
 */
class AvailabilityManager {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		// Reserved for kitchen-side toggles in this phase.
	}

	/**
	 * Whether a product is currently sellable at a branch.
	 */
	public static function is_available( int $branch_id, int $product_id ): bool {
		$row = self::row( $branch_id, $product_id );
		if ( ! $row ) {
			return true;
		}
		if ( (int) $row['is_available'] ) {
			return true;
		}
		if ( ! empty( $row['unavailable_until'] ) && strtotime( (string) $row['unavailable_until'] ) < time() ) {
			self::set( $branch_id, $product_id, true, null, 0 );
			return true;
		}
		return false;
	}

	/**
	 * Set availability and bump menu version.
	 */
	public static function set( int $branch_id, int $product_id, bool $available, ?string $until, int $user_id, string $reason = '' ): void {
		global $wpdb;
		$table = Schema::table( 'flavor_availability' );
		$now   = current_time( 'mysql' );
		$old   = self::row( $branch_id, $product_id );

		$row = array(
			'branch_id'         => $branch_id,
			'product_id'        => $product_id,
			'is_available'      => $available ? 1 : 0,
			'unavailable_until' => $available ? null : $until,
			'reason'            => $reason,
			'updated_by'        => $user_id ?: null,
			'updated_at'        => $now,
		);

		if ( $old ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $row, array( 'id' => (int) $old['id'] ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $row );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			Schema::table( 'flavor_availability_log' ),
			array(
				'branch_id'         => $branch_id,
				'product_id'        => $product_id,
				'old_available'     => $old ? (int) $old['is_available'] : null,
				'new_available'     => $available ? 1 : 0,
				'unavailable_until' => $until,
				'changed_by'        => $user_id ?: null,
				'changed_at'        => $now,
			)
		);

		Settings::bump_menu_version( $branch_id );
	}

	/**
	 * Current row.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function row( int $branch_id, int $product_id ): ?array {
		global $wpdb;
		$table = Schema::table( 'flavor_availability' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE branch_id = %d AND product_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$branch_id,
				$product_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}
}
