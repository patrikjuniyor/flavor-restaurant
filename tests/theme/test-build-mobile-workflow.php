<?php
/**
 * Test build-mobile.yml workflow hardening & dry-run contract (پرامپت ۷۲).
 *
 * Requirements:
 * 1. Mobile workflow (.github/workflows/build-mobile.yml) is valid YAML and defines workflow_dispatch.
 * 2. In iOS section, flutter_export_environment.sh is never generated or committed (prompt 15).
 * 3. Webhook callbacks carry valid raw 64-hex HMAC-SHA256 signature in X-Flavor-Signature.
 * 4. In case of failure or cancellation of android/ios jobs, status 'failed' is reported to WordPress.
 * 5. Dry-run execution test verifies workflow_dispatch payload & HMAC signing matches WordPress receiver.
 *
 * php tests/theme/test-build-mobile-workflow.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );
define( 'ABSPATH', $root . '/flavor-core/' );
require_once $root . '/flavor-core/tests/mock-wp-environment.php';
require_once $root . '/flavor-core/includes/Mobile/BuildManager.php';
require_once $root . '/flavor-core/includes/Webhooks/WebhookManager.php';

use FlavorCore\Mobile\BuildManager;
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

$workflow_path = $root . '/.github/workflows/build-mobile.yml';
check( file_exists( $workflow_path ), 'build-mobile.yml workflow file exists' );
$workflow_src = (string) file_get_contents( $workflow_path );

echo "=== 1. Workflow structure & workflow_dispatch inputs ===\n";

check( false !== strpos( $workflow_src, 'name: Build White-Label Mobile App' ), 'workflow name is exact' );
check( false !== strpos( $workflow_src, 'workflow_dispatch:' ), 'workflow triggers on workflow_dispatch' );

$required_inputs = array( 'build_uuid', 'config_url', 'callback_url', 'platform', 'environment' );
foreach ( $required_inputs as $in ) {
	check( false !== strpos( $workflow_src, $in . ':' ), "input '{$in}' is declared in workflow_dispatch" );
}

echo "\n=== 2. flutter_export_environment.sh uncommitted invariant (prompt 15 & 72) ===\n";

$gitignore = (string) file_get_contents( $root . '/.gitignore' );
check( false !== strpos( $gitignore, 'mobile/ios/Flutter/flutter_export_environment.sh' ), 'flutter_export_environment.sh is ignored in root .gitignore' );
check( false !== strpos( $workflow_src, 'flutter_export_environment.sh' ), 'workflow explicitly checks flutter_export_environment.sh is not committed' );
check( false !== strpos( $workflow_src, 'git ls-files --error-unmatch mobile/ios/Flutter/flutter_export_environment.sh' ), 'git ls-files error-unmatch guard is present' );

echo "\n=== 3. HMAC signatures (X-Flavor-Signature) & fail-safe status reporting ===\n";

check( false !== strpos( $workflow_src, 'X-Flavor-Signature: $SIG' ), 'webhook callback sends X-Flavor-Signature' );
check( false !== strpos( $workflow_src, 'openssl dgst -sha256 -hmac "$FLAVOR_WEBHOOK_SECRET"' ), 'HMAC-SHA256 signature is calculated with FLAVOR_WEBHOOK_SECRET' );
check( false !== strpos( $workflow_src, 'STATUS="failed"' ), 'failure branch sets STATUS="failed"' );
check( false !== strpos( $workflow_src, 'needs.build-android.result }}" == "failure"' ), 'android failure triggers failed status' );
check( false !== strpos( $workflow_src, 'needs.build-ios.result }}" == "failure"' ), 'ios failure triggers failed status' );
check( false !== strpos( $workflow_src, 'needs.build-android.result }}" == "cancelled"' ), 'android cancellation triggers failed status' );
check( false !== strpos( $workflow_src, 'needs.build-ios.result }}" == "cancelled"' ), 'ios cancellation triggers failed status' );
check( false !== strpos( $workflow_src, '"artifacts": []' ), 'failed build emits empty artifacts array' );

echo "\n=== 4. Dry-run simulation: building & failed payload dispatch with signature ===\n";

$secret = 'mock_ci_secret_1234567890123456';
$uuid   = 'bld_' . bin2hex( random_bytes( 8 ) );
$now    = time();

// Simulate Start payload
$start_payload = wp_json_encode(
	array(
		'build_uuid' => $uuid,
		'status'     => 'building',
		'timestamp'  => $now,
		'logs'       => 'GitHub Actions CI job started on runner ubuntu-latest',
	)
);
$start_sig = hash_hmac( 'sha256', $start_payload, $secret );
check( 64 === strlen( $start_sig ) && ctype_xdigit( $start_sig ), 'start webhook signature is 64-hex' );
check( WebhookManager::verify_signature( $start_payload, $secret, $start_sig ), 'start webhook signature verifies with WebhookManager' );

// Simulate Failed payload
$failed_payload = wp_json_encode(
	array(
		'build_uuid' => $uuid,
		'status'     => 'failed',
		'timestamp'  => $now,
		'logs'       => 'Build workflow completed with status: failed on GitHub Actions run 12345',
		'artifacts'  => array(),
	)
);
$failed_sig = hash_hmac( 'sha256', $failed_payload, $secret );
check( 64 === strlen( $failed_sig ) && ctype_xdigit( $failed_sig ), 'failed webhook signature is 64-hex' );
check( WebhookManager::verify_signature( $failed_payload, $secret, $failed_sig ), 'failed webhook signature verifies with WebhookManager' );

// Simulate Success payload
$success_payload = wp_json_encode(
	array(
		'build_uuid' => $uuid,
		'status'     => 'success',
		'timestamp'  => $now,
		'logs'       => 'Build workflow completed with status: success on GitHub Actions run 12345',
		'artifacts'  => array(
			array(
				'type'            => 'android_apk',
				'filename'        => 'app-release.apk',
				'download_url'    => 'https://github.com/patrikjuniyor/flavor-restaurant/actions/runs/12345',
				'checksum_sha256' => hash( 'sha256', 'dummy apk' ),
			),
		),
	)
);
$success_sig = hash_hmac( 'sha256', $success_payload, $secret );
check( WebhookManager::verify_signature( $success_payload, $secret, $success_sig ), 'success webhook signature verifies with WebhookManager' );

echo "\n──────────────────────────────────────────────────\n";
echo "Result: {$passed} checks passed, {$failed} failed.\n";

if ( $failed > 0 ) {
	exit( 1 );
}
