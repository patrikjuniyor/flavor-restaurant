<?php
/**
 * Pure suite — WebhookManager: signatures, retry arithmetic, event
 * allowlist, SSRF fence.
 *
 * These surfaces were deliberately extracted as pure statics (is_terminal's
 * own docblock says so); this suite exercises them LIVE without WordPress.
 * The transport half (process_queue/attempt/send_delivery) stays covered by
 * tests/theme/test-webhook-hardening.php and the e2e queue assertions.
 *
 * Loaded by run-pure-tests.php (assert_test protocol).
 *
 * @package FlavorCore
 */

// phpcs:disable WordPress.Security.EscapeOutput -- CLI harness output, not an HTTP response.

require_once __DIR__ . '/../../includes/Webhooks/WebhookManager.php';

use FlavorCore\Webhooks\WebhookManager;

echo "\n--- P47. Webhook signatures (sign_body / verify_signature / signing_ready) ---\n";

$body   = '{"event":"order.created","id":7}';
$secret = '0123456789abcdef0123456789abcdef';
$sig    = WebhookManager::sign_body( $body, $secret );

assert_test( $sig === hash_hmac( 'sha256', $body, $secret ) && 1 === preg_match( '/^[0-9a-f]{64}$/', $sig ), 'sign_body is exactly raw 64-hex HMAC-SHA256 (the build-mobile.yml wire format)' );
assert_test( WebhookManager::verify_signature( $body, $secret, $sig ), 'verification succeeds on an untampered body' );
assert_test( WebhookManager::verify_signature( $body, $secret, 'sha256=' . $sig ), 'the sha256= prefix is tolerated' );
assert_test( WebhookManager::verify_signature( $body, $secret, strtoupper( $sig ) ), 'hex casing is normalised before comparison' );
assert_test( WebhookManager::verify_signature( $body, $secret, '  ' . $sig . "\t" ), 'surrounding whitespace is trimmed' );
assert_test( ! WebhookManager::verify_signature( $body . ' ', $secret, $sig ), 'a tampered body never verifies' );
assert_test( ! WebhookManager::verify_signature( $body, 'another-secret-0123456789abcdef', $sig ), 'a wrong secret never verifies' );
assert_test( ! WebhookManager::verify_signature( $body, '', $sig ), 'an empty secret never verifies (fail closed)' );
assert_test( ! WebhookManager::verify_signature( $body, $secret, substr( $sig, 0, 63 ) ), 'a 63-hex truncation is rejected before any comparison' );
assert_test( ! WebhookManager::verify_signature( $body, $secret, str_repeat( 'z', 64 ) ), 'non-hex garbage is rejected by the format guard' );
assert_test( false === strpos( file_get_contents( __DIR__ . '/../../includes/Webhooks/WebhookManager.php' ), '$signature === self::sign_body' ), 'no strict-equality signature comparison exists (timing oracle stays closed — hash_equals only)' );

assert_test( ! WebhookManager::signing_ready( '' ) && ! WebhookManager::signing_ready( str_repeat( 'x', 15 ) ), 'secrets under 16 chars are not dispatch-ready' );
assert_test( WebhookManager::signing_ready( str_repeat( 'x', 16 ) ), 'a 16-char secret is dispatch-ready (boundary inclusive)' );
assert_test( ! WebhookManager::signing_ready( '   ' . str_repeat( 'x', 15 ) . '   ' ), 'whitespace padding does not smuggle a short secret past the length gate' );

echo "\n--- P47. Webhook retry arithmetic (next_attempt_offset / delivery_is_due / is_terminal) ---\n";

assert_test( 5 === WebhookManager::MAX_ATTEMPTS && 30 === WebhookManager::RETRY_BASE_SECONDS && 3600 === WebhookManager::RETRY_CAP_SECONDS, 'retry policy constants: 5 attempts, 30s base, 1h cap' );
assert_test( 0 === WebhookManager::next_attempt_offset( 0 ), 'attempt 0 is due immediately (offset 0)' );
assert_test( 30 === WebhookManager::next_attempt_offset( 1 ), 'after 1 attempt the next is due at +30s' );
assert_test( 90 === WebhookManager::next_attempt_offset( 2 ), 'offsets accumulate exponentially: 30+60 = 90 after 2' );
assert_test( 210 === WebhookManager::next_attempt_offset( 3 ) && 450 === WebhookManager::next_attempt_offset( 4 ) && 930 === WebhookManager::next_attempt_offset( 5 ), 'the doubling schedule holds through attempt 5 (210/450/930)' );
assert_test( 7410 === WebhookManager::next_attempt_offset( 8 ), 'the per-step cap binds at attempt 8 (3840 capped to 3600 → 7410 total)' );
assert_test( ! WebhookManager::delivery_is_due( 1000, 1, 1029 ) && WebhookManager::delivery_is_due( 1000, 1, 1030 ), 'due eligibility is inclusive exactly at the offset boundary (injectable clock)' );
assert_test( WebhookManager::delivery_is_due( 1000, 0, 1000 ), 'a never-attempted row is due at its creation second' );
assert_test( WebhookManager::is_terminal( true, 1 ), 'success ends the lifecycle on the first attempt' );
assert_test( ! WebhookManager::is_terminal( false, 4 ), 'failure with budget left is retryable' );
assert_test( WebhookManager::is_terminal( false, 5 ), 'failure at the attempt budget is terminal' );

echo "\n--- P47. Webhook event allowlist (validate_events) ---\n";

$names = WebhookManager::allowed_event_names();
assert_test( 8 === count( $names ) && in_array( 'order.created', $names, true ) && in_array( 'loyalty.points_awarded', $names, true ), 'the allowlist is the eight dot-names the application actually emits' );
$bad = WebhookManager::validate_events( array() );
assert_test( is_wp_error( $bad ) && 400 === ( $bad->get_error_data()['status'] ?? 0 ), 'an empty subscription is a 400, never an empty webhook' );
assert_test( array( '*' ) === WebhookManager::validate_events( array( '*', 'order.created' ) ), 'a wildcard collapses the subscription to just "*"' );
assert_test( array( 'order.created' ) === WebhookManager::validate_events( array( 'order.created', 'order.created' ) ), 'duplicates normalise to one entry' );
assert_test( array( 'order.created' ) === WebhookManager::validate_events( array( ' order.created ' ) ), 'entries are trimmed' );
$unknown = WebhookManager::validate_events( array( 'order.hacked' ) );
assert_test( is_wp_error( $unknown ) && 'order.hacked' === ( $unknown->get_error_data()['event'] ?? '' ), 'an unknown event is rejected by name in the error data (allowlist, not sanitize-and-hope)' );
$nonstrings = WebhookManager::validate_events( array( 123, null, array() ) );
assert_test( is_wp_error( $nonstrings ), 'a list of only non-strings validates to a 400, not to an empty subscription' );

echo "\n--- P47. Webhook SSRF fence (is_safe_target_url — literal hosts, no DNS needed) ---\n";

assert_test( WebhookManager::is_safe_target_url( 'https://8.8.8.8/hook' ), 'a public IPv4 literal over https passes' );
assert_test( ! WebhookManager::is_safe_target_url( 'http://8.8.8.8/hook' ), 'cleartext http is refused without the dev opt-in constant' );
assert_test( ! WebhookManager::is_safe_target_url( 'https://127.0.0.1/hook' ), 'loopback is refused' );
assert_test( ! WebhookManager::is_safe_target_url( 'https://192.168.1.10/hook' ) && ! WebhookManager::is_safe_target_url( 'https://10.0.0.5/hook' ), 'private ranges are refused' );
assert_test( ! WebhookManager::is_safe_target_url( 'https://169.254.169.254/latest/meta-data/' ), 'the cloud metadata endpoint is refused (link-local/reserved)' );
assert_test( ! WebhookManager::is_safe_target_url( 'https://localhost/hook' ) && ! WebhookManager::is_safe_target_url( 'https://x.localhost/hook' ) && ! WebhookManager::is_safe_target_url( 'https://printer.local/hook' ) && ! WebhookManager::is_safe_target_url( 'https://db.internal/hook' ), 'localhost and the .localhost/.local/.internal families are refused by name' );
assert_test( ! WebhookManager::is_safe_target_url( 'https://user:pass@example.com/hook' ), 'credential smuggling in the URL is refused' );
assert_test( ! WebhookManager::is_safe_target_url( '' ) && ! WebhookManager::is_safe_target_url( 'https://' . str_repeat( 'a', 2040 ) . '.example.com/' ), 'empty and over-long targets are refused' );
assert_test( ! WebhookManager::is_safe_target_url( 'ftp://8.8.8.8/hook' ) && ! WebhookManager::is_safe_target_url( 'file:///etc/passwd' ), 'non-http(s) schemes are refused' );
assert_test( WebhookManager::is_safe_target_url( 'https://hook.example.invalid/' ), 'an unresolvable name defers to the transport-layer re-check (fail-open only into wp_safe_remote_post)' );
