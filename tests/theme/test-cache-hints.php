<?php
/**
 * Test contract for CacheHints (پرامپت ۷۷).
 *
 * Verifies:
 *   1. Cache exclusions: URIs list covers customer and dynamic endpoints
 *   2. Rocket filter integration: rejects all critical URIs
 *   3. Immutable caching: static assets (demo images, versioned CSS/JS, fonts) get public, max-age, immutable
 *   4. Customer pages: cart, checkout, account, table ordering get strictly no-store
 *   5. Public pages: get stale-while-revalidate with configurable max-age
 *   6. Admin pages & logged-in users: get private or no-store
 *   7. Filter hook verification: wp_headers decorates Cache-Control appropriately
 *
 *   php tests/theme/test-cache-hints.php
 *
 * @package FlavorCore
 */

$root = dirname( __DIR__, 2 );

define( 'ABSPATH', $root . '/flavor-core/' );
require_once $root . '/flavor-core/tests/mock-wp-environment.php';
require_once $root . '/flavor-core/includes/Support/CacheHints.php';

use FlavorCore\Support\CacheHints;

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
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

echo "=== 1. URIs Exclusion List & WP Rocket Filter ===\n";

$uris = CacheHints::uris();
check( in_array( '/cart', $uris, true ), 'CacheHints excludes /cart' );
check( in_array( '/checkout', $uris, true ), 'CacheHints excludes /checkout' );
check( in_array( '/my-account', $uris, true ), 'CacheHints excludes /my-account' );
check( in_array( '/kitchen-dashboard', $uris, true ), 'CacheHints excludes /kitchen-dashboard' );
check( in_array( '/kitchen-receipt', $uris, true ), 'CacheHints excludes /kitchen-receipt' );
check( in_array( '/branch/.*/table/', $uris, true ), 'CacheHints excludes table orders pattern' );
check( in_array( '/wp-json/flavor/', $uris, true ), 'CacheHints excludes /wp-json/flavor/' );

$hints = new CacheHints();
$rocket_uris = $hints->rocket( array( '/some-custom-page' ) );
check( in_array( '/some-custom-page', $rocket_uris, true ) && in_array( '/cart', $rocket_uris, true ), 'rocket filter appends CacheHints::uris()' );

echo "\n=== 2. Static Immutable Asset Identification ===\n";

check( CacheHints::is_immutable_asset( '/wp-content/themes/flavor/demos/pack1/hero.jpg' ), 'demo images in demos/ are marked immutable' );
check( CacheHints::is_immutable_asset( 'https://example.com/flavor-demo-pack/category.png' ), 'demo images in flavor-demo-pack/ are marked immutable' );
check( CacheHints::is_immutable_asset( '/assets/css/style.css?ver=1.6.2' ), 'versioned CSS with ver= is marked immutable' );
check( CacheHints::is_immutable_asset( '/assets/js/bundle.1.6.2.js' ), 'bundle with version number is marked immutable' );
check( CacheHints::is_immutable_asset( '/assets/fonts/vazirmatn.woff2' ), 'font files (.woff2) are marked immutable' );
check( ! CacheHints::is_immutable_asset( '/dynamic-page/' ), 'regular page is not marked immutable' );
check( ! CacheHints::is_immutable_asset( '/assets/css/style.css' ), 'unversioned plain CSS is not marked immutable' );

echo "\n=== 3. Customer Pages Detection (no-store) ===\n";

check( CacheHints::is_customer_page( '/cart' ), 'path /cart identified as customer page' );
check( CacheHints::is_customer_page( '/checkout/' ), 'path /checkout/ identified as customer page' );
check( CacheHints::is_customer_page( '/my-account/orders/' ), 'path /my-account/... identified as customer page' );
check( CacheHints::is_customer_page( '/kitchen-dashboard' ), 'path /kitchen-dashboard identified as dynamic/customer page' );
check( CacheHints::is_customer_page( '/branch/tehran/table/5' ), 'table order URL identified as customer page' );
check( ! CacheHints::is_customer_page( '/menu' ), 'public /menu is NOT a customer page' );
check( ! CacheHints::is_customer_page( '/' ), 'home page is NOT a customer page' );

echo "\n=== 4. Cache-Control Header Policy Computation ===\n";

// A. Immutable static asset
$cc_immutable = CacheHints::compute_cache_control( array( 'is_immutable' => true ) );
check( str_contains( $cc_immutable, 'public' ) && str_contains( $cc_immutable, 'immutable' ) && str_contains( $cc_immutable, 'max-age=31536000' ), 'immutable static asset gets public, max-age=31536000, immutable' );

// B. Customer dynamic page
$cc_customer = CacheHints::compute_cache_control( array( 'is_customer' => true ) );
check( str_contains( $cc_customer, 'no-store' ) && str_contains( $cc_customer, 'must-revalidate' ), 'customer page gets strict no-store, no-cache, must-revalidate' );

// C. Admin page
$cc_admin = CacheHints::compute_cache_control( array( 'is_admin' => true ) );
check( str_contains( $cc_admin, 'private, no-cache' ), 'admin page gets private, no-cache' );

// D. Logged-in user
$cc_logged_in = CacheHints::compute_cache_control( array( 'is_logged_in' => true ) );
check( str_contains( $cc_logged_in, 'no-store' ), 'logged-in user request gets no-store' );

// E. Public storefront page
$cc_public = CacheHints::compute_cache_control( array() );
check( str_contains( $cc_public, 'public' ) && str_contains( $cc_public, 'stale-while-revalidate=' ), 'public storefront page gets stale-while-revalidate' );

echo "\n=== 5. wp_headers Filter Contract ===\n";

// Case 1: customer page through wp_headers
$_SERVER['REQUEST_URI'] = '/cart';
$h_customer = $hints->filter_wp_headers( array() );
check( isset( $h_customer['Cache-Control'] ) && str_contains( $h_customer['Cache-Control'], 'no-store' ), 'wp_headers on customer page enforces no-store' );
check( isset( $h_customer['Pragma'] ) && $h_customer['Pragma'] === 'no-cache', 'wp_headers on customer page sets Pragma: no-cache' );

// Case 2: public page through wp_headers
$_SERVER['REQUEST_URI'] = '/about-us';
$h_public = $hints->filter_wp_headers( array() );
check( isset( $h_public['Cache-Control'] ) && str_contains( $h_public['Cache-Control'], 'stale-while-revalidate' ), 'wp_headers on public page sets stale-while-revalidate' );

// Case 3: admin page through wp_headers
$GLOBALS['_test_is_admin'] = true;
$h_admin = $hints->filter_wp_headers( array( 'X-Custom' => '1' ) );
$GLOBALS['_test_is_admin'] = false;
check( ! isset( $h_admin['Cache-Control'] ), 'wp_headers leaves admin Cache-Control untouched' );

if ( $failed > 0 ) {
	echo "\n{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}

echo "\nALL {$passed} checks passed.\n";
exit( 0 );
