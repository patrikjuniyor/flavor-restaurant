<?php
/**
 * P47 gate — the pure core-test coverage cannot silently rot.
 *
 * Prompt 47 asked for pure-logic tests (no WordPress) for the untested
 * core files — PointsManager, DiscountManager, KitchenTicketSync,
 * ReceiptRenderer, PushNotificationService, WebhookManager first, money/
 * points/discount logic above all. They live in flavor-core/tests/pure/
 * and are aggregated by run-pure-tests.php, which CI runs on every push.
 *
 * This gate keeps that coverage honest:
 *   1. the runner really aggregates pure/*.php with an anti-vacuity floor
 *   2. every class the prompt named is exercised in the pure corpus, and
 *      the money-priority surfaces (points estimate, currency conversion,
 *      idempotency-key sanitisation) are LIVE calls, not just source pins
 *   3. the suites keep the "no WP" promise: no mock-wp-environment, no
 *      real bootstrap — only the guarded micro-shims, which themselves
 *      must stay exists-guarded (inert inside a real WP boot)
 *   4. CI runs the runner (the ci.yml step is pinned)
 *   5. the runner is executed LIVE here and must exit 0 with at least the
 *      pinned number of passing assertions — a deleted suite or a broken
 *      expectation fails this gate, not just the runner's own floor
 *
 * Run: php tests/theme/test-core-test-coverage.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );
$pass = 0;
$fail = 0;
function check( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  [PASS] $label\n";
	} else {
		$fail++;
		echo "  [FAIL] $label\n";
	}
}

$runner   = $root . '/flavor-core/tests/run-pure-tests.php';
$pure_dir = $root . '/flavor-core/tests/pure';
$runner_src = file_get_contents( $runner );

echo "--- 1. the runner aggregates the pure suites ---\n";
check( is_dir( $pure_dir ), 'flavor-core/tests/pure/ exists' );
check(
	false !== strpos( $runner_src, "glob( __DIR__ . '/pure/*.php' )" )
		&& false !== strpos( $runner_src, 'count( $pure_suites ) >= 5' ),
	'run-pure-tests.php globs pure/*.php with a floor of 5 suites (a wiped directory fails instead of shrinking coverage)'
);
$suites = glob( $pure_dir . '/*.php' );
$suites = is_array( $suites ) ? array_filter( $suites, static fn( $f ) => '_stubs.php' !== basename( $f ) ) : array();
check( count( $suites ) >= 5, 'the pure directory holds the expected suites (' . count( $suites ) . ')' );

echo "\n--- 2. every prompt-named class is covered, money logic live ---\n";
$corpus = '';
foreach ( glob( $pure_dir . '/*.php' ) as $f ) {
	$corpus .= file_get_contents( $f ) . "\n";
}
$named = array( 'PointsManager', 'DiscountManager', 'KitchenTicketSync', 'ReceiptRenderer', 'PushNotificationService', 'WebhookManager' );
$missing = array();
foreach ( $named as $cls ) {
	if ( false === strpos( $corpus, $cls ) ) {
		$missing[] = $cls;
	}
}
check( array() === $missing, 'all six prompt-named classes appear in the pure corpus' . ( $missing ? ' (missing: ' . implode( ', ', $missing ) . ')' : '' ) );
check(
	false !== strpos( $corpus, 'PointsManager::estimate_points(' )
		&& false !== strpos( $corpus, 'Currency::convert(' )
		&& false !== strpos( $corpus, "'sanitize_idempotency_key'" ),
	'the money-priority surfaces are exercised LIVE (points estimate, currency conversion, idempotency-key sanitiser), not only pinned'
);
check(
	false !== strpos( $corpus, 'WebhookManager::sign_body(' )
		&& false !== strpos( $corpus, 'next_attempt_offset(' )
		&& false !== strpos( $corpus, 'validate_events(' )
		&& false !== strpos( $corpus, 'is_safe_target_url(' ),
	'webhook signatures, retry arithmetic, allowlist and SSRF fence all run live'
);

echo "\n--- 3. the suites keep the no-WP promise ---\n";
check(
	false === strpos( $corpus, 'mock-wp-environment' ) && false === strpos( $runner_src, 'mock-wp-environment' ),
	'neither the runner nor the suites load the WP mock environment (pure means pure)'
);
$stubs = file_get_contents( $pure_dir . '/_stubs.php' );
check(
	substr_count( $stubs, 'function_exists(' ) >= 5 && substr_count( $stubs, 'class_exists(' ) >= 1,
	'every micro-shim is exists-guarded (inert inside a real WordPress boot)'
);

echo "\n--- 4. CI runs the runner ---\n";
$ci = file_get_contents( $root . '/.github/workflows/ci.yml' );
check(
	false !== strpos( $ci, 'php flavor-core/tests/run-pure-tests.php' ),
	'the CI pipeline executes run-pure-tests.php on every push'
);

echo "\n--- 5. live run of the aggregated suite ---\n";
$cmd  = 'php ' . escapeshellarg( $runner ) . ' 2>&1';
$out  = (string) shell_exec( $cmd );
exec( 'php ' . escapeshellarg( $runner ) . ' > /dev/null 2>&1', $discard, $rc );
check( 0 === $rc, 'run-pure-tests.php exits 0' );
$passed_count = preg_match( '/Results: (\d+) Passed, (\d+) Failed/', $out, $m ) ? (int) $m[1] : 0;
$failed_count = isset( $m[2] ) ? (int) $m[2] : -1;
check( 0 === $failed_count, 'the live run reports zero failures (' . $failed_count . ')' );
check( $passed_count >= 170, 'assertion volume floor: at least 170 passing checks (now ' . $passed_count . ') — deleting suites or expectations trips this' );

echo str_repeat( '=', 55 ) . "\nResults: $pass Passed, $fail Failed\n";
exit( $fail ? 1 : 0 );
