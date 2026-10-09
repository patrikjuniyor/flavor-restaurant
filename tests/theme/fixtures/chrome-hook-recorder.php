<?php
/**
 * Hook recorder for the chrome tests.
 *
 * It is declared inside the theme's own namespace on purpose: the classes in
 * `flavor/inc/class-*.php` call `add_filter()` unqualified, so a namespaced
 * definition shadows the global one for them and nothing else. That lets the
 * tests see exactly which filters the Customizer validators were bound to and
 * with how many accepted arguments — the detail that silently breaks a
 * validator when it is registered through `add_setting()` instead.
 *
 * @package Flavor\Tests
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'Flavor\add_filter' ) ) {
	/**
	 * Record the registration instead of wiring a real hook.
	 *
	 * @param string   $tag           Hook name.
	 * @param callable|null $callback Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted argument count.
	 * @return bool
	 */
	function add_filter( $tag, $callback = null, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['_flavor_test_filters'][ (string) $tag ][] = array( $callback, (int) $priority, (int) $accepted_args );

		return true;
	}
}
