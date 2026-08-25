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
		flush_rewrite_rules();
	}
}
