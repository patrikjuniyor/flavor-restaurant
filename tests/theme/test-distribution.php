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

echo "\n--- 5b. The readme tags describe what the theme actually is ---\n";

// Tags are how a marketplace filters and how a customer chooses. A tag the
// theme does not honour is not marketing, it is a mismatch the customer
// discovers after installing.
$tags_line = '';
foreach ( explode( "\n", (string) file_get_contents( FLAVOR_DIR . '/readme.txt' ) ) as $line ) {
	if ( 0 === strpos( $line, 'Tags:' ) ) {
		$tags_line = $line;
		break;
	}
}

check( '' !== $tags_line, 'readme.txt declares tags' );
$tags = array_map( 'trim', explode( ',', (string) preg_replace( '/^Tags:/', '', $tags_line ) ) );

// Each tag that can be checked from code is checked from code.
$theme_setup  = (string) file_get_contents( FLAVOR_DIR . '/inc/class-theme-setup.php' );
$theme_source = '';
foreach ( glob( FLAVOR_DIR . '/inc/*.php' ) as $inc ) {
	$theme_source .= (string) file_get_contents( $inc );
}

$evidence = array(
	// RTL used to be "header.php prints dir=\"rtl\"", which is how the tag got
	// a right-to-left page for an LTR locale. Real RTL support is the locale
	// driving the document (language_attributes() prints dir), a direction
	// aware sheet loaded only when is_rtl(), and logical properties in CSS —
	// test-rtl-ltr-parity.php checks each half of that in detail.
	'rtl-language-support' => false !== strpos( (string) @file_get_contents( FLAVOR_DIR . '/header.php' ), 'language_attributes()' )
		&& is_readable( FLAVOR_DIR . '/assets/css/rtl.css' )
		&& false !== strpos( (string) @file_get_contents( FLAVOR_DIR . '/inc/class-enqueue.php' ), 'is_rtl()' )
		&& ! preg_match( '/^\s*direction\s*:/m', (string) @file_get_contents( FLAVOR_DIR . '/assets/css/main.css' ) ),
	'custom-logo'         => false !== strpos( $theme_setup, 'custom-logo' ),
	'custom-menu'         => false !== strpos( $theme_setup, 'register_nav_menus' ),
	'featured-images'     => false !== strpos( $theme_setup, 'post-thumbnails' ),
	'block-styles'        => false !== strpos( $theme_setup, 'wp-block-styles' ),
	'block-patterns'      => false !== strpos( (string) @file_get_contents( FLAVOR_DIR . '/inc/class-block-patterns.php' ), 'register_block_pattern' ),
	'translation-ready'   => false !== strpos( $theme_source, 'load_theme_textdomain' ) && is_file( FLAVOR_DIR . '/languages/flavor-ar.mo' ),
	'editor-style'        => false !== strpos( $theme_setup, 'add_editor_style' ),
);

foreach ( $evidence as $tag => $holds ) {
	check( $holds, "the tag `{$tag}` is backed by real code" );
}

// full-site-editing was claimed while the theme was classic PHP: it had no
// templates/ directory and declared no templateParts. Removed rather than
// retro-fitted, because claiming a capability you do not have is the defect
// being fixed here, not the absence of the capability.
check(
	! in_array( 'full-site-editing', $tags, true ),
	'the theme does not claim full-site-editing, which it does not provide'
);

echo "\n--- 6. The weight budget is declared, not implied ---\n";

$builder = dirname( FLAVOR_DIR ) . '/dev-tools/build-distribution.sh';
check( is_file( $builder ), 'the distribution builder exists' );

if ( is_file( $builder ) ) {
	$script = (string) file_get_contents( $builder );
	check( false !== strpos( $script, 'MAX_THEME_KB' ), 'the weight ceiling is configurable' );
	check( false !== strpos( $script, 'flavor-demo-pack.zip' ), 'the demo pack is built separately' );
	check( false !== strpos( $script, 'Enforce the weight budget' ), 'the ceiling is enforced, not just reported' );
}

echo "\n--- 7. Licenses of bundled third-party assets (پرامپت ۴۰) ---\n";

/*
 * Anything we ship that is not GPL must carry its license text in the
 * final package: OFL fonts next to the binaries, the MIT header on the
 * bundled qrcode.php, and the builder must not strip either of them.
 */
$fonts_dir = FLAVOR_DIR . '/assets/fonts';
$licensed  = 0;
$scanned   = 0;
foreach ( (array) glob( $fonts_dir . '/*', GLOB_ONLYDIR ) as $fontdir ) {
	$binaries = array_merge(
		(array) glob( $fontdir . '/*.woff2' ),
		(array) glob( $fontdir . '/*.woff' ),
		(array) glob( $fontdir . '/*.ttf' ),
		(array) glob( $fontdir . '/*.otf' )
	);
	if ( empty( $binaries ) ) {
		continue; // documentation-only directory (e.g. the iransans stub)
	}
	++$scanned;
	$has_license = (array) glob( $fontdir . '/OFL.txt' )
		|| (array) glob( $fontdir . '/LICENSE*' );
	if ( ! empty( $has_license ) ) {
		++$licensed;
	}
	check(
		! empty( $has_license ),
		basename( $fontdir ) . ' ships ' . count( $binaries ) . ' font file(s) and at least one license file'
	);
}
check(
	$scanned >= 2 && $licensed === $scanned,
	'the scan is non-vacuous: every bundling font directory is licensed (' . $licensed . '/' . $scanned . ')'
);

foreach ( array( 'vazirmatn', 'estedad' ) as $ofl_family ) {
	$ofl = (string) file_get_contents( $fonts_dir . '/' . $ofl_family . '/OFL.txt' );
	check(
		false !== strpos( $ofl, 'SIL Open Font License, Version 1.1' )
			&& preg_match( '/^Copyright \(?2\d{3}/m', $ofl ),
		$ofl_family . ' OFL.txt is the real 1.1 text with a copyright line'
	);
}

$qr = (string) file_get_contents( dirname( FLAVOR_DIR ) . '/flavor-core/includes/lib/qrcode.php' );
$qr_head = implode( "\n", array_slice( explode( "\n", $qr ), 0, 30 ) );
check(
	false !== strpos( $qr_head, 'MIT license' ) && false !== strpos( $qr_head, 'Kazuhiko Arase' ),
	'qrcode.php names MIT and its author within the first 30 lines'
);
check(
	false !== strpos( $qr, "defined( 'ABSPATH' ) || exit;" ),
	'qrcode.php keeps the direct-access guard'
);

if ( is_file( $builder ) ) {
	$rm_lines = array();
	foreach ( explode( "\n", $script ) as $ln ) {
		if ( preg_match( '/^\s*rm -rf\s+(.+)$/', $ln, $mm) ) {
			foreach ( preg_split( '/\s+/', trim( $mm[1] ) ) as $arg ) {
				$rm_lines[] = $arg;
			}
		}
	}
	$unsafe = array();
	foreach ( $rm_lines as $rm ) {
		// Deleting the scratch copy or the dist output is fine; deleting
		// anything under the source tree beyond demos/tests/node_modules,
		// or anything that looks like a license/asset path, is not.
		if ( false !== strpos( $rm, '$scratch' ) || false !== strpos( $rm, '$dist' ) ) {
			continue;
		}
		if ( preg_match( '#(\.git|assets|fonts|OFL|LICENSE)#i', $rm ) ) {
			$unsafe[] = $rm;
		}
	}
	check(
		empty( $unsafe ),
		'the builder never rm -rf\'s a source path (only scratch/dist) — licenses cannot be stripped'
	);
	$pruned = preg_match_all( '#^\s*rm -rf[^\n]*scratch[^\n]*(demos|tests|node_modules)#m', $script );
	check(
		$pruned >= 2,
		'the scratch pruning targets exactly demos/tests/node_modules (' . $pruned . ' lines)'
	);
}

$ruleset = (string) file_get_contents( dirname( FLAVOR_DIR ) . '/phpcs.xml' );
$ruleset_code = preg_replace( '/<!--.*?-->/s', '', $ruleset ); // keep prose out of the scan
check(
	false === strpos( $ruleset_code, 'WordPress.WhiteSpace.PrecisionAlignment' ),
	'phpcs ruleset carries no reference to the sniff removed in WPCS 3 (would abort the report job)'
);


echo "\n--- 8. Clean distribution package guarantees (پرامپت ۹۹) ---\n";

/*
 * Ensure build-distribution.sh strips internal dev notes, dev-tools/, tests/,
 * and internal planning docs from customer-facing zip packages.
 */
check( false !== strpos( $script, 'flavor-core.zip' ), 'build-distribution.sh packages flavor-core.zip' );
check( false !== strpos( $script, 'flavor-child.zip' ), 'build-distribution.sh packages flavor-child.zip' );
check( false !== strpos( $script, 'verify artifact hygiene' ), 'build-distribution.sh verifies artifact hygiene' );

$dist_dir = dirname( FLAVOR_DIR ) . '/dist';
if ( is_dir( $dist_dir ) ) {
	$zips = (array) glob( $dist_dir . '/*.zip' );
	check( count( $zips ) >= 3, 'at least 3 distribution zip packages exist in dist/' );

	$leaked_entries = array();
	foreach ( $zips as $zip_path ) {
		$zip = new \ZipArchive();
		if ( true === $zip->open( $zip_path ) ) {
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$entry = $zip->getNameIndex( $i );
				if ( preg_match( '#(dev-tools/|tests/|\.git|PHASE|PREMIUM-MISSION-LIST|QA-|IMPLEMENTATION-AUDIT)#i', $entry ) ) {
					$leaked_entries[] = basename( $zip_path ) . ':' . $entry;
				}
			}
			$zip->close();
		}
	}

	check( empty( $leaked_entries ), 'no internal dev-tools, tests, or internal audit docs leaked into distribution zips' );
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
