<?php
/**
 * Single-product page contract tests (R3-01, decision «ب»).
 *
 * The product page is the surface Elementor Pro's Theme Builder would otherwise
 * own, and it is also where the restaurant's modifier data was invisible: Flavor
 * Core stores the option groups on the product and reads a posted selection back
 * in `add_cart_item_data()`, but the only UI for that data was the sheet in
 * menu.js. A customer landing on a product page from a search result or a
 * category grid could therefore order a dish whose receipt lists additions they
 * never chose.
 *
 * These checks run offline, against the Flavor Core mock environment and the
 * real ProductModifiers class, so the theme/plugin field contract is exercised
 * rather than asserted: the ids are scraped out of the rendered HTML and posted
 * into the plugin's own filter.
 *
 *   php tests/theme/test-product-page.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-shopping-extras.php';
require_once FLAVOR_DIR . '/inc/class-product-page.php';

/* Page-state doubles live in a global-namespace fixture; see its header. */
require_once __DIR__ . '/fixtures/product-page-stubs.php';

use Flavor\Product_Page;
use Flavor\UI;

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
 * Capture whatever a callback prints.
 *
 * @param callable $callback Callback to run.
 * @return string
 */
function capture( callable $callback ): string {
	ob_start();
	$callback();
	return (string) ob_get_clean();
}

/**
 * Modifier rows in the shape the plugin's admin panel stores them.
 *
 * @return array<int,array<string,mixed>>
 */
function fixture_rows(): array {
	return array(
		array(
			'id'         => 'size-large',
			'type'       => 'size',
			'name'       => 'پرس بزرگ',
			'price'      => 45000,
			'is_default' => 1,
		),
		array(
			'id'         => 'size-small',
			'type'       => 'size',
			'name'       => 'پرس کوچک',
			'price'      => 0,
			'is_default' => 0,
		),
		array(
			'id'         => 'topping-cheese',
			'type'       => 'topping',
			'name'       => 'پنیر اضافه',
			'price'      => 25000,
			'is_default' => 1,
		),
		array(
			'id'         => 'topping-bacon',
			'type'       => 'topping',
			'name'       => 'بیکن',
			'price'      => 30000,
			'is_default' => 0,
		),
		array(
			'id'         => 'cook-medium',
			'type'       => 'cook',
			'name'       => 'میان‌درجه',
			'price'      => 0,
			'is_default' => 0,
		),
		array(
			'id'         => 'removal-onion',
			'type'       => 'removal',
			'name'       => 'بدون پیاز',
			'price'      => 0,
			'is_default' => 0,
		),
	);
}

/**
 * Pull the checked option ids out of rendered markup, the way a browser would.
 *
 * @param string $html Rendered picker.
 * @return array<int,string>
 */
function checked_ids( string $html ): array {
	$ids = array();
	if ( preg_match_all( '/<input[^>]*name="flavor_modifiers\[ids\]\[\]"[^>]*value="([^"]*)"[^>]*checked="checked"/', $html, $matches ) ) {
		$ids = array_values( array_filter( $matches[1], static fn( string $id ): bool => '' !== $id ) );
	}
	return $ids;
}

echo "=== Flavor single-product page tests ===\n\n";

echo "--- 1. A site without Flavor Core renders nothing ---\n";

/*
 * This section has to run before the plugin class is loaded, because that load is
 * what makes the guard's false branch reachable. It is the state of every site
 * that installs the theme and not the plugin: no modifier model, so no picker.
 */
check( ! class_exists( '\FlavorCore\WooCommerce\ProductModifiers' ), 'the plugin modifier class is genuinely absent at this point' );
$GLOBALS['_p_page'] = 'product';
check( ! Product_Page::modifiers_enabled(), 'the picker is off on a product page without Flavor Core' );
check( '' === trim( capture( static fn() => Product_Page::modifiers() ) ), 'the add-to-cart form gains no markup of its own' );
check( array() === Product_Page::groups( 1 ), 'no product id resolves to modifier groups' );

/* Now load the real class the way the plugin would, so the rest of the file runs against it. */
require_once dirname( __DIR__, 2 ) . '/flavor-core/includes/WooCommerce/ProductModifiers.php';

echo "\n--- 2. Off the product page, off we go ---\n";

$GLOBALS['_p_page'] = 'other';
check( ! Product_Page::active(), 'a category archive is not a product page' );
check( ! Product_Page::modifiers_enabled(), 'and carries no picker' );
check( '' === trim( capture( static fn() => Product_Page::modifiers() ) ), 'rendering there prints nothing' );

$GLOBALS['_p_styles'] = array();
Product_Page::assets();
check( ! isset( $GLOBALS['_p_styles']['flavor-product'] ), 'the product stylesheet is not loaded on other screens' );

echo "\n--- 3. The picker posts the fields the plugin already reads ---\n";

$GLOBALS['_p_page'] = 'product';
update_post_meta( 1, '_flavor_modifiers', fixture_rows() );
$GLOBALS['product'] = wc_get_product( 1 );

check( Product_Page::active() && Product_Page::modifiers_enabled(), 'the picker is on for a product page with Flavor Core' );
check( 1 === Product_Page::product_id(), 'the queried product is the one being rendered' );

$html = capture( static fn() => Product_Page::modifiers() );
check( str_contains( $html, 'name="flavor_modifiers[ids][]"' ), 'options post flavor_modifiers[ids][]' );
check( str_contains( $html, 'name="flavor_instructions"' ), 'the kitchen note posts flavor_instructions' );
check( str_contains( $html, 'maxlength="' . Product_Page::NOTE_LIMIT . '"' ), 'the note field carries the length limit' );
check( preg_match( '/<form/', $html ) === 0, 'the picker opens no nested form of its own' );
check( substr_count( $html, '<fieldset' ) === 4, 'one fieldset per modifier type in the fixture' );

echo "\n--- 4. Scraped ids survive the round trip into the cart line ---\n";

$ids = checked_ids( $html );
check( array( 'size-large', 'topping-cheese' ) === $ids, 'the pre-checked selection is the default size and the default topping (' . implode( ',', $ids ) . ')' );

$_REQUEST['flavor_modifiers']    = array( 'ids' => $ids );
$_REQUEST['flavor_instructions'] = 'سس جدا';
$modifiers                       = new \FlavorCore\WooCommerce\ProductModifiers();
$cart_item                       = $modifiers->add_cart_item_data( array(), 1, 0 );
check( isset( $cart_item['flavor_modifiers'] ) && count( $cart_item['flavor_modifiers'] ) === 2, 'the plugin accepts the posted ids as written by the theme' );
check( (int) ( $cart_item['flavor_extra'] ?? -1 ) === 70000, 'the extras the plugin prices match what the form showed (45000 + 25000)' );
check( 'سس جدا' === ( $cart_item['flavor_instructions'] ?? '' ), 'the kitchen note reaches the cart line' );
$_REQUEST['flavor_modifiers'] = array( 'ids' => array( 'size-small', 'topping-bacon' ) );
$other                        = $modifiers->add_cart_item_data( array(), 1, 0 );
check( (int) $other['flavor_extra'] === 30000, 'a different selection prices differently (30000)' );
check( (string) $cart_item['unique_key'] !== (string) $other['unique_key'], 'and becomes a separate cart line rather than a merge');
unset( $_REQUEST['flavor_modifiers'], $_REQUEST['flavor_instructions'] );
$bare = $modifiers->add_cart_item_data( array(), 1, 0 );
check( '' === $bare['flavor_instructions'], 'an absent note reaches the line as an empty string, never a warning' );
check( array() === $bare['flavor_modifiers'], 'and an absent selection as an empty list, so a plain product still adds' );

echo "\n--- 5. The groups follow the rules the menu sheet uses ---\n";

$groups = Product_Page::groups( 1 );
check( array( 'size', 'topping', 'cook', 'removal' ) === array_keys( $groups ), 'groups keep the modifier types the merchant entered' );
check( ! empty( $groups['size']['required'] ) && empty( $groups['topping']['required'] ), 'size is required, toppings are not' );
check( ! empty( $groups['topping']['multi'] ) && empty( $groups['size']['multi'] ), 'toppings are multi-select, size is single' );
check( empty( $groups['removal']['multi'] ) && empty( $groups['cook']['multi'] ), 'cook and removal stay single-select' );

$core = (string) file_get_contents( dirname( __DIR__, 2 ) . '/flavor-core/includes/API/MenuController.php' );
check(
	(bool) preg_match( "/'required'\s*=>\s*'size' === \\\$type/", $core )
	&& (bool) preg_match( "/'multi'\s*=>\s*'topping' === \\\$type/", $core ),
	'the REST payload the sheet consumes states the same two rules'
);

echo "\n--- 6. What starts selected, and what can be left alone ---\n";

check( (bool) preg_match( '/value="size-large"[^>]*checked="checked"/', $html ), 'the default size arrives checked' );
check( ! preg_match( '/value="size-small"[^>]*checked="checked"/', $html ), 'and the other one does not' );
check( substr_count( $html, 'value=""' ) === 2, 'only the optional single-select groups get a «no change» row' );
check( (bool) preg_match( '/data-flavor-mod-group="size"[^>]*data-required="1"/', $html ), 'the required group says so in the markup' );
check( (bool) preg_match( '/data-flavor-mod-group="topping"[^>]*data-required="0"/', $html ), 'and the optional one says that' );

// An optional single-select group with no default must stay untouched — the
// sheet leaves it alone too, and the «no change» row is how the customer says so.
update_post_meta( 2, '_flavor_modifiers', array( array( 'id' => 'cook-rare', 'type' => 'cook', 'name' => 'کم‌پز', 'price' => 0, 'is_default' => 0 ) ) );
$GLOBALS['product'] = wc_get_product( 2 );
$no_default         = capture( static fn() => Product_Page::modifiers() );
check( ! preg_match( '/value="cook-rare"[^>]*checked="checked"/', $no_default ), 'nothing is pre-picked in an optional group without a default' );
check( substr_count( $no_default, 'value=""' ) === 1, 'and the way out of that group is rendered instead' );

// A required group with no default is the one place a pre-pick is load-bearing:
// with no checked radio the size could never be posted at all.
update_post_meta( 5, '_flavor_modifiers', array( array( 'id' => 'size-family', 'type' => 'size', 'name' => 'پرس خانوادگی', 'price' => 60000, 'is_default' => 0 ) ) );
$GLOBALS['product'] = wc_get_product( 5 );
$no_default_size    = capture( static fn() => Product_Page::modifiers() );
check( (bool) preg_match( '/value="size-family"[^>]*checked="checked"/', $no_default_size ), 'a required size with no default pre-picks its first option' );
check( ! preg_match( '/data-flavor-mod-group="size"[^>]*>.*?value=""/s', $no_default_size ), 'and a required group offers no way out' );

echo "\n--- 7. Rows the server would ignore are never rendered ---\n";

update_post_meta(
	3,
	'_flavor_modifiers',
	array(
		array( 'id' => '', 'type' => 'topping', 'name' => 'بی‌شناسه', 'price' => 1000 ),
		array( 'id' => 'topping-anon', 'type' => 'topping', 'name' => '', 'price' => 1000 ),
		array( 'id' => 'topping-real', 'type' => 'topping', 'name' => 'قارچ', 'price' => 12000 ),
	)
);
$GLOBALS['product']   = wc_get_product( 3 );
$partially_broken     = capture( static fn() => Product_Page::modifiers() );
check( ! str_contains( $partially_broken, 'بی‌شناسه' ), 'an option with no id is not offered — sanitize_selection() would drop it' );
check( ! str_contains( $partially_broken, 'value="topping-anon"' ), 'an unnamed option is not offered either' );
check( str_contains( $partially_broken, 'value="topping-real"' ), 'the sound option still is' );

echo "\n--- 8. Merchant copy is escaped ---\n";

update_post_meta(
	4,
	'_flavor_modifiers',
	array( array( 'id' => 'topping-x"><b>', 'type' => 'topping', 'name' => '<script>alert(1)</script>', 'price' => 0 ) )
);
$GLOBALS['product'] = wc_get_product( 4 );
$hostile            = capture( static fn() => Product_Page::modifiers() );
check( ! str_contains( $hostile, '<script>alert' ), 'a stored option name cannot open a script tag' );
check( str_contains( $hostile, '&lt;script&gt;' ), 'and shows up as escaped text' );
check( ! preg_match( '/value="[^"]*"[^>]*><b>/', $hostile ), 'the id cannot break out of its attribute either' );

echo "\n--- 9. The wording is the sheet's wording, from one place ---\n";

$labels  = UI::modifier_labels();
$page    = (string) file_get_contents( FLAVOR_DIR . '/inc/class-product-page.php' );
check(
	! preg_match( "/(?:__|_e|_ex|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'/", $page ),
	'the class holds no translation literal of its own'
);
$enqueue = (string) file_get_contents( FLAVOR_DIR . '/inc/class-enqueue.php' );
foreach ( array( 'group_size', 'group_topping', 'group_cook', 'group_removal', 'group_choices', 'required_suffix', 'no_change', 'no_fee', 'kitchen_note', 'optional', 'note_placeholder', 'note_help' ) as $key ) {
	check(
		(bool) preg_match( "/'" . preg_quote( $key, '/' ) . "'\s*=>\s*__\(\s*'" . preg_quote( (string) $labels[ $key ], '/' ) . "',\s*'flavor'\s*\)/", $enqueue ),
		"the localized payload agrees on {$key}"
	);
}
$pot = (string) file_get_contents( FLAVOR_DIR . '/languages/flavor.pot' );
check( str_contains( $pot, 'msgid "' . $labels['price_note'] . '"' ), 'the one product-page-only string is in the catalogue' );

echo "\n--- 10. The stylesheet is scoped, logical-property and reachable ---\n";

$GLOBALS['_p_styles'] = array();
Product_Page::assets();
check( isset( $GLOBALS['_p_styles']['flavor-product'] ), 'a product page enqueues flavor-product' );
check( in_array( 'flavor-ui', $GLOBALS['_p_styles']['flavor-product'], true ), 'and it chains after the base UI sheet' );

$css_path = FLAVOR_DIR . '/assets/css/ui-product.css';
check( is_file( $css_path ) && filesize( $css_path ) > 0, 'ui-product.css exists and is not empty' );
$css = is_file( $css_path ) ? (string) file_get_contents( $css_path ) : '';
check( substr_count( $css, '.flavor-product-mods' ) >= 10, 'the sheet styles the picker rather than the whole page' );
check( ! str_contains( $css, 'direction:' ), 'ui-product.css never forces a direction' );
check( ! preg_match( '/text-align:\s*(left|right)/', $css ), 'it aligns with logical keywords only' );
check( ! preg_match( '/(^|[;{\s])(left|right)\s*:/m', $css ), 'it positions with inset-*, not with bare left/right' );
check( (bool) preg_match( '/outline:/', $css ), 'keyboard focus is visible' );
check( (bool) preg_match( '/min-height:\s*4[4-9]px|min-height:\s*[5-9][0-9]px/', $css ), 'each option row is at least a 44px touch target' );
check( ! preg_match( '/#[0-9a-f]{3,6}\b/i', preg_replace( '/rgba?\([^)]*\)/', '', $css ) ), 'no colour is hardcoded; the skin supplies them' );

echo "\n--- 11. The note limit is the plugin's limit ---\n";

$plugin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/flavor-core/includes/WooCommerce/ProductModifiers.php' );
check(
	(bool) preg_match( '/mb_substr\(\s*\$clean,\s*0,\s*(' . preg_quote( (string) Product_Page::NOTE_LIMIT, '/' ) . ')/', $plugin ),
	'clean_instructions() truncates at the same ' . Product_Page::NOTE_LIMIT . ' characters the field allows'
);

echo "\n--- 12. R3-01 is decided and R3-11 holds ---\n";

foreach ( array( 'README.md', 'flavor/readme.txt', 'docs/raastichin/listing.md' ) as $surface ) {
	$text = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $surface );
	check( ! preg_match( '/theme\s*builder/i', $text ), "{$surface} makes no Elementor Theme Builder claim" );
}
$doc = (string) file_get_contents( dirname( __DIR__, 2 ) . '/docs/fa/11-elementor.md' );
check( str_contains( $doc, 'گزینهٔ (ب)' ), 'docs/fa/11-elementor.md records the chosen option' );
check( str_contains( $doc, 'Elementor Pro') && (bool) preg_match( '/(نیاز ندارد|نبودِ|لازم نیست|بی‌نیاز)/u', $doc ), 'and says Pro is not required' );
check( str_contains( $doc, 'Product_Page' ), 'the doc names the class that owns the product page' );
check( str_contains( $doc, 'Chrome_Builder' ) || str_contains( $doc, 'class-chrome-builder.php' ), 'and the class that owns header and footer' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
