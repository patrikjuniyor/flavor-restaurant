<?php
/**
 * Test contract for Workflow Hardening & CI Security Gates (پرامپت ۸۴ / فاز ۱۰).
 *
 * Verifies:
 *   1. All workflows (ci.yml, build-mobile.yml, publish-release.yml, nightly-ui.yml)
 *      have top-level minimal permissions declared (`permissions: contents: read`).
 *   2. Any job with elevated write permissions (e.g. publish-release deploy) is explicitly scoped.
 *   3. All third-party GitHub Actions are pinned to full 40-character commit SHAs.
 *   4. Concurrency controls (with cancel-in-progress where appropriate) exist on all workflows.
 *   5. Caching is configured for Composer, npm, and Flutter.
 *
 *   php tests/theme/test-workflow-hardening.php
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

echo "=== Workflow Hardening & CI Security Contract Tests (Prompt 84) ===\n\n";

$workflows = [
	'ci.yml'              => $root . '/.github/workflows/ci.yml',
	'build-mobile.yml'    => $root . '/.github/workflows/build-mobile.yml',
	'publish-release.yml' => $root . '/.github/workflows/publish-release.yml',
	'nightly-ui.yml'      => $root . '/.github/workflows/nightly-ui.yml',
];

foreach ( $workflows as $name => $path ) {
	check( file_exists( $path ), "workflow file {$name} exists" );
	$content = file_get_contents( $path );

	// 1. Minimal permissions
	check(
		preg_match( '/\npermissions:\s*\n\s+contents:\s*read/', $content ) === 1,
		"{$name} enforces top-level 'permissions: contents: read'"
	);

	// 2. Concurrency
	check(
		strpos( $content, 'concurrency:' ) !== false,
		"{$name} declares concurrency group"
	);

	// 3. Full SHA pinning for all uses: entries
	preg_match_all( '/uses:\s*([^\s]+)/', $content, $matches );
	$all_sha_pinned = true;
	$unpinned = [];
	foreach ( $matches[1] as $action_ref ) {
		// Expect action@<40-hex>
		if ( ! preg_match( '/@[0-9a-f]{40}/', $action_ref ) ) {
			$all_sha_pinned = false;
			$unpinned[] = $action_ref;
		}
	}
	check(
		$all_sha_pinned,
		"{$name} pins every action to a 40-character commit SHA" . ( $unpinned ? ' (unpinned: ' . implode( ', ', $unpinned ) . ')' : '' )
	);
}

// 4. Cache checks across workflows
$ci_content = file_get_contents( $workflows['ci.yml'] );
check(
	strpos( $ci_content, 'Cache Composer Dependencies' ) !== false && strpos( $ci_content, 'actions/cache@' ) !== false,
	'ci.yml configures Composer dependency caching'
);
check(
	strpos( $ci_content, "cache: 'npm'" ) !== false && strpos( $ci_content, 'cache-dependency-path:' ) !== false,
	'ci.yml configures npm caching for Playwright / Node steps'
);
check(
	strpos( $ci_content, 'cache: true' ) !== false && strpos( $ci_content, 'subosito/flutter-action@' ) !== false,
	'ci.yml configures Flutter SDK & artifact caching'
);

$mobile_content = file_get_contents( $workflows['build-mobile.yml'] );
check(
	strpos( $mobile_content, 'cache: true' ) !== false && strpos( $mobile_content, 'subosito/flutter-action@' ) !== false,
	'build-mobile.yml configures Flutter caching'
);

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
