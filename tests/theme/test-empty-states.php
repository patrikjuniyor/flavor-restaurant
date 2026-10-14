<?php
/**
 * Empty-state and network-error contract tests (prompt 59).
 *
 * Every data-driven view owes the visitor two designed answers: what the
 * screen says when there is nothing to show (icon, title, description and a
 * CTA — the pattern mobile/lib/ui/common/empty_state.dart established), and
 * what it says when the network failed (a message plus one press that tries
 * again). Before this prompt the web theme had the pattern in six places but
 * the menu empty state had no CTA, the wishlist panel had a bare sentence,
 * the native Woo orders notice was untranslated chrome, and four fetch
 * failures (tracking, live search, reservation loads, the cart boot) were
 * dead ends with no retry. On mobile the ErrorView widget existed with a
 * «تلاش مجدد» button but NO screen used it, five EmptyStates had no CTA,
 * and two providers kept a stale error after a successful reload.
 *
 * This gate pins the whole contract statically: the shared pattern in every
 * rendered empty block, each retry button's template↔script wiring in both
 * directions, the live menu empty toggle, the Woo hook and its CSS
 * deduplication, the mobile imports/branches/CTAs, the provider error
 * clearing, and the translation catalogue entries the new copy needs.
 *
 *   php tests/theme/test-empty-states.php
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

echo PHP_EOL . '--- 1. Every rendered empty block follows the pattern ---' . PHP_EOL;

// icon + heading + description + CTA, exactly like empty_state.dart:
// Icon / title (bold) / description / optional-but-present action button.
$empty_files = array(
	'flavor/template-parts/menu/item-card.php',
	'flavor/template-parts/menu/cart-drawer.php',
	'flavor/template-parts/branch/branch-selector.php',
	'flavor/template-parts/reservation/reservation-form.php',
	'flavor/search.php',
	'flavor/inc/class-customer-ui.php',
);
$blocks = 0;
foreach ( $empty_files as $relative ) {
	$source = read( $flavor_dir . '/' . str_replace( 'flavor/', '', $relative ) );
	if ( ! preg_match_all( '/<div class="flavor-ui-empty[^"]*"[^>]*>(.*?)<\/div>/s', $source, $matches ) ) {
		continue;
	}
	foreach ( $matches[1] as $block ) {
		++$blocks;
		$label = basename( $relative );
		check( false !== strpos( $block, 'icon(' ), $label . ': empty block carries an icon' );
		check( 1 === preg_match( '/<h[23]>/', $block ), $label . ': empty block has a title heading' );
		check( false !== strpos( $block, '<p>' ), $label . ': empty block has a description' );
		check( false !== strpos( $block, 'flavor-btn' ), $label . ': empty block has a CTA button' );
	}
}
check( 8 === $blocks, 'the theme renders exactly eight designed empty blocks (menu, cart, branches, reservation ×2, search, dashboard, orders list); found ' . $blocks );

$item_card = read( $flavor_dir . '/template-parts/menu/item-card.php' );
check( false !== strpos( $item_card, 'راه‌های تماس' ) && false !== strpos( $item_card, "UI::url( 'contact' )" ), 'the menu empty state CTA is the contact route (it used to be prose only)' );
check( false !== strpos( $item_card, "\$flavor_menu_empty_attr = \$products ? ' hidden' : '';" ), 'the menu empty block ships in the DOM always, hidden only while products exist' );

$reservation_form = read( $flavor_dir . '/template-parts/reservation/reservation-form.php' );
check( false !== strpos( $reservation_form, "<noscript><div class=\"flavor-ui-empty\"><?php \\Flavor\\UI::icon( 'calendar', 42 ); ?>" ), 'the noscript reservation state carries the pattern icon too' );

$cart_drawer = read( $flavor_dir . '/template-parts/menu/cart-drawer.php' );
check( false !== strpos( $cart_drawer, 'data-ui-cart-close' ) && false !== strpos( $cart_drawer, 'برگشت به منو' ), 'the empty cart CTA returns the visitor to the menu' );

echo PHP_EOL . '--- 2. The wishlist panel follows the pattern ---' . PHP_EOL;

$wishlist  = read( $flavor_dir . '/inc/class-wishlist.php' );
$premium   = read( $flavor_dir . '/assets/js/premium.js' );
check( false !== strpos( $wishlist, '<div class="flavor-wish__empty" id="flavor-wish-empty">' ), 'the wishlist empty state is a div block, not one bare sentence in a <p>' );
check(
	1 === preg_match( '/<div class="flavor-wish__empty" id="flavor-wish-empty">.*?UI::icon\( \'heart\', 34 \).*?<h3>.*?هنوز غذایی را ذخیره نکرده‌اید\..*?<\/h3><p>.*?با زدن قلب روی هر غذا، اینجا نگه‌داری می‌شود\..*?<\/p><a class="flavor-btn flavor-btn--primary".*?data-wishlist-close/s', $wishlist ),
	'wishlist empty: icon + title + description + CTA that also closes the panel'
);
check( false !== strpos( $wishlist, "'empty'    => __( 'هنوز غذایی را ذخیره نکرده‌اید.', 'flavor' )" ), "the panel title is the strings() 'empty' entry the screen reader status shares" );
check( false === strpos( $wishlist, 'هنوز غذایی را ذخیره نکرده‌اید. با زدن قلب روی هر غذا' ), 'the old fused sentence is gone from the panel' );
check( false !== strpos( $premium, 'empty.hidden = list_.length > 0' ), 'premium.js still toggles the same #flavor-wish-empty element' );

echo PHP_EOL . '--- 3. The native orders list gets the designed state ---' . PHP_EOL;

$customer_ui  = read( $flavor_dir . '/inc/class-customer-ui.php' );
$ui_account   = read( $flavor_dir . '/assets/css/ui-account.css' );
check( false !== strpos( $customer_ui, "add_action( 'woocommerce_before_account_orders', array( self::class, 'orders_empty' ), 5 );" ), 'Customer_UI hooks the orders endpoint' );
check( false !== strpos( $customer_ui, 'public static function orders_empty( $has_orders ): void {' ), 'orders_empty() exists' );
check( 1 === preg_match( '/orders_empty\( \$has_orders \): void \{\s*if \( \$has_orders \) \{\s*return; \}/', $customer_ui ), 'orders_empty() renders only when the endpoint found no orders' );
check(
	false !== strpos( $customer_ui, 'flavor-orders__empty' )
	&& false !== strpos( $customer_ui, 'هنوز سفارشی به این حساب متصل نیست.' )
	&& false !== strpos( $customer_ui, "UI::url( 'menu' )" ),
	'the orders empty state is the shared pattern with the menu CTA'
);
check( false !== strpos( $ui_account, '.flavor-ui-account .flavor-ui-empty~.woocommerce-Message--info{display:none}' ), 'the native notice hides whenever the designed state precedes it' );
check( false !== strpos( $customer_ui, "'Browse products' => 'دیدن منو'," ), 'the native fallback button is translated too (no-CSS path)' );

echo PHP_EOL . '--- 4. Every fetch failure offers one press to retry ---' . PHP_EOL;

// Menu — the wiring prompt 59 replicates everywhere else.
$menu_js = read( $flavor_dir . '/assets/js/menu.js' );
check( false !== strpos( $item_card, 'id="flavor-menu-retry"' ) && false !== strpos( $item_card, 'id="flavor-menu-reload"' ), 'the menu template ships the retry shell' );
check( false !== strpos( $menu_js, "getElementById('flavor-menu-retry')" ) && false !== strpos( $menu_js, "getElementById('flavor-menu-reload')" ), 'menu.js finds both halves' );
check( false !== strpos( $menu_js, "reload.addEventListener('click', loadMenu)" ), 'the menu retry re-runs the load' );
check( false !== strpos( $menu_js, "getElementById('flavor-menu-empty'); if (menuEmpty) menuEmpty.hidden = catalog.length > 0" ), 'menu.js toggles the designed empty state from the live catalogue' );

// Tracking.
$tracking_tpl = read( $flavor_dir . '/page-templates/template-order-tracking.php' );
$tracking_js  = read( $flavor_dir . '/assets/js/tracking.js' );
check( false !== strpos( $tracking_tpl, 'data-ui-track-retry hidden' ) && false !== strpos( $tracking_tpl, 'تلاش دوباره' ), 'the tracking form ships a server-rendered retry button' );
check( false !== strpos( $tracking_js, "form.querySelector('[data-ui-track-retry]')" ), 'tracking.js finds the retry button' );
check( false !== strpos( $tracking_js, 'error.textContent=problem.message;if(retry)retry.hidden=false;' ), 'only the network catch reveals it — validation failures do not' );
check( false !== strpos( $tracking_js, "retry.addEventListener('click',function(){form.requestSubmit();})" ), 'the tracking retry repeats the same lookup' );
check( substr_count( $tracking_js, 'if(retry)retry.hidden=true;' ) >= 2, 'typing or resubmitting hides the tracking retry again' );

// Live search.
$smart_search = read( $flavor_dir . '/template-parts/menu/smart-search.php' );
$search_js    = read( $flavor_dir . '/assets/js/search.js' );
check( false !== strpos( $smart_search, 'id="flavor-search-retry-row"' ) && false !== strpos( $smart_search, 'id="flavor-search-retry"' ), 'the search panel ships a server-rendered retry row' );
check( false !== strpos( $search_js, "getElementById('flavor-search-retry-row')" ), 'search.js finds the retry row' );
check( false !== strpos( $search_js, 'retryRow.hidden = false;' ), 'the search failure reveals the retry row' );
check( false !== strpos( $search_js, 'if (lastQuery) {' ) && false !== strpos( $search_js, 'run(lastQuery);' ), 'the search retry repeats the last query' );
check( false !== strpos( $search_js, "retryRow.hidden = true;" ), 'a new run hides the retry row again' );

// Reservation loads.
$res_js = read( $flavor_dir . '/assets/js/reservation.js' );
check( false !== strpos( $reservation_form, 'id="flavor-res-retry" hidden' ), 'the reservation form ships a server-rendered retry button' );
check( false !== strpos( $res_js, 'function bootData() {' ) && false !== strpos( $res_js, "\tbootData();" ), 'the reservation availability boot is a re-runnable function' );
check( false !== strpos( $res_js, "var retry = document.getElementById('flavor-res-retry');\n\t\tif (retry) { retry.hidden = false; }" ), 'reportError — every load-path failure — reveals the retry' );
check( false !== strpos( $res_js, 'resRetry.addEventListener' ) && false !== strpos( $res_js, "if (host) { host.hidden = true; host.textContent = ''; }\n\t\t\tbootData();" ), 'the reservation retry clears the error and re-boots availability' );
check( false !== strpos( $res_js, 'if (retry) { retry.hidden = true; }' ), 'submitting hides the reservation retry' );

// Cart boot.
check( false !== strpos( $cart_drawer, 'id="flavor-cart-retry" hidden' ), 'the cart drawer ships a server-rendered retry button' );
check( false !== strpos( $menu_js, "var retry = document.getElementById('flavor-cart-retry'); if (retry) retry.hidden = !message;" ), 'setCartError toggles the cart retry with the alert' );
check( false !== strpos( $menu_js, 'function bootCart() {' ) && false !== strpos( $menu_js, "cartRetry.addEventListener('click', bootCart);" ) && false !== strpos( $menu_js, "\tbootCart();" ), 'the cart boot is a re-runnable function wired to the retry' );
check( false !== strpos( $menu_js, "setCartError('');" ), 'a recovered boot clears the cart error and its retry' );

echo PHP_EOL . '--- 5. Retry buttons are server-rendered, scripts add no copy ---' . PHP_EOL;

foreach ( array( 'tracking.js', 'search.js', 'reservation.js', 'menu.js' ) as $script ) {
	$source = read( $flavor_dir . '/assets/js/' . $script );
	check( false === strpos( $source, 'تلاش دوباره' ), $script . ' carries no retry label of its own (the template owns the string)' );
}
check( substr_count( $item_card, 'تلاش دوباره' ) + substr_count( $tracking_tpl, 'تلاش دوباره' ) + substr_count( $smart_search, 'تلاش دوباره' ) + substr_count( $reservation_form, 'تلاش دوباره' ) + substr_count( $cart_drawer, 'تلاش دوباره' ) === 5, 'all five retry buttons print the same translated label' );

echo PHP_EOL . '--- 6. Mobile screens wire ErrorView and CTA-bearing EmptyStates ---' . PHP_EOL;

$error_view = read( $mobile_dir . '/lib/ui/common/error_view.dart' );
check( false !== strpos( $error_view, 'تلاش مجدد' ) && false !== strpos( $error_view, 'final VoidCallback? onRetry;' ), 'the shared ErrorView still offers the retry press' );

$error_screens = array(
	'lib/ui/screens/search/search_screen.dart',
	'lib/ui/screens/orders/orders_screen.dart',
	'lib/ui/screens/reservation/reservation_history_screen.dart',
	'lib/ui/screens/menu/menu_screen.dart',
	'lib/ui/screens/cart/cart_screen.dart',
);
foreach ( $error_screens as $relative ) {
	$source = read( $mobile_dir . '/' . $relative );
	$label  = basename( $relative );
	check( false !== strpos( $source, "import '../../common/error_view.dart';" ), $label . ' imports the error view' );
	check( false !== strpos( $source, 'ErrorView(' ), $label . ' renders the error view' );
	check( false !== strpos( $source, 'onRetry:' ), $label . ' wires the retry callback' );
}

$cta_screens = array(
	'lib/ui/screens/search/search_screen.dart'            => 'دیدن منوی کامل',
	'lib/ui/screens/orders/orders_screen.dart'            => 'مشاهده منو',
	'lib/ui/screens/reservation/reservation_history_screen.dart' => 'رزرو میز جدید',
	'lib/ui/screens/menu/menu_screen.dart'                => 'دیدن همهٔ غذاها',
	'lib/ui/screens/cart/cart_screen.dart'                => 'مشاهده منوی رستوران',
	'lib/ui/screens/profile/favorites_screen.dart'        => 'مشاهده منوی رستوران',
	'lib/ui/screens/notifications/notifications_screen.dart' => 'مشاهده منوی رستوران',
);
foreach ( $cta_screens as $relative => $cta ) {
	$source = read( $mobile_dir . '/' . $relative );
	check( false !== strpos( $source, "buttonText: '" . $cta . "'" ), basename( $relative ) . ': the empty state carries the «' . $cta . '» CTA' );
}

$search_screen = read( $mobile_dir . '/lib/ui/screens/search/search_screen.dart' );
check( false !== strpos( $search_screen, 'bool _hasError = false;' ) && false !== strpos( $search_screen, '_hasError = true;' ), 'a failed mobile search stops masquerading as "no results"' );
check( false !== strpos( $search_screen, 'onRetry: () => _handleSearch(_lastQuery),' ), 'the mobile search retry repeats the same query' );

$cart_screen = read( $mobile_dir . '/lib/ui/screens/cart/cart_screen.dart' );
check( false !== strpos( $cart_screen, 'cart.errorMessage != null && cart.isEmpty && !cart.isLoading' ), 'a failed cart load is checked before the empty-cart branch' );

echo PHP_EOL . '--- 7. Providers clear stale errors so a retry can succeed ---' . PHP_EOL;

$reservation_state = read( $mobile_dir . '/lib/state/reservation_state.dart' );
check(
	1 === preg_match( '/loadMyReservations\(\) async \{[^}]*?_errorMessage = null;/s', $reservation_state ),
	'loadMyReservations() clears the previous failure'
);
$menu_state = read( $mobile_dir . '/lib/state/menu_state.dart' );
check(
	1 === preg_match( '/selectCategory\(int categoryId, int branchId\) async \{[^}]*?_errorMessage = null;/s', $menu_state ),
	'selectCategory() clears the previous failure (retry and pull-to-refresh paths)'
);
$order_state = read( $mobile_dir . '/lib/state/order_state.dart' );
check( 1 === preg_match( '/loadOrders\(\{int page = 1\}\) async \{[^}]*?_errorMessage = null;/s', $order_state ), 'loadOrders() clears the previous failure' );
$cart_state = read( $mobile_dir . '/lib/state/cart_state.dart' );
check( 1 === preg_match( '/loadCart\(\) async \{[^}]*?_errorMessage = null;/s', $cart_state ), 'loadCart() clears the previous failure' );

echo PHP_EOL . '--- 8. Styles and the translation catalogue ---' . PHP_EOL;

$ui_css     = read( $flavor_dir . '/assets/css/ui.css' );
$premium_cs = read( $flavor_dir . '/assets/css/premium.css' );
$search_css = read( $flavor_dir . '/assets/css/search.css' );
check( false !== strpos( $ui_css, '.flavor-ui .flavor-ui-empty{text-align:center;' ), 'the shared empty-state rules are intact' );
check( false !== strpos( $ui_css, '.flavor-ui #flavor-cart-retry,.flavor-ui #flavor-res-retry,.flavor-ui [data-ui-track-retry]{margin-top:12px}' ), 'retry buttons keep one step of distance from their alert host' );
check( false !== strpos( $premium_cs, '.flavor-wish__empty h3 {' ) && false !== strpos( $premium_cs, 'color: var(--flavor-ink);' ), 'the wishlist title reads in the ink token (night-safe)' );
check( false !== strpos( $premium_cs, '.flavor-wish__empty p {' ), 'the wishlist description keeps its own rule' );
check( false !== strpos( $search_css, '.flavor-search__retry {' ), 'the search retry row has layout' );

$pot = read( $flavor_dir . '/languages/flavor.pot' );
check( false !== strpos( $pot, 'msgid "با زدن قلب روی هر غذا، اینجا نگه‌داری می‌شود."' ), 'the new wishlist description is in the catalogue' );
check( false === strpos( $pot, 'هنوز غذایی را ذخیره نکرده‌اید. با زدن قلب روی هر غذا' ), 'the fused wishlist sentence left the catalogue' );
check( false !== strpos( $pot, 'msgid "تلاش دوباره"' ), 'the shared retry label is translatable' );
check( false !== strpos( $pot, 'msgid "راه‌های تماس"' ), 'the menu empty CTA is translatable' );
$po = read( $flavor_dir . '/languages/flavor-ar.po' );
check(
	1 === preg_match( '/msgid "با زدن قلب روی هر غذا، اینجا نگه‌داری می‌شود\."\nmsgstr "[^"]+"/u', $po ),
	'the Arabic catalogue answers the new wishlist description'
);

echo PHP_EOL . '=======================================================' . PHP_EOL;
echo "Results: {$passed} Passed, {$failed} Failed" . PHP_EOL;
echo '=======================================================' . PHP_EOL;

exit( $failed > 0 ? 1 : 0 );
