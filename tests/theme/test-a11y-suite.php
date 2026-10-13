<?php
/**
 * Accessibility suite contract tests (P51).
 *
 * The browser scan (tests/ui/a11y.mjs) is the authority on whether pages
 * pass axe — but it needs Chromium, so it runs only in the Playwright CI
 * job. This gate protects the same work on every PHP run: it pins the
 * runner's contract (tags, motion freeze, blocking verdict), the fixture
 * builder's contract, the CI wiring, and — most importantly — it
 * recomputes the WCAG ratios of every colour pair the scan forced us to
 * fix, straight from the shipped CSS, with the theme's own Contrast
 * class. Reverting a token or a band flip turns this file red even on a
 * machine with no browser at all.
 *
 *   php tests/theme/test-a11y-suite.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-contrast.php';

use Flavor\Contrast;

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

$root = dirname( FLAVOR_DIR );

/**
 * Read a repo file or fail loudly.
 *
 * @param string $rel Path relative to the repo root.
 * @return string
 */
function a11y_file( string $rel ): string {
	global $root;
	$path = $root . '/' . $rel;
	if ( ! is_readable( $path ) ) {
		check( false, "missing file {$rel}" );
		return '';
	}
	return (string) file_get_contents( $path );
}

/**
 * Extract the first regex capture from a file, or fail.
 *
 * @param string $content Haystack.
 * @param string $pattern Regex with one capture group.
 * @param string $label   Failure message.
 * @return string
 */
function a11y_extract( string $content, string $pattern, string $label ): string {
	if ( 1 !== preg_match( $pattern, $content, $m ) ) {
		check( false, "cannot extract {$label}" );
		return '';
	}
	return $m[1];
}

/**
 * Composite a foreground colour over a background at an alpha, as CSS does.
 *
 * @param string $fg    Foreground hex.
 * @param string $bg    Background hex.
 * @param float  $alpha Foreground alpha.
 * @return string Hex.
 */
function a11y_blend( string $fg, string $bg, float $alpha ): string {
	$f = array_map( 'hexdec', str_split( ltrim( $fg, '#' ), 2 ) );
	$b = array_map( 'hexdec', str_split( ltrim( $bg, '#' ), 2 ) );
	$out = '';
	foreach ( array( 0, 1, 2 ) as $i ) {
		$out .= str_pad( dechex( (int) round( $alpha * $f[ $i ] + ( 1 - $alpha ) * $b[ $i ] ) ), 2, '0', STR_PAD_LEFT );
	}
	return '#' . $out;
}

/**
 * Assert AA (4.5:1) for a pair.
 *
 * @param string $fg   Foreground.
 * @param string $bg   Background.
 * @param string $what Description.
 */
function a11y_aa( string $fg, string $bg, string $what ): void {
	if ( '' === $fg || '' === $bg ) {
		check( false, "{$what}: missing colour" );
		return;
	}
	$ratio = Contrast::ratio( $fg, $bg );
	check( $ratio >= 4.5, sprintf( '%s: %s on %s = %.2f (need 4.5)', $what, $fg, $bg, $ratio ) );
}

echo "=== Flavor Accessibility Suite Contract Tests ===\n\n";

echo "--- 1. The browser runner keeps its contract ---\n";
$runner = a11y_file( 'tests/ui/a11y.mjs' );
check( str_contains( $runner, "'wcag2a', 'wcag2aa', 'wcag21aa'" ), 'runner audits the WCAG 2.x AA tag set' );
check( str_contains( $runner, "classList.remove('flavor-anim-ready')" ), 'runner freezes motion in its final state before auditing (opacity blends fake contrast failures)' );
check( str_contains( $runner, "const pageSlugs = ['home', 'menu', 'reservation', 'contact', 'about', 'tracking']" ), 'runner scans all six core pages' );
check( str_contains( $runner, 'width: 390' ), 'runner rescans every page at phone width' );
check( str_contains( $runner, '#pv-skins button[data-skin' ), 'runner walks all twelve demo skins' );
check( str_contains( $runner, 'Fatal error' ) && str_contains( $runner, 'Undefined (index|variable|array key)' ), 'runner fails on PHP noise inside rendered fixtures' );
check( str_contains( $runner, 'const blocking = tally.critical + tally.serious;' ) && str_contains( $runner, 'process.exit(blocking || pageErrors.length ? 1 : 0);' ), 'runner exits non-zero on any critical/serious violation or page error' );

echo "\n--- 2. The offline fixture builder keeps its contract ---\n";
$builder = a11y_file( 'tests/ui/a11y-pages.php' );
check( str_contains( $builder, 'flavor_a11y_head_assets' ) && str_contains( $builder, '<title>' ), 'builder emits a document title like production wp_head does' );
check( str_contains( $builder, 'class-branch-map' ), 'builder loads the branch map the contact template needs' );
check( str_contains( $builder, 'shortcode_exists' ) && str_contains( $builder, 'is_home' ), 'builder stubs the WordPress conditionals offline templates call' );

echo "\n--- 3. Status tokens clear AA on their own chips and on white ---\n";
$main = a11y_file( 'flavor/assets/css/main.css' );
foreach ( array( 'success', 'warning', 'danger' ) as $name ) {
	$fg = a11y_extract( $main, '/--flavor-' . $name . ':\s*(#[0-9a-fA-F]{6})/', "--flavor-{$name}" );
	$bg = a11y_extract( $main, '/--flavor-' . $name . '-bg:\s*(#[0-9a-fA-F]{6})/', "--flavor-{$name}-bg" );
	a11y_aa( $fg, $bg, "--flavor-{$name} on its chip" );
	a11y_aa( $fg, '#ffffff', "--flavor-{$name} on white" );
}

echo "\n--- 4. The shared offer banner scrim is in place ---\n";
$marketing = a11y_file( 'flavor/assets/css/marketing.css' );
check( str_contains( $marketing, 'rgba(17, 12, 8, 0.22)' ), 'offer banner darkens the primary→accent gradient by 22% so white copy keeps AA across skins' );

echo "\n--- 5. Testimonial stars stay a labelled image ---\n";
$testimonials = a11y_file( 'flavor/template-parts/marketing/testimonials.php' );
check( str_contains( $testimonials, 'flavor-review-card__stars" role="img" aria-label=' ), 'star row carries role="img" (bare aria-label on a div is prohibited)' );
check( str_contains( $testimonials, '%d ستاره از ۵' ), 'star label is generated from the item rating, not hardcoded' );

echo "\n--- 6. Dark-band skins flip their text tokens (axe P51 fixes) ---\n";
// Band backgrounds are the design constants each skin paints; the flips
// below are extracted live from the shipped CSS so reverting one fails.
$skins = array(
	'modern-restaurant'   => array( 'bands' => array( '#26352b', '#201c19', '#171b18' ), 'footer' => '#171b18' ),
	'luxury-dining'       => array( 'bands' => array( '#070707', '#0b0b0b', '#040404' ), 'footer' => '#040404' ),
	'persian-traditional' => array( 'bands' => array( '#073f45', '#3b111c', '#24120f' ), 'footer' => '#24120f' ),
	'pizza-italian'       => array( 'bands' => array( '#17382a', '#183a2b', '#1b241d' ), 'footer' => '#1b241d' ),
	'cafe-bistro'         => array( 'bands' => array( '#35402f', '#241b15' ), 'footer' => '#241b15' ),
	'bakery-pastry'       => array( 'bands' => array( '#465442', '#31252a' ), 'footer' => '#31252a' ),
	'fast-food'           => array( 'bands' => array( '#171512', '#0e0d0b' ), 'footer' => '#0e0d0b' ),
);
foreach ( $skins as $skin => $meta ) {
	$css = a11y_file( 'flavor/assets/css/skins/' . $skin . '.css' );
	$ink = a11y_extract( $css, '/\.flavor-skin-' . $skin . ' \.flavor-intro,\s*\.flavor-skin-' . $skin . ' \.flavor-res-banner,\s*\.flavor-skin-' . $skin . ' \.flavor-footer \{\s*--flavor-ink:\s*(#[0-9a-fA-F]{6});/', "{$skin} band ink flip" );
	$muted = a11y_extract( $css, '/\.flavor-skin-' . $skin . ' \.flavor-intro,\s*\.flavor-skin-' . $skin . ' \.flavor-res-banner,\s*\.flavor-skin-' . $skin . ' \.flavor-footer \{\s*--flavor-ink:\s*#[0-9a-fA-F]{6};\s*--flavor-muted:\s*(#[0-9a-fA-F]{6});/', "{$skin} band muted flip" );
	foreach ( $meta['bands'] as $bg ) {
		a11y_aa( $ink, $bg, "{$skin} flipped ink on {$bg}" );
		a11y_aa( $muted, $bg, "{$skin} flipped muted on {$bg}" );
	}
	$tag = a11y_extract( $css, '/\.flavor-skin-' . $skin . ' \.flavor-footer__contact-item a \{\s*color:\s*(#[0-9a-fA-F]{6});/', "{$skin} footer tel link" );
	a11y_aa( $tag, $meta['footer'], "{$skin} footer tel link" );
}

echo "\n--- 7. Fast-food red carries a scrim, not raw #cd2a14, under white copy ---\n";
$fast = a11y_file( 'flavor/assets/css/skins/fast-food.css' );
check( str_contains( $fast, 'linear-gradient(rgba(23, 21, 18, 0.22), rgba(23, 21, 18, 0.22)), var(--fast-red)' ), 'intro band is scrimmed' );
a11y_aa( '#ffffff', a11y_blend( '#171512', '#cd2a14', 0.22 ), 'white card copy on the scrimmed fast red' );
$ff_black = a11y_extract( $fast, '/--fast-black:\s*(#[0-9a-fA-F]{6});/', '--fast-black' );
check( str_contains( $fast, '.flavor-skin-fast-food .flavor-offer-banner__badge {' . "\n\t" . 'background: var(--fast-black);' ), 'offer badge sits on fast black now' );
a11y_aa( '#ffffff', $ff_black, 'white on the fast-food badge chip' );

echo "\n--- 8. The noir and mizan bespoke demos flip their bands too ---\n";
$noir = a11y_file( 'flavor/assets/css/skins/dark-luxe.css' );
$noir_ink = a11y_extract( $noir, '/\.flavor-skin-dark-luxe \.fd-footer \{\s*--flavor-ink:\s*(#[0-9a-fA-F]{6});/', 'dark-luxe chrome ink flip' );
$noir_muted = a11y_extract( $noir, '/\.flavor-skin-dark-luxe \.fd-footer \{\s*--flavor-ink:\s*#[0-9a-fA-F]{6};\s*--flavor-muted:\s*(#[0-9a-fA-F]{6});/', 'dark-luxe chrome muted flip' );
$noir_primary = a11y_extract( $noir, '/\.flavor-skin-dark-luxe \.fd-footer \{\s*--flavor-ink:\s*#[0-9a-fA-F]{6};\s*--flavor-muted:\s*#[0-9a-fA-F]{6};\s*--flavor-primary:\s*(#[0-9a-fA-F]{6});/', 'dark-luxe chrome accent flip' );
foreach ( array( '#0e1116', '#13171d', '#171c23' ) as $bg ) {
	a11y_aa( $noir_ink, $bg, "dark-luxe flipped ink on {$bg}" );
	a11y_aa( $noir_muted, $bg, "dark-luxe flipped muted on {$bg}" );
	a11y_aa( $noir_primary, $bg, "dark-luxe flipped copper on {$bg}" );
}
$mizan = a11y_file( 'flavor/assets/css/skins/catering.css' );
$gold = a11y_extract( $mizan, '/--fd-mizan-gold:\s*(#[0-9a-fA-F]{6});/', 'mizan gold' );
a11y_aa( $gold, '#5c6b3a', 'mizan gold on the olive masthead' );
$hero_text = a11y_extract( $mizan, '/\.fd-hero__text \{\s*color:\s*(#[0-9a-fA-F]{6});/', 'mizan hero copy' );
a11y_aa( $hero_text, '#5c6b3a', 'mizan hero copy on olive' );
$foot_bottom = a11y_extract( $mizan, '/\.fd-footer__bottom \{[^}]*color:\s*(#[0-9a-fA-F]{6});/', 'mizan footer bottom bar' );
a11y_aa( $foot_bottom, '#5c6b3a', 'mizan footer bottom bar on olive' );

echo "\n--- 9. Review-card meta lines were lifted out of the mud ---\n";
a11y_aa( a11y_blend( '#fff8ed', '#525f4d', 0.8 ), '#525f4d', 'bakery review role at 0.8 alpha' );
a11y_aa( a11y_blend( '#fffaf2', '#434c3c', 0.8 ), '#434c3c', 'cafe review role at 0.8 alpha' );
$bakery = a11y_file( 'flavor/assets/css/skins/bakery-pastry.css' );
$bakery_tag = a11y_extract( $bakery, '/flavor-testimonials \.flavor-section-header__tag \{[^}]*color:\s*(#[0-9a-fA-F]{6});/', 'bakery testimonial tag' );
a11y_aa( $bakery_tag, '#5c6354', 'bakery testimonial tag on its chip' );

echo "\n--- 10. CI actually runs the scan ---\n";
$ci = a11y_file( '.github/workflows/ci.yml' );
check( str_contains( $ci, 'node tests/ui/a11y.mjs' ), 'the Playwright CI job runs the axe scan' );
check( str_contains( $ci, 'a11y-evidence' ), 'CI uploads the report and screenshots when the scan fails' );
$pkg = a11y_file( 'tests/ui/package.json' );
check( str_contains( $pkg, '"test:a11y": "node a11y.mjs"' ), 'npm run test:a11y is wired' );

echo "\n=======================================================\n";
echo "a11y suite: {$passed} passed, {$failed} failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
