<?php
/**
 * Loading-state contract tests (prompt 60).
 *
 * Two promises this gate pins:
 *
 * 1. While data is in flight the visitor sees a designed placeholder, not a
 *    blank hole. Mobile already had this (loading_shimmer.dart feeds
 *    LoadingShimmer.dishGrid into the menu and home screens); the web theme
 *    only had a background sweep on the aria-busy grid and an empty results
 *    list during a first search. The web now mirrors the mobile pattern:
 *    skeleton dish cards while the live catalogue loads, skeleton category
 *    chips until the tabs render, skeleton result rows during an uncached
 *    live query — all aria-hidden decoration, all stopped under
 *    prefers-reduced-motion.
 *
 * 2. A sending button locks while it sends. Every AJAX submit on the web
 *    disables its button AND marks it aria-busy for the exact lifetime of
 *    the request (set on start, removed in a finally), so a double click
 *    cannot fire two requests; server-side idempotency remains the
 *    backstop. The newsletter form had no guard at all before this prompt.
 *
 * Skeletons carry no user-facing strings (they are decoration), so the
 * translation catalogues are untouched by design — the gate pins that too.
 *
 *   php tests/theme/test-loading-states.php
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

$flavor_dir = FLAVOR_DIR;
$root_dir   = dirname( FLAVOR_DIR );
$mobile_dir = $root_dir . '/mobile';

/**
 * Read a file or fail loudly.
 *
 * @param string $path File.
 * @return string
 */
function read( string $path ): string {
	$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local gate on repo files.
	if ( false === $contents ) {
		echo '  [FAIL] missing file: ' . $path . PHP_EOL;
		exit( 1 );
	}
	return $contents;
}

$ui_css     = read( $flavor_dir . '/assets/css/ui.css' );
$menu_css   = read( $flavor_dir . '/assets/css/ui-menu.css' );
$search_css = read( $flavor_dir . '/assets/css/search.css' );
$menu_js    = read( $flavor_dir . '/assets/js/menu.js' );
$search_js  = read( $flavor_dir . '/assets/js/search.js' );

echo PHP_EOL . '--- 1. The skeleton primitive lives in ui.css ---' . PHP_EOL;

check(
	false !== strpos( $ui_css, '@keyframes flavor-skel-shimmer{0%{background-position:150% 0}100%{background-position:-50% 0}}' ),
	'ui.css defines its own flavor-skel-shimmer keyframes (no cross-file dependency on ui-menu.css)'
);
check(
	false !== strpos( $ui_css, '.flavor-skel{display:block;background:linear-gradient(110deg,var(--flavor-surface-alt) 25%' ),
	'.flavor-skel sweeps a highlight over the muted surface-alt token'
);
check(
	false !== strpos( $ui_css, 'color-mix(in srgb,var(--flavor-line) 55%,var(--flavor-surface-alt)) 45%' ),
	'the sweep highlight is computed from tokens, so night mode inherits it for free'
);
check(
	false !== strpos( $ui_css, 'background-size:200% 100%;animation:flavor-skel-shimmer 1.4s linear infinite' ),
	'the primitive animates the oversized gradient (same 1.4s cadence as the menu sweep)'
);
check(
	false !== strpos( $ui_css, 'border-radius:calc(var(--flavor-radius) / 2)' ),
	'skeleton blocks inherit half the surface radius'
);
foreach ( array( 'w40' => '40%', 'w60' => '60%', 'w70' => '70%', 'w92' => '92%' ) as $mod => $width ) {
	check(
		false !== strpos( $ui_css, '.flavor-skel--' . $mod . '{width:' . $width . '}' ),
		'.flavor-skel--' . $mod . ' sizes text lines to ' . $width
	);
}
foreach ( array( 'h12' => '12px', 'h16' => '16px', 'h18' => '18px' ) as $mod => $height ) {
	check(
		false !== strpos( $ui_css, '.flavor-skel--' . $mod . '{height:' . $height . '}' ),
		'.flavor-skel--' . $mod . ' sets the ' . $height . ' line height'
	);
}
check(
	false !== strpos( $ui_css, '@media(prefers-reduced-motion:reduce){.flavor-skel{animation:none;background:var(--flavor-surface-alt)}}' ),
	'reduced motion stops the sweep and leaves a flat muted block (covers skeletons outside .flavor-ui too)'
);

echo PHP_EOL . '--- 2. Menu skeletons match the real card and chip anatomy ---' . PHP_EOL;

check(
	false !== strpos( $menu_css, '.flavor-ui .flavor-food-card.is-skeleton {' ) && false !== strpos( $menu_css, 'pointer-events: none;' ),
	'skeleton dish cards swallow no clicks'
);
check(
	false !== strpos( $menu_css, '.flavor-ui .flavor-food-card.is-skeleton .flavor-food-card__media.flavor-skel {' ) && false !== strpos( $menu_css, 'border-radius: 0;' ),
	'the skeleton media block squares off like the real card media (the card clips it)'
);
check(
	false !== strpos( $menu_css, '.flavor-ui .flavor-food-card.is-skeleton .flavor-food-card__body {' ) && false !== strpos( $menu_css, 'gap: 12px;' ),
	'the skeleton body stacks its lines with breathing room'
);
check(
	false !== strpos( $menu_css, '.flavor-ui .flavor-cats .flavor-cat-skel {' ),
	'the category chip skeleton is scoped to the cats scroller'
);
check(
	false !== strpos( $menu_css, 'width: 96px;' ) && false !== strpos( $menu_css, 'min-height: 44px;' ) && false !== strpos( $menu_css, 'border-radius: var(--flavor-btn-radius);' ),
	'skeleton chips keep the real chip footprint (44px touch target, button radius) so tabs do not jump the layout'
);

echo PHP_EOL . '--- 3. Search skeletons match the real row anatomy ---' . PHP_EOL;

check(
	false !== strpos( $search_css, '.flavor-search__row.is-skeleton {' ) && false !== strpos( $search_css, 'cursor: default;' ),
	'skeleton result rows are inert (no pointer, no hover affordance)'
);
check(
	1 === preg_match( '/\.flavor-search__row\.is-skeleton:hover\s*\{\s*background:\s*transparent;\s*\}/', $search_css ),
	'skeleton rows do not light up on hover like real options'
);
check(
	1 === preg_match( '/\.flavor-search__row\.is-skeleton \.flavor-search__body\s*\{\s*gap:\s*8px;\s*\}/', $search_css ),
	'the skeleton row body separates the name and meta lines'
);
check(
	1 === preg_match( '/\.flavor-search__row\.is-skeleton \.flavor-search__thumb-ph\.flavor-skel\s*\{\s*border-radius:\s*12px;\s*\}/', $search_css ),
	'the skeleton thumb keeps the real 12px thumbnail radius'
);
check(
	false !== strpos( $search_css, '.flavor-skel--price {' ) && false !== strpos( $search_css, 'width: 52px;' ),
	'the skeleton price block reserves the trailing column'
);

echo PHP_EOL . '--- 4. menu.js paints skeletons while the catalogue and cats load ---' . PHP_EOL;

check( false !== strpos( $menu_js, 'function skeletonCards() {' ), 'menu.js builds its skeleton cards in one place' );
check(
	1 === preg_match( '/function skeletonCards\(\) \{(.*?)\n\t\}/s', $menu_js, $skel_cards ) && false !== strpos( $skel_cards[1], 'aria-hidden="true"' ),
	'every skeleton card is aria-hidden decoration'
);
check(
	false !== strpos( $skel_cards[1], '<article class="flavor-card flavor-food-card is-skeleton"' ),
	'skeleton cards reuse the real card classes so grid sizing is identical'
);
check(
	false !== strpos( $skel_cards[1], 'flavor-food-card__media flavor-skel' ) && false !== strpos( $skel_cards[1], 'flavor-skel--w60 flavor-skel--h16' ) && false !== strpos( $skel_cards[1], 'flavor-skel--w40 flavor-skel--h18' ),
	'the card skeleton mirrors the anatomy: media block, title line, description lines, footer line'
);
check( false !== strpos( $skel_cards[1], 'i < 6' ), 'six placeholder cards fill the grid (a full row pair on wide screens)' );
check(
	false !== strpos( $menu_js, "if (!grid.querySelector('.flavor-food-card:not(.is-skeleton)')) grid.innerHTML = skeletonCards();" ),
	'skeletons appear only when the grid has no server-rendered fallback cards to keep'
);
check( false !== strpos( $menu_js, 'function skeletonChips() {' ), 'menu.js builds its skeleton category chips in one place' );
check(
	1 === preg_match( '/function skeletonChips\(\) \{(.*?)\n\t\}/s', $menu_js, $skel_chips ) && false !== strpos( $skel_chips[1], 'aria-hidden="true"' ) && false !== strpos( $skel_chips[1], 'i < 5' ),
	'five aria-hidden chips stand in for the tab row'
);
check(
	false !== strpos( $menu_js, 'if (catsEl && catsNav && !catsEl.children.length) { catsEl.innerHTML = skeletonChips(); catsNav.hidden = false; }' ),
	'loadMenu reveals the cat bar with chips while the categories request is in flight'
);
check( false !== strpos( $menu_js, 'function clearCatSkeleton() {' ), 'a helper removes the chip skeletons and re-hides the bar' );
check(
	1 === preg_match( '/function clearCatSkeleton\(\) \{(.*?)\n\t\}/s', $menu_js, $clear_cats ) && false !== strpos( $clear_cats[1], "catsEl.querySelector('.flavor-cat-skel')" ) && false !== strpos( $clear_cats[1], 'catsNav.hidden = true' ),
	'the cleanup only fires while chips are still skeletons and hides the nav again'
);
check(
	false !== strpos( $menu_js, "grid.querySelectorAll('.flavor-food-card.is-skeleton').forEach(function (el) { el.remove(); });" ),
	'a failed menu load strips the skeleton cards instead of leaving them spinning forever'
);
check(
	false !== strpos( $menu_js, '.catch(function (error) { clearCatSkeleton(); showError(error); });' ),
	'a failed categories load also clears the chip skeletons'
);
check(
	substr_count( $menu_js, 'clearCatSkeleton()' ) >= 3,
	'clearCatSkeleton is defined once and called from both failure paths'
);

echo PHP_EOL . '--- 5. search.js shows skeleton rows during an uncached query ---' . PHP_EOL;

check(
	1 === preg_match( '/function busy\(on\) \{(.*?)\n\t\}/s', $search_js, $busy_fn ),
	'search.js busy() is the single in-flight switch'
);
check(
	false !== strpos( $busy_fn[1], 'if (!list.children.length) {' ) && false !== strpos( $busy_fn[1], 'list.innerHTML = skeletonRows();' ),
	'skeletons are injected only when the list would otherwise stand empty (previous results never flicker away)'
);
check(
	false !== strpos( $busy_fn[1], 'panel.hidden = false;' ) && false !== strpos( $busy_fn[1], "input.setAttribute('aria-expanded', 'true');" ),
	'the first query opens the panel around its skeletons and keeps the combobox contract honest'
);
check(
	false !== strpos( $busy_fn[1], "list.setAttribute('aria-busy', 'true');" ) && false !== strpos( $busy_fn[1], "list.removeAttribute('aria-busy');" ),
	'the listbox carries aria-busy for the exact lifetime of the request'
);
check( false !== strpos( $search_js, 'function skeletonRows() {' ), 'search.js builds its skeleton rows in one place' );
check(
	1 === preg_match( '/function skeletonRows\(\) \{(.*?)\n\t\}/s', $search_js, $skel_rows ) && false !== strpos( $skel_rows[1], 'aria-hidden="true"' ),
	'every skeleton row is aria-hidden — the listbox never exposes placeholders as options'
);
check(
	false !== strpos( $skel_rows[1], '<li class="flavor-search__row is-skeleton"' ),
	'skeleton rows reuse the real row class so list padding and dividers match'
);
check(
	false !== strpos( $skel_rows[1], 'flavor-search__thumb-ph flavor-skel' ) && false !== strpos( $skel_rows[1], 'flavor-skel--w60 flavor-skel--h16' ) && false !== strpos( $skel_rows[1], 'flavor-skel--price flavor-skel--h16' ),
	'the row skeleton mirrors the anatomy: thumb, name line, meta line, price block'
);
check( false !== strpos( $skel_rows[1], 'i < 4' ), 'four placeholder rows cover the visible panel height' );
check(
	false !== strpos( $search_js, "busy(false);\n\t\t\t\tlist.innerHTML = '';" ),
	'a failed query clears the skeletons before offering the retry row'
);

echo PHP_EOL . '--- 6. Every sending button locks: disabled + aria-busy, both directions ---' . PHP_EOL;

$reservation_js = read( $flavor_dir . '/assets/js/reservation.js' );
check(
	false !== strpos( $reservation_js, 'if (submit) { submit.disabled = true; submit.setAttribute(\'aria-busy\', \'true\'); }' ),
	'reservation: the submit button disables and marks itself busy when the booking sends'
);
check(
	false !== strpos( $reservation_js, 'if (submit) { submit.disabled = false; submit.removeAttribute(\'aria-busy\'); }' ),
	'reservation: the finally handler releases the button on success AND failure'
);

$tracking_js = read( $flavor_dir . '/assets/js/tracking.js' );
check(
	false !== strpos( $tracking_js, "button.disabled=true;button.setAttribute('aria-busy','true');form.setAttribute('aria-busy','true');" ),
	'tracking: the lookup button disables and marks itself busy alongside the form'
);
check(
	false !== strpos( $tracking_js, "button.disabled=false;button.removeAttribute('aria-busy');form.removeAttribute('aria-busy');" ),
	'tracking: the finally handler releases button and form together'
);

$contact_js = read( $flavor_dir . '/assets/js/contact.js' );
check(
	false !== strpos( $contact_js, "submit.disabled = true;" ) && false !== strpos( $contact_js, "submit.setAttribute('aria-busy', 'true');" ),
	'contact: the send button disables and marks itself busy'
);
check(
	false !== strpos( $contact_js, "submit.disabled = false;" ) && false !== strpos( $contact_js, "submit.removeAttribute('aria-busy');" ),
	'contact: the finally handler releases the button'
);

check(
	false !== strpos( $menu_js, "if (button) { button.setAttribute('aria-busy', 'true'); button.textContent = t('placing'); }" ),
	'checkout: the place-order button marks itself busy while the order sends'
);
check(
	false !== strpos( $menu_js, "if (button) { button.removeAttribute('aria-busy'); button.textContent = t('place_order'); }" ),
	'checkout: the finally handler releases the place-order button'
);
check(
	false !== strpos( $menu_js, 'sheetAdd.disabled = busy || current.available === false || !sheetSelectionValid();' )
		&& false !== strpos( $menu_js, "if (busy) { sheetAdd.setAttribute('aria-busy', 'true'); } else { sheetAdd.removeAttribute('aria-busy'); }" ),
	'the product-sheet add/save button mirrors its busy flag into aria-busy'
);

$auth_js = read( $flavor_dir . '/assets/js/auth.js' );
check(
	false !== strpos( $auth_js, "busy=true;send.disabled=true;send.setAttribute('aria-busy','true');" ),
	'auth: the OTP send button disables and marks itself busy'
);
check(
	false !== strpos( $auth_js, "busy=false;send.removeAttribute('aria-busy');" ),
	'auth: the send button releases in the finally handler'
);
check(
	false !== strpos( $auth_js, "busy=true;verify.disabled=true;verify.setAttribute('aria-busy','true');" ),
	'auth: the OTP verify button disables and marks itself busy'
);
check(
	false !== strpos( $auth_js, "busy=false;verify.removeAttribute('aria-busy');" ),
	'auth: the verify button releases in the finally handler'
);

$demos_js = read( $flavor_dir . '/assets/js/bespoke-demos.js' );
check(
	false !== strpos( $demos_js, "button.disabled = true; button.setAttribute('aria-busy', 'true');" ),
	'coverage demo: the zone-check button disables and marks itself busy'
);
check(
	false !== strpos( $demos_js, "button.disabled = false; button.removeAttribute('aria-busy');" ),
	'coverage demo: the finally handler releases the button'
);

$premium_js = read( $flavor_dir . '/assets/js/premium.js' );
check(
	false !== strpos( $premium_js, "var button = form.querySelector('[type=\"submit\"]');\n\t\t\tif (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }" ),
	'newsletter: the subscribe button locks (it had NO guard before this prompt)'
);
$guard_pos = strpos( $premium_js, "if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }" );
$fetch_pos = strpos( $premium_js, 'fetch(cfg.ajax' );
check(
	false !== $guard_pos && false !== $fetch_pos && $guard_pos < $fetch_pos,
	'newsletter: the lock happens BEFORE the request fires, so the second click of a double click meets a disabled button'
);
check(
	false !== strpos( $premium_js, ".finally(function () { if (button) { button.disabled = false; button.removeAttribute('aria-busy'); } });" ),
	'newsletter: the finally handler releases the button on success AND failure'
);
check(
	false !== strpos( $premium_js, "if (!email || email.indexOf('@') < 1) {" ) && $guard_pos > strpos( $premium_js, "say(t('email_invalid'), true);" ),
	'newsletter: validation failures return without ever locking the button'
);

echo PHP_EOL . '--- 7. Skeletons are decoration: no strings, no translation surface ---' . PHP_EOL;

foreach ( array( 'skel_cards' => $skel_cards[1], 'skel_chips' => $skel_chips[1], 'skel_rows' => $skel_rows[1] ) as $label => $body ) {
	check( false === strpos( $body, 't(' ), $label . ' calls no t() — placeholders carry no copy' );
	check( 0 === preg_match( '/[\x{0600}-\x{06FF}]/u', $body ), $label . ' contains no Persian literals (the catalogues stay untouched)' );
}

echo PHP_EOL . '--- 8. Mobile parity: the reference implementation stays pinned ---' . PHP_EOL;

$flavor_button = read( $mobile_dir . '/lib/ui/common/flavor_button.dart' );
check(
	false !== strpos( $flavor_button, 'onTap: isLoading ? null : onPressed' ),
	'FlavorButton still refuses taps while loading (the pattern the web buttons now mirror)'
);
check(
	false !== strpos( $flavor_button, 'final bool isLoading;' ) && false !== strpos( $flavor_button, 'if (isLoading) {' ),
	'FlavorButton swaps its label for a spinner while sending'
);
$shimmer_dart = read( $mobile_dir . '/lib/ui/common/loading_shimmer.dart' );
check(
	false !== strpos( $shimmer_dart, 'class LoadingShimmer extends StatelessWidget' ) && false !== strpos( $shimmer_dart, 'Shimmer.fromColors' ),
	'loading_shimmer.dart remains the shimmer source of truth'
);
check(
	false !== strpos( $shimmer_dart, 'static Widget dishGrid' ),
	'the dishGrid helper the web skeletonCards mirrors still exists'
);
$menu_screen = read( $mobile_dir . '/lib/ui/screens/menu/menu_screen.dart' );
check(
	false !== strpos( $menu_screen, 'LoadingShimmer.dishGrid(count: 4)' ),
	'the mobile menu screen still feeds dishGrid while its catalogue loads'
);
$home_screen = read( $mobile_dir . '/lib/ui/screens/home/home_screen.dart' );
check(
	false !== strpos( $home_screen, 'LoadingShimmer' ),
	'the mobile home screen still uses the shimmer placeholders'
);

echo PHP_EOL . '=======================================================' . PHP_EOL;
echo "Results: {$passed} Passed, {$failed} Failed" . PHP_EOL;
echo '=======================================================' . PHP_EOL;

exit( $failed > 0 ? 1 : 0 );
