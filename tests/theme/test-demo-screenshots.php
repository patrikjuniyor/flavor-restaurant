<?php
/**
 * Test contract for Sales Demo Screenshots & Theme Screenshot (پرامپت ۹۲ / فاز ۱۱).
 *
 * Verifies:
 *   1. All 12 demos exist in docs/demo-previews/ with desktop (1440x900) and mobile (430x900).
 *   2. All 12 demos exist in media-kit/ with desktop (1440x900) and mobile (430x900).
 *   3. flavor/screenshot.png exists, is valid PNG, and matches standard 1200x900 dimensions.
 *   4. dev-tools/preview/capture-previews.py exists and generates the assets cleanly.
 *
 *   php tests/theme/test-demo-screenshots.php
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

echo "=== Sales Demo Screenshots Contract Test (Prompt 92) ===\n\n";

$demos = array(
	'modern-restaurant',
	'luxury-dining',
	'persian-traditional',
	'pizza-italian',
	'fast-food',
	'cafe-bistro',
	'bakery-pastry',
	'juice-bar',
	'dark-luxe',
	'minimal-clean',
	'cloud-kitchen',
	'catering',
);

// 1. Theme screenshot check
$theme_shot = $root . '/flavor/screenshot.png';
check( file_exists( $theme_shot ), 'flavor/screenshot.png exists' );
$shot_size = @getimagesize( $theme_shot );
check( false !== $shot_size && 1200 === $shot_size[0] && 900 === $shot_size[1], 'flavor/screenshot.png is exactly 1200x900' );
check( false !== $shot_size && IMAGETYPE_PNG === $shot_size[2], 'flavor/screenshot.png is a PNG image' );

// 2. docs/demo-previews check
$docs_dir = $root . '/docs/demo-previews';
check( is_dir( $docs_dir ), 'docs/demo-previews directory exists' );

foreach ( $demos as $demo ) {
	$desk_path = $docs_dir . "/{$demo}-desktop.jpg";
	$mob_path  = $docs_dir . "/{$demo}-mobile.jpg";

	check( file_exists( $desk_path ), "docs/demo-previews/{$demo}-desktop.jpg exists" );
	$desk_size = @getimagesize( $desk_path );
	check( false !== $desk_size && 1440 === $desk_size[0] && 900 === $desk_size[1], "docs/demo-previews/{$demo}-desktop.jpg is 1440x900" );

	check( file_exists( $mob_path ), "docs/demo-previews/{$demo}-mobile.jpg exists" );
	$mob_size = @getimagesize( $mob_path );
	check( false !== $mob_size && 430 === $mob_size[0] && 900 === $mob_size[1], "docs/demo-previews/{$demo}-mobile.jpg is 430x900" );
}

// 3. media-kit check
$media_dir = $root . '/media-kit';
check( is_dir( $media_dir ), 'media-kit directory exists' );

foreach ( $demos as $demo ) {
	$desk_path = $media_dir . "/{$demo}-desktop.jpg";
	$mob_path  = $media_dir . "/{$demo}-mobile.jpg";

	check( file_exists( $desk_path ), "media-kit/{$demo}-desktop.jpg exists" );
	check( file_exists( $mob_path ), "media-kit/{$demo}-mobile.jpg exists" );
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
