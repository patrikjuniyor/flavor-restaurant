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
 * Prompt 57 grew the sweep into a twin: every icon producer stamps
 * data-icon so the directional arrow mirrors under html[dir="ltr"], every
 * direction-bound keyframe ships an -ltr twin, both keyboard walkers read
 * the document direction, the drawer anchors inline-start (the old
 * inline-end anchor left 90px of the parked LTR drawer on screen),
 * physical alignment and offset values are scanned out of every sheet, and
 * tests/ui/elementor-visual.mjs renders all main surfaces in both
 * directions to measure the mirrors live.
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


echo "\n--- 6. Arrow icons carry the data-icon stamp and mirror in LTR ---\n";

$ui_src      = read_theme( 'inc/class-ui.php' );
$bespoke_src = read_theme( 'inc/class-bespoke-demos.php' );
$chrome_src  = read_theme( 'inc/class-chrome-builder.php' );
foreach ( array( 'class-ui.php' => $ui_src, 'class-bespoke-demos.php' => $bespoke_src, 'class-chrome-builder.php' => $chrome_src ) as $producer => $producer_src ) {
	check(
		false !== strpos( $producer_src, 'data-icon=' ),
		"{$producer} stamps data-icon on every icon svg so directional glyphs are selectable"
	);
}
check(
	false !== strpos( $ui_src, 'M19 12H5m6-6-6 6 6 6' ),
	'the UI arrow still points left — the RTL reading direction stays the default'
);
check(
	false !== strpos( $bespoke_src, 'M19 12H5m6-6-6 6 6 6' ),
	'the bespoke-demo arrow still points left'
);
check(
	false !== strpos( $chrome_src, 'points="12 19 5 12 12 5"' ),
	'the chrome-builder arrow still points left'
);
$arrow_rule = implode( "\n", array(
	'html[dir="ltr"] svg[data-icon="arrow"] {',
	"\ttransform: scaleX(-1);",
	'}',
) );
check(
	false !== strpos( $main, $arrow_rule ),
	'main.css mirrors the arrow icon for LTR documents'
);

echo "\n--- 7. The drawer anchors inline-start; physical values stay out of the sheets ---\n";

check(
	false !== strpos( $main, "" . "\t" . "inset-inline-start: 0;" . "\n" . "" . "\t" . "width: min(340px, 85vw);" ),
	'the drawer parks on the inline-start edge — right in RTL, left in LTR'
);
$old_anchor = implode( "\n", array(
	"\tinset-inline-end: 0;",
	"\twidth: min(340px",
) );
check(
	false === strpos( $main, $old_anchor ),
	'the inline-end anchor is gone — it left 90px of the parked LTR drawer on screen'
);
$align_leaks  = array();
$offset_leaks = array();
$gap_leaks    = array();
$css_files    = array_merge(
	glob( FLAVOR_DIR . '/assets/css/*.css' ) ?: array(),
	glob( FLAVOR_DIR . '/assets/css/skins/*.css' ) ?: array()
);
foreach ( $css_files as $css_file ) {
	$css_rel = basename( $css_file );
	/* rtl.css is the conditional RTL sheet and print-receipt.css belongs to a
	   standalone document that declares dir="rtl" itself — both may speak
	   physical. Everything else uses logical keywords. */
	if ( in_array( $css_rel, array( 'rtl.css', 'print-receipt.css' ), true ) ) {
		continue;
	}
	$css_src = (string) file_get_contents( $css_file );
	if ( preg_match( '/text-align:\s*(left|right)\b/', $css_src ) ) {
		$align_leaks[] = $css_rel;
	}
	if ( preg_match( '/^\s*(left|right)\s*:/m', $css_src ) ) {
		$offset_leaks[] = $css_rel;
	}
	if ( preg_match( '/(margin|padding)-(left|right)\s*:/', $css_src ) ) {
		$gap_leaks[] = $css_rel;
	}
}
check(
	array() === $align_leaks,
	'no stylesheet outside the allowlist aligns with physical text-align: left/right'
		. ( $align_leaks ? ' — leaking: ' . implode( ', ', $align_leaks ) : '' )
);
check(
	array() === $offset_leaks,
	'no stylesheet outside the allowlist positions with bare left:/right:'
		. ( $offset_leaks ? ' — leaking: ' . implode( ', ', $offset_leaks ) : '' )
);
check(
	array() === $gap_leaks,
	'no stylesheet outside the allowlist spaces with physical margin/padding-left/right'
		. ( $gap_leaks ? ' — leaking: ' . implode( ', ', $gap_leaks ) : '' )
);
check(
	false !== strpos( read_theme( 'assets/css/skins/catering.css' ), 'text-align: end;' ),
	'the catering hero signature keeps its opposite edge with a logical keyword'
);

echo "\n--- 8. Direction-bound animations have their LTR twins ---\n";

$motion_src = read_theme( 'assets/css/motion.css' );
$demos_src  = read_theme( 'assets/css/demo-animations.css' );
foreach ( array(
	'flavor-m-hero-art-ltr',
	'flavor-m-mega-item-ltr',
	'flavor-m-shimmer-ltr',
	'flavor-m-wish-row-ltr',
	'flavor-m-line-in-ltr',
	'flavor-m-marquee-ltr',
) as $twin_keyframe ) {
	check(
		false !== strpos( $motion_src, '@keyframes ' . $twin_keyframe . ' {' ),
		'motion.css ships the ' . $twin_keyframe . ' twin keyframes'
	);
}
check(
	false !== strpos( $motion_src, "html[dir=\"ltr\"].flavor-anim-ready .flavor-hero__media," . "\n" . "html[dir=\"ltr\"].flavor-anim-ready .flavor-hero__img-frame {" . "\n" . "" . "\t" . "animation-name: flavor-m-hero-art-ltr;" . "\n" . "}" . "\n" . "" ),
	'the split-hero art entrance flips to the right edge in LTR'
);
check(
	false !== strpos( $motion_src, "html[dir=\"ltr\"] .flavor-header__nav .flavor-mega > .sub-menu > li {" . "\n" . "" . "\t" . "animation-name: flavor-m-mega-item-ltr;" . "\n" . "}" . "\n" . "" ),
	'mega-menu items arrive from the left in LTR'
);
check(
	false !== strpos( $motion_src, "html[dir=\"ltr\"] .flavor-anim-shimmer::before {" . "\n" . "" . "\t" . "transform: translateX(100%);" . "\n" . "" . "\t" . "animation-name: flavor-m-shimmer-ltr;" . "\n" . "}" . "\n" . "" ),
	'the skeleton gloss sweeps the other way in LTR'
);
check(
	false !== strpos( $motion_src, "html[dir=\"ltr\"] .flavor-wish__item {" . "\n" . "" . "\t" . "animation-name: flavor-m-wish-row-ltr;" . "\n" . "}" . "\n" . "" ),
	'wishlist rows enter from the left in LTR'
);
check(
	false !== strpos( $motion_src, "html[dir=\"ltr\"] .flavor-cart-line {" . "\n" . "" . "\t" . "animation-name: flavor-m-line-in-ltr;" . "\n" . "}" . "\n" . "" ),
	'cart lines enter from the left in LTR'
);
check(
	false !== strpos( $motion_src, "html[dir=\"ltr\"] .flavor-anim-marquee > span {" . "\n" . "" . "\t" . "animation-name: flavor-m-marquee-ltr;" . "\n" . "}" . "\n" . "" ),
	'the topbar marquee travels right-to-left in LTR'
);
check(
	false !== strpos( $motion_src, "html[dir=\"ltr\"].flavor-anim-ready .flavor-anim-enter[data-flavor-anim=\"start\"] {" . "\n" . "" . "\t" . "transform: translate3d(calc(var(--flavor-m-slide) * -1), 0, 0);" . "\n" . "}" . "\n" . "" ),
	'the start entrance flips to the left edge in LTR'
);
check(
	false !== strpos( $motion_src, "html[dir=\"ltr\"].flavor-anim-ready .flavor-anim-enter[data-flavor-anim=\"end\"] {" . "\n" . "" . "\t" . "transform: translate3d(var(--flavor-m-slide), 0, 0);" . "\n" . "}" . "\n" . "" ),
	'the end entrance flips to the right edge in LTR'
);
check(
	false !== strpos( $demos_src, "html[dir=\"ltr\"] .fd-hero__copy.fd-anim-enter {" . "\n" . "" . "\t" . "transform: translateX(-30px) translateY(0);" . "\n" . "}" . "\n" . "" ),
	'the demo hero copy enters from the left in LTR'
);
check(
	false !== strpos( $demos_src, "html[dir=\"ltr\"] .fd-hero__art.fd-anim-enter {" . "\n" . "" . "\t" . "transform: translateX(30px) translateY(0);" . "\n" . "}" . "\n" . "" ),
	'the demo hero art enters from the right in LTR'
);
check(
	false !== strpos( $demos_src, "html[dir=\"ltr\"] .fd-faq details.fd-anim-enter {" . "\n" . "" . "\t" . "transform: translateX(-20px);" . "\n" . "}" . "\n" . "" ),
	'the demo FAQ items enter from the left in LTR'
);
check(
	false !== strpos( $demos_src, "html[dir=\"ltr\"] .fd-visit__card::after {" . "\n" . "" . "\t" . "animation-name: fd-shimmer-ltr;" . "\n" . "}" . "\n" . "" ),
	'the visit-card shimmer sweeps the other way in LTR'
);
check(
	false !== strpos( $demos_src, "@keyframes fd-shimmer-ltr {" . "\n" . "" . "\t" . "0%   { transform: translateX(100%); }" . "\n" . "" . "\t" . "100% { transform: translateX(-100%); }" . "\n" . "}" . "\n" . "" ),
	'demo-animations.css ships the fd-shimmer-ltr twin keyframes'
);

echo "\n--- 9. Keyboard walking follows the document direction ---\n";

$menu_src    = read_theme( 'assets/js/menu.js' );
$premium_src = read_theme( 'assets/js/premium.js' );
check(
	false !== strpos( $menu_src, "var rtl = (document.documentElement.getAttribute('dir')" ),
	'menu.js reads the direction off the document element'
);
check(
	false !== strpos( $menu_src, 'next = rtl ? index + 1 : index - 1' ),
	'the category tabs walk forward on ArrowLeft only in RTL'
);
check(
	false !== strpos( $premium_src, "var rtl = (document.documentElement.getAttribute('dir')" ),
	'premium.js reads the direction off the document element too'
);
check(
	false !== strpos( $premium_src, "var forward = rtl ? (key === 'ArrowLeft') : (key === 'ArrowRight')" ),
	'the top-level nav walk flips with the document direction instead of assuming RTL'
);

echo "\n--- 10. The kitchen screen states its direction exactly once ---\n";

$kitchen_src = (string) @file_get_contents( dirname( FLAVOR_DIR ) . '/flavor-core/admin/views/kitchen-dashboard.php' );
check(
	false !== strpos( $kitchen_src, '<html lang="fa" dir="rtl">' ),
	'the kitchen document declares lang and dir exactly like the receipt does'
);
check(
	false === strpos( $kitchen_src, 'language_attributes(); ?> dir=' ),
	'the kitchen screen no longer prints a duplicate dir attribute in RTL locales'
);

echo "\n--- 11. The twin sweep is wired into the fixtures, the guard and CI ---\n";

$repo_root    = dirname( FLAVOR_DIR );
$fixture_src  = (string) @file_get_contents( $repo_root . '/tests/ui/elementor-pages.php' );
$a11y_src     = (string) @file_get_contents( $repo_root . '/tests/ui/a11y-pages.php' );
$visual_src   = (string) @file_get_contents( $repo_root . '/tests/ui/elementor-visual.mjs' );
$workflow_src = (string) @file_get_contents( $repo_root . '/.github/workflows/ci.yml' );
check(
	false !== strpos( $fixture_src, 'flavor-states-direction-light.html' ),
	'the elementor fixture builds the direction probe page'
);
check(
	false !== strpos( $fixture_src, '$rtl_sheet' ) && false !== strpos( $fixture_src, 'ltr' ),
	'the elementor fixture writes both directions and loads rtl.css only for RTL'
);
check(
	false !== strpos( $a11y_src, '_a11y_dir' ) && false !== strpos( $a11y_src, '/ltr' ),
	'the a11y fixture renders every surface as an RTL page and an LTR twin'
);
check(
	false !== strpos( $visual_src, 'twinDirs' ),
	'the visual guard sweeps both directions'
);
check(
	false !== strpos( $visual_src, 'flavor-m-marquee' ) && false !== strpos( $visual_src, 'drawerParked' ),
	'the guard measures the mirrored animations and the parked-drawer geometry live'
);
check(
	false !== strpos( $visual_src, 'a11y-pages.php' ),
	'the guard builds the a11y fixtures so the twin sweep covers the real pages'
);
check(
	false !== strpos( $workflow_src, 'elementor-visual.mjs' ),
	'CI still runs the visual guard — twin sweep included'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
