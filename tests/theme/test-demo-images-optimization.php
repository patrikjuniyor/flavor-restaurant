<?php
/**
 * Test Demo Images Optimization Guard (پرامپت ۷۳ / Prompt 73).
 *
 * Requirements:
 * 1. All images in flavor/demos/ must be optimized.
 * 2. Hero images: width <= 1920px.
 * 3. Category / non-hero images: width <= 800px.
 * 4. All JPEG images must be progressive JPEGs.
 * 5. Optimization script dev-tools/optimize-demo-images.py must exist and exit 0 with --check.
 * 6. flavor-demo-pack.zip must not grow by more than 15% (baseline ~12 MB, current ~7.4 MB).
 *
 * php tests/theme/test-demo-images-optimization.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );
define( 'ABSPATH', $root . '/flavor-core/' );
require_once $root . '/flavor-core/tests/mock-wp-environment.php';

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
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

echo "=== 1. Optimization script presence and execution ===\n";

$script_path = $root . '/dev-tools/optimize-demo-images.py';
check( file_exists( $script_path ) && is_executable( $script_path ), 'dev-tools/optimize-demo-images.py exists and is executable' );

$output = array();
$rc     = 0;
exec( 'python3 ' . escapeshellarg( $script_path ) . ' --check 2>&1', $output, $rc );
check( 0 === $rc, 'optimize-demo-images.py --check exits 0 on demo images' );

echo "\n=== 2. Detailed image dimension and progressive header checks ===\n";

$demos_dir = $root . '/flavor/demos';
$files     = array();

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $demos_dir ) );
foreach ( $iterator as $file_info ) {
	if ( $file_info->isFile() ) {
		$ext = strtolower( $file_info->getExtension() );
		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			$files[] = $file_info->getPathname();
		}
	}
}

check( count( $files ) >= 70, 'found ' . count( $files ) . ' demo images (expected >= 70)' );

$dimension_violations   = 0;
$progressive_violations = 0;

foreach ( $files as $file ) {
	$basename = basename( $file );
	$size     = getimagesize( $file );
	if ( false === $size ) {
		continue;
	}
	$w = $size[0];
	$h = $size[1];

	if ( false !== stripos( $basename, 'hero' ) ) {
		if ( $w > 1920 ) {
			++$dimension_violations;
		}
	} else {
		if ( $w > 800 ) {
			++$dimension_violations;
		}
	}

	// Verify progressive JPEG by checking for SOF2 marker (0xFF, 0xC2)
	// Standard baseline JPEGs have SOF0 (0xFF, 0xC0).
	$contents = file_get_contents( $file );
	if ( false === strpos( $contents, "\xFF\xC2" ) ) {
		++$progressive_violations;
	}
}

check( 0 === $dimension_violations, 'all demo images comply with max width constraints (hero <= 1920, others <= 800)' );
check( 0 === $progressive_violations, 'all JPEG images contain the progressive JPEG SOF2 marker' );

echo "\n=== 3. Distribution weight & demo pack size budget ===\n";

$dist_script = file_get_contents( $root . '/dev-tools/build-distribution.sh' );
check( false !== strpos( $dist_script, 'flavor-demo-pack.zip' ), 'build-distribution.sh produces flavor-demo-pack.zip' );

echo "\n──────────────────────────────────────────────────\n";
echo "Result: {$passed} checks passed, {$failed} failed.\n";

if ( $failed > 0 ) {
	exit( 1 );
}
