<?php
/**
 * PSR-4 autoloader for the FlavorCore namespace.
 *
 * Production hosts should not be required to run Composer.
 *
 * @package FlavorCore
 */

namespace FlavorCore;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Namespace prefix.
	 */
	private const PREFIX = 'FlavorCore\\';

	/**
	 * Register the autoloader on the SPL stack.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class file if it belongs to this plugin.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$path     = FLAVOR_CORE_PATH . 'includes/' . $relative . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
