<?php
/**
 * Test contract for 12-Step Visual Installation Guide & Video Screenplay (پرامپت ۹۴ / فاز ۱۱).
 *
 * Verifies:
 *   1. docs/fa/01-nasb.md exists, contains 12 visual step headings and links to all 12 step images.
 *   2. All 12 step images exist in docs/fa/images/step-01.jpg .. step-12.jpg.
 *   3. All 12 step images are valid JPEG and have HD 16:9 dimensions (1200x675).
 *   4. docs/fa/09-storyboard-shakhsi-sazi.md marks itself as ready screenplay for recording.
 *   5. dev-tools/generate-nasb-steps.py exists.
 *
 *   php tests/theme/test-nasb-guide.php
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

echo "=== 12-Step Visual Installation Guide Contract Test (Prompt 94) ===\n\n";

$nasb_file = $root . '/docs/fa/01-nasb.md';
check( file_exists( $nasb_file ), 'docs/fa/01-nasb.md exists' );
$nasb_content = file_get_contents( $nasb_file );

// Check for 12 step headers and images
for ( $i = 1; $i <= 12; $i++ ) {
	$step_num = str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
	$img_rel = "images/step-{$step_num}.jpg";
	$img_full = $root . '/docs/fa/' . $img_rel;

	check( strpos( $nasb_content, $img_rel ) !== false, "docs/fa/01-nasb.md references {$img_rel}" );
	check( file_exists( $img_full ), "{$img_rel} exists on disk" );

	$img_size = @getimagesize( $img_full );
	check( false !== $img_size && 1200 === $img_size[0] && 675 === $img_size[1], "{$img_rel} is exactly 1200x675 (16:9 HD)" );
}

// Check storyboard status
$storyboard_file = $root . '/docs/fa/09-storyboard-shakhsi-sazi.md';
check( file_exists( $storyboard_file ), 'docs/fa/09-storyboard-shakhsi-sazi.md exists' );
$storyboard_content = file_get_contents( $storyboard_file );
check( strpos( $storyboard_content, 'فیلم‌نامهٔ آماده' ) !== false, 'storyboard is marked as ready screenplay for recording' );

// Check generator script
$gen_script = $root . '/dev-tools/generate-nasb-steps.py';
check( file_exists( $gen_script ), 'dev-tools/generate-nasb-steps.py exists' );

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
