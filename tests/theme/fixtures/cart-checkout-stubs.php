<?php
/**
 * WordPress page-state doubles for the cart/checkout asset suite.
 *
 * Deliberately in the GLOBAL namespace: UI::assets() calls these unqualified
 * from namespace Flavor, which falls back to the global function only when a
 * global of that name exists. A stub declared inside another namespace is
 * invisible to it.
 *
 * One global switch, $GLOBALS['_t_page'], selects the screen being simulated.
 *
 * @package Flavor
 */

$GLOBALS['_t_page']    = $GLOBALS['_t_page'] ?? 'home';
$GLOBALS['_t_styles']  = $GLOBALS['_t_styles'] ?? array();
$GLOBALS['_t_scripts'] = $GLOBALS['_t_scripts'] ?? array();

if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool { return 'home' === $GLOBALS['_t_page']; }
}
if ( ! function_exists( 'is_home' ) ) {
	function is_home(): bool { return false; }
}
if ( ! function_exists( 'is_cart' ) ) {
	function is_cart(): bool { return 'cart' === $GLOBALS['_t_page']; }
}
if ( ! function_exists( 'is_checkout' ) ) {
	function is_checkout(): bool { return 'checkout' === $GLOBALS['_t_page']; }
}
if ( ! function_exists( 'is_account_page' ) ) {
	function is_account_page(): bool { return 'account' === $GLOBALS['_t_page']; }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, $src = '', array $deps = array(), $ver = false ): void {
		$GLOBALS['_t_styles'][ $handle ] = $deps;
	}
}
if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( string $handle, string $data ): bool { return true; }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, $src = '', array $deps = array(), $ver = false, $args = array() ): void {
		$GLOBALS['_t_scripts'][ $handle ] = $deps;
	}
}
if ( ! function_exists( 'wp_style_is' ) ) {
	function wp_style_is( string $handle, string $type = 'enqueued' ): bool {
		return isset( $GLOBALS['_t_styles'][ $handle ] );
	}
}
