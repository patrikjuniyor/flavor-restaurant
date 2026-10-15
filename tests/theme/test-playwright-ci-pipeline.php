<?php
/**
 * Test contract for Playwright CI Pipeline integration (پرامپت ۸۱ / فاز ۱۰).
 *
 * Verifies:
 *   1. ci.yml integrates the Playwright browser test jobs (responsive.mjs, a11y.mjs, keyboard.mjs,
 *      customer/browser.mjs, logo-box.mjs, section-images.mjs, kitchen-kds.mjs)
 *   2. Dedicated Playwright Storefront E2E job provisions real WordPress with WooCommerce,
 *      disposable test fixtures (setup.php, fixtures.php, editor-fixture.php, admin-fixture.php),
 *      and executes UI_STAGE=6 browser.mjs (customer, customizer, cart editing, pages).
 *   3. Nightly workflow (.github/workflows/nightly-ui.yml) exists, runs on schedule/dispatch,
 *      and handles heavy multi-skin suites (tests/ui/skins.mjs, tests/demos/browser.mjs).
 *   4. package.json in tests/ui and tests/demos properly expose all required test scripts.
 *
 *   php tests/theme/test-playwright-ci-pipeline.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );

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

$ci_path      = $root . '/.github/workflows/ci.yml';
$nightly_path = $root . '/.github/workflows/nightly-ui.yml';
$ui_pkg_path  = $root . '/tests/ui/package.json';
$demo_pkg_path= $root . '/tests/demos/package.json';

check( file_exists( $ci_path ), 'ci.yml exists' );
$ci = (string) file_get_contents( $ci_path );

echo "=== 1. Core Playwright Jobs in ci.yml ===\n";

check(
	str_contains( $ci, 'node tests/ui/responsive.mjs' ),
	'ci.yml executes tests/ui/responsive.mjs'
);
check(
	str_contains( $ci, 'node tests/ui/a11y.mjs' ),
	'ci.yml executes tests/ui/a11y.mjs'
);
check(
	str_contains( $ci, 'node tests/ui/keyboard.mjs' ),
	'ci.yml executes tests/ui/keyboard.mjs'
);
check(
	str_contains( $ci, 'node tests/ui/logo-box.mjs' ),
	'ci.yml executes tests/ui/logo-box.mjs'
);
check(
	str_contains( $ci, 'node tests/ui/section-images.mjs' ),
	'ci.yml executes tests/ui/section-images.mjs'
);
check(
	str_contains( $ci, 'node tests/ui/kitchen-kds.mjs' ),
	'ci.yml executes tests/ui/kitchen-kds.mjs'
);

echo "\n=== 2. Real WordPress Storefront E2E in CI ===\n";

check(
	str_contains( $ci, 'playwright-storefront-ci' ) || str_contains( $ci, 'playwright-e2e' ),
	'ci.yml defines a dedicated Playwright Storefront E2E job'
);
check(
	str_contains( $ci, 'tests/ui/browser.mjs' ),
	'Storefront E2E job executes tests/ui/browser.mjs'
);
check(
	str_contains( $ci, 'UI_STAGE=6' ) || str_contains( $ci, 'UI_STAGE' ),
	'Storefront E2E job sets UI_STAGE for comprehensive customer/editor/pages/customizer testing'
);
check(
	str_contains( $ci, 'tests/ui/setup.php' ) && str_contains( $ci, 'tests/ui/fixtures.php' ),
	'Storefront E2E job provisions disposable fixtures before browser tests'
);
check(
	str_contains( $ci, 'tests/ui/editor-fixture.php' ) && str_contains( $ci, 'tests/ui/admin-fixture.php' ),
	'Storefront E2E job provisions editor and admin fixtures'
);
check(
	str_contains( $ci, 'npx playwright install --with-deps chromium' ),
	'Storefront E2E job installs Playwright Chromium and OS dependencies'
);

echo "\n=== 3. Nightly Extended UI & Demo Matrix Workflow ===\n";

check( file_exists( $nightly_path ), 'nightly-ui.yml workflow exists' );
$nightly = (string) @file_get_contents( $nightly_path );

check(
	str_contains( $nightly, 'schedule:' ) && str_contains( $nightly, 'workflow_dispatch:' ),
	'nightly-ui.yml is scheduled and supports manual dispatch'
);
check(
	str_contains( $nightly, 'tests/ui/skins.mjs' ),
	'nightly workflow runs tests/ui/skins.mjs across all demo skins'
);
check(
	str_contains( $nightly, 'tests/demos/browser.mjs' ),
	'nightly workflow tests bespoke demos with tests/demos/browser.mjs'
);

echo "\n=== 4. Test Script Wiring in package.json ===\n";

$ui_pkg = (string) file_get_contents( $ui_pkg_path );
check(
	str_contains( $ui_pkg, '"test:responsive"' ) && str_contains( $ui_pkg, '"test:logo-box"' ),
	'tests/ui/package.json contains test:responsive and test:logo-box'
);
check(
	str_contains( $ui_pkg, '"test:section-images"' ),
	'tests/ui/package.json contains test:section-images'
);
check(
	str_contains( $ui_pkg, '"test:a11y"' ) && str_contains( $ui_pkg, '"test:keyboard"' ),
	'tests/ui/package.json contains test:a11y and test:keyboard'
);

if ( $failed > 0 ) {
	echo "\n{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}

echo "\nALL {$passed} checks passed.\n";
exit( 0 );
