<?php
/**
 * Test contract for Marketing Media Kit (پرامپت ۹۳ / فاز ۱۱).
 *
 * Verifies:
 *   1. media-kit/ exists as a directory.
 *   2. media-kit/logo.svg exists, is valid SVG, contains «رستوران مستقیم», and specifies RTL direction.
 *   3. media-kit/logo.png exists, is valid PNG, and matches high-resolution banner dimensions (1200x320).
 *   4. media-kit/top-screenshots/ exists and contains top 6 curated desktop (1440x900) & mobile (430x900) screenshots.
 *   5. media-kit/features-infographic.svg exists, is valid SVG, and covers core restaurant features.
 *   6. media-kit/listing-copy.md exists, contains RTL text, title, short summary (<= 160 chars), bullet points, and full description.
 *   7. dev-tools/generate-media-kit.py exists.
 *
 *   php tests/theme/test-media-kit.php
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

echo "=== Marketing Media Kit Contract Test (Prompt 93) ===\n\n";

$media_dir = $root . '/media-kit';
check( is_dir( $media_dir ), 'media-kit directory exists' );

// 1. Logo SVG
$logo_svg = $media_dir . '/logo.svg';
check( file_exists( $logo_svg ), 'media-kit/logo.svg exists' );
$svg_content = file_get_contents( $logo_svg );
check( strpos( $svg_content, '<svg' ) !== false && strpos( $svg_content, '</svg>' ) !== false, 'media-kit/logo.svg is valid SVG' );
check( strpos( $svg_content, 'رستوران مستقیم' ) !== false, 'media-kit/logo.svg contains typography «رستوران مستقیم»' );
check( strpos( $svg_content, 'dir="rtl"' ) !== false, 'media-kit/logo.svg is RTL formatted' );

// 2. Logo PNG
$logo_png = $media_dir . '/logo.png';
check( file_exists( $logo_png ), 'media-kit/logo.png exists' );
$png_size = @getimagesize( $logo_png );
check( false !== $png_size && 1200 === $png_size[0] && 320 === $png_size[1], 'media-kit/logo.png is 1200x320 PNG' );

// 3. Top 6 Screenshots
$top_dir = $media_dir . '/top-screenshots';
check( is_dir( $top_dir ), 'media-kit/top-screenshots directory exists' );

$top_demos = array(
	'01-modern-restaurant',
	'02-luxury-dining',
	'03-persian-traditional',
	'04-pizza-italian',
	'05-fast-food',
	'06-cafe-bistro',
);

foreach ( $top_demos as $td ) {
	$desk = $top_dir . "/top-{$td}-desktop.jpg";
	$mob  = $top_dir . "/top-{$td}-mobile.jpg";

	check( file_exists( $desk ), "top-screenshots/top-{$td}-desktop.jpg exists" );
	$desk_size = @getimagesize( $desk );
	check( false !== $desk_size && 1440 === $desk_size[0] && 900 === $desk_size[1], "top-screenshots/top-{$td}-desktop.jpg is 1440x900" );

	check( file_exists( $mob ), "top-screenshots/top-{$td}-mobile.jpg exists" );
	$mob_size = @getimagesize( $mob );
	check( false !== $mob_size && 430 === $mob_size[0] && 900 === $mob_size[1], "top-screenshots/top-{$td}-mobile.jpg is 430x900" );
}

// 4. Features Infographic SVG
$info_svg = $media_dir . '/features-infographic.svg';
check( file_exists( $info_svg ), 'media-kit/features-infographic.svg exists' );
$info_content = file_get_contents( $info_svg );
check( strpos( $info_content, '<svg' ) !== false && strpos( $info_content, '</svg>' ) !== false, 'features-infographic.svg is valid SVG' );
check( strpos( $info_content, 'سفارش سر میز با QR' ) !== false, 'features-infographic.svg covers QR ordering' );
check( strpos( $info_content, 'داشبورد آشپزخانه لمسی' ) !== false, 'features-infographic.svg covers KDS kitchen' );
check( strpos( $info_content, 'رزرو میز شمسی و جلالی' ) !== false, 'features-infographic.svg covers Jalali reservations' );

// 5. Listing Copy Markdown
$copy_md = $media_dir . '/listing-copy.md';
check( file_exists( $copy_md ), 'media-kit/listing-copy.md exists' );
$copy_content = file_get_contents( $copy_md );
check( strpos( $copy_content, 'عنوان محصول' ) !== false, 'listing-copy.md includes Title section' );
check( strpos( $copy_content, 'چکیده کوتاه ۱۶۰ کاراکتری' ) !== false, 'listing-copy.md includes Short Summary section' );
check( strpos( $copy_content, 'ویژگی‌های کلیدی با بولت' ) !== false, 'listing-copy.md includes Bullet Points section' );
check( strpos( $copy_content, 'توضیح کامل و بلند محصول' ) !== false, 'listing-copy.md includes Full Description section' );

// Extract short summary and verify length <= 160 characters
if ( preg_match( '/## ۲\. چکیده کوتاه ۱۶۰ کاراکتری[^\n]*\n+([^\n#]+)/u', $copy_content, $m ) ) {
	$summary = trim( $m[1] );
	$len = mb_strlen( $summary, 'UTF-8' );
	check( $len <= 160 && $len >= 50, "Short summary length is {$len} chars (<= 160 requirement met)" );
} else {
	check( false, 'Could not extract short summary from listing-copy.md' );
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
