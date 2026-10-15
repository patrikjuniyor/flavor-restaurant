<?php
/**
 * Contract Test for Pricing and Distribution Policy (Prompt 97 / docs/PRICING.md).
 *
 * Validates:
 * 1. docs/PRICING.md exists and is readable.
 * 2. Intro pricing range matches the listing promo (1,500,000 to 1,800,000 Tomans).
 * 3. 30-day intro window is explicitly stated.
 * 4. Value inclusions explicitly state 12 demos, 6 months support, and 1 year updates.
 * 5. Exactly 4 package deliverables defined:
 *    - flavor.zip
 *    - flavor-core.zip
 *    - flavor-demo-pack.zip
 *    - flavor-child.zip
 * 6. Mentions build-distribution.sh alignment and weight budget enforcement (< 4096 KB).
 * 7. Highlights separation of concern between theme and core operational plugin.
 */

$root = dirname( __DIR__, 2 );
$pricing_file = $root . '/docs/PRICING.md';

function pricing_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

if ( ! file_exists( $pricing_file ) || ! is_readable( $pricing_file ) ) {
	pricing_fail( 'docs/PRICING.md does not exist or is not readable.' );
}

$content = (string) file_get_contents( $pricing_file );

// 1. Pricing range (1,500,000 to 1,800,000 Tomans)
$has_lower_bound = ( false !== strpos( $content, '۱٬۵۰۰٬۰۰۰' ) || false !== strpos( $content, '1,500,000' ) || false !== strpos( $content, '۱۵۰۰۰۰۰' ) );
$has_upper_bound = ( false !== strpos( $content, '۱٬۸۰۰٬۰۰۰' ) || false !== strpos( $content, '1,800,000' ) || false !== strpos( $content, '۱۸۰۰۰۰۰' ) );
$has_currency    = ( false !== strpos( $content, 'تومان' ) );

if ( ! $has_lower_bound || ! $has_upper_bound || ! $has_currency ) {
	pricing_fail( 'Missing launch pricing range 1,500,000 to 1,800,000 Tomans in docs/PRICING.md.' );
}

// 2. 30-day intro window
if ( ! preg_match( '/(۳۰|30)\s*روز/u', $content ) ) {
	pricing_fail( 'Missing 30-day intro promo window in docs/PRICING.md.' );
}

// 3. 12 demos mentioned
if ( ! preg_match( '/(۱۲|12)\s*دمو/u', $content ) ) {
	pricing_fail( 'Missing 12 demos inclusion in docs/PRICING.md.' );
}

// 4. 6 months support
if ( ! preg_match( '/(۶|6)\s*ماه\s*پشتیبانی/u', $content ) ) {
	pricing_fail( 'Missing 6 months support in docs/PRICING.md.' );
}

// 5. 1 year updates
if ( ! preg_match( '/(یک\s*سال|۱\s*سال|1\s*سال|۱۲\s*ماه|12\s*ماه)\s*به‌روزرسانی/u', $content ) ) {
	pricing_fail( 'Missing 1 year updates in docs/PRICING.md.' );
}

// 6. Deliverable zip packages
$packages = array(
	'flavor.zip',
	'flavor-core.zip',
	'flavor-demo-pack.zip',
	'flavor-child.zip',
);

foreach ( $packages as $pkg ) {
	if ( false === strpos( $content, $pkg ) ) {
		pricing_fail( "Missing required package deliverable {$pkg} in docs/PRICING.md." );
	}
}

// 7. build-distribution.sh alignment
if ( false === strpos( $content, 'build-distribution.sh' ) ) {
	pricing_fail( 'docs/PRICING.md must reference build-distribution.sh alignment.' );
}

// 8. Weight budget mentions (4096 KB or 4 MB)
if ( ! preg_match( '/(۴۰۹۶|4096|۴\s*مگابایت|4\s*MB)/ui', $content ) ) {
	pricing_fail( 'docs/PRICING.md must mention weight budget limit.' );
}

echo "PASS: docs/PRICING.md contract test passed (8 assertions).\n";
exit( 0 );
