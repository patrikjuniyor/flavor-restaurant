<?php
/**
 * Cart and checkout asset contract (M-06).
 *
 * The earlier stub-based suites never built a page, so they could not tell
 * whether the cart and checkout screens received the Flavor checkout styling.
 * This suite fakes the page state and records what UI::assets() enqueues.
 *
 * Guards: the cart and checkout screens load ui-checkout.css after ui.css,
 * the body gets the flavor-ui hook class, and an ordinary page does not
 * receive the checkout sheet (so the negative case is real, not vacuous).
 *
 *   php tests/theme/test-cart-checkout-assets.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';

use Flavor\UI;

$passed = 0;
$failed = 0;

function check( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

/* Page-state doubles live in a global-namespace fixture; see its header. */
require_once __DIR__ . '/fixtures/cart-checkout-stubs.php';

$GLOBALS['_t_page']   = 'home';

/**
 * Renders the asset phase for one page and returns what it enqueued.
 *
 * @param string $page home|cart|checkout|account.
 * @return array{styles: array, body: array}
 */
function simulate( string $page ): array {
	$GLOBALS['_t_page']   = $page;
	$GLOBALS['_t_styles'] = array();
	UI::assets();
	return array(
		'styles' => $GLOBALS['_t_styles'],
		'body'   => UI::body_class( array() ),
	);
}

echo "\n--- 1. The cart screen loads the checkout sheet ---\n";
$cart = simulate( 'cart' );
check( isset( $cart['styles']['flavor-ui-checkout'] ), 'cart enqueues flavor-ui-checkout' );
check( isset( $cart['styles']['flavor-ui'] ), 'cart enqueues the base flavor-ui sheet' );
/* The sheets chain: ui -> ui-menu -> ui-checkout. Follow the chain rather than
   assume a direct edge, so the test states the real property (checkout loads
   after the base sheet), not one arbitrary link in the middle. */
$reaches_base = false;
$node         = 'flavor-ui-checkout';
for ( $hop = 0; $hop < 5 && isset( $cart['styles'][ $node ] ); $hop++ ) {
	$parents = $cart['styles'][ $node ];
	if ( in_array( 'flavor-ui', $parents, true ) ) {
		$reaches_base = true;
		break;
	}
	$node = $parents[0] ?? '';
}
check( $reaches_base, 'checkout sheet is ordered after flavor-ui through its dependency chain' );
check( in_array( 'flavor-ui', $cart['body'], true ), 'cart body carries the flavor-ui hook class' );

echo "\n--- 2. The checkout screen loads the checkout sheet ---\n";
$checkout = simulate( 'checkout' );
check( isset( $checkout['styles']['flavor-ui-checkout'] ), 'checkout enqueues flavor-ui-checkout' );
check( in_array( 'flavor-ui', $checkout['body'], true ), 'checkout body carries the flavor-ui hook class' );

echo "\n--- 3. Negative: the home page does not get the checkout sheet ---\n";
$home = simulate( 'home' );
check( ! isset( $home['styles']['flavor-ui-checkout'] ), 'home does not enqueue flavor-ui-checkout' );
check( ! in_array( 'flavor-ui', $home['body'], true ), 'home body does not carry flavor-ui' );

echo "\n--- 4. The shipped stylesheet exists and targets WooCommerce screens ---\n";
$css_path = FLAVOR_DIR . '/assets/css/ui-checkout.css';
check( is_file( $css_path ) && filesize( $css_path ) > 0, 'ui-checkout.css exists and is not empty' );
$css = is_file( $css_path ) ? (string) file_get_contents( $css_path ) : '';
check( false !== strpos( $css, 'woocommerce' ), 'ui-checkout.css contains WooCommerce selectors' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
