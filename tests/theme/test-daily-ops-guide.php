<?php
/**
 * Test contract for daily operations guide (docs/fa/03-bahrebardari.md).
 * Prompt 89 contract gate.
 *
 * @package Flavor
 */

declare( strict_types=1 );

$root = dirname( __DIR__, 2 );
$doc_file = $root . '/docs/fa/03-bahrebardari.md';

if ( ! file_exists( $doc_file ) ) {
	fwrite( STDERR, "Check 1 FAIL: docs/fa/03-bahrebardari.md does not exist.\n" );
	exit( 1 );
}

$content = file_get_contents( $doc_file );

// Check 1: Document length substantial (at least 3000 bytes).
if ( strlen( $content ) < 3000 ) {
	fwrite( STDERR, "Check 1 FAIL: docs/fa/03-bahrebardari.md too short (" . strlen( $content ) . " bytes).\n" );
	exit( 1 );
}
echo "OK 1: docs/fa/03-bahrebardari.md is substantial (" . strlen( $content ) . " bytes).\n";

// Check 2: Covers daily operating cycle topics.
$cycle_topics = array(
	'چرخهٔ روزانه',
	'ساعات کاری',
	'زمان‌بندی منو',
	'کیوسک آشپزخانه',
	'ناموجود کردن موقت آیتم‌ها',
	'گزارش فروش',
);

foreach ( $cycle_topics as $topic ) {
	if ( false === strpos( $content, $topic ) ) {
		fwrite( STDERR, "Check 2 FAIL: docs/fa/03-bahrebardari.md missing operating cycle topic: {$topic}\n" );
		exit( 1 );
	}
}
echo "OK 2: Operating cycle topics fully covered.\n";

// Check 3: Troubleshooting section covers the 3 specific issues from prompt.
$troubleshooting_topics = array(
	'سفارش ثبت نمی‌شود',
	'پیامک‌های تایید، OTP یا تغییر وضعیت ارسال نمی‌شود',
	'اسکن بارکد QR میز کار نمی‌کند',
);

foreach ( $troubleshooting_topics as $topic ) {
	if ( false === strpos( $content, $topic ) ) {
		fwrite( STDERR, "Check 3 FAIL: docs/fa/03-bahrebardari.md missing required troubleshooting case: {$topic}\n" );
		exit( 1 );
	}
}
echo "OK 3: All 3 mandatory troubleshooting cases present.\n";

// Check 4: KDS and live operation routes.
if ( false === strpos( $content, '/kitchen-dashboard/' ) || false === strpos( $content, '/order-tracking/' ) ) {
	fwrite( STDERR, "Check 4 FAIL: docs/fa/03-bahrebardari.md missing live routes.\n" );
	exit( 1 );
}
echo "OK 4: Kitchen dashboard and tracking routes present.\n";

// Check 5: Phone order and loyalty covered.
if ( false === strpos( $content, 'سفارش تلفنی' ) || false === strpos( $content, 'باشگاه مشتریان' ) ) {
	fwrite( STDERR, "Check 5 FAIL: docs/fa/03-bahrebardari.md missing phone order or loyalty coverage.\n" );
	exit( 1 );
}
echo "OK 5: Phone order and loyalty programs covered.\n";

echo "ALL 5/5 DAILY OPERATIONS GUIDE CHECKS PASSED!\n";
exit( 0 );
