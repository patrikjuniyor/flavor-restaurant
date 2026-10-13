<?php
/**
 * ARIA for the dynamic components (prompt 55).
 *
 * The browser suites watch behaviour (axe on the shipped pages, keyboard
 * walks); this gate protects the contracts underneath them on every PHP
 * run, with no browser needed: the category chips follow the WAI-ARIA
 * tabs pattern, cart counters keep their accessible name in sync and
 * announce changes, the floating dock is a labelled group, the tracking
 * result is a live labelled region, and every decorative SVG the theme
 * prints — PHP or JS — stays out of the accessibility tree.
 *
 *   php tests/theme/test-aria-dynamic.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';

use Flavor\UI;

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
 * @param string $rel Path under the theme root.
 * @return string
 */
function read( string $rel ): string {
	return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
}

$root = dirname( __DIR__, 2 );

echo "\n--- 1. Decorative SVG never reaches the accessibility tree ---\n";

ob_start();
UI::icon( 'bag', 20 );
$icon = (string) ob_get_clean();
check( str_contains( $icon, 'aria-hidden="true"' ), 'UI::icon prints aria-hidden="true"' );
check( str_contains( $icon, 'focusable="false"' ), 'UI::icon prints focusable="false" (legacy IE/Edge focus trap closed)' );

/* Every literal <svg the PHP side prints must be hidden: the theme has no
   SVG that carries meaning without adjacent text or an aria-label. */
$php_files = array();
$php_dirs  = array( 'flavor', 'flavor/inc', 'flavor/page-templates' );
foreach ( glob( $root . '/flavor/template-parts/*', GLOB_ONLYDIR ) ?: array() as $sub ) {
	$php_dirs[] = 'flavor/template-parts/' . basename( $sub );
}
$php_dirs[] = 'flavor/template-parts';
foreach ( $php_dirs as $dir ) {
	$full = $root . '/' . $dir;
	if ( ! is_dir( $full ) ) {
		continue;
	}
	foreach ( glob( $full . '/*.php' ) ?: array() as $file ) {
		$php_files[ $file ] = true;
	}
}
$bare_svg = array();
foreach ( array_keys( $php_files ) as $file ) {
	$source = (string) file_get_contents( $file );
	if ( preg_match_all( '/<svg\b[^>]*>/i', $source, $hits ) ) {
		foreach ( $hits[0] as $tag ) {
			if ( ! str_contains( $tag, 'aria-hidden' ) ) {
				$bare_svg[] = str_replace( $root . '/', '', $file );
			}
		}
	}
}
check( array() === $bare_svg, 'every literal <svg> in the PHP layer carries aria-hidden' . ( $bare_svg ? ' — bare: ' . implode( ', ', array_unique( $bare_svg ) ) : '' ) );

$bare_js = array();
foreach ( glob( $root . '/flavor/assets/js/*.js' ) ?: array() as $file ) {
	$source = (string) file_get_contents( $file );
	if ( preg_match_all( '/<svg\b[^>]*>/i', $source, $hits ) ) {
		foreach ( $hits[0] as $tag ) {
			if ( ! str_contains( $tag, 'aria-hidden' ) ) {
				$bare_js[] = basename( $file );
			}
		}
	}
}
check( array() === $bare_js, 'every literal <svg> in the JS layer carries aria-hidden' . ( $bare_js ? ' — bare: ' . implode( ', ', array_unique( $bare_js ) ) : '' ) );

$social = read( 'flavor/inc/template-tags.php' );
check( 3 === preg_match_all( '/<svg aria-hidden="true" focusable="false" width="20"/', $social ), 'the three footer social icons (instagram/telegram/whatsapp) are hidden + unfocusable' );

echo "\n--- 2. Category chips are a real tablist, not loose toggles ---\n";

$cats_nav = read( 'flavor/template-parts/menu/category-nav.php' );
check( str_contains( $cats_nav, 'id="flavor-cats-nav"' ) && str_contains( $cats_nav, 'aria-label' ), 'the sticky category rail is a labelled <nav>' );
check( str_contains( $cats_nav, 'id="flavor-cats" role="tablist"' ) && substr_count( $cats_nav, 'aria-label' ) >= 2, 'the scroller inside is the labelled role=tablist host' );

$menu_js = read( 'flavor/assets/js/menu.js' );
check( str_contains( $menu_js, "role=\"tab\"" ) && str_contains( $menu_js, 'aria-selected="true"' ) && str_contains( $menu_js, 'aria-selected="false"' ), 'renderCats prints role=tab chips with aria-selected' );
check( str_contains( $menu_js, 'id="flavor-cat-tab-0"' ) && str_contains( $menu_js, "id=\"flavor-cat-tab-' + esc(c.id) + '\"" ), 'every tab has a stable id the panel can be labelled by' );
check( str_contains( $menu_js, 'tabindex="0"' ) && str_contains( $menu_js, 'tabindex="-1"' ), 'the tabs ship with a roving tabindex (one stop, not N)' );
check( substr_count( $menu_js, 'aria-controls="flavor-menu-grid"' ) >= 2, 'each tab points at the shared grid with aria-controls' );
check( str_contains( $menu_js, "grid.setAttribute('role', 'tabpanel')" ) && str_contains( $menu_js, "grid.setAttribute('aria-labelledby', 'flavor-cat-tab-0')" ), 'renderCats turns the grid into the tabpanel labelled by the first tab' );
check( str_contains( $menu_js, "if (selected) grid.setAttribute('aria-labelledby', button.id);" ), 'filterCat re-labels the panel with the newly selected tab' );
check( str_contains( $menu_js, 'button.tabIndex = selected ? 0 : -1;' ), 'filterCat moves the roving tabindex with the selection' );

$bad_pressed = array();
foreach ( explode( "\n", $menu_js ) as $line ) {
	if ( str_contains( $line, 'aria-pressed' ) && ( str_contains( $line, 'data-cat' ) || str_contains( $line, 'role="tab"' ) ) ) {
		$bad_pressed[] = trim( substr( $line, 0, 60 ) );
	}
}
check( array() === $bad_pressed, 'no category tab line mixes aria-pressed into the tabs pattern' . ( $bad_pressed ? ' — ' . implode( ' | ', $bad_pressed ) : '' ) );

check( str_contains( $menu_js, "catsEl.addEventListener('keydown'" ), 'the tablist listens for keydown' );
foreach ( array( 'ArrowLeft', 'ArrowRight', 'Home', 'End' ) as $key ) {
	check( str_contains( $menu_js, "e.key === '$key'" ), "tablist keyboard: $key moves between tabs" );
}
check( str_contains( $menu_js, "var rtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';" )
	&& str_contains( $menu_js, 'next = rtl ? index + 1 : index - 1;' )
	&& str_contains( $menu_js, 'next = rtl ? index - 1 : index + 1;' ), 'arrow direction is mirrored for the RTL document' );
check( str_contains( $menu_js, 'e.preventDefault();' ) && str_contains( $menu_js, 'target.focus();' ) && str_contains( $menu_js, "filterCat(target.getAttribute('data-cat'));" ), 'arrows use automatic activation: focus moves and the panel repaints' );
check( str_contains( $menu_js, "var b = e.target.closest('[role=\"tab\"]');" ), 'the click handler delegates on the tab role' );

echo "\n--- 3. Cart counters: truthful names, announced changes ---\n";

$chrome = read( 'flavor/inc/class-chrome-builder.php' );
check( str_contains( $chrome, "sprintf( __( 'سبد خرید، %s قلم', 'flavor' ), (string) \$count )" ), 'the header cart link names the item count when the cart is not empty' );
check( str_contains( $chrome, 'data-flavor-cart-count' ), 'the header badge keeps its data hook for the JS sync' );

$enqueue = read( 'flavor/inc/class-enqueue.php' );
check( str_contains( $enqueue, "'cart_label'        => __( 'سبد خرید', 'flavor' )," ), "the menu dictionary carries cart_label for the JS-side name" );
check( str_contains( $enqueue, "'cart_count_live'   => __( 'سبد خرید، %s قلم', 'flavor' )," ), 'the menu dictionary carries cart_count_live for the JS-side name' );

check( str_contains( $menu_js, "document.querySelectorAll('[data-flavor-cart-count]')" ), 'drawCart syncs the server-rendered header badge after every refresh' );
check( str_contains( $menu_js, "var link = element.closest('a');" ) && str_contains( $menu_js, "link.setAttribute('aria-label', (cart.count || 0) ? fill('cart_count_live', fa(cart.count)) : t('cart_label'));" ), 'the header link’s accessible name follows the count, down to the empty-cart label' );
check( str_contains( $menu_js, "ui.announce(fill('cart_updated', fa(cart.count)))" ), 'every cart mutation is announced through the shared polite status region' );

$ui_js = read( 'flavor/assets/js/ui.js' );
check( str_contains( $ui_js, "status.setAttribute('role', 'status')" ) && str_contains( $ui_js, "status.setAttribute('aria-live', 'polite')" ) && str_contains( $ui_js, "status.className = 'screen-reader-text'" ), 'ui.announce builds a screen-reader-only role=status polite region on demand' );

$drawer = read( 'flavor/template-parts/menu/cart-drawer.php' );
check( str_contains( $drawer, 'aria-haspopup="dialog"' ) && str_contains( $drawer, 'aria-controls="flavor-cart-panel"' ) && str_contains( $drawer, 'aria-expanded="false"' ), 'the drawer handle keeps its dialog toggle semantics' );
check( str_contains( $drawer, 'id="flavor-cart-count" hidden aria-hidden="true"' ), 'the machine-readable count stays hidden from both eyes and AT' );
check( str_contains( $drawer, 'data-ui-cart-count' ), 'the visible drawer badge keeps its data hook' );

echo "\n--- 4. The floating dock is a labelled group of shortcuts ---\n";

$dock = read( 'flavor/inc/class-floating-dock.php' );
check( str_contains( $dock, 'role="group"' ) && str_contains( $dock, "esc_attr_e( 'میان‌برهای شناور', 'flavor' )" ), 'the dock container is a group with a translatable name' );
check( 4 === preg_match_all( '/aria-label="/', $dock ), 'the dock group and all three actions (whatsapp, call, back-to-top) carry aria-labels' );
check( str_contains( $dock, '<svg class="flavor-dock__ring" viewBox="0 0 44 44" aria-hidden="true" focusable="false">' ), 'the scroll-progress ring stays decorative' );

echo "\n--- 5. Order tracking: the result is a live, labelled region ---\n";

$tracking_tpl = read( 'flavor/page-templates/template-order-tracking.php' );
check( str_contains( $tracking_tpl, 'data-ui-track-result role="region" aria-labelledby="flavor-tracking-result-title" aria-live="polite" tabindex="-1" hidden' ), 'the result host is a labelled polite live region that can take focus' );
check( str_contains( $tracking_tpl, 'data-ui-track-error class="flavor-ui-error" role="alert"' ), 'the lookup error host is an assertive alert' );

$tracking_js = read( 'flavor/assets/js/tracking.js' );
check( str_contains( $tracking_js, "form.setAttribute('aria-busy','true')" ) && str_contains( $tracking_js, "form.removeAttribute('aria-busy')" ), 'the lookup marks itself busy while the request flies' );
check( str_contains( $tracking_js, 'aria-current="step"' ) && str_contains( $tracking_js, 'class="flavor-order-timeline" aria-label=' ), 'the kitchen timeline is a labelled list with aria-current on the reached step' );
check( str_contains( $tracking_js, 'result.focus({preventScroll:true})' ), 'the revealed result takes focus so screen readers read it' );
check( str_contains( $tracking_js, 'error.hidden=false;error.textContent=problem.message' ), 'failures land in the role=alert host' );

echo "\n--- 6. The filter/toggle patterns already shipped stay pinned ---\n";

$menu_tpl = read( 'flavor/page-templates/template-menu.php' );
check( str_contains( $menu_tpl, 'data-ui-menu-view' ) && str_contains( $menu_tpl, 'role="group"' ) && substr_count( $menu_tpl, 'aria-pressed' ) >= 2, 'the grid/list view switch is a labelled group of pressed-state toggles' );
check( str_contains( $ui_js, "button.setAttribute('aria-pressed', String(button.dataset.uiView === mode))" ), 'paintView keeps the view toggles’ aria-pressed truthful' );

check( str_contains( $drawer, '<fieldset class="flavor-cart__service"><legend>' ) && str_contains( $drawer, 'id="flavor-modes"' ), 'the service-mode chooser is a fieldset with a legend' );
check( str_contains( $menu_js, "button.setAttribute('aria-pressed', String(button.dataset.mode === mode))" ), 'the service-mode buttons keep aria-pressed in sync' );

$branch = read( 'flavor/template-parts/branch/branch-selector.php' );
check( str_contains( $branch, 'role="group" aria-label' ) && str_contains( $branch, 'data-ui-city' ) && str_contains( $branch, 'aria-pressed' ), 'the city filters are a labelled pressed-state group' );
$pages_js = read( 'flavor/assets/js/pages.js' );
check( str_contains( $pages_js, "item.setAttribute('aria-pressed',String(item===button))" ), 'the city filter clicks keep aria-pressed in sync' );

$wishlist = read( 'flavor/inc/class-wishlist.php' );
check( str_contains( $wishlist, 'aria-pressed="false"' ), 'the wishlist toggle ships as a pressed-state button' );

$dark = read( 'flavor/inc/class-dark-mode.php' );
check( str_contains( $dark, 'data-flavor-scheme-toggle' ) && str_contains( $dark, 'aria-pressed="false"' ), 'the night-scheme toggle ships with aria-pressed' );
$premium_js = read( 'flavor/assets/js/premium.js' );
check( str_contains( $premium_js, "toggle.setAttribute('aria-pressed', String(mode === 'dark'))" ), 'the scheme toggle’s aria-pressed follows the resolved scheme' );
check( str_contains( $premium_js, "announce(next === 'dark' ? t('scheme_night') : t('scheme_day'))" ), 'the scheme change is announced' );

$item_card = read( 'flavor/template-parts/menu/item-card.php' );
check( str_contains( $item_card, 'id="flavor-menu-status" role="status" aria-live="polite"' ), 'the menu result counter is a polite status region' );

echo "\n=======================================================\n";
echo 'Results: ' . $passed . ' Passed, ' . $failed . " Failed\n";
echo "=======================================================\n";
exit( $failed > 0 ? 1 : 0 );
