<?php
/**
 * Outgoing-webhook hardening guard (R4-6 / پرامپت ۴۲).
 *
 * WebhookManager dispatches signed deliveries to admin-registered URLs.
 * Prompt 42 demands three invariants, all machine-pinned here:
 *
 *   1. HMAC signatures follow the build-mobile.yml / X-Flavor-Signature
 *      convention (raw 64-hex HMAC-SHA256) and verification is timing-safe
 *      (hash_equals), fail-closed against empty secrets and garbage input.
 *   2. Targets are https-only unless FLAVOR_WEBHOOK_ALLOW_HTTP is explicitly
 *      defined for development — and even then loopback/private ranges stay
 *      blocked (the opt-in relaxes wire encryption, never the SSRF fence).
 *   3. Failed deliveries retry with exponential backoff (30s doubling,
 *      capped at 1h, MAX_ATTEMPTS budget) instead of dying on attempt one,
 *      and only terminal outcomes touch the webhook failure counter.
 *
 *   php tests/theme/test-webhook-hardening.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );
define( 'ABSPATH', $root . '/flavor-core/' );
require_once $root . '/flavor-core/tests/mock-wp-environment.php';
require_once $root . '/flavor-core/includes/Webhooks/WebhookManager.php';

use FlavorCore\Webhooks\WebhookManager;

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

$manager_src = file_get_contents( $root . '/flavor-core/includes/Webhooks/WebhookManager.php' );
$ctrl_src    = file_get_contents( $root . '/flavor-core/includes/API/WebhookController.php' );

echo "=== 1. signature sign/verify matrix (unit per prompt) ===\n";

$body = wp_json_encode( array( 'event' => 'order.created', 'data' => array( 'id' => 7 ) ) );
$secret = 'fixture-webhook-secret-001'; // 26 chars, dispatch-ready.

$sig = WebhookManager::sign_body( $body, $secret );
check(
	(bool) preg_match( '/^[0-9a-f]{64}$/', $sig ),
	'sign_body emits the raw 64-hex wire format (build-mobile.yml convention)'
);
check(
	$sig === hash_hmac( 'sha256', $body, $secret ),
	'sign_body is byte-identical to openssl/HMAC reference for the same inputs'
);
check( WebhookManager::verify_signature( $body, $secret, $sig ), 'valid signature verifies' );
check( ! WebhookManager::verify_signature( $body . ' ', $secret, $sig ), 'trailing-whitespace body tamper rejected' );
check( ! WebhookManager::verify_signature( $body, 'other-fixture-secret-002', $sig ), 'wrong-secret rejected' );
check( ! WebhookManager::verify_signature( $body, $secret, substr( $sig, 0, 63 ) ), 'truncated signature rejected' );
check( ! WebhookManager::verify_signature( $body, $secret, str_repeat( '0', 64 ) ), 'all-zero 64-hex garbage rejected' );
check( ! WebhookManager::verify_signature( $body, $secret, 'not-hex-not-even-close' ), 'non-hex header value rejected before any comparison' );
check( ! WebhookManager::verify_signature( $body, $secret, '' ), 'missing signature rejected' );
check(
	WebhookManager::verify_signature( $body, $secret, 'sha256=' . strtoupper( $sig ) ),
	'sha256= prefix and uppercase hex are tolerated (normalized, then compared)'
);
check(
	! WebhookManager::verify_signature( $body, '', $sig ),
	'empty secret never verifies — unsigned payloads cannot be "accepted"'
);
check( ! WebhookManager::signing_ready( '' ), 'empty secret is not dispatch-ready' );
check( ! WebhookManager::signing_ready( '  short-15chr  ' ), 'short secret trimmed and refused' );
check( WebhookManager::signing_ready( $secret ), 'normal-length secret is dispatch-ready' );

echo "\n=== 2. verification source pins (timing-safety is not optional) ===\n";

check(
	(bool) preg_match( '/hash_equals\( *self::sign_body\( *\$json_body, *\$secret *\), *\$signature *\)/', $manager_src ),
	'verify_signature compares with hash_equals against a recomputed signature'
);
check(
	1 === substr_count( $manager_src, 'hash_hmac(' ),
	'hash_hmac appears exactly once (inside sign_body) — single source of truth for the wire format'
);
check(
	(bool) preg_match( '/signing_ready\( *\$secret *\)[\s\S]*?X-Flavor-Signature\' *=> *self::sign_body\(/', $manager_src ),
	'attempt() refuses unsigned dispatch BEFORE building headers/transport (guard precedes send)'
);
check(
	! preg_match( '/(?:! ==|===?) *\$signature|\$signature *(?:===?|==)/', $manager_src ),
	'no loose or early-exit comparison of $signature anywhere in the manager'
);
check(
	1 === preg_match_all( "/'X-Flavor-Signature' *=>/", $manager_src ),
	'the signature header is emitted from exactly one transport site (no second unsynced signer)'
);

echo "\n=== 3. https-only targets (dev opt-in is explicit, SSRF fence never moves) ===\n";

check( WebhookManager::is_safe_target_url( 'https://203.0.113.7/hook' ), 'https to a public IP literal is accepted' );
check( ! WebhookManager::is_safe_target_url( 'http://203.0.113.7/hook' ), 'cleartext http to a public host is refused (pre-opt-in)' );
check( ! WebhookManager::is_safe_target_url( 'ftp://203.0.113.7/hook' ), 'non-http scheme refused' );
check( ! WebhookManager::is_safe_target_url( 'file:///etc/passwd' ), 'file:// refused' );

define( 'FLAVOR_WEBHOOK_ALLOW_HTTP', true ); // explicit dev mode, as per prompt.

check( WebhookManager::is_safe_target_url( 'http://203.0.113.7/hook' ), 'dev opt-in relaxes cleartext to public hosts' );
check( ! WebhookManager::is_safe_target_url( 'http://127.0.0.1:9000/internal' ), 'even in dev mode loopback http stays blocked (SSRF fence unchanged)' );
check( ! WebhookManager::is_safe_target_url( 'http://169.254.169.254/latest/meta-data' ), 'cloud metadata endpoint stays blocked in dev mode too' );
check( ! WebhookManager::is_safe_target_url( 'ftp://203.0.113.7/hook' ), 'opt-in still never widens beyond http/https' );

echo "\n=== 4. exponential backoff schedule ===\n";

check( 0 === WebhookManager::next_attempt_offset( 0 ), 'fresh deliveries (0 attempts) are due immediately' );
check( 30 === WebhookManager::next_attempt_offset( 1 ), 'retry #1 due 30s after creation' );
check( 90 === WebhookManager::next_attempt_offset( 2 ), 'retry #2 due at 30+60=90s' );
check( 210 === WebhookManager::next_attempt_offset( 3 ), 'retry #3 due at 210s (doubling holds)' );
check(
	3600 === WebhookManager::next_attempt_offset( 8 ) - WebhookManager::next_attempt_offset( 7 ),
	'per-attempt delay saturates at the 1h cap'
);
check( 5 === WebhookManager::MAX_ATTEMPTS, 'retry budget is 5 attempts total' );
check(
	! WebhookManager::delivery_is_due( 1000, 1, 1029 ) && WebhookManager::delivery_is_due( 1000, 1, 1030 ),
	'due boundary is inclusive (>=): 1s early refused, at-offset accepted'
);
$t1 = WebhookManager::is_terminal( true, 1 );
$t2 = WebhookManager::is_terminal( false, 1 );
$t3 = WebhookManager::is_terminal( false, 4 );
$t4 = WebhookManager::is_terminal( false, 5 );
check(
	$t1 && ! $t2 && ! $t3 && $t4,
	'is_terminal: success ends delivery; failures 1-4 stay retryable; the 5th is terminal'
);

echo "\n=== 5. queue wiring (backoff consulted before attempt; only terminal hits stats) ===\n";

check(
	(bool) preg_match( '/delivery_is_due\( *\$created, *\$attempts *\)/', $manager_src ),
	'process_queue checks the due window against the row before dispatching'
);
check(
	(bool) preg_match( '/terminal\s*\?\s*self::STATUS_FAILED : self::STATUS_QUEUED/', $manager_src ),
	'non-terminal failure returns the row to the queue (retry), not to failed'
);
check(
	(bool) preg_match( '/self::is_terminal\( \(bool\) \$delivery\[.success.\], \$attempts \+ 1 \)/', $manager_src ),
	'process_queue routes the terminal decision through the pure helper (no inlined flip)'
);
check(
	(bool) preg_match( '/if \( \$terminal \) \{\s*self::record_webhook_outcome/', $manager_src ),
	'webhook failure_count moves on terminal outcomes only (retries do not punish stats early)'
);
check(
	(bool) preg_match( '/schedule_queue\( *max\( *time\(\), *\$next_due *\)/', $manager_src ),
	'pending backoff windows re-arm the cron drain at the earliest due time'
);

echo "\n=== 6. controller edge (registration + rotation) ===\n";

check(
	2 === substr_count( $ctrl_src, 'فقط https عمومی پذیرفته می‌شود' )
	&& 0 === substr_count( $ctrl_src, 'فقط http/https' ),
	'create+update URL errors now state https-only (old message fully replaced)'
);
check(
	2 === substr_count( $ctrl_src, 'signing_ready' ),
	'create rejects stub secrets and update validates rotation (2 gates)'
);
check(
	(bool) preg_match( '/\$data\[.secret.\] = \$secret_in;/', $ctrl_src ),
	'secret rotation through update is persisted'
);

echo "\n=== 7. precedent drift guard ===\n";

$build_mgr = file_get_contents( $root . '/flavor-core/includes/Mobile/BuildManager.php' );
check(
	false !== strpos( $build_mgr, 'hash_equals( $expected_sig, $signature )' ),
	'BuildManager CI-callback verification (the convention P42 mirrors) is intact'
);

echo "\n";
if ( $failed > 0 ) {
	echo "{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}
echo "ALL {$passed} checks passed.\n";
exit( 0 );
