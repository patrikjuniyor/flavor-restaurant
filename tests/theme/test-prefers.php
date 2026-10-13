<?php
/**
 * Motion and contrast preferences (prompt 56).
 *
 * Two operating-system preferences have to survive every future edit:
 * prefers-reduced-motion — the motion layer must go completely silent, on
 * the CSS side (a global kill switch plus a per-file block wherever an
 * animation lives) and on the JS side (every requestAnimationFrame driver
 * gated behind the media query, including live changes while the page is
 * open); and prefers-contrast: more — one block in the main stylesheet has
 * to sharpen borders and helper text for every skin, derived from the
 * skin's own tokens. Section 4 recomputes the derived line colour for all
 * twelve skins in both schemes with the theme's own WCAG formula.
 *
 * The browser-side guard (tests/ui/motion.mjs) stays wired in CI; this
 * gate protects the same contract on every PHP run, with no browser.
 *
 *   php tests/theme/test-prefers.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-contrast.php';
require_once FLAVOR_DIR . '/inc/class-dark-mode.php';

use Flavor\Dark_Mode;
use Flavor\Design;

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $ok      Condition.
 * @param string $message Label.
 * @return void
 */
function check( bool $ok, string $message ): void {
	global $passed, $failed;
	if ( $ok ) {
		++$passed;
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

/**
 * Read a repo file.
 *
 * @param string $rel Path under the repository root.
 * @return string
 */
function read( string $rel ): string {
	return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
}

$root = dirname( __DIR__, 2 );

echo "\n--- 1. prefers-reduced-motion silences the whole CSS layer ---\n";

$main = read( 'flavor/assets/css/main.css' );

$kill = '@media (prefers-reduced-motion: reduce) {
	*, *::before, *::after {
		animation-duration: 0.01ms !important;
		animation-iteration-count: 1 !important;
		transition-duration: 0.01ms !important;
		scroll-behavior: auto !important;
	}
}';
check( str_contains( $main, $kill ), 'main.css carries the universal kill switch (*, ::before, ::after — duration, iteration, transition, scroll-behavior)' );

/* Every stylesheet that moves anything also has to carry its own
   reduced-motion block, so a future file cannot ship an animation that
   only the kill switch fights. */
$leaks = array();
foreach ( glob( $root . '/flavor/assets/css/*.css' ) ?: array() as $file ) {
	$source = (string) file_get_contents( $file );
	$moves  = preg_match( '/animation:|animation-name|transition:/', $source );
	$blocks = preg_match( '/prefers-reduced-motion/', $source );
	if ( $moves && ! $blocks ) {
		$leaks[] = basename( $file );
	}
}
check( array() === $leaks, 'every stylesheet with animations/transitions carries its own prefers-reduced-motion block' . ( $leaks ? ' — leaking: ' . implode( ', ', $leaks ) : '' ) );

$motion_css = read( 'flavor/assets/css/motion.css' );
check( str_contains( $motion_css, '@media (prefers-reduced-motion: reduce)' ) && str_contains( $motion_css, 'animation: none' ) && str_contains( $motion_css, 'transition: none' ), 'motion.css neutralises its own effects explicitly (animation/transition: none), not only through the kill switch' );

echo "\n--- 2. prefers-reduced-motion silences the JS drivers ---\n";

$demos = read( 'flavor/assets/js/demo-animations.js' );
check( str_contains( $demos, "window.matchMedia('(prefers-reduced-motion: reduce)')" ), 'demo-animations.js reads the reduced-motion media query' );
check( str_contains( $demos, 'if (reduced()) return;' ) && str_contains( $demos, 'if (reduced()) { teardown(); return; }' ), 'demo-animations.js gates its entrance AND tears running effects down when the preference is on/changes' );
check( str_contains( $demos, "reduceQuery.addEventListener('change', onReduceChange)" ) && str_contains( $demos, 'reduceQuery.addListener(onReduceChange)' ), 'demo-animations.js reacts to live preference changes (modern + legacy listener)' );
check( str_contains( $demos, "reduced: reduced" ) && str_contains( $demos, 'ready: function () { return false; }' ), 'the reduced-motion export advertises itself as inert' );

$motion_js = read( 'flavor/assets/js/motion.js' );
check( str_contains( $motion_js, "window.matchMedia('(prefers-reduced-motion: reduce)')" ) && str_contains( $motion_js, 'function reduced()' ), 'motion.js reads the reduced-motion media query through one helper' );
check( substr_count( $motion_js, 'reduced()' ) >= 10, 'motion.js consults reduced() at every effect entry point (≥10 call sites)' );

$premium_js = read( 'flavor/assets/js/premium.js' );
check( 2 === preg_match_all( "/behavior: reduced\(\) \? 'auto' : 'smooth'/", $premium_js ), 'premium.js programmatic scrolls honour the preference (both scrollTo and scrollIntoView)' );
check( str_contains( $premium_js, 'if (!reduced()) {' ), 'the scheme-switch wash only plays when motion is allowed' );

$rAF_files = array();
foreach ( glob( $root . '/flavor/assets/js/*.js' ) ?: array() as $file ) {
	if ( str_contains( (string) file_get_contents( $file ), 'requestAnimationFrame' ) ) {
		$rAF_files[] = basename( $file );
	}
}
sort( $rAF_files );
check( array( 'demo-animations.js', 'motion.js' ) === $rAF_files, 'only the two guarded drivers touch requestAnimationFrame' . ( array( 'demo-animations.js', 'motion.js' ) !== $rAF_files ? ' — found: ' . implode( ', ', $rAF_files ) : '' ) );

echo "\n--- 3. prefers-contrast: more sharpens edges in the main stylesheet ---\n";

$contrast_files = array();
foreach ( glob( $root . '/flavor/assets/css/*.css' ) ?: array() as $file ) {
	if ( str_contains( (string) file_get_contents( $file ), 'prefers-contrast' ) ) {
		$contrast_files[] = basename( $file );
	}
}
check( array( 'main.css' ) === $contrast_files, 'exactly one stylesheet owns the prefers-contrast block (main.css)' . ( array( 'main.css' ) !== $contrast_files ? ' — found: ' . implode( ', ', $contrast_files ) : '' ) );

check( str_contains( $main, '@media (prefers-contrast: more) {' ), 'main.css has the @media (prefers-contrast: more) block' );
check( str_contains( $main, '--flavor-line-strong: color-mix(in srgb, var(--flavor-ink) 55%, var(--flavor-bg));' ), 'the strong line colour is derived from the skin’s own ink/bg tokens with color-mix' );

$block_start = strpos( $main, '@media (prefers-contrast: more) {' );
$block       = false !== $block_start ? substr( $main, $block_start ) : '';
$block       = substr( $block, 0, (int) strpos( $block, "\n}\n" ) + 3 );

foreach ( array( '.flavor-card', '.flavor-ui-panel', '.flavor-cart__dialog', '.flavor-sheet__panel', '.flavor-header', '.flavor-ui-tag', '.flavor-btn--outline' ) as $surface ) {
	check( str_contains( $block, 'body.flavor-theme ' . $surface ), 'sharpened border reaches ' . $surface );
}
check( str_contains( $block, 'border-color: var(--flavor-line-strong) !important;' ), 'sharpened borders use the derived line colour with !important (skins load after main.css)' );
check( str_contains( $block, 'border-width: 2px !important;' ), 'form controls gain a 2px edge' );
check( str_contains( $block, 'body.flavor-theme input,' ) && str_contains( $block, 'body.flavor-theme select,' ) && str_contains( $block, 'body.flavor-theme textarea' ), 'inputs, selects and textareas are all covered' );
foreach ( array( '.flavor-ui-note', '.flavor-ui-hint', '.flavor-ui-overline', '.flavor-food-card__desc' ) as $helper ) {
	check( str_contains( $block, $helper ), 'helper text ' . $helper . ' is promoted' );
}
check( str_contains( $block, 'color: var(--flavor-ink) !important;' ), 'promoted helper text reads at full ink' );
check( str_contains( $block, 'outline-width: 3px !important;' ) && str_contains( $block, 'outline-offset: 2px !important;' ), 'the focus ring thickens for :focus-visible' );

echo "\n--- 4. The derived line clears 3:1 on every skin, in both schemes ---\n";

foreach ( array_keys( Design::skins() ) as $slug ) {
	$schemes = array(
		'light' => Design::tokens( (string) $slug ),
		'dark'  => Dark_Mode::palette( (string) $slug ),
	);
	foreach ( $schemes as $scheme => $p ) {
		/* color-mix(in srgb, ink 55%, bg) === Dark_Mode::mix( bg, ink, 0.55 ). */
		$line  = Dark_Mode::mix( (string) $p['bg'], (string) $p['ink'], 0.55 );
		$ratio = Dark_Mode::contrast( $line, (string) $p['bg'] );
		check( $ratio >= 3.0, sprintf( '%s (%s): derived line %s vs bg %s = %.2f ≥ 3.0', $slug, $scheme, $line, $p['bg'], $ratio ) );
	}
}

echo "\n--- 5. The browser guard stays wired ---\n";

$ci = read( '.github/workflows/ci.yml' );
check( str_contains( $ci, 'node tests/ui/motion.mjs' ), 'CI still runs the motion browser guard (motion.mjs)' );
check( str_contains( $ci, 'test-prefers.php' ) || str_contains( $ci, 'tests/theme/test-*.php' ) || str_contains( $ci, 'run-theme-tests' ) || str_contains( $ci, 'whole theme suite' ), 'this gate rides the standing theme-suite step in CI' );

echo "\n=======================================================\n";
echo 'Results: ' . $passed . ' Passed, ' . $failed . " Failed\n";
echo "=======================================================\n";
exit( $failed > 0 ? 1 : 0 );
