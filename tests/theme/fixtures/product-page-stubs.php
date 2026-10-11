<?php
/**
 * WordPress page-state doubles for the single-product suite.
 *
 * Same trick as cart-checkout-stubs.php, and the same reason: the declarations
 * have to sit in the GLOBAL namespace, because Flavor\Product_Page calls
 * is_product() and wp_enqueue_style() unqualified from namespace Flavor, and PHP
 * only falls back to a global function when that global exists.
 *
 * $GLOBALS['_p_page'] selects the screen being simulated.
 *
 * @package Flavor
 */

$GLOBALS['_p_page']   = $GLOBALS['_p_page'] ?? 'other';
$GLOBALS['_p_styles'] = $GLOBALS['_p_styles'] ?? array();

if ( ! function_exists( 'is_product' ) ) {
	function is_product(): bool { return 'product' === $GLOBALS['_p_page']; }
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_type = '' ): bool { return 'product' === $GLOBALS['_p_page']; }
}
if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool { return false; }
}
if ( ! function_exists( 'is_home' ) ) {
	function is_home(): bool { return false; }
}
if ( ! function_exists( 'is_account_page' ) ) {
	function is_account_page(): bool { return false; }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, $src = '', array $deps = array(), $ver = false ): void {
		$GLOBALS['_p_styles'][ $handle ] = $deps;
	}
}
