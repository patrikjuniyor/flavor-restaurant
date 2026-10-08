<?php
/**
 * Offline bootstrap for theme-level tests.
 *
 * Brings up just enough WordPress surface (via the Flavor Core mock) to
 * exercise the theme's head/manifest code without a WordPress install.
 *
 * @package Flavor
 */

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$flavor_root = dirname( dirname( __DIR__ ) );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $flavor_root . '/' );
}

require_once $flavor_root . '/flavor-core/tests/mock-wp-environment.php';

if ( ! defined( 'FLAVOR_VERSION' ) ) {
	define( 'FLAVOR_VERSION', '1.4.0' );
}
if ( ! defined( 'FLAVOR_DIR' ) ) {
	define( 'FLAVOR_DIR', $flavor_root . '/flavor' );
}
if ( ! defined( 'FLAVOR_URI' ) ) {
	define( 'FLAVOR_URI', 'https://example.test/wp-content/themes/flavor' );
}

if ( ! function_exists( 'is_rtl' ) ) {
	/**
	 * The product is RTL-first; the fixture site is Persian.
	 */
	function is_rtl(): bool {
		return true;
	}
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	/**
	 * Fixture site has no published "menu" page.
	 *
	 * @param string $path Page path.
	 */
	function get_page_by_path( string $path ) {
		return null;
	}
}

if ( ! function_exists( 'has_site_icon' ) ) {
	/**
	 * Fixture site has no Site Icon set (core would own the icon tags).
	 */
	function has_site_icon(): bool {
		return false;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $url URL.
	 */
	function esc_url( string $url ): string {
		return $url;
	}
}
