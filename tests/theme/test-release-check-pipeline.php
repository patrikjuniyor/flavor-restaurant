<?php
/**
 * Test contract for Release Check Workflow (پرامپت ۸۵ / فاز ۱۰).
 *
 * Verifies:
 *   1. .github/workflows/release-check.yml exists and triggers on tag push 'v*' and workflow_dispatch.
 *   2. Workflow enforces top-level 'permissions: contents: read'.
 *   3. All action uses entries are pinned to 40-character commit SHAs.
 *   4. Step 1: verifies tag version against check-versions.php and style.css.
 *   5. Step 2-6: runs all test suites (pure, e2e, theme, mobile provision, Flutter test/coverage).
 *   6. Step 7: runs build-distribution.sh and checks both zip files (flavor.zip & flavor-demo-pack.zip).
 *   7. Step 8: verifies update manifest signature with public key only (no private keys in CI).
 *   8. Step 9: uploads distribution packages to artifact.
 *
 *   php tests/theme/test-release-check-pipeline.php
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

echo "=== Release Check Workflow Contract Tests (Prompt 85) ===\n\n";

$workflow_path = $root . '/.github/workflows/release-check.yml';
check( file_exists( $workflow_path ), 'release-check.yml exists' );
$content = file_get_contents( $workflow_path );

// 1. Triggers
check(
	strpos( $content, "tags:\n      - 'v*'" ) !== false || strpos( $content, "tags: ['v*']" ) !== false || preg_match( "/tags:\s*\n\s+-\s*['\"]v\*['\"]/", $content ),
	'triggers on tags matching v*'
);
check(
	strpos( $content, 'workflow_dispatch:' ) !== false,
	'supports manual workflow_dispatch'
);

// 2. Permissions & Concurrency
check(
	preg_match( '/\npermissions:\s*\n\s+contents:\s*read/', $content ) === 1,
	'enforces top-level minimal permissions: contents: read'
);
check(
	strpos( $content, 'concurrency:' ) !== false,
	'defines concurrency group'
);

// 3. Pinned SHAs
preg_match_all( '/uses:\s*([^\s]+)/', $content, $matches );
$all_pinned = true;
$unpinned = [];
foreach ( $matches[1] as $ref ) {
	if ( ! preg_match( '/@[0-9a-f]{40}/', $ref ) ) {
		$all_pinned = false;
		$unpinned[] = $ref;
	}
}
check(
	$all_pinned && count( $matches[1] ) > 0,
	'all action references are pinned with 40-character commit SHAs'
);

// 4. Version parity
check(
	strpos( $content, 'check-versions.php' ) !== false,
	'executes dev-tools/check-versions.php'
);
check(
	strpos( $content, 'style.css' ) !== false,
	'validates tag version directly against style.css'
);

// 5. Test battery execution
check(
	strpos( $content, 'run-pure-tests.php' ) !== false,
	'runs core pure logic tests'
);
check(
	strpos( $content, 'run-e2e-integration.php' ) !== false,
	'runs core e2e integration tests'
);
check(
	strpos( $content, 'run-theme-tests.sh' ) !== false,
	'runs theme regression test suite'
);
check(
	strpos( $content, 'test_provision_brand.py' ) !== false,
	'runs white-label brand provisioning tests'
);
check(
	strpos( $content, 'flutter test --coverage' ) !== false && strpos( $content, 'check-mobile-coverage.py' ) !== false,
	'runs mobile test suite with 60% coverage gate'
);

// 6. Distribution build & zip validation
check(
	strpos( $content, 'build-distribution.sh' ) !== false,
	'runs build-distribution.sh'
);
check(
	strpos( $content, 'dist/flavor.zip' ) !== false && strpos( $content, 'dist/flavor-demo-pack.zip' ) !== false,
	'checks existence of both flavor.zip and flavor-demo-pack.zip'
);

// 7. Safe manifest verification (public key only)
check(
	strpos( $content, 'build-release.php verify' ) !== false,
	'verifies update manifest with build-release.php'
);
check(
	strpos( $content, 'public.key' ) !== false && strpos( $content, 'secret' ) === false,
	'relies strictly on committed public.key with zero secret keys in CI'
);

// 8. Artifact upload
check(
	strpos( $content, 'actions/upload-artifact@' ) !== false && strpos( $content, 'flavor-release-distribution' ) !== false,
	'uploads flavor-release-distribution artifact'
);

// 9. Local verification of distribution builder & manifest verifier
$dist_script = $root . '/dev-tools/build-distribution.sh';
check( is_executable( $dist_script ), 'dev-tools/build-distribution.sh is executable' );

$output = [];
$exit_code = 0;
exec( escapeshellcmd( "bash {$dist_script}" ), $output, $exit_code );
check( 0 === $exit_code, 'dev-tools/build-distribution.sh runs cleanly' );
check( file_exists( $root . '/dist/flavor.zip' ), 'dist/flavor.zip was generated' );
check( file_exists( $root . '/dist/flavor-demo-pack.zip' ), 'dist/flavor-demo-pack.zip was generated' );

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
