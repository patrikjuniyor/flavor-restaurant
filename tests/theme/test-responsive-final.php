<?php
/**
 * Final responsive-contract tests (prompt 61).
 *
 * The 320→1440 sweep (tests/ui/responsive.mjs plus the prompt-61 page sweep
 * over home/menu/reservation/contact/about/tracking, the built price-list
 * page and the kitchen kiosk) must stay break-free: no horizontal overflow,
 * no clipped text, no stacked-button overlap at 320/360/390/430/768/1024/1440.
 * Five layout bugs survived the earlier suites because each hid behind a
 * detector blind spot; this gate pins the CSS contract that closes them:
 *
 * 1. Kitchen kiosk (flavor-core kitchen-dashboard.css): the filter bar wraps
 *    instead of running off a 320px kiosk; the <=900px lane column is
 *    minmax(0, 1fr) — a bare 1fr is minmax(auto, 1fr) and let one long
 *    zero-width-non-joiner dish name widen the lane by 109px; ticket cards
 *    break long names inside the card instead of widening it.
 * 2. Price-list widget (elementor-widgets.css): the list grid declares
 *    minmax(0, 1fr) — with no template at all the implicit auto track sized
 *    to max-content (499px) and pushed every price off-canvas to the left,
 *    invisible under main.flavor-main{overflow-x:hidden}; the name flex item
 *    pairs min-width:0 with overflow-wrap so it can actually shrink.
 * 3. Header actions (ui-navigation.css): the <=420px CTA hide used to key off
 *    body.flavor-ui, which UI::body_class() only adds on inner pages — the
 *    front page never carries it, so the 307px action row overflowed the
 *    256px container at 320px. The rule now also keys off the mobile bottom
 *    nav (the mobile ordering entry) via body:has(.flavor-mobile-nav).
 * 4. Reservation banner (marketing.css): the global .flavor-btn
 *    white-space:nowrap makes the banner CTA an unbreakable 255px box; no
 *    banner padding leaves that much content width at 320px, so <=480px
 *    narrows the banner padding and lets that one CTA wrap full-width.
 *
 * The global button nowrap is deliberate design and must stay; only the
 * scoped banner exception is allowed to relax it. Brace balance is checked
 * on every touched sheet so a half-reverted edit cannot slip through as a
 * "passing" file.
 *
 *   php tests/theme/test-responsive-final.php
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
		echo '  [PASS] ' . $message . PHP_EOL;
	} else {
		++$failed;
		echo '  [FAIL] ' . $message . PHP_EOL;
	}
}

/**
 * Read a repo-relative file.
 *
 * @param string $rel Path below repo root.
 * @return string Contents.
 */
function css( string $rel ): string {
	$path = dirname( __DIR__, 2 ) . '/' . $rel;
	$body = file_get_contents( $path );
	if ( false === $body ) {
		echo '  [FAIL] missing stylesheet ' . $rel . PHP_EOL;
		exit( 1 );
	}
	return $body;
}

/**
 * Extract the body of the first rule whose selector matches.
 *
 * @param string $sheet    CSS source.
 * @param string $selector Selector substring (regex-quoted by caller).
 * @return string Rule body or empty string.
 */
function rule_body( string $sheet, string $selector ): string {
	if ( ! preg_match( '/' . $selector . '([^{}]*)\}/', $sheet, $m ) ) {
		return '';
	}
	return $m[1];
}

/**
 * Extract the body of a media block by its condition.
 *
 * @param string $sheet     CSS source.
 * @param string $condition e.g. '(max-width: 900px)'.
 * @return string Media block body or empty string.
 */
function media_body( string $sheet, string $condition ): string {
	$pos = strpos( $sheet, '@media ' . $condition );
	if ( false === $pos ) {
		$pos = strpos( $sheet, '@media' . $condition );
	}
	if ( false === $pos ) {
		return '';
	}
	$open  = strpos( $sheet, '{', $pos );
	$depth = 0;
	$len   = strlen( $sheet );
	for ( $i = $open; $i < $len; $i++ ) {
		if ( '{' === $sheet[ $i ] ) {
			++$depth;
		} elseif ( '}' === $sheet[ $i ] ) {
			--$depth;
			if ( 0 === $depth ) {
				return substr( $sheet, $open + 1, $i - $open - 1 );
			}
		}
	}
	return '';
}

echo "== prompt 61: final responsive contract ==\n";

$kitchen   = css( 'flavor-core/assets/public/css/kitchen-dashboard.css' );
$widgets   = css( 'flavor/assets/css/elementor-widgets.css' );
$nav       = css( 'flavor/assets/css/ui-navigation.css' );
$marketing = css( 'flavor/assets/css/marketing.css' );
$main      = css( 'flavor/assets/css/main.css' );
$ui_php    = css( 'flavor/inc/class-ui.php' );

// --- Sheet integrity -------------------------------------------------------
foreach ( array( 'kitchen-dashboard.css' => $kitchen, 'elementor-widgets.css' => $widgets, 'ui-navigation.css' => $nav, 'marketing.css' => $marketing ) as $name => $sheet ) {
	check( substr_count( $sheet, '{' ) === substr_count( $sheet, '}' ), $name . ': braces balanced' );
}

// --- 1. Kitchen kiosk ------------------------------------------------------
check( str_contains( rule_body( $kitchen, '\.flavor-kitchen__filters\s*\{' ), 'flex-wrap: wrap' ), 'kitchen filter bar wraps (flex-wrap: wrap)' );
$k900 = media_body( $kitchen, '(max-width: 900px)' );
check( '' !== $k900 && str_contains( $k900, 'grid-template-columns: minmax(0, 1fr);' ), 'kitchen <=900px lane column is minmax(0, 1fr)' );
check( '' !== $k900 && ! preg_match( '/grid-template-columns:\s*1fr\s*;/', $k900 ), 'kitchen <=900px has no bare 1fr track' );
check( str_contains( rule_body( $kitchen, '\.flavor-kcard\s*\{' ), 'overflow-wrap: break-word' ), 'kitchen ticket cards break long dish names' );

// --- 2. Price-list widget --------------------------------------------------
check( str_contains( rule_body( $widgets, '\.flavor-price-list\s*\{' ), 'grid-template-columns: minmax(0, 1fr);' ), 'price list grid track is minmax(0, 1fr)' );
$name_body = rule_body( $widgets, '\.flavor-price-list__name\s*\{' );
check( str_contains( $name_body, 'min-width: 0' ), 'price list name can shrink (min-width: 0)' );
check( str_contains( $name_body, 'overflow-wrap: break-word' ), 'price list name breaks long unbroken strings' );

// --- 3. Header actions at <=420px ------------------------------------------
$m420 = media_body( $nav, '(max-width:420px)' );
check( str_contains( $m420, 'body.flavor-ui .flavor-header .flavor-header__actions>.flavor-btn,' ), 'inner pages still hide the header CTA at <=420px (selector list)' );
check( str_contains( $m420, 'body:has(.flavor-mobile-nav) .flavor-header .flavor-header__actions>.flavor-btn{display:none}' ), 'front page hides the header CTA at <=420px via the mobile nav' );
check( str_contains( $m420, 'body:has(.flavor-mobile-nav) .flavor-header .flavor-header__inner{gap:14px}' ), 'front page header gap tightens at <=420px too' );
check( (bool) preg_match( '/if\s*\(\s*self::inner\(\)\s*\)\s*\{\s*\$classes\[\]\s*=\s*\'flavor-ui\'/', $ui_php ), 'flavor-ui stays inner-only (why the :has fallback exists)' );

// --- 4. Reservation banner at <=480px --------------------------------------
$m480 = media_body( $marketing, '(max-width: 480px)' );
check( str_contains( $m480, 'padding: 28px 20px' ), 'banner padding narrows at <=480px' );
$cta_body = '';
if ( preg_match( '/\.flavor-res-banner__action \.flavor-btn\s*\{([^{}]*)\}/', $m480, $m ) ) {
	$cta_body = $m[1];
}
check( str_contains( $cta_body, 'white-space: normal' ), 'banner CTA may wrap at <=480px' );
check( str_contains( $cta_body, 'width: 100%' ), 'banner CTA fills the narrowed banner' );
check( str_contains( rule_body( $main, '\.flavor-btn\s*\{' ), 'white-space: nowrap' ), 'global button nowrap (design intent) untouched' );

// --- Provenance ------------------------------------------------------------
check( str_contains( $kitchen, 'prompt 61' ) && str_contains( $widgets, 'prompt 61' ) && str_contains( $nav, 'prompt 61' ) && str_contains( $marketing, 'prompt 61' ), 'every patched sheet carries its prompt-61 provenance comment' );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
