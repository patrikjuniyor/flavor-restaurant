<?php
/**
 * Elementor Kit sync contract tests.
 *
 * The write path needs a live Elementor kit and is verified against real
 * Elementor 4.3.4 on a scratch site (see docs/fa/11-elementor.md). These
 * tests pin the parts that can be checked without it: the mapping must only
 * point at real design tokens, must cover the system globals Flavor promises,
 * and must do nothing when Elementor is not active.
 *
 *   php tests/theme/test-elementor-kit-sync.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/elementor/class-kit-sync.php';

use Flavor\Design;
use Flavor\Elementor\Kit_Sync;

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

echo "Elementor Kit sync\n";

$map = Kit_Sync::map();

// Every mapped token must exist in the resolved token set of every preset.
$tokens_ok = true;
$missing   = array();
foreach ( array_keys( Design::skins() ) as $skin ) {
	$tokens = Design::tokens( $skin );
	foreach ( array( 'colors', 'custom', 'fonts' ) as $group ) {
		foreach ( $map[ $group ] as $id => $token ) {
			if ( ! array_key_exists( $token, $tokens ) ) {
				$tokens_ok = false;
				$missing[] = "{$skin}:{$token}";
			}
		}
	}
}
check( $tokens_ok, 'every mapped token exists in every preset' . ( $missing ? ' — ' . implode( ', ', array_slice( $missing, 0, 3 ) ) : '' ) );

// The four Elementor system colours must all be covered.
$system_ids = array_keys( $map['colors'] );
check(
	array( 'primary', 'secondary', 'text', 'accent' ) === $system_ids,
	'system colours cover primary, secondary, text and accent'
);

// Fonts: heading and body only.
check(
	array( 'primary' => 'font_heading', 'text' => 'font_body' ) === $map['fonts'],
	'fonts map heading to primary and body to text'
);

// Custom ids must be the flavor-* namespace so they cannot clash with the
// Elementor defaults or with user-created globals.
$custom_ok = true;
foreach ( array_keys( $map['custom'] ) as $id ) {
	if ( 0 !== strpos( $id, 'flavor-' ) ) {
		$custom_ok = false;
	}
}
check( $custom_ok, 'custom colour ids use the flavor- prefix' );

// Without Elementor the sync is a no-op, not a fatal.
check( false === Kit_Sync::available(), 'available() is false when Elementor is not loaded' );
check(
	array( 'status' => 'elementor_inactive' ) === Kit_Sync::sync(),
	'sync() returns elementor_inactive without Elementor'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
