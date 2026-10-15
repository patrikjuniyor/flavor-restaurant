<?php
/**
 * Test contract for Support & Warranty document (پرامپت ۹۵ / فاز ۱۱).
 *
 * Verifies:
 *   1. docs/SUPPORT.md exists and is readable.
 *   2. docs/SUPPORT.md specifies the exact 6-month support duration matching listing.md.
 *   3. docs/SUPPORT.md specifies the 1-year updates policy matching listing.md.
 *   4. docs/SUPPORT.md defines the official support channel (ticket system) and response time SLA.
 *   5. docs/SUPPORT.md explicitly distinguishes included scope (bugs, installation) from excluded scope (custom dev).
 *   6. README.md links directly to docs/SUPPORT.md.
 *
 *   php tests/theme/test-support-doc.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );

$passed = 0;
$failed = 0;

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

echo "=== Support & Warranty Document Contract Test (Prompt 95) ===\n\n";

// 1. docs/SUPPORT.md exists
$support_file = $root . '/docs/SUPPORT.md';
check( file_exists( $support_file ), 'docs/SUPPORT.md exists' );
$content = file_get_contents( $support_file );

// 2. 6 months support duration
check( strpos( $content, '۶ ماه' ) !== false, 'docs/SUPPORT.md defines 6-month support duration' );

// 3. 1 year updates policy
check( strpos( $content, 'یک سال' ) !== false || strpos( $content, '۱ سال' ) !== false || strpos( $content, '۱۲ ماه' ) !== false, 'docs/SUPPORT.md defines 1-year updates policy' );

// 4. Official communication channel & response SLA
check( strpos( $content, 'تیکت' ) !== false, 'docs/SUPPORT.md specifies ticket system channel' );
check( strpos( $content, 'ساعت' ) !== false && ( strpos( $content, '۱۲' ) !== false || strpos( $content, '۲۴' ) !== false ), 'docs/SUPPORT.md defines response time SLA' );

// 5. Scope coverage: included (bugs, installation) vs excluded (custom development)
check( strpos( $content, 'باگ' ) !== false, 'docs/SUPPORT.md includes bug fixes in scope' );
check( strpos( $content, 'نصب' ) !== false, 'docs/SUPPORT.md includes installation guidance in scope' );
check( strpos( $content, 'سفارشی‌سازی اختصاصی' ) !== false, 'docs/SUPPORT.md explicitly excludes custom development' );

// 6. Ed25519 updates & backup mention
check( strpos( $content, 'Ed25519' ) !== false, 'docs/SUPPORT.md references Ed25519 signed update channel' );

// 7. README.md link
$readme_file = $root . '/README.md';
check( file_exists( $readme_file ), 'README.md exists' );
$readme_content = file_get_contents( $readme_file );
check( strpos( $readme_content, 'docs/SUPPORT.md' ) !== false, 'README.md links directly to docs/SUPPORT.md' );

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
