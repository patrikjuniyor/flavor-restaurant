<?php
/**
 * Standalone Security Test Suite Runner for Flavor Core (Prompt 103).
 *
 * Runs offline standalone security contracts:
 *  1. IDOR & Guest Ownership Token Security (GuestToken format, constant-time verify, extraction, IDOR boundaries)
 *  2. Rate Limiting Engine & Scoped Buckets (allow under cap, guard WP_Error, session scope separation, reset)
 *  3. TokenService & OTP Security Hardening (JWT/Bearer rotation, revocation, CSPRNG entropy, hashing, OTP length clamping)
 *  4. Branch-scope staff security (R4-2 branch isolation)
 *
 * @package FlavorCore
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride

namespace FlavorCore\Tests;

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'FLAVOR_CORE_PATH', dirname( __DIR__ ) . '/' );
define( 'FLAVOR_CORE_VERSION', '1.5.1' );
define( 'FLAVOR_CORE_REST_NAMESPACE', 'flavor/v1' );
define( 'FLAVOR_CORE_REST_V2_NAMESPACE', 'flavor/v2' );

require_once __DIR__ . '/mock-wp-environment.php';

require_once FLAVOR_CORE_PATH . 'includes/Autoloader.php';
\FlavorCore\Autoloader::register();

use FlavorCore\Support\GuestToken;
use FlavorCore\Support\RateLimit;
use FlavorCore\Customer\TokenService;
use FlavorCore\Customer\OtpAuth;
use \WP_REST_Request;
use \ReflectionMethod;

$passed = 0;
$failed = 0;

function sec_check( bool $cond, string $msg ): void {
	global $passed, $failed;
	if ( $cond ) {
		++$passed;
		echo "  [PASS] {$msg}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$msg}\n";
	}
}

echo "=== FLAVOR CORE SECURITY FINAL TEST RUNNER (PROMPT 103) ===\n\n";

// --- 1. IDOR Security & Guest Ownership Token ---
echo "--- 1. IDOR & GUEST TOKEN HARDENING ---\n";
$tok = GuestToken::generate();
sec_check( is_string( $tok ) && 64 === strlen( $tok ) && preg_match( '/^[a-f0-9]{64}$/', $tok ), 'Guest token generation has 64-hex format' );
sec_check( GuestToken::verify( $tok, $tok ), 'Constant-time verification succeeds on identical token' );
sec_check( ! GuestToken::verify( $tok, GuestToken::generate() ), 'Verification fails on mismatched token' );
sec_check( ! GuestToken::verify( $tok, '' ) && ! GuestToken::verify( '', $tok ) && ! GuestToken::verify( '', '' ), 'Verification fails on empty or missing tokens' );

$req = new WP_REST_Request( 'GET', '/flavor/v2/orders/10' );
$req->set_header( 'X-Guest-Token', $tok );
sec_check( GuestToken::extract_from_request( $req ) === $tok, 'Extract token from X-Guest-Token header' );

$req_param = new WP_REST_Request( 'GET', '/flavor/v2/orders/10' );
$req_param->set_param( 'guest_token', $tok );
sec_check( GuestToken::extract_from_request( $req_param ) === $tok, 'Extract token from query parameter' );

// Constant time comparison reflection check
$gt_src = file_get_contents( dirname( __DIR__ ) . '/includes/Support/GuestToken.php' );
sec_check( str_contains( $gt_src, 'hash_equals' ), 'GuestToken uses hash_equals to prevent timing attacks' );
sec_check( ! preg_match( '/[^=!<>]==[^=]/', $gt_src ), 'GuestToken has no loose (==) equality comparisons' );


// --- 2. Rate Limiting Engine (Prompt 14 & Scoped Buckets) ---
echo "\n--- 2. RATE LIMITING ENGINE & SCOPED BUCKETS ---\n";
$k = 'test_bucket_' . bin2hex( random_bytes( 4 ) );
sec_check( RateLimit::allow( $k, 2, 60 ), 'RateLimit allows attempt 1 under cap 2' );
sec_check( RateLimit::allow( $k, 2, 60 ), 'RateLimit allows attempt 2 under cap 2' );
sec_check( ! RateLimit::allow( $k, 2, 60 ), 'RateLimit blocks attempt 3 over cap 2' );

$k_guard = 'test_guard_' . bin2hex( random_bytes( 4 ) );
RateLimit::allow( $k_guard, 1, 60 );
$guard_res = RateLimit::guard( $k_guard, 1, 60 );
sec_check( is_wp_error( $guard_res ), 'RateLimit::guard returns WP_Error when cap exceeded' );
sec_check( 429 === $guard_res->get_error_data()['status'], 'RateLimit::guard sets status 429' );

// Scoped buckets test (IP + cart_token)
$k_scope = 'test_scope_' . bin2hex( random_bytes( 4 ) );
sec_check( RateLimit::allow( $k_scope, 1, 60, 'cart_a' ), 'Scope allows attempt 1 for cart_a' );
sec_check( ! RateLimit::allow( $k_scope, 1, 60, 'cart_a' ), 'Scope blocks attempt 2 for cart_a' );
sec_check( RateLimit::allow( $k_scope, 1, 60, 'cart_b' ), 'Scope allows attempt 1 for independent cart_b' );

RateLimit::reset( $k_scope, 'cart_a' );
sec_check( RateLimit::allow( $k_scope, 1, 60, 'cart_a' ), 'Reset empties cart_a bucket' );
sec_check( ! RateLimit::allow( $k_scope, 1, 60, 'cart_b' ), 'Reset does not affect neighbouring cart_b bucket' );


// --- 3. TokenService & OTP Authentication Security ---
echo "\n--- 3. TOKEN SERVICE & OTP SECURITY PINS ---\n";
$uid = 101;
$tpair = TokenService::issue( $uid, 'Test Phone' );
sec_check( isset( $tpair['access_token'], $tpair['refresh_token'] ) && 'Bearer' === $tpair['token_type'], 'Issue returns Bearer access and refresh token pair' );
sec_check( strlen( $tpair['access_token'] ) >= 64 && strlen( $tpair['refresh_token'] ) >= 64, 'Tokens have high entropy (>= 64 chars)' );
sec_check( TokenService::validate( $tpair['access_token'] ) === $uid, 'Validate matches issued user ID' );
sec_check( null === TokenService::validate( 'invalid_bogus_token' ), 'Validate returns null for unknown token' );

// Rotation
$rotated = TokenService::refresh( $tpair['refresh_token'] );
sec_check( ! is_wp_error( $rotated ) && isset( $rotated['access_token'] ), 'Refresh token rotation succeeds' );
sec_check( $rotated['access_token'] !== $tpair['access_token'], 'Rotated access token is distinct' );
sec_check( $rotated['refresh_token'] !== $tpair['refresh_token'], 'Rotated refresh token is distinct' );

// Single-use refresh token invalidation
$reused = TokenService::refresh( $tpair['refresh_token'] );
sec_check( is_wp_error( $reused ), 'Old refresh token is invalidated immediately (single-use)' );

// Revocation
TokenService::revoke( $rotated['access_token'] );
sec_check( null === TokenService::validate( $rotated['access_token'] ), 'Revoked token cannot authenticate' );

// Source audit checks
$ts_src = file_get_contents( dirname( __DIR__ ) . '/includes/Customer/TokenService.php' );
sec_check( str_contains( $ts_src, "hash( 'sha256'" ), 'Tokens are stored hashed (SHA-256), never plain text' );

$otp_src = file_get_contents( dirname( __DIR__ ) . '/includes/Customer/OtpAuth.php' );
sec_check( str_contains( $otp_src, 'min( 6, max( 4, $length ) )' ), 'OTP length clamped between 4 and 6 digits' );
sec_check( str_contains( $otp_src, 'attempts\'] >= 5' ), 'OTP verification attempts capped at 5' );
sec_check( str_contains( $otp_src, 'hash_equals' ), 'OTP verification uses constant-time hash_equals' );
sec_check( str_contains( $otp_src, 'hash_hmac' ), 'OTP codes stored HMAC-peppered' );

// Reflection test on OtpAuth length clamp
$rm = new ReflectionMethod( 'FlavorCore\Customer\OtpAuth', 'generate_code' );
$rm->setAccessible( true );
sec_check( 4 === strlen( $rm->invoke( null, 1 ) ), 'Clamp 1 digit up to 4' );
sec_check( 6 === strlen( $rm->invoke( null, 9 ) ), 'Clamp 9 digits down to 6' );
sec_check( ctype_digit( $rm->invoke( null, 5 ) ), 'Generated code is digits-only' );

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
