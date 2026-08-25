<?php
/**
 * Runs on plugin activation.
 *
 * @package FlavorCore
 */

namespace FlavorCore;

use FlavorCore\Database\Schema;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
class Activator {

	/**
	 * Activation callback. Keep this fast and idempotent.
	 *
	 * @param bool $network_wide Whether the plugin is network-activated.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			$site_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				restore_current_blog();
			}
			return;
		}

		self::activate_site();
	}

	/**
	 * Single-site activation work.
	 */
	private static function activate_site(): void {
		Schema::install();
		Roles::register();

		if ( false === get_option( 'flavor_core_settings', false ) ) {
			add_option( 'flavor_core_settings', self::default_settings() );
		}

		if ( false === get_option( 'flavor_core_remove_data', false ) ) {
			add_option( 'flavor_core_remove_data', 'no' );
		}

		self::seed_default_schedules();
		self::maybe_seed_default_branch();

		flush_rewrite_rules();
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'currency_storage'      => 'irr',
			'currency_display'      => 'irt',
			'digits'                => 'persian',
			'otp_length'            => 5,
			'otp_ttl_minutes'       => 2,
			'otp_max_per_10min'     => 3,
			'reservation_buffer'    => 30,
			'kitchen_poll_seconds'  => 15,
			'default_branch_id'     => 0,
			'pay_at_counter'        => 'yes',
			'cash_on_delivery'      => 'yes',
			'guest_checkout'        => 'yes',
		);
	}

	/**
	 * Global breakfast/lunch/dinner/late-night windows (branch_id = 0).
	 */
	private static function seed_default_schedules(): void {
		global $wpdb;

		$table = Schema::table( 'flavor_menu_schedules' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $exists > 0 ) {
			return;
		}

		$now  = current_time( 'mysql' );
		$days = wp_json_encode( array( 0, 1, 2, 3, 4, 5, 6 ) );

		$rows = array(
			array( 'صبحانه', 'breakfast', '07:00:00', '11:00:00' ),
			array( 'ناهار', 'lunch', '11:30:00', '16:00:00' ),
			array( 'شام', 'dinner', '18:00:00', '23:00:00' ),
			array( 'دیرهنگام', 'late_night', '23:00:00', '02:00:00' ),
		);

		foreach ( $rows as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'branch_id'  => 0,
					'name'       => $row[0],
					'slug'       => $row[1],
					'start_time' => $row[2],
					'end_time'   => $row[3],
					'days_json'  => $days,
					'is_active'  => 1,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Create a draft default branch so single-location shops are not empty.
	 * Deferred if the CPT is not registered yet (first activation).
	 */
	private static function maybe_seed_default_branch(): void {
		update_option( 'flavor_core_need_default_branch', 'yes', false );
	}
}
