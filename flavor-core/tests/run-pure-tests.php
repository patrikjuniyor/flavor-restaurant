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

// P38: GuestToken hardening pins.
$gt_src = file_get_contents( __DIR__ . '/../includes/Support/GuestToken.php' );
assert_test(
	false !== strpos( $gt_src, "bin2hex( random_bytes( 32 ) )" )
	&& false === strpos( $gt_src, 'uniqid( (string) wp_rand' )
	&& false === strpos( $gt_src, 'microtime( true )' ),
	'guest token generation is CSPRNG-only (256-bit, no time-derived fallback)'
);
$gt_seen = array();
for ( $gi = 0; $gi < 500; ++$gi ) {
	$gt_seen[ GuestToken::generate() ] = true;
}
assert_test( 500 === count( $gt_seen ), '500 consecutive guest tokens are all distinct' );
assert_test( false !== strpos( $gt_src, 'hash_equals( $known, $user )' ), 'guest verify compares with hash_equals, never ==' );
assert_test( ! GuestToken::verify( 'abc', 'abcd' ), 'guest verify rejects different-length tokens without warning' );

// 2. Mobile Guest Cart Token Tests
echo "\n--- 2. Mobile Guest Cart Token Service ---\n";
$cart_token = CartTokenService::generate_token();
assert_test( str_starts_with( $cart_token, 'fcart_' ), "Cart token starts with prefix 'fcart_': {$cart_token}" );
assert_test( strlen( $cart_token ) > 40, "Cart token has high entropy" );
$hash1 = CartTokenService::hash_token( $cart_token );
$hash2 = CartTokenService::hash_token( $cart_token );
assert_test( $hash1 === $hash2 && strlen( $hash1 ) === 64, "SHA-256 cart token hash is idempotent and 64 chars" );

// P38: CartTokenService hardening pins.
$ct_src = file_get_contents( __DIR__ . '/../includes/WooCommerce/CartTokenService.php' );
assert_test( preg_match( '/^fcart_[0-9a-f]{48}$/', $cart_token ) === 1, 'cart token is fcart_ + 192 bits of hex' );
assert_test(
	false !== strpos( $ct_src, "bin2hex( random_bytes( 24 ) )" )
	&& false === strpos( $ct_src, 'uniqid( (string) wp_rand' ),
	'cart token generation is CSPRNG-only (no time-derived fallback)'
);
$ct_seen = array();
for ( $gi = 0; $gi < 300; ++$gi ) {
	$ct_seen[ CartTokenService::generate_token() ] = true;
}
assert_test( 300 === count( $ct_seen ), '300 consecutive cart tokens are all distinct' );

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

// 4b. Gregorian → Jalali direction (regression: this used to decode to year 7715).
echo "\n--- 4b. Gregorian → Jalali Conversion Anchors ---\n";
$anchors = array(
	'2024-03-20' => array( 1403, 1, 1 ),   // Nowruz 1403
	'2025-03-21' => array( 1404, 1, 1 ),   // Nowruz 1404
	'2026-03-21' => array( 1405, 1, 1 ),   // Nowruz 1405
	'2024-09-26' => array( 1403, 7, 5 ),
	'1979-02-11' => array( 1357, 11, 22 ), // 22 Bahman
);
foreach ( $anchors as $gregorian => $expected ) {
	list( $gy, $gm, $gd ) = array_map( 'intval', explode( '-', $gregorian ) );
	$got                  = Jalali::from_gregorian( $gy, $gm, $gd );
	assert_test(
		$got === $expected,
		"{$gregorian} converts to " . implode( '/', $expected ) . ' (got ' . implode( '/', $got ) . ')'
	);
}

// 4c. Round trip must be the identity across a two-century span.
echo "\n--- 4c. Gregorian ↔ Jalali Round Trip (1900–2100) ---\n";
$rt_failures = 0;
$rt_checked  = 0;
$rt_day      = new DateTime( '1900-01-01', new DateTimeZone( 'UTC' ) );
$rt_end      = new DateTime( '2100-12-31', new DateTimeZone( 'UTC' ) );
while ( $rt_day <= $rt_end ) {
	list( $jy, $jm, $jd ) = Jalali::from_gregorian( (int) $rt_day->format( 'Y' ), (int) $rt_day->format( 'n' ), (int) $rt_day->format( 'j' ) );
	list( $by, $bm, $bd ) = Jalali::to_gregorian( $jy, $jm, $jd );
	++$rt_checked;
	if ( $by !== (int) $rt_day->format( 'Y' ) || $bm !== (int) $rt_day->format( 'n' ) || $bd !== (int) $rt_day->format( 'j' ) ) {
		++$rt_failures;
	}
	$rt_day->modify( '+1 day' );
}
assert_test( 0 === $rt_failures, "Round trip is identity for {$rt_checked} consecutive Gregorian days" );

$today = Jalali::parse_gregorian( gmdate( 'Y-m-d' ) );
assert_test(
	$today['y'] >= 1390 && $today['y'] <= 1500,
	"Today's Jalali year is a plausible 13xx/14xx value ({$today['y']}) — never a 77xx garbage year"
);
assert_test(
	Jalali::month_length( 1403, 12 ) === 30 && Jalali::month_length( 1404, 12 ) === 29,
	'Esfand is 30 days in a leap year and 29 days otherwise'
);

// 5. Persian Text Processing
echo "\n--- 5. Persian Typography & Normalization ---\n";
$persian = PersianText::normalize( 'كباب شيشليك با گوشت تازه' );
assert_test( $persian === 'کباب شیشلیک با گوشت تازه', "Arabic Yeh/Kaf replaced with Persian equivalents" );

// 6. OTP & auth-token hardening (پرامپت ۳۸)
echo "\n--- 6. OTP & Auth-Token Hardening Pins ---\n";
$otp_src = file_get_contents( __DIR__ . '/../includes/Customer/OtpAuth.php' );
assert_test( false !== strpos( $otp_src, 'min( 6, max( 4, $length ) )' ), 'OTP length clamped to 4-6 digits (never raw setting)' );
assert_test( false !== strpos( $otp_src, '(int) $row[\'attempts\'] >= 5' ), 'OTP verify caps attempts at 5 per code' );
assert_test( false !== strpos( $otp_src, 'hash_equals( (string) $row[\'code_hash\'], self::hash_code( $mobile, $code ) )' ), 'OTP compare is constant-time hash_equals' );
assert_test( false !== strpos( $otp_src, 'hash_hmac( \'sha256\', $mobile . \'|\' . $code, $secret )' ), 'OTP stored as AUTH_SALT-peppered HMAC, never plain' );
assert_test( false !== strpos( $otp_src, "'otp_max_per_10min', 3" ), 'issue-side rate limit kept (max 3/10min per mobile)' );
assert_test( false !== strpos( $otp_src, 'consumed_at IS NULL' ), 'codes are single-use' );
$ts_src = file_get_contents( __DIR__ . '/../includes/Customer/TokenService.php' );
assert_test( 2 === substr_count( $ts_src, 'wp_generate_password( 64, false, false )' ), 'access+refresh tokens: 62^64 (~381-bit) CSPRNG-backed generator' );
assert_test( false !== strpos( $ts_src, "hash( 'sha256', \$access_token )" ) && false !== strpos( $ts_src, "hash( 'sha256', \$refresh_token )" ), 'auth tokens stored hashed, validated by hash lookup' );
assert_test( false === strpos( $ts_src, '== $raw_token' ) && false === strpos( $ts_src, '$raw_token ==' ), 'no loose token comparison in TokenService' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
