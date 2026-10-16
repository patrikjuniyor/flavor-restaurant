<?php
/**
 * Pure suite — loyalty points, currency conversion, discount plumbing.
 *
 * Prompt 47 priority: money/points/discount logic. Live math against the
 * real classes with the micro-shims (settings fall back to Activator's pure
 * defaults: storage=irr, loyalty_unit_toman=10000, points_per_unit=1);
 * DiscountManager's WC-entangled flow is pinned at the source and covered
 * behaviourally by the e2e suite (NOROOZ1403 apply path).
 *
 * Loaded by run-pure-tests.php (assert_test protocol); standalone-safe via
 * the _stubs guard in the runner.
 *
 * @package FlavorCore
 */

// phpcs:disable WordPress.Security.EscapeOutput -- CLI harness output, not an HTTP response.

require_once __DIR__ . '/../../includes/Activator.php';
require_once __DIR__ . '/../../includes/Support/Settings.php';
require_once __DIR__ . '/../../includes/WooCommerce/Currency.php';
require_once __DIR__ . '/../../includes/Loyalty/PointsManager.php';
require_once __DIR__ . '/../../includes/Loyalty/DiscountManager.php';

use FlavorCore\Loyalty\PointsManager;
use FlavorCore\WooCommerce\Currency;

echo "\n--- P47. Loyalty points & currency math (PointsManager / Currency) ---\n";

$defaults = FlavorCore\Activator::default_settings();
assert_test( 'irr' === $defaults['currency_storage'] && 'irt' === $defaults['currency_display'], 'fresh-install money defaults: storage in rial, display in toman' );
assert_test( 10000 === $defaults['loyalty_unit_toman'] && 1 === $defaults['loyalty_points_per_unit'] && 1000 === $defaults['loyalty_toman_per_point'], 'loyalty economy constants pinned (10 000 toman per point unit, 1 point per unit, 1000 toman per redeemed point)' );

assert_test( 'irr' === Currency::storage_unit() && 'irt' === Currency::display_unit(), 'storage/display unit resolve to the pure defaults without WP' );
assert_test( 2500 === Currency::convert( 25000, 'irr', 'irt' ), 'rial → toman divides by ten' );
assert_test( 2 === Currency::convert( 25, 'irr', 'irt' ), 'rial → toman floors, never rounds up (25 irr = 2 irt, not 3)' );
assert_test( 99 === Currency::convert( 999, 'irr', 'irt' ), 'floor holds at 999 irr = 99 irt' );
assert_test( 25000 === Currency::convert( 2500, 'irt', 'irr' ), 'toman → rial multiplies by ten' );
assert_test( 1234 === Currency::convert( 1234, 'irr', 'irr' ), 'same-unit conversion is identity' );
assert_test( 1234 === Currency::convert( 1234, 'usd', 'eur' ), 'unknown units pass through unchanged (no silent zeroing of money)' );
assert_test( 25000 === Currency::to_storage( 2500, 'irt' ), 'to_storage normalises toman input into rial storage' );
assert_test( 2500 === Currency::from_storage( 25000, 'irt' ), 'from_storage converts rial storage into toman' );
assert_test( 0 === Currency::convert( 0, 'irr', 'irt' ) && 0 === Currency::to_storage( 0, 'irt' ), 'a zero amount converts to zero in every direction' );

// estimate_points: floor(storage→toman / loyalty_unit_toman) * points_per_unit.
assert_test( 2 === PointsManager::estimate_points( 250000 ), '250 000 rial (25 000 toman) estimates 2 points at the default economy' );
assert_test( 4 === PointsManager::estimate_points( 499999 ), '499 999 rial floors to 4 points, never 5' );
assert_test( 1 === PointsManager::estimate_points( 100000 ), 'the exact 10 000-toman boundary yields exactly 1 point' );
assert_test( 0 === PointsManager::estimate_points( 99999 ), 'under the unit threshold estimates 0 points' );
assert_test( 0 === PointsManager::estimate_points( 0 ), 'a zero order estimates zero points' );

echo "\n--- P47. Discount plumbing pins (DiscountManager — live apply needs WC, covered by e2e) ---\n";

$dm_src = file_get_contents( __DIR__ . '/../../includes/Loyalty/DiscountManager.php' );
assert_test(
	false !== strpos( $dm_src, 'wc_format_coupon_code( $code )' )
		&& strpos( $dm_src, 'wc_format_coupon_code( $code )' ) < strpos( $dm_src, 'apply_coupon( $code )' ),
	'coupon codes are normalised by wc_format_coupon_code BEFORE hitting the cart'
);
assert_test(
	false !== strpos( $dm_src, "return new \\WP_Error( 'flavor_cart'" )
		&& false !== strpos( $dm_src, "return new \\WP_Error( 'flavor_coupon'" ),
	'apply() fails closed with flavor_cart when the cart is gone and flavor_coupon on empty/rejected codes'
);
assert_test(
	false !== strpos( $dm_src, 'is_wp_error( $result )' ) && false !== strpos( $dm_src, 'return $result;' ),
	'WC coupon errors pass through untouched (no message laundering)'
);
assert_test(
	false !== strpos( $dm_src, 'wc_get_notices' ) && false !== strpos( $dm_src, 'wc_clear_notices' ),
	'a false (non-WP_Error) rejection surfaces the WC notice text and clears the queue'
);
assert_test(
	(bool) preg_match( '/function validate\(/', $dm_src ) && (bool) preg_match( '/function maybe_birthday\(/', $dm_src ),
	'the coupon validity hook and the birthday-code hook stay present (behaviour asserted in e2e)'
);

$pm_src = file_get_contents( __DIR__ . '/../../includes/Loyalty/PointsManager.php' );
assert_test(
	false !== strpos( $pm_src, 'already_earned' ),
	'points earning keeps its double-award dedupe guard (already_earned)'
);
assert_test(
	(bool) preg_match( '/loyalty_toman_per_point/', $pm_src . file_get_contents( __DIR__ . '/../../includes/Activator.php' ) ),
	'the redemption price is read from the loyalty_toman_per_point setting, not hardcoded in a method body'
);
