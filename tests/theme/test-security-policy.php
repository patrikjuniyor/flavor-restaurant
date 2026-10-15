<?php
/**
 * Test contract for Security Policy & Vulnerability Disclosure (پرامپت ۹۶ / فاز ۱۱).
 *
 * Verifies:
 *   1. SECURITY.md exists and is readable.
 *   2. SECURITY.md covers supported versions table (1.6.x for Theme, 1.5.x for Core).
 *   3. SECURITY.md specifies email reporting channel for coordinated disclosure.
 *   4. SECURITY.md defines response SLAs (acknowledgment within 24-48h, patch timeline).
 *   5. SECURITY.md includes token revocation warnings and policy matching Prompt 32 / R3-12.
 *   6. SECURITY.md specifies HMAC OTP protection, rate limits, and Ed25519 update channel.
 *
 *   php tests/theme/test-security-policy.php
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

echo "=== Security Policy Contract Test (Prompt 96) ===\n\n";

$sec_file = $root . '/SECURITY.md';
check( file_exists( $sec_file ), 'SECURITY.md exists' );
$content = file_get_contents( $sec_file );

// 1. Supported versions
check( strpos( $content, '1.6.x' ) !== false || strpos( $content, '1.6.2' ) !== false, 'SECURITY.md defines Theme 1.6.x support' );
check( strpos( $content, '1.5.x' ) !== false || strpos( $content, '1.5.1' ) !== false, 'SECURITY.md defines Core 1.5.x support' );
check( strpos( $content, 'Supported Versions' ) !== false || strpos( $content, 'نسخه‌های تحت پشتیبانی' ) !== false, 'SECURITY.md has Supported Versions section' );

// 2. Vulnerability reporting via email
check( strpos( $content, '@' ) !== false && ( strpos( $content, 'security@' ) !== false || strpos( $content, '@users.noreply.github.com' ) !== false ), 'SECURITY.md provides email reporting channel' );
check( strpos( $content, 'محرمانه' ) !== false || strpos( $content, 'privately' ) !== false || strpos( $content, 'Coordinated' ) !== false, 'SECURITY.md mandates private/coordinated disclosure' );

// 3. Response timeline & SLA
check( strpos( $content, '۲۴' ) !== false || strpos( $content, '۴۸' ) !== false || strpos( $content, '24' ) !== false || strpos( $content, '48' ) !== false, 'SECURITY.md defines initial response time' );
check( strpos( $content, 'پچ' ) !== false || strpos( $content, 'Patch' ) !== false, 'SECURITY.md outlines patch delivery commitments' );

// 4. Token revocation warnings (Prompt 32 / R3-12)
check( strpos( $content, 'Revoke' ) !== false || strpos( $content, 'ابطال' ) !== false || strpos( $content, 'باطل' ) !== false, 'SECURITY.md details token revocation policy' );
check( strpos( $content, 'Personal access tokens' ) !== false || strpos( $content, 'ghp_' ) !== false, 'SECURITY.md covers GitHub Personal Access Tokens' );

// 5. Core security hardening pillars
check( strpos( $content, 'HMAC' ) !== false, 'SECURITY.md covers HMAC hashed OTP' );
check( strpos( $content, 'Ed25519' ) !== false, 'SECURITY.md references Ed25519 signed update channel' );

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
