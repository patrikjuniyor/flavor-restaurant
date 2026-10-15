<?php
/**
 * Test contract for buyer installation guide (docs/fa/01-nasb.md).
 * Prompt 88 contract gate.
 *
 * @package Flavor
 */

declare( strict_types=1 );

$root = dirname( __DIR__, 2 );
$nasb_file = $root . '/docs/fa/01-nasb.md';

if ( ! file_exists( $nasb_file ) ) {
	fwrite( STDERR, "Check 1 FAIL: docs/fa/01-nasb.md does not exist.\n" );
	exit( 1 );
}

$content = file_get_contents( $nasb_file );

// Check 1: Substantial length (at least 3000 bytes).
if ( strlen( $content ) < 3000 ) {
	fwrite( STDERR, "Check 1 FAIL: docs/fa/01-nasb.md is too short (" . strlen( $content ) . " bytes).\n" );
	exit( 1 );
}
echo "OK 1: docs/fa/01-nasb.md is comprehensive (" . strlen( $content ) . " bytes).\n";

// Check 2: Mentions the two distribution zip files explicitly.
if ( false === strpos( $content, '`flavor.zip`' ) || false === strpos( $content, '`flavor-core.zip`' ) ) {
	fwrite( STDERR, "Check 2 FAIL: docs/fa/01-nasb.md must document flavor.zip and flavor-core.zip packages.\n" );
	exit( 1 );
}
echo "OK 2: Distribution zip packages explicitly documented.\n";

// Check 3: Clear step-by-step navigation paths with WP admin menus.
$required_menus = array(
	'افزونه‌ها ← افزودن',
	'نمایش ← پوسته‌ها',
	'تنظیمات ← پیوندهای یکتا',
	'نمایش ← دموهای Flavor',
	'رستوران مستقیم ← تنظیمات',
	'رستوران مستقیم ← میزها و QR',
	'ووکامرس ← پیکربندی ← پرداخت‌ها',
);

foreach ( $required_menus as $menu ) {
	if ( false === strpos( $content, $menu ) ) {
		fwrite( STDERR, "Check 3 FAIL: docs/fa/01-nasb.md missing admin navigation step: {$menu}\n" );
		exit( 1 );
	}
}
echo "OK 3: All essential WP admin menu navigation steps present.\n";

// Check 4: Mentions HPOS and WooCommerce activation.
if ( false === strpos( $content, 'HPOS' ) || false === strpos( $content, 'نصب WooCommerce' ) ) {
	fwrite( STDERR, "Check 4 FAIL: docs/fa/01-nasb.md must explain WooCommerce installation and HPOS.\n" );
	exit( 1 );
}
echo "OK 4: WooCommerce automatic/manual install and HPOS covered.\n";

// Check 5: Live operational paths documented.
$live_paths = array(
	'/kitchen-dashboard/',
	'/menu/',
	'/order-tracking/',
);

foreach ( $live_paths as $lp ) {
	if ( false === strpos( $content, $lp ) ) {
		fwrite( STDERR, "Check 5 FAIL: docs/fa/01-nasb.md missing live route: {$lp}\n" );
		exit( 1 );
	}
}
echo "OK 5: Live operational routes documented.\n";

// Check 6: Troubleshooting & common shared host errors section.
if ( false === strpos( $content, 'عیب‌یابی' ) || false === strpos( $content, 'upload_max_filesize' ) || false === strpos( $content, 'WP_MEMORY_LIMIT' ) ) {
	fwrite( STDERR, "Check 6 FAIL: docs/fa/01-nasb.md must have a troubleshooting section covering shared hosting limits.\n" );
	exit( 1 );
}
echo "OK 6: Shared hosting troubleshooting section present.\n";

echo "ALL 6/6 BUYER INSTALLATION GUIDE CHECKS PASSED!\n";
exit( 0 );
