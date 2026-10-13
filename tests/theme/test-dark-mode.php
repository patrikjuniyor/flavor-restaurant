<?php
/**
 * Night scheme contract tests (prompt 58).
 *
 * The dark scheme is a chain: Dark_Mode::palette() derives the night tokens,
 * css() ships them for both the explicit choice and the device preference,
 * head() prints a pre-paint boot script, premium.js persists the visitor's
 * click, and the stylesheets keep every component readable on the night
 * canvases. This gate pins the whole chain WITHOUT a browser: the storage
 * contract between the boot script and premium.js (the JSON-encoded value
 * used to be unreadable for the boot comparison — the stored night choice
 * silently never restored), the status-hue foregrounds the palette lifts,
 * the per-skin night twins for raw art-direction literals, the always-dark
 * kitchen board (measured statically, since it opts out of the scheme by
 * design) and the fixture/guard wiring that keeps measuring all of it.
 *
 *   php tests/theme/test-dark-mode.php
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
 * Valid 6-digit CSS hex, without pulling a regex escape into this file.
 *
 * @param string $value Candidate.
 * @return bool
 */
function hex_ok( string $value ): bool {
	$value = ltrim( trim( $value ), '#' );
	return 6 === strlen( $value ) && ctype_xdigit( $value );
}

/**
 * Read a custom-property value out of a stylesheet.
 *
 * @param string $css   Stylesheet source.
 * @param string $token Property name (with dashes).
 * @return string
 */
function css_value( string $css, string $token ): string {
	$at = strpos( $css, $token . ':' );
	if ( false === $at ) {
		return '';
	}
	$rest = substr( $css, $at + strlen( $token ) + 1 );
	$end  = strpos( $rest, ';' );
	if ( false === $end ) {
		return '';
	}
	return trim( substr( $rest, 0, $end ) );
}

$root      = dirname( __DIR__, 2 );
$css_dir   = FLAVOR_DIR . '/assets/css';
$skins_dir = $css_dir . '/skins';
$ui_dir    = dirname( __DIR__ ) . '/ui';

echo '=== Flavor Night Scheme Contract Tests (prompt 58) ===' . PHP_EOL . PHP_EOL;

/* ------------------------------------------------------------------ */
echo '--- 1. css() ships both scheme blocks and the full token set ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

$css = Dark_Mode::css( 'modern-restaurant' );

check( false !== strpos( $css, ':root[data-flavor-scheme="dark"]' ), 'explicit night block keys off :root[data-flavor-scheme="dark"]' );
check( false !== strpos( $css, 'html[data-flavor-scheme="dark"] body{' ), 'explicit night block also seeds body (frames without :root scope)' );
check( false !== strpos( $css, '@media (prefers-color-scheme:dark)' ), 'the auto block rides the device preference media query' );
check(
	false !== strpos( $css, ':root[data-flavor-scheme="auto"]' ) && false !== strpos( $css, 'html[data-flavor-scheme="auto"] body{' ),
	'auto block covers both the untouched state on :root and on body'
);

$core_tokens = array(
	'--flavor-bg', '--flavor-surface', '--flavor-surface-rgb', '--flavor-surface-alt',
	'--flavor-ink', '--flavor-ink-rgb', '--flavor-muted', '--flavor-line',
	'--flavor-primary', '--flavor-primary-rgb', '--flavor-accent', '--flavor-accent-rgb',
	'--ui-action-ink', '--ui-muted',
);
$missing = array();
foreach ( $core_tokens as $token ) {
	if ( false === strpos( $css, $token . ':' ) ) {
		$missing[] = $token;
	}
}
check( array() === $missing, 'every core night token is declared' . ( $missing ? ' — missing: ' . implode( ', ', $missing ) : '' ) );

$status_tokens = array();
foreach ( array( 'success', 'warning', 'danger', 'info' ) as $hue ) {
	$status_tokens[] = '--flavor-' . $hue;
	$status_tokens[] = '--flavor-' . $hue . '-bg';
}
$missing = array();
foreach ( $status_tokens as $token ) {
	if ( false === strpos( $css, $token . ':' ) ) {
		$missing[] = $token;
	}
}
check(
	array() === $missing,
	'prompt 58: the status hues ship night foregrounds next to their tints' . ( $missing ? ' — missing: ' . implode( ', ', $missing ) : '' )
);

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 2. Status foregrounds clear AA on every skin ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

/*
 * The live-status pill, stock badges and notices paint status-hue copy on
 * the hue's own night tint (and sometimes straight on surface-alt, the
 * lightest night canvas). Before prompt 58 the palette only mixed the
 * tint and left the day hue as the foreground — dark green on dark green
 * measured 2.7:1 in the a11y night sweep.
 */
foreach ( array_keys( Design::skins() ) as $slug ) {
	$p     = Dark_Mode::palette( (string) $slug );
	$fails = array();
	foreach ( array( 'success', 'warning', 'danger', 'info' ) as $hue ) {
		if ( ! hex_ok( (string) $p[ $hue ] ) || ! hex_ok( (string) $p[ $hue . '_bg' ] ) ) {
			$fails[] = $hue . ':not-a-hex';
			continue;
		}
		foreach ( array( $hue . '_bg', 'surface_alt' ) as $bg_key ) {
			$ratio = Dark_Mode::contrast( (string) $p[ $hue ], (string) $p[ $bg_key ] );
			if ( $ratio < 4.5 ) {
				$fails[] = sprintf( '%s-on-%s=%.2f', $hue, $bg_key, $ratio );
			}
		}
	}
	check(
		array() === $fails,
		(string) $slug . ': all four status hues clear 4.5:1 on their tint and on surface-alt' . ( $fails ? ' — ' . implode( ', ', $fails ) : '' )
	);
}

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 3. The boot script reads what premium.js writes ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

/*
 * premium.js store() JSON-encodes every value, so the persisted choice is
 * the seven-character string "dark" — quotes included. The pre-paint boot
 * script used to compare the raw getItem() result against dark/light, so
 * the stored choice NEVER matched: night visitors got a white flash on
 * every navigation and their preference was lost. The boot now parses
 * tolerantly (encoded value and legacy raw value both restore).
 */
$premium_js = (string) file_get_contents( FLAVOR_DIR . '/assets/js/premium.js' );

check( 'flavorScheme' === Dark_Mode::STORAGE_KEY, 'STORAGE_KEY stays flavorScheme (the shipped storage contract)' );
check( false !== strpos( $premium_js, "store('flavorScheme', next)" ), 'premium.js persists the toggle choice under the same key' );
check( false !== strpos( $premium_js, 'JSON.stringify(value)' ), 'premium.js store() JSON-encodes — the boot must parse, not raw-compare' );

$GLOBALS['_mock_theme_mods']['flavor_color_scheme'] = 'auto';
ob_start();
Dark_Mode::head();
$head = (string) ob_get_clean();

check( false !== strpos( $head, '<style id="flavor-night-palette">' ), 'head() prints the generated palette before the first paint' );
check( false !== strpos( $head, '<meta name="theme-color" media="(prefers-color-scheme: dark)"' ), 'head() tags the dark browser-chrome colour' );
check( false !== strpos( $head, '<script id="flavor-scheme-boot">' ), 'head() prints the pre-paint boot script' );
check( false !== strpos( $head, 'if(d.hasAttribute("data-flavor-scheme-locked"))return;' ), 'the boot bails on a locked (owner-fixed) scheme' );
check( false !== strpos( $head, 'try{s=JSON.parse(s);}catch(e){}' ), 'prompt 58: the boot parses the JSON-encoded choice tolerantly' );
check( false !== strpos( $head, 'localStorage.getItem("flavorScheme")' ), 'the boot reads exactly the key premium.js writes' );
check(
	false !== strpos( $head, 'if(s==="dark"||s==="light")' ) && false !== strpos( $head, 'd.setAttribute("data-flavor-scheme",s)' ),
	'the boot restores only the two valid choices onto the html state attribute'
);

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 4. Locked mode is server-rendered, script-free ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

$GLOBALS['_mock_theme_mods']['flavor_color_scheme_toggle'] = 'no';

$attrs = Dark_Mode::html_attributes( 'class="hfeed"' );
check( false !== strpos( $attrs, 'data-flavor-scheme="auto"' ), 'locked site still stamps the owner scheme on <html>' );
check( false !== strpos( $attrs, 'data-flavor-scheme-locked="1"' ), 'locked site stamps the locked marker the boot and CSS honour' );

ob_start();
Dark_Mode::head();
$locked_head = (string) ob_get_clean();
check(
	false !== strpos( $locked_head, 'flavor-night-palette' ) && false === strpos( $locked_head, 'flavor-scheme-boot' ),
	'locked head(): palette ships, boot script does not (nothing to restore)'
);

ob_start();
Dark_Mode::toggle( 'header' );
$locked_toggle = (string) ob_get_clean();
check( '' === trim( $locked_toggle ), 'locked site renders no toggle button at all' );

$GLOBALS['_mock_theme_mods']['flavor_color_scheme_toggle'] = 'yes';
check(
	false === strpos( Dark_Mode::html_attributes( 'class="hfeed"' ), 'data-flavor-scheme-locked' ),
	'unlocked site carries no locked marker'
);

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 5. The toggle button is accessible markup ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

ob_start();
Dark_Mode::toggle( 'header' );
$toggle = (string) ob_get_clean();

check( false !== strpos( $toggle, '<button type="button"' ), 'toggle is a real button' );
check( false !== strpos( $toggle, 'data-flavor-scheme-toggle' ), 'toggle carries the hook premium.js and the guards click' );
check( false !== strpos( $toggle, 'aria-pressed="false"' ), 'toggle starts unpressed; premium.js keeps the state honest' );
check( false !== strpos( $toggle, 'تغییر حالت شب و روز' ), 'toggle label is the shipped Persian msgid (already in the PO — no new string)' );
check(
	false !== strpos( $toggle, 'flavor-scheme-toggle__icon--sun' ) && false !== strpos( $toggle, 'flavor-scheme-toggle__icon--moon' ),
	'both icons render; CSS swaps them per scheme so it is correct without JS'
);

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 6. premium.js drives attribute, storage, pressed state and auto ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

check( false !== strpos( $premium_js, "root.setAttribute('data-flavor-scheme', next)" ), 'the click paints the state attribute (CSS reacts before storage)' );
check( false !== strpos( $premium_js, "toggle.setAttribute('aria-pressed', String(mode === 'dark'))" ), 'paint() keeps aria-pressed in sync with the resolved scheme' );
check( false !== strpos( $premium_js, "window.matchMedia('(prefers-color-scheme: dark)')" ), 'resolved() consults the device preference for auto' );
check(
	false !== strpos( $premium_js, "var onChange = function () { if ((root.getAttribute('data-flavor-scheme') || 'auto') === 'auto') { paint(); } };" ),
	'the media listener repaints only while the visitor is on auto'
);
check( false !== strpos( $premium_js, "meta.setAttribute('content'" ), 'paint() moves the theme-color meta with the scheme' );

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 7. dark-mode.css keeps its night contract ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

$dark_css = (string) file_get_contents( $css_dir . '/dark-mode.css' );

check( substr_count( $dark_css, '.custom-logo-link' ) >= 2, 'prompt 58: the uploaded-logo chip covers the explicit and the auto scheme' );
check( substr_count( $dark_css, '.flavor-offer-coupon' ) >= 2, 'prompt 58: the white coupon ground resolves through surface-alt in both night paths' );
check(
	false !== strpos( $dark_css, 'html[data-flavor-scheme="dark"] body {' ) && false !== strpos( $dark_css, 'background-color: var(--flavor-bg);' ),
	'prompt 58: the night body canvas outranks raw skin body rules'
);
check( false !== strpos( $dark_css, 'color-scheme' ), 'the sheet declares color-scheme so UA widgets follow the night' );
check( false !== strpos( $dark_css, '.flavor-scheme-switching' ), 'the scheme cross-fade class is styled' );
check( false !== strpos( $dark_css, 'prefers-reduced-motion' ), 'the cross-fade stands down for reduced motion' );

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 8. Every classic skin carries night twins for its literals ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

/*
 * The prompt-58 a11y night sweep (tests/ui/a11y.mjs) renders every classic
 * skin under the dark palette; these twins are what made it pass. Each pin
 * is one measured violation class: cream header veils, dark-band grounds
 * painted with flipped ink tokens, and raw day-side copy literals.
 */
$skin_pins = array(
	'modern-restaurant'   => array( '.flavor-header', '.flavor-nav a', '.flavor-header__phone', '.flavor-res-banner', '.flavor-live-status--open' ),
	'cafe-bistro'         => array( '.flavor-header', '.flavor-nav a', '.flavor-header__phone' ),
	'pizza-italian'       => array( '.flavor-header', '.flavor-nav a', '.flavor-header__phone', '.flavor-brand__name', '.flavor-food-card' ),
	'bakery-pastry'       => array( '.flavor-header', '.flavor-nav a', '.flavor-header__phone' ),
	'persian-traditional' => array( '.flavor-header', '.flavor-nav a', '.flavor-header__phone', '.flavor-brand__name', '.flavor-section-header__title', '.flavor-food-card__price', '.flavor-about__exp-badge' ),
	'fast-food'           => array( '.flavor-topbar', '.flavor-header__phone', '.flavor-intro', '.flavor-res-banner', '.flavor-offer-banner__badge', '.flavor-food-card', '.flavor-review-card', '.flavor-testimonials', '.flavor-food-card__price', '.flavor-section-header__tag', '.flavor-section-header__desc', '.flavor-offer-banner', '.flavor-contact-row__icon', '.flavor-hours-card' ),
);
foreach ( $skin_pins as $skin => $selectors ) {
	$sheet = (string) file_get_contents( $skins_dir . '/' . $skin . '.css' );
	$fails = array();
	foreach ( $selectors as $selector ) {
		$night = 'html[data-flavor-scheme="dark"] body.flavor-skin-' . $skin . ' ' . $selector;
		$auto  = 'html[data-flavor-scheme="auto"] body.flavor-skin-' . $skin . ' ' . $selector;
		if ( false === strpos( $sheet, $night ) ) {
			$fails[] = $selector . '(explicit)';
		}
		if ( false === strpos( $sheet, $auto ) ) {
			$fails[] = $selector . '(auto)';
		}
	}
	check(
		array() === $fails,
		$skin . ': every measured night twin exists in both scheme paths' . ( $fails ? ' — missing: ' . implode( ', ', $fails ) : '' )
	);
}

/* The band grounds stay the day-side darks; a flipped ink token would paint them LIGHT. */
$modern_sheet = (string) file_get_contents( $skins_dir . '/modern-restaurant.css' );
check( false !== strpos( $modern_sheet, 'body.flavor-skin-modern-restaurant .flavor-res-banner {' ) && false !== strpos( $modern_sheet, 'background: #201c19;' ), 'modern: the reservation band ground stays charcoal at night' );
$fast_sheet = (string) file_get_contents( $skins_dir . '/fast-food.css' );
check( false !== strpos( $fast_sheet, 'background: #171512 !important;' ), 'fast-food: the testimonials band stays day black at night' );
$persian_sheet = (string) file_get_contents( $skins_dir . '/persian-traditional.css' );
check( false !== strpos( $persian_sheet, 'color: #fff6e5;' ), 'persian: the wine seal badge keeps cream copy (its ground never flips)' );

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 9. Primary grounds ride the audited action ink ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

/*
 * The night palette lifts --flavor-primary until it reads as TEXT on dark
 * canvases; white copy on that lifted ground measures ~2.2:1. Everything
 * that paints primary as a ground takes --ui-action-ink, which palette()
 * picks per skin (white or black) against the lifted primary.
 */
$main_css = (string) file_get_contents( $css_dir . '/main.css' );
check( false !== strpos( $main_css, 'color: var(--ui-action-ink, #ffffff); /* prompt 58' ), 'main.css: the skip link and the topbar ride the action ink' );
check( substr_count( $main_css, 'var(--ui-action-ink' ) >= 3, 'main.css: skip link, topbar and the primary button all ride the action ink' );
$marketing_css = (string) file_get_contents( $css_dir . '/marketing.css' );
check( false !== strpos( $marketing_css, 'color: var(--ui-action-ink, #ffffff); /* prompt 58' ), 'marketing.css: the experience badge rides the action ink' );
$ui_css = (string) file_get_contents( $css_dir . '/ui.css' );
check( false !== strpos( $ui_css, '.flavor-ui .flavor-ui-text-link{' ) && false !== strpos( $ui_css, 'background:transparent' ), 'ui.css: text links stay transparent — the UA buttonface slab broke night AA at 4.35:1' );

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 10. The kitchen board is night by design and stays readable ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

/*
 * The KDS kiosk runs its own always-dark palette (its own --kd tokens, 24/7
 * wall screens). It deliberately ignores the site scheme — flipping it with
 * the visitor toggle would blind a kitchen at noon. So the gate measures it
 * statically instead: the text pairs at 4.5:1 and the status hues at 3:1
 * (large badge copy), and the opt-out itself is pinned.
 */
$kd_css = (string) file_get_contents( $root . '/flavor-core/assets/public/css/kitchen-dashboard.css' );

check( false === strpos( $kd_css, 'prefers-color-scheme' ) && false === strpos( $kd_css, 'data-flavor-scheme' ), 'the KDS sheet opts out of the site scheme entirely (always-dark kiosk)' );

$kd_panel  = css_value( $kd_css, '--kd-panel' );
$kd_ink    = css_value( $kd_css, '--kd-ink' );
$kd_muted  = css_value( $kd_css, '--kd-muted' );
$kd_washed = array();
foreach ( array( '--kd-panel' => $kd_panel, '--kd-ink' => $kd_ink, '--kd-muted' => $kd_muted ) as $name => $value ) {
	if ( ! hex_ok( $value ) ) {
		$kd_washed[] = $name;
	}
}
$kds_text_fails = array();
foreach ( array( array( $kd_ink, $kd_panel, 'ink-on-panel' ), array( $kd_muted, $kd_panel, 'muted-on-panel' ), array( $kd_ink, '#0f1216', 'ink-on-shell' ) ) as $pair ) {
	$ratio = Dark_Mode::contrast( $pair[0], $pair[1] );
	if ( $ratio < 4.5 ) {
		$kds_text_fails[] = sprintf( '%s=%.2f', $pair[2], $ratio );
	}
}
check( array() === $kd_washed && array() === $kds_text_fails, 'KDS text pairs clear 4.5:1 on every ground they print on' . ( $kds_text_fails ? ' — ' . implode( ', ', $kds_text_fails ) : '' ) );

$kds_status_fails = array();
foreach ( array( '--kd-new', '--kd-preparing', '--kd-ready', '--kd-late' ) as $token ) {
	$value = css_value( $kd_css, $token );
	if ( ! hex_ok( $value ) ) {
		$kds_status_fails[] = $token . ':not-a-hex';
		continue;
	}
	$ratio = Dark_Mode::contrast( $value, $kd_panel );
	if ( $ratio < 3.0 ) {
		$kds_status_fails[] = sprintf( '%s=%.2f', $token, $ratio );
	}
}
check( array() === $kds_status_fails, 'KDS status hues clear 3:1 against the panel (large badge copy)' . ( $kds_status_fails ? ' — ' . implode( ', ', $kds_status_fails ) : '' ) );
check( false !== strpos( $kd_css, 'background: #0f1216' ), 'the kiosk shell ground is still the pinned near-black' );

/* ------------------------------------------------------------------ */
echo PHP_EOL . '--- 11. Fixtures and guards keep measuring the night ---' . PHP_EOL;
/* ------------------------------------------------------------------ */

$a11y_pages = (string) file_get_contents( $ui_dir . '/a11y-pages.php' );
check( false !== strpos( $a11y_pages, "'rtl/light', 'ltr/light', 'rtl/dark'" ), 'a11y fixtures render the night twin pass' );
check( false !== strpos( $a11y_pages, "getenv( 'A11Y_SKIN' )" ), 'a11y fixtures take the skin from A11Y_SKIN (the sweep dresses every classic skin)' );
check(
	false !== strpos( $a11y_pages, 'flavor-night-palette' ) && false !== strpos( $a11y_pages, 'Dark_Mode::css()' ),
	'the night twin inlines the generated palette where the live wp_head prints it'
);
check( false !== strpos( $a11y_pages, 'data-flavor-scheme="dark"' ), 'the night twin stamps the state attribute on <html>' );

$a11y_mjs = (string) file_get_contents( $ui_dir . '/a11y.mjs' );
check( false !== strpos( $a11y_mjs, 'const darkSkins' ), 'the a11y guard sweeps the classic skins under night' );
check( false !== strpos( $a11y_mjs, 'dark/page-${slug}.html' ), 'the a11y guard scans the offline night twins' );
check( false !== strpos( $a11y_mjs, "document.documentElement.setAttribute('data-flavor-scheme', 'dark')" ), 'LIVE mode flips the state attribute the way the toggle does' );
check( false !== strpos( $a11y_mjs, 'product-sheet-dark' ), 'the cart, checkout and sheet panels get night audits too' );

$el_pages = (string) file_get_contents( $ui_dir . '/elementor-pages.php' );
check(
	false !== strpos( $el_pages, 'Dark_Mode::head();' ) && false !== strpos( $el_pages, "Dark_Mode::toggle( 'header' )" ),
	'the elementor probe carries the real night head and a clickable toggle'
);

$el_mjs = (string) file_get_contents( $ui_dir . '/elementor-visual.mjs' );
check( false !== strpos( $el_mjs, "click('[data-flavor-scheme-toggle]')" ), 'the elementor guard clicks the real toggle' );
check( false !== strpos( $el_mjs, 'clicked.stored !== \'"dark"\'' ), 'the guard pins the JSON-encoded storage value premium.js writes' );
check( false !== strpos( $el_mjs, 'schemePage.reload' ), 'the guard reloads and demands the boot script restore the choice' );
check(
	false !== strpos( $el_mjs, "emulateMedia({ colorScheme: 'dark' })" ) && false !== strpos( $el_mjs, "emulateMedia({ colorScheme: 'light' })" ),
	'the guard flips the device preference both ways for the auto scheme'
);
check( false !== strpos( $el_mjs, 'scheme: { violations: schemeViolations.length }' ), 'the scheme mechanism lands in the run report' );

echo PHP_EOL . '=======================================================' . PHP_EOL;
echo "Results: {$passed} Passed, {$failed} Failed" . PHP_EOL;
echo '=======================================================' . PHP_EOL;

exit( $failed > 0 ? 1 : 0 );
