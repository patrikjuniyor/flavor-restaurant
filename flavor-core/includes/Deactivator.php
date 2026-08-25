<?php
/**
 * Runs on plugin deactivation. Data is kept.
 *
 * @package FlavorCore
 */

namespace FlavorCore;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Deactivation callback.
	 */
	public static function deactivate(): void {
		\FlavorCore\Reservation\ReminderCron::clear();
		flush_rewrite_rules();
	}
}
