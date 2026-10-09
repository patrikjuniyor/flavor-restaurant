<?php
/**
 * Premium parity contract tests.
 *
 * Guards the features that were added to close the gap with paid restaurant
 * themes: night scheme, wishlist, mega menu, sticky buy bar, quick view,
 * free-delivery progress, trust badges, countdown, consent, opt-in, filters,
 * dock and the branch map.
 *
 * Runs offline against the Flavor Core mock environment:
 *
 *   php tests/theme/test-premium-features.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-dark-mode.php';
require_once FLAVOR_DIR . '/inc/class-wishlist.php';
require_once FLAVOR_DIR . '/inc/class-nav-mega.php';
require_once FLAVOR_DIR . '/inc/class-shopping-extras.php';
require_once FLAVOR_DIR . '/inc/class-engagement.php';
require_once FLAVOR_DIR . '/inc/class-floating-dock.php';
require_once FLAVOR_DIR . '/inc/class-branch-map.php';
require_once FLAVOR_DIR . '/inc/class-premium-customizer.php';

use Flavor\Branch_Map;
use Flavor\Dark_Mode;
use Flavor\Engagement;
use Flavor\Floating_Dock;
use Flavor\Nav_Mega;
use Flavor\Premium_Customizer;
use Flavor\Shopping_Extras;
use Flavor\Wishlist;

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
 * Capture the output of a printer.
 *
 * @param callable $callback Callback.
 * @return string
 */
function capture( callable $callback ): string {
	ob_start();
	$callback();
	return (string) ob_get_clean();
}

/**
 * Seed a theme mod for the current test.
 *
 * @param string $key   Mod key.
 * @param mixed  $value Value.
 */
function mod( string $key, $value ): void {
	$GLOBALS['_mock_theme_mods'][ $key ] = $value;
}

echo "=== Flavor Premium Parity Tests ===\n\n";

/* ------------------------------------------------------------------ colours */

echo "--- 1. Night scheme palette (all twelve skins) ---\n";

check( Dark_Mode::mix( '#000000', '#ffffff', 0.5 ) === '#808080', 'mix() blends halfway between black and white' );
check( abs( Dark_Mode::contrast( '#000000', '#ffffff' ) - 21 ) < 0.01, 'contrast() returns the WCAG maximum of 21:1 for black on white' );
check( Dark_Mode::normalize( '#ABC' ) === 'aabbcc' && Dark_Mode::normalize( 'nope' ) === '', 'normalize() validates and expands hex values' );

$skins     = array_keys( \Flavor\Design::skins() );
$darker    = 0;
$readable  = 0;

foreach ( $skins as $skin ) {
	$light = \Flavor\Design::tokens( $skin );
	$night = Dark_Mode::palette( $skin );

	if ( Dark_Mode::luminance( $night['bg'] ) <= Dark_Mode::luminance( $light['bg'] ) ) {
		++$darker;
	}

	$ok = Dark_Mode::contrast( $night['ink'], $night['bg'] ) >= 4.5
		&& Dark_Mode::contrast( $night['ink'], $night['surface'] ) >= 4.5
		&& Dark_Mode::contrast( $night['muted'], $night['surface'] ) >= 4.5
		&& Dark_Mode::contrast( $night['primary'], $night['surface'] ) >= 4.5;

	if ( $ok ) {
		++$readable;
	}
}

check( count( $skins ) === 12, 'All twelve commercial presets are covered (' . count( $skins ) . ')' );
check( $darker === count( $skins ), "Every skin gets a darker canvas at night ({$darker}/12)" );
check( $readable === count( $skins ), "Body text keeps WCAG AA contrast in every night palette ({$readable}/12)" );

$css = Dark_Mode::css();
check( strpos( $css, 'data-flavor-scheme="dark"' ) !== false, 'Explicit dark choice is styled' );
check( strpos( $css, 'prefers-color-scheme' ) !== false, 'Device preference is honoured without JavaScript' );
check( strpos( $css, 'body' ) !== false, 'Palette is applied on body so skin-level tokens cannot win' );
check( strpos( $css, '--ui-action-ink' ) !== false, 'Button ink is recalculated against the night primary' );

$attributes = Dark_Mode::html_attributes( 'lang="fa-IR"' );
check( strpos( $attributes, 'data-flavor-scheme="auto"' ) !== false, 'Markup declares the scheme before paint' );
check( strpos( $attributes, 'data-flavor-scheme-locked' ) === false, 'A visible toggle is not locked by default' );

mod( 'flavor_color_scheme', 'dark' );
check( strpos( Dark_Mode::html_attributes( '' ), 'data-flavor-scheme="dark"' ) !== false, 'A fixed night site renders night markup server-side' );
mod( 'flavor_color_scheme_toggle', 'no' );
check( strpos( Dark_Mode::html_attributes( '' ), 'data-flavor-scheme-locked' ) !== false, 'Disabling the toggle locks the owner choice for visitors' );
$GLOBALS['_mock_theme_mods'] = array();

$toggle = capture( static function (): void { Dark_Mode::toggle( 'header' ); } );
check( strpos( $toggle, 'data-flavor-scheme-toggle' ) !== false && strpos( $toggle, 'aria-pressed' ) !== false, 'Toggle button exposes state to assistive tech' );

/* ------------------------------------------------------------------- mega */

echo "\n--- 2. Mega menu ---\n";

check( Nav_Mega::columns() === 3, 'Three columns is the shipped default' );
mod( 'flavor_mega_columns', 9 );
check( Nav_Mega::columns() === 4, 'Column count is clamped to a readable maximum' );
$GLOBALS['_mock_theme_mods'] = array();
check( Nav_Mega::threshold() === 5, 'Five children is the default promotion threshold' );

$parent = (object) array( 'ID' => 11, 'classes' => array(), 'description' => '' );
$child  = (object) array( 'ID' => 12, 'classes' => array(), 'description' => '' );

$GLOBALS['_mock_menu_items'] = array(
	(object) array( 'ID' => 11, 'menu_item_parent' => 0 ),
	(object) array( 'ID' => 12, 'menu_item_parent' => 11 ),
	(object) array( 'ID' => 13, 'menu_item_parent' => 11 ),
	(object) array( 'ID' => 14, 'menu_item_parent' => 11 ),
	(object) array( 'ID' => 15, 'menu_item_parent' => 11 ),
	(object) array( 'ID' => 16, 'menu_item_parent' => 11 ),
	(object) array( 'ID' => 17, 'menu_item_parent' => 11 ),
	(object) array( 'ID' => 18, 'menu_item_parent' => 0 ),
);

$args = (object) array( 'theme_location' => 'primary', 'menu' => 7 );

check( Nav_Mega::is_mega( $parent, $args ) === true, 'A branch with six children becomes a panel' );
check( Nav_Mega::is_mega( $child, $args ) === false, 'A leaf item stays a plain link' );

$classes = Nav_Mega::classes( array( 'menu-item' ), $parent, $args, 0 );
check( in_array( 'flavor-mega', $classes, true ) && in_array( 'flavor-mega--cols-3', $classes, true ), 'Panel classes carry the column count' );
check( Nav_Mega::classes( array( 'menu-item' ), $child, $args, 1 ) === array( 'menu-item' ), 'Nested levels are never promoted' );

$forced = (object) array( 'ID' => 40, 'classes' => array( 'mega-menu' ), 'description' => '' );
check( Nav_Mega::is_mega( $forced, $args ) === true, 'The conventional `mega-menu` class still forces a panel' );

$link_atts = Nav_Mega::link_attributes( array(), $parent, $args, 0 );
check( ( $link_atts['aria-haspopup'] ?? '' ) === 'true' && isset( $link_atts['aria-expanded'] ), 'Branches announce themselves as popups' );
check( ! isset( Nav_Mega::link_attributes( array(), $child, $args, 0 )['aria-haspopup'] ), 'Leaves get no popup semantics' );

$described = (object) array( 'ID' => 50, 'classes' => array(), 'description' => 'همبرگر دست‌ساز با نان تازه' );
$markup    = Nav_Mega::description( '<li><a href="#/a/">تست</a>', $described, 1, $args );
check( strpos( $markup, 'flavor-mega__desc' ) !== false && strpos( $markup, '</a>' ) !== false, 'Menu descriptions render inside the link' );
check( $markup === '<li><a href="#/a/">تست<span class="flavor-mega__desc">همبرگر دست‌ساز با نان تازه</span></a>', 'Description markup is nested, not appended after the anchor' );

$GLOBALS['_mock_menu_items'] = array();

/* --------------------------------------------------------------- wishlist */

echo "\n--- 3. Wishlist ---\n";

$item = Wishlist::item( 77, 'کباب کوبیده', '<span>۲۵۰٫۰۰۰ تومان</span>', 'https://example.test/a.jpg', 'https://example.test/77' );
check( $item['price'] === '۲۵۰٫۰۰۰ تومان', 'Price markup is flattened for storage in the browser' );

$button = capture( static function () use ( $item ): void { Wishlist::button( $item, 'menu' ); } );
check( strpos( $button, 'data-wishlist-toggle' ) !== false && strpos( $button, 'data-wish-id="77"' ) !== false, 'Cards get a list toggle carrying their own snapshot' );
check( strpos( $button, 'aria-pressed="false"' ) !== false, 'Toggle starts unpressed' );
check( strpos( $button, 'aria-label=' ) !== false, 'Toggle is labelled for screen readers' );

$panel = capture( static function (): void { Wishlist::panel(); } );
check( strpos( $panel, 'role="dialog"' ) !== false && strpos( $panel, 'aria-modal="true"' ) !== false, 'The list is a real dialog, not a div with a class' );
check( strpos( $panel, 'flavorWishlist' ) !== false && strpos( $panel, '&quot;max&quot;:60' ) !== false, 'Browser storage key and cap are shipped to the script' );

mod( 'flavor_wishlist_enable', 'no' );
check( capture( static function () { Wishlist::panel(); } ) === '', 'The feature has an off switch' );
check( Wishlist::enabled() === false, 'Off switch is visible to the rest of the theme' );
$GLOBALS['_mock_theme_mods'] = array();

/* --------------------------------------------------------- shopping extras */

echo "\n--- 4. Sticky buy bar, quick view, delivery, badges ---\n";

$badges = Shopping_Extras::badges();
check( count( $badges ) === 4, 'Four trust badges ship by default' );
check( in_array( $badges[0]['icon'], array( 'shield', 'truck', 'leaf', 'clock' ), true ), 'Default badges use real icon names' );

mod( 'flavor_trust_badges', "star|منتخب مشتریان\nکلاه|آیکون غلط" );
$custom = Shopping_Extras::badges();
check( $custom[0]['icon'] === 'star', 'A known icon keeps its glyph' );
check( $custom[1]['icon'] === 'check', 'An unknown icon degrades to a safe glyph instead of breaking the list' );

mod( 'flavor_trust_badges', implode( "\n", array_fill( 0, 9, 'star|برچسب' ) ) );
check( count( Shopping_Extras::badges() ) === 6, 'Badge list is capped at six entries' );
$GLOBALS['_mock_theme_mods'] = array();

check( Shopping_Extras::free_delivery_state()['enabled'] === false, 'Free-delivery bar is off until a threshold exists' );
check( Shopping_Extras::threshold() === 0, 'No threshold means no bar' );

mod( 'flavor_free_delivery_threshold', 500000 );
check( Shopping_Extras::threshold() === 500000, 'Configured threshold is read as storage units' );

$bar = capture( static function (): void { Shopping_Extras::free_delivery_markup( 300000, false ); } );
check( strpos( $bar, 'role="progressbar"' ) !== false, 'Progress is exposed as a progressbar' );
check( strpos( $bar, 'aria-valuenow="60"' ) !== false, 'Sixty percent of the threshold reports sixty' );
check( strpos( $bar, 'width:60%' ) !== false, 'Visual fill matches the reported value' );

$done = capture( static function (): void { Shopping_Extras::free_delivery_markup( 600000, false ); } );
check( strpos( $done, 'aria-valuenow="100"' ) !== false && strpos( $done, 'رایگان' ) !== false, 'Crossing the threshold announces free delivery' );

$GLOBALS['_mock_theme_mods'] = array();
check( capture( static function (): void { Shopping_Extras::free_delivery_markup( 1000, false ); } ) === '', 'Bar markup stays silent without a threshold' );

$state = Shopping_Extras::free_delivery_state();
check( isset( $state['i18n']['remaining'] ) && isset( $state['i18n']['done'] ), 'Both progress messages are translated server-side' );

/* ------------------------------------------------------------- engagement */

echo "\n--- 5. Countdown, opt-in and consent ---\n";

check( Engagement::latin_digits( '۰۹۱۲۳۴۵۶۷۸۹' ) === '09123456789', 'Persian digits are accepted wherever numbers are typed' );
check( Engagement::validate_email( 'ali@example.com' ) === 'ali@example.com', 'A normal address validates' );
check( Engagement::validate_email( 'nope' ) === '', 'A malformed address is rejected before storage' );

$deadline = Engagement::parse_deadline( '2030-06-01 12:30' );
check( $deadline > time(), 'A future deadline parses' );
check( Engagement::parse_deadline( '' ) === 0 && Engagement::parse_deadline( 'florence' ) === 0, 'Empty and nonsense deadlines collapse to zero' );
check( Premium_Customizer::sanitize_deadline( '2030-06-01 12:30' ) === '2030-06-01 12:30', 'Sanitised deadlines keep site local time' );
check( Premium_Customizer::sanitize_deadline( 'not a date' ) === '', 'Invalid deadlines are dropped, never stored' );

$clock = capture( static function (): void { Engagement::countdown( 'offer', time() + 86400, 'پایان پیشنهاد تا:' ); } );
check( substr_count( $clock, 'data-cd=' ) === 4, 'Countdown renders day, hour, minute and second' );
check( strpos( $clock, 'data-flavor-countdown-now' ) !== false, 'Server clock is shipped so skew cannot fake the deadline' );
check( strpos( $clock, '<noscript>' ) !== false, 'Visitors without JavaScript still see the end date' );

$past = capture( static function (): void { Engagement::countdown( 'offer', time() - 60 ); } );
check( $past === '', 'An expired countdown removes itself instead of ticking below zero' );

check( Premium_Customizer::sanitize_badges( "shield|امن\n\nclock|سریع" ) === "shield|امن\nclock|سریع", 'Badge sanitiser drops blank lines' );

/* ------------------------------------------------------------ dock and map */

echo "\n--- 6. Floating dock and branch map ---\n";

mod( 'flavor_phone', '۰۲۱-۸۸۰۰۱۲۳۴' );
check( Floating_Dock::phone() === '02188001234', 'Contact number is normalised for tel: links' );
check( Floating_Dock::whatsapp_url() === 'https://wa.me/982188001234', 'Iranian numbers are converted to the wa.me format' );

mod( 'flavor_social_whatsapp', 'https://wa.me/989120000000' );
check( Floating_Dock::whatsapp_url() === 'https://wa.me/989120000000', 'An explicit WhatsApp link wins over the derived one' );

mod( 'flavor_dock_whatsapp', 'no' );
mod( 'flavor_social_whatsapp', '' );
check( Floating_Dock::whatsapp_url() === '', 'The WhatsApp shortcut can be switched off' );
$GLOBALS['_mock_theme_mods'] = array();

check( Branch_Map::coordinates( 0 ) === null, 'A branch without an id has no coordinates' );
check( Branch_Map::coordinates( 5 ) === null, 'A branch without saved coordinates renders no map' );

$embed = Branch_Map::embed_url( 35.7, 51.4 );
check( strpos( $embed, 'openstreetmap.org/export/embed.html' ) !== false && strpos( $embed, 'marker=' ) !== false, 'Map embed needs no API key' );
check( strpos( Branch_Map::directions_url( 35.7, 51.4 ), 'destination=35.7' ) !== false, 'Directions deep-link to the visitor device' );

/* ------------------------------------------------------ markup and wiring */

echo "\n--- 7. Templates, wiring and reduced motion ---\n";

$root = dirname( dirname( __DIR__ ) );

$filters = (string) file_get_contents( $root . '/flavor/template-parts/menu/filters.php' );
check( strpos( $filters, 'data-flavor-filter="diet"' ) !== false, 'Diet chips are rendered' );
check( strpos( $filters, 'data-flavor-filter="avoid"' ) !== false, 'Allergen avoidance chips are rendered' );
check( strpos( $filters, 'data-flavor-sort' ) !== false, 'Sorting control is rendered' );
check( strpos( $filters, 'aria-pressed' ) !== false && strpos( $filters, 'role="status"' ) !== false, 'Filter state and result count are announced' );

$functions = (string) file_get_contents( $root . '/flavor/functions.php' );
$registered = array( 'Dark_Mode', 'Wishlist', 'Nav_Mega', 'Shopping_Extras', 'Engagement', 'Floating_Dock', 'Premium_Customizer', 'Premium_Assets' );
$missing    = array();
foreach ( $registered as $class ) {
	if ( strpos( $functions, 'Flavor\\' . $class . '::init();' ) === false ) {
		$missing[] = $class;
	}
}
check( ! $missing, 'Every premium class is bootstrapped from functions.php' . ( $missing ? ' (missing: ' . implode( ', ', $missing ) . ')' : '' ) );

$dark_css  = (string) file_get_contents( $root . '/flavor/assets/css/dark-mode.css' );
$premium   = (string) file_get_contents( $root . '/flavor/assets/css/premium.css' );
$premiumjs = (string) file_get_contents( $root . '/flavor/assets/js/premium.js' );

check( strpos( $dark_css, 'prefers-reduced-motion' ) !== false, 'Scheme cross-fade is dropped for reduced-motion visitors' );
check( strpos( $premium, 'prefers-reduced-motion' ) !== false, 'Every premium animation has a reduced-motion guard' );
check( strpos( $premiumjs, 'prefers-reduced-motion' ) !== false, 'Scripts check reduced motion before smooth scrolling' );
check( strpos( $premiumjs, 'jQuery(' ) === false && strpos( $premiumjs, 'window.jQuery' ) === false, 'No jQuery in the premium script' );
check( preg_match( '#https?://(?!wa\.me)[a-z0-9.-]+#i', $premiumjs ) === 0, 'The script talks to the shop, never to a third-party host' );
check( strpos( $premium, 'env(safe-area-inset-bottom' ) !== false, 'Phone layouts respect the safe area around notches' );

$assets = (string) file_get_contents( $root . '/flavor/inc/class-premium-assets.php' );
check( strpos( $assets, 'flavor-premium' ) !== false && strpos( $assets, "'defer'" ) !== false, 'Premium layer ships as one deferred request' );

$child = $root . '/flavor-child/style.css';
check( is_readable( $child ), 'A child theme ships with the product' );
if ( is_readable( $child ) ) {
	$child_css = (string) file_get_contents( $child );
	check( strpos( $child_css, 'Template: flavor' ) !== false, 'Child theme points at the parent template' );
}

$style = (string) file_get_contents( $root . '/flavor/style.css' );
$bootstrap = (string) file_get_contents( $root . '/flavor/functions.php' );
preg_match( '/Version:\s*([0-9.]+)/', $style, $style_version );
preg_match( '/FLAVOR_VERSION\x27,\s*\x27([0-9.]+)\x27/', $bootstrap, $const_version );
check(
	isset( $style_version[1], $const_version[1] ) && version_compare( $style_version[1], '1.5.0', '>' ) && $style_version[1] === $const_version[1],
	'Theme version is bumped and style.css agrees with FLAVOR_VERSION (' . ( $style_version[1] ?? '?' ) . ')'
);

$core_menu = (string) file_get_contents( $root . '/flavor-core/includes/API/MenuController.php' );
check( strpos( $core_menu, "'allergens'" ) !== false, 'Allergen data reaches the menu payload the filters read' );

echo "\n=======================================================\n";
echo 'Results: ' . $passed . ' Passed, ' . $failed . " Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
