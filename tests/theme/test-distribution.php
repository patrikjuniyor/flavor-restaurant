<?php
/**
 * Distribution weight contract tests (N-15).
 *
 * Splitting the demo pack out of the theme only works if the theme still
 * renders without it. A theme that points at /demos/... and ships without
 * that directory shows broken-image icons on every section, which reads as
 * a defect rather than as "install the demo pack".
 *
 * These tests assert the degradation, not the packaging: the zip builder is
 * a shell script, but the guarantee it depends on lives in PHP.
 *
 *   php tests/theme/test-distribution.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';

require_once FLAVOR_DIR . '/inc/template-tags.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-bespoke-demos.php';
require_once FLAVOR_DIR . '/inc/class-customizer.php';
require_once FLAVOR_DIR . '/inc/class-repeater.php';

use Flavor\Bespoke_Demos;
use Flavor\Design;
use Flavor\Repeater;

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

echo "=== Flavor Distribution Weight Tests ===\n\n";

echo "--- 1. A placeholder exists for art that is not installed ---\n";

$placeholder_path = FLAVOR_DIR . '/assets/img/demo-placeholder.svg';
check( is_file( $placeholder_path ), 'the placeholder file is bundled with the theme' );
check(
	is_file( $placeholder_path ) && filesize( $placeholder_path ) < 4096,
	'the placeholder is small enough to ship everywhere (' . ( is_file( $placeholder_path ) ? filesize( $placeholder_path ) : 0 ) . ' bytes)'
);
check( false !== strpos( Bespoke_Demos::placeholder(), 'demo-placeholder.svg' ), 'placeholder() points at it' );

echo "\n--- 2. asset() never returns a URL for a file that is not there ---\n";

// Every one of these is a name the templates ask for. Whether the file
// exists depends on whether the demo pack is installed; asset() must be
// correct either way.
$requested = array( 'hero.jpg', 'story.jpg', 'category-shots.jpg', 'category-bread.jpg', 'menu.jpg', 'about.jpg' );

foreach ( $requested as $file ) {
	$url  = Bespoke_Demos::asset( $file );
	$path = FLAVOR_DIR . '/demos/' . Design::current_skin() . '/' . $file;

	if ( is_readable( $path ) ) {
		check( false !== strpos( $url, $file ), "{$file} is installed, so it is served" );
	} elseif ( is_readable( FLAVOR_DIR . '/demos/' . Design::current_skin() . '/hero.jpg' ) ) {
		check( false !== strpos( $url, 'hero.jpg' ), "{$file} is missing, so the skin hero substitutes" );
	} else {
		check( $url === Bespoke_Demos::placeholder(), "{$file} is missing and so is the hero, so the placeholder is used" );
	}

	check( 0 === strpos( $url, 'http' ), "{$file} resolves to an absolute URL" );
}

// Traversal must still be rejected after the change.
check( false === strpos( Bespoke_Demos::asset( '../../../etc/passwd' ), 'passwd' ), 'traversal is still rejected' );
check( false === strpos( Bespoke_Demos::asset( 'sub/dir/x.jpg' ), 'sub/dir' ), 'a nested path is flattened' );

echo "\n--- 3. The gallery offers only art that exists ---\n";

$items = Repeater::items( 'gallery' );

$demo_dir = FLAVOR_DIR . '/demos';
$has_demo = is_dir( $demo_dir );

foreach ( $items as $index => $item ) {
	$image = (string) ( $item['image'] ?? '' );
	check( '' !== $image, "gallery item {$index} has an image" );

	if ( 0 === strpos( $image, 'https://example.test/wp-content/themes/flavor/demos/' ) ) {
		$relative = substr( $image, strlen( 'https://example.test/wp-content/themes/flavor/' ) );
		check(
			is_readable( FLAVOR_DIR . '/' . $relative ),
			"gallery item {$index} points at a file that exists on disk"
		);
	}
}

if ( $has_demo ) {
	check( ! empty( $items ), 'with the demo pack present the gallery has items (' . count( $items ) . ')' );
}

echo "\n--- 4. The packless theme emits no dead demo URL ---\n";

// Simulate the sellable zip: the same theme with demos/ removed. Any code
// path that builds a /demos/ URL without checking is_readable() would
// produce a 404 here.
$scratch = sys_get_temp_dir() . '/flavor-packless-' . getmypid();
@mkdir( $scratch );

$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( FLAVOR_DIR, \FilesystemIterator::SKIP_DOTS ) );
$copied   = 0;
foreach ( $iterator as $file ) {
	$relative = substr( $file->getPathname(), strlen( FLAVOR_DIR ) + 1 );
	if ( 0 === strpos( $relative, 'demos' . DIRECTORY_SEPARATOR ) ) {
		continue;
	}
	if ( ! $file->isFile() ) {
		continue;
	}
	$target = $scratch . '/' . $relative;
	@mkdir( dirname( $target ), 0755, true );
	@copy( $file->getPathname(), $target );
	++$copied;
}

check( $copied > 150, 'the packless copy was built (' . $copied . ' files)' );
check( ! is_dir( $scratch . '/demos' ), 'the packless copy really has no demos directory' );

// Scan the shipped PHP for demo URLs built without a readability check.
$offenders = array();
$php_files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $scratch ) );
foreach ( $php_files as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}
	$source = (string) file_get_contents( $file->getPathname() );

	// Only a raw "FLAVOR_URI . '/demos/'" concatenation is a hazard: it
	// builds a URL from a filename without asking whether the file is
	// there. Everything else reaches the filesystem through
	// Bespoke_Demos::asset(), which checks. class-bespoke-demos.php is the
	// one file allowed to do it, because it is the function that checks.
	$relative = substr( $file->getPathname(), strlen( $scratch ) + 1 );
	if ( false === strpos( $source, "FLAVOR_URI . '/demos/'" ) ) {
		continue;
	}
	if ( 'inc/class-bespoke-demos.php' === $relative ) {
		continue;
	}
	if ( false !== strpos( $source, 'is_readable' ) ) {
		continue;
	}
	$offenders[] = $relative;
}

check(
	empty( $offenders ),
	'no PHP builds a /demos/ URL without checking the file first'
	. ( empty( $offenders ) ? '' : ' — ' . implode( ', ', array_slice( $offenders, 0, 5 ) ) )
);

// Clean up.
$cleanup = new \RecursiveIteratorIterator(
	new \RecursiveDirectoryIterator( $scratch, \FilesystemIterator::SKIP_DOTS ),
	\RecursiveIteratorIterator::CHILD_FIRST
);
foreach ( $cleanup as $item ) {
	$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
}
@rmdir( $scratch );

echo "\n--- 5. The screenshot is a reasonable size ---\n";

foreach ( array( FLAVOR_DIR . '/screenshot.png', dirname( FLAVOR_DIR ) . '/flavor-child/screenshot.png' ) as $shot ) {
	if ( ! is_file( $shot ) ) {
		continue;
	}
	$name  = basename( dirname( $shot ) );
	$bytes = (int) filesize( $shot );
	check( $bytes < 1048576, "{$name}/screenshot.png is under a megabyte (" . round( $bytes / 1024 ) . ' KB)' );
}

echo "\n--- 6. The weight budget is declared, not implied ---\n";

$builder = dirname( FLAVOR_DIR ) . '/dev-tools/build-distribution.sh';
check( is_file( $builder ), 'the distribution builder exists' );

if ( is_file( $builder ) ) {
	$script = (string) file_get_contents( $builder );
	check( false !== strpos( $script, 'MAX_THEME_KB' ), 'the weight ceiling is configurable' );
	check( false !== strpos( $script, 'flavor-demo-pack.zip' ), 'the demo pack is built separately' );
	check( false !== strpos( $script, 'Enforce the weight budget' ), 'the ceiling is enforced, not just reported' );
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
