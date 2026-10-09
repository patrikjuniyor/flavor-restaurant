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

/**
 * Locate the Flavor Core mock WordPress environment.
 *
 * The theme's tests exercise code that calls get_theme_mod(), add_action()
 * and friends, and the only maintained offline stand-in for those lives in
 * the plugin. In this repository they sit side by side, so the default
 * resolution just works.
 *
 * The theme and the plugin ship as separate packages, and tests are a
 * development artifact rather than something installed on a customer's
 * site — so this is a developer dependency, not a runtime one. It is
 * resolved explicitly here rather than with a bare require, because the
 * default failure mode of a missing file is a PHP fatal that says nothing
 * about what to install. FLAVOR_CORE_DIR overrides it for a checkout where
 * the plugin lives elsewhere.
 *
 * @param string $root Repository root.
 * @return string Absolute path to the mock, or '' when it cannot be found.
 */
function flavor_locate_core_mock( string $root ): string {
	$candidates = array();

	$env = getenv( 'FLAVOR_CORE_DIR' );
	if ( is_string( $env ) && '' !== trim( $env ) ) {
		$candidates[] = rtrim( trim( $env ), '/' ) . '/tests/mock-wp-environment.php';
	}

	$candidates[] = $root . '/flavor-core/tests/mock-wp-environment.php';

	foreach ( $candidates as $candidate ) {
		if ( is_file( $candidate ) ) {
			return $candidate;
		}
	}

	return '';
}

$flavor_core_mock = flavor_locate_core_mock( $flavor_root );

if ( '' === $flavor_core_mock ) {
	fwrite(
		STDERR,
		"Cannot find the Flavor Core mock WordPress environment.\n\n" .
		"The theme's tests need the offline WordPress stand-in that lives in the\n" .
		"Flavor Core plugin's test suite. This repository normally holds both, so a\n" .
		"missing file usually means a partial checkout.\n\n" .
		"Fix one of these ways:\n" .
		"  - clone the plugin alongside the theme:  flavor-core/tests/mock-wp-environment.php\n" .
		"  - or point at it:                         FLAVOR_CORE_DIR=/path/to/flavor-core\n\n" .
		"Tests are a development dependency only; nothing on a live site loads this file.\n"
	);
	exit( 1 );
}

require_once $flavor_core_mock;

if ( ! defined( 'FLAVOR_VERSION' ) ) {
	define( 'FLAVOR_VERSION', '1.6.1' );
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

if ( ! function_exists( 'esc_js' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $text Text.
	 */
	function esc_js( string $text ): string {
		return addslashes( $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Stub: fixtures carry no hostile markup.
	 *
	 * @param string $data Markup.
	 */
	function wp_kses_post( string $data ): string {
		return $data;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Stub for the site-timezone formatter.
	 *
	 * @param string   $format    Date format.
	 * @param int|null $timestamp Timestamp.
	 */
	function wp_date( string $format, ?int $timestamp = null ): string {
		return date( $format, $timestamp ?? time() );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $class    Class.
	 * @param string $fallback Fallback.
	 */
	function sanitize_html_class( string $class, string $fallback = '' ): string {
		$clean = preg_replace( '/[^A-Za-z0-9_-]/', '', $class );
		return '' === $clean ? $fallback : $clean;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 */
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 */
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 */
	function esc_attr_e( string $text, string $domain = 'default' ): void {
		echo htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * The front end is the only surface under test.
	 */
	function is_admin(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_page_template' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $template Template.
	 */
	function is_page_template( string $template = '' ): bool {
		return false;
	}
}

if ( ! function_exists( 'has_nav_menu' ) ) {
	/**
	 * Stub.
	 *
	 * @param string $location Location.
	 */
	function has_nav_menu( string $location ): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_get_nav_menu_object' ) ) {
	/**
	 * Fixture menu: id 7, seeded items in `$GLOBALS['_mock_menu_items']`.
	 *
	 * @param mixed $menu Menu reference.
	 */
	function wp_get_nav_menu_object( $menu ) {
		return is_object( $menu ) ? $menu : (object) array( 'term_id' => 7, 'name' => 'primary' );
	}
}

if ( ! function_exists( 'wp_get_nav_menu_items' ) ) {
	/**
	 * Stub.
	 *
	 * @param mixed $menu Menu.
	 * @param array $args Args.
	 */
	function wp_get_nav_menu_items( $menu, array $args = array() ): array {
		unset( $menu, $args );
		return (array) ( $GLOBALS['_mock_menu_items'] ?? array() );
	}
}

if ( ! function_exists( 'get_privacy_policy_url' ) ) {
	/**
	 * Fixture site has not published a privacy page.
	 */
	function get_privacy_policy_url(): string {
		return '';
	}
}
