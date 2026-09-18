<?php
/**
 * Standalone pure logic unit test runner for Flavor Core.
 * Executes offline test assertions for fast regression verification.
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

require_once __DIR__ . '/../includes/Support/GuestToken.php';
require_once __DIR__ . '/../includes/WooCommerce/CartTokenService.php';
require_once __DIR__ . '/../includes/Support/Jalali.php';
require_once __DIR__ . '/../includes/Support/Iran.php';
require_once __DIR__ . '/../includes/Support/PersianText.php';

use FlavorCore\Support\GuestToken;
use FlavorCore\WooCommerce\CartTokenService;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\Iran;
use FlavorCore\Support\PersianText;

$passed = 0;
$failed = 0;

function assert_test( $condition, $message ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "  [PASS] {$message}\n";
	} else {
		$failed++;
		echo "  [FAIL] {$message}\n";
	}
}

echo "=== Running Flavor Core Pure Logic Regression Suite ===\n\n";

// 1. Guest Ownership Token Tests
echo "--- 1. Guest Ownership Token (IDOR Security) ---\n";
$token = GuestToken::generate();
assert_test( is_string( $token ) && strlen( $token ) === 64, "Generated guest token is 64-char hex string: {$token}" );
assert_test( ctype_xdigit( $token ), "Guest token contains only valid hexadecimal characters" );
assert_test( GuestToken::verify( $token, $token ), "Constant-time token verification succeeds on matching token" );
assert_test( ! GuestToken::verify( $token, 'wrong_token' ), "Token verification fails on mismatched token" );
assert_test( ! GuestToken::verify( $token, '' ), "Token verification fails on empty token" );

// 2. Mobile Guest Cart Token Tests
echo "\n--- 2. Mobile Guest Cart Token Service ---\n";
$cart_token = CartTokenService::generate_token();
assert_test( str_starts_with( $cart_token, 'fcart_' ), "Cart token starts with prefix 'fcart_': {$cart_token}" );
assert_test( strlen( $cart_token ) > 40, "Cart token has high entropy" );
$hash1 = CartTokenService::hash_token( $cart_token );
$hash2 = CartTokenService::hash_token( $cart_token );
assert_test( $hash1 === $hash2 && strlen( $hash1 ) === 64, "SHA-256 cart token hash is idempotent and 64 chars" );

// 3. Mobile Normalization
echo "\n--- 3. Iranian Mobile Phone Normalization ---\n";
assert_test( Iran::normalize_mobile( '09121234567' ) === '09121234567', "Standard mobile 09121234567 normalized" );
assert_test( Iran::normalize_mobile( '+989121234567' ) === '09121234567', "International +989121234567 normalized to 09121234567" );
assert_test( Iran::normalize_mobile( '۰۰۹۸۹۱۲۱۲۳۴۵۶۷' ) === '09121234567', "Persian digits and prefix normalized" );

// 4. Jalali Dates
echo "\n--- 4. Jalali Calendar & Leap Year Calculation ---\n";
$conv = Jalali::to_gregorian( 1403, 1, 1 );
assert_test( $conv[0] === 2024 && $conv[1] === 3 && $conv[2] === 20, "1403/01/01 corresponds to 2024-03-20" );
assert_test( Jalali::is_leap( 1403 ), "Year 1403 is a Jalali leap year" );
assert_test( ! Jalali::is_leap( 1404 ), "Year 1404 is not a Jalali leap year" );

// 5. Persian Text Processing
echo "\n--- 5. Persian Typography & Normalization ---\n";
$persian = PersianText::normalize( 'كباب شيشليك با گوشت تازه' );
assert_test( $persian === 'کباب شیشلیک با گوشت تازه', "Arabic Yeh/Kaf replaced with Persian equivalents" );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
