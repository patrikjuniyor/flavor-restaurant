<?php
/**
 * WP-Cron hook for reservation reminders.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reservation;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReminderCron
 */
class ReminderCron {

	public const HOOK = 'flavor_core_reservation_reminders';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( self::HOOK, array( ReservationService::class, 'send_reminders' ) );
		add_action( 'init', array( $this, 'schedule' ) );
	}

	/**
	 * Ensure hourly event exists.
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Clear on deactivation.
	 */
	public static function clear(): void {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}
}
