<?php
/**
 * Keyboard-navigation contract tests (P52).
 *
 * The browser suite (tests/ui/keyboard.mjs) is the authority on whether the
 * storefront is operable from the keyboard — but it needs Chromium, so it
 * runs only in the Playwright CI job. This gate protects the same work on
 * every PHP run: it pins the product fixes the suite forced (the
 * paintSheetPrice variable shadowing that broke every item-sheet open since
 * prompt 22, the disclosure CSS/JS pair for the mega menu, the layer
 * coordination between ui.js and the drawer, the aria-expanded reset on
 * every dialog close path), the fixture builder's contract, and the CI
 * wiring. Reverting any of it turns this file red on a machine with no
 * browser at all.
 *
 *   php tests/theme/test-keyboard-suite.php
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

$root = dirname( FLAVOR_DIR );

/**
 * Read a repo file or fail loudly.
 *
 * @param string $rel Path relative to the repo root.
 * @return string
 */
function kb_file( string $rel ): string {
	global $root;
	$path = $root . '/' . $rel;
	if ( ! is_readable( $path ) ) {
		check( false, "readable source: {$rel}" );
		return '';
	}
	return (string) file_get_contents( $path );
}

echo "\n== menu.js: the shadowing bug that broke every sheet open ==\n";

$menu_js = kb_file( 'flavor/assets/js/menu.js' );
check(
	str_contains( $menu_js, 'var priceEl = document.getElementById(\'flavor-sheet-price\')' ),
	'paintSheetPrice writes the label through priceEl, not a hoisted `var price`'
);
check(
	! preg_match( '/var\s+price\s*=/', $menu_js ),
	'no `var price =` survives anywhere in menu.js to shadow the price() formatter'
);
check(
	str_contains( $menu_js, 'var label = price(n);' ),
	'the sheet price label still comes from the module-level price() formatter'
);

echo "\n== ui.js: dialog stack coordination and Tab recapture ==\n";

$ui_js = kb_file( 'flavor/assets/js/ui.js' );
check(
	str_contains( $ui_js, 'dialogCount: dialogCount' ) && str_contains( $ui_js, 'function dialogCount() { return stack.length; }' ),
	'FlavorUI exports dialogCount() so independent layers can see the stack depth'
);
check(
	str_contains( $ui_js, '!entry.host.contains(document.activeElement)' ),
	'Tab with focus outside the top dialog (body after a text click) is recaptured inside it'
);
check(
	str_contains( $ui_js, '(event.shiftKey ? items[items.length - 1] : items[0]).focus()' ),
	'the recapture respects Tab direction (first stop forward, last stop back)'
);

echo "\n== main.js: the drawer yields Escape to a dialog above it ==\n";

$main_js = kb_file( 'flavor/assets/js/main.js' );
check(
	str_contains( $main_js, 'window.FlavorUI.dialogCount()' ),
	'the drawer Escape handler consults the dialog stack before closing itself'
);
check(
	(bool) preg_match( '/dialogCount&&window\.FlavorUI\.dialogCount\(\)>0\)return;/', preg_replace( '/\s+/', '', $main_js ) )
		|| str_contains( $main_js, 'dialogCount() > 0' ),
	'a dialog stacked over the drawer owns the Escape keystroke (one key, one layer)'
);
check(
	str_contains( $main_js, 'select:not([disabled]),textarea:not([disabled]),[tabindex="0"]' )
		&& str_contains( $main_js, '!el.closest(\'[inert]\')' ),
	'the drawer trap counts the same focusable set ui.js does, inert subtrees excluded'
);

echo "\n== premium.js: mega disclosure, drawer normalization, trigger-state reset ==\n";

$premium_js = kb_file( 'flavor/assets/js/premium.js' );
check(
	str_contains( $premium_js, "link.removeAttribute('data-flavor-nav')" )
		&& str_contains( $premium_js, "link.removeAttribute('aria-expanded')" ),
	'drawer copies of the menu lose the disclosure attributes (their submenu is statically open)'
);
check(
	str_contains( $premium_js, "link.setAttribute('aria-controls', panel.id)" ),
	'every header branch link points at its own panel through aria-controls'
);
check(
	str_contains( $premium_js, "classList.add('flavor-nav-disclosure')" ),
	'JS-managed branches carry flavor-nav-disclosure — the class the collapsing CSS keys on'
);
check(
	str_contains( $premium_js, '_flavorEscClosed' ),
	'Escape sets a one-shot flag so its own refocus cannot reopen the panel'
);
foreach ( array( 'Escape', 'ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Home', 'End' ) as $kb_key ) {
	check(
		str_contains( $premium_js, "key === '{$kb_key}'" ) || str_contains( $premium_js, "'{$kb_key}'" ),
		"the mega keyboard layer handles {$kb_key}"
	);
}
check(
	str_contains( $premium_js, "key === 'ArrowLeft' ? 1 : -1" ),
	'RTL arrow semantics: ArrowLeft advances to the next top-level item, ArrowRight goes back'
);
check(
	2 === substr_count( $premium_js, "host.addEventListener('flavor:dialog-closed'" ),
	'wishlist AND quickview reset their triggers on flavor:dialog-closed (every close path, Escape included)'
);
check(
	! preg_match( "/data-wishlist-close'\]\)\)\s*\{\s*closeDialog\(host\);\s*\\\$\\\$\\(/", $premium_js ),
	'the wishlist click branch no longer carries its own partial aria-expanded reset'
);

echo "\n== CSS: the collapse only happens where a script can reopen ==\n";

$premium_css = kb_file( 'flavor/assets/css/premium.css' );
check(
	str_contains( $premium_css, 'html.flavor-js .flavor-header__nav .flavor-nav-disclosure > .sub-menu {' )
		&& str_contains( $premium_css, 'display: none;' ),
	'a JS-managed branch panel collapses out of sight and tab order (gated on html.flavor-js)'
);
check(
	str_contains( $premium_css, 'html.flavor-js .flavor-header__nav .flavor-nav-disclosure > a[aria-expanded="true"] ~ .sub-menu {' ),
	'the open state is the same aria-expanded assistive tech reads — one source of truth'
);
check(
	str_contains( $premium_css, '.flavor-header__nav .flavor-nav .sub-menu {' )
		&& str_contains( $premium_css, 'position: absolute;' ),
	'panels hang below the header as positioned slabs even without JS (visible, reachable fallback)'
);
check(
	str_contains( $premium_css, 'margin-inline: auto;' ) && str_contains( $premium_css, 'width: max-content;' ),
	'the mega panel stays a centered full-width slab'
);

$main_css = kb_file( 'flavor/assets/css/main.css' );
check(
	str_contains( $main_css, '.flavor-drawer__nav .flavor-nav .sub-menu {' )
		&& str_contains( $main_css, 'border-inline-start: 2px solid var(--flavor-line);' ),
	'the drawer submenu is styled as the static nested list it is'
);

echo "\n== enqueue: the flavor-js marker is a production contract ==\n";

$enqueue = kb_file( 'flavor/inc/class-enqueue.php' );
check(
	str_contains( $enqueue, "wp_add_inline_script( 'flavor-digits', \"document.documentElement.classList.add( 'flavor-js' );\", 'before' )" ),
	'html.flavor-js lands from an inline script before every deferred bundle'
);

echo "\n== fixture builder: the offline shop must mirror the live one ==\n";

$builder = kb_file( 'tests/ui/keyboard-pages.php' );
check(
	str_contains( $builder, "\\Flavor\\UI::body_class" ),
	'the fixture body carries the .flavor-ui scope class exactly like a live shop'
);
check(
	str_contains( $builder, "classList.add('flavor-js')" ),
	'the fixture sets the same flavor-js marker the enqueue inline script sets'
);
check(
	str_contains( $builder, 'function is_page_template' ),
	'is_page_template is pre-defined before bootstrap so the bottom-nav cart button renders'
);
check(
	str_contains( $builder, "path === 'cart/items' && method === 'POST'" )
		&& str_contains( $builder, '/^dishes' ),
	'the fetch stub answers the dish-detail and add-to-cart routes the suite drives'
);

echo "\n== suite contract: tests/ui/keyboard.mjs ==\n";

$suite = kb_file( 'tests/ui/keyboard.mjs' );
check(
	str_contains( $suite, "state: 'attached'" ) && ! preg_match( "/waitForSelector\('#flavor-(?:sheet|cart-panel|wishlist)\[hidden\]', \{ timeout/", $suite ),
	'every dialog-closed wait uses state:attached — the default visible state can never match a [hidden] element'
);
check(
	str_contains( $suite, "reducedMotion: 'reduce'" ),
	'the suite freezes reduced motion so focus, not choreography, is under test'
);
foreach ( range( 'A', 'H' ) as $group ) {
	check(
		str_contains( $suite, "check('{$group}'" ),
		"group {$group} of the keyboard suite is still present"
	);
}
check(
	str_contains( $suite, 'ui-qa/keyboard' ),
	'the suite writes its report and screenshots into .cache/ui-qa/keyboard/'
);

echo "\n== CI wiring ==\n";

$ci = kb_file( '.github/workflows/ci.yml' );
check( str_contains( $ci, 'node tests/ui/keyboard.mjs' ), 'the Playwright CI job runs the keyboard suite' );
check(
	str_contains( $ci, 'name: keyboard-evidence' ) && str_contains( $ci, 'path: .cache/ui-qa/keyboard/' ),
	'a failing keyboard run uploads its report and screenshots as evidence'
);

$pkg = kb_file( 'tests/ui/package.json' );
check( str_contains( $pkg, '"test:keyboard": "node keyboard.mjs"' ), 'tests/ui/package.json exposes test:keyboard' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
