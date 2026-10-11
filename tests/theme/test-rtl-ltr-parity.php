<?php
/**
 * RTL / LTR parity tests.
 *
 * The theme ships Persian-first, and for a while that was written into the
 * markup itself: <html dir="rtl"> was hardcoded next to language_attributes(),
 * main.css forced direction and text-align, and the drawer parked itself with
 * a physical translateX(100%). Each of those made an LTR locale render wrong,
 * and the hardcoded dir also fought WordPress over the attribute.
 *
 * Direction must come from the document (language_attributes() prints dir from
 * the active locale), and every physical rule that survives has to have its
 * mirror. These checks are static on purpose: they run without a browser and
 * without WordPress, and they fail on the exact thing that regressed.
 *
 *   php tests/theme/test-rtl-ltr-parity.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

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

/**
 * Read a file from the theme, or return '' so the assertion reports it missing.
 *
 * @param string $relative Path under the theme directory.
 * @return string
 */
function read_theme( string $relative ): string {
	$path = FLAVOR_DIR . '/' . ltrim( $relative, '/' );
	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}

echo "=== Flavor RTL / LTR Parity Tests ===\n\n";

echo "--- 1. The document owns the direction, not the template ---\n";

foreach ( array( 'header.php', 'footer.php', 'template-parts/demos/header.php' ) as $template ) {
	$source = read_theme( $template );
	check( '' !== $source, "{$template} exists" );
	check(
		! preg_match( '/\bdir=[\'"]rtl[\'"]/', $source ),
		"{$template} does not hardcode dir=\"rtl\""
	);
	check(
		! preg_match( '/\bdir=[\'"]ltr[\'"]/', $source ),
		"{$template} does not hardcode dir=\"ltr\" either"
	);
}

check(
	(bool) preg_match( '/<html\s+<\?php\s+language_attributes\(\);\s*\?>>/', read_theme( 'header.php' ) ),
	'header.php opens <html> with language_attributes() alone'
);
check(
	false !== strpos( read_theme( 'template-parts/demos/header.php' ), 'language_attributes()' ),
	'the bespoke demo header uses language_attributes() too, so both skins follow the locale'
);

echo "\n--- 2. main.css carries no forced direction ---\n";

$main = read_theme( 'assets/css/main.css' );
check(
	! preg_match( '/^\s*direction\s*:/m', $main ),
	'main.css never declares direction (the dir attribute does)'
);
check(
	! preg_match( '/text-align:\s*(left|right)\b/', $main ),
	'main.css aligns with logical keywords, never text-align: left/right'
);
check(
	! preg_match( '/^\s*(left|right)\s*:/m', $main ),
	'main.css positions with inset-inline-*, not with bare left/right'
);
check(
	(bool) preg_match( '/\.flavor-skip\s*\{[^}]*inset-inline-end/', $main ),
	'the skip link sticks to the inline-end edge so it stays visible in both directions'
);

echo "\n--- 3. Every physical transform has its mirror ---\n";

check(
	(bool) preg_match( '/html\[dir="ltr"\]\s+\.flavor-drawer\s*\{[^}]*translateX\(-100%\)/', $main ),
	'the drawer gets a negative translateX for LTR (positive one stays the RTL default)'
);
check(
	(bool) preg_match( '/\.flavor-drawer\s*\{[^}]*translateX\(100%\)/', $main ),
	'the RTL drawer value is untouched — a Persian site sees exactly the old sheet'
);

$checkout = read_theme( 'assets/css/ui-checkout.css' );
check(
	(bool) preg_match( '/@keyframes\s+flavor-cart-in-ltr\s*\{[^}]*translateX\(-46px\)/', $checkout ),
	'the cart dialog entrance is mirrored for LTR'
);
check(
	(bool) preg_match( '/html\[dir="ltr"\][^{]*\{[^}]*animation-name:\s*flavor-cart-in-ltr/', $checkout ),
	'the mirrored cart animation is actually selected in LTR'
);

$premium = read_theme( 'assets/css/premium.css' );
check(
	(bool) preg_match( '/@keyframes\s+flavor-wish-in-ltr\s*\{[^}]*translateX\(24px\)/', $premium ),
	'the wishlist entrance is mirrored for LTR'
);

echo "\n--- 4. The RTL sheet is still loaded by condition, not by default ---\n";

$enqueue = read_theme( 'inc/class-enqueue.php' );
check(
	(bool) preg_match( '/if\s*\(\s*is_rtl\(\)\s*\)\s*\{[^}]*assets\/css\/rtl\.css/s', $enqueue ),
	'rtl.css is enqueued only when the locale is right-to-left'
);
$rtl = read_theme( 'assets/css/rtl.css' );
check(
	(bool) preg_match( '/\.flavor-breadcrumbs span::after\s*\{[^}]*content:\s*"←"/', $rtl ),
	'rtl.css flips the breadcrumb arrow for the right-to-left reading order'
);
check(
	(bool) preg_match( '/\.flavor-breadcrumbs span::after\s*\{[^}]*content:\s*"→"/', $main ),
	'main.css supplies the arrow for LTR, so the separator is not an RTL-only feature'
);

echo "\n--- 5. Standalone Persian documents declare a direction deliberately ---\n";

// The printable receipt and the kitchen screen are their own HTML documents,
// not the site front end. They are Persian by construction, so they say so —
// but they must say it, because a document with no dir and a mirrored sheet
// loaded conditionally is how you get a right-to-left receipt in left-to-right
// order.
$receipt = (string) @file_get_contents( dirname( FLAVOR_DIR ) . '/flavor-core/includes/Receipt/ReceiptRenderer.php' );
check(
	(bool) preg_match( '/<html\b[^>]*dir=|<html\b.*?\bdir=/s', $receipt ),
	'the printed receipt declares its direction explicitly'
);
$kitchen = (string) @file_get_contents( dirname( FLAVOR_DIR ) . '/flavor-core/admin/views/kitchen-dashboard.php' );
check(
	(bool) preg_match( '/<html\b[^>]*dir=|<html\b.*?\bdir=/s', $kitchen ),
	'the kitchen screen declares its direction explicitly'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
