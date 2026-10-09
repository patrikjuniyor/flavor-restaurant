<?php
/**
 * Visual preset picker contract tests.
 *
 * Two things matter here:
 *
 *  1. The swatch must be the preset's real palette. It is derived from the
 *     same tokens the front end uses, so the test asserts that rather than
 *     checking against a second hand-written list of colours — a duplicate
 *     list would drift the moment someone retones a skin.
 *
 *  2. "Clear my manual colours" must never write to the database. The PHP
 *     only hands the JS a list of setting ids; the JS stages '' in the
 *     changeset. The test greps the script for any persistence call.
 *
 *   php tests/theme/test-preset-control.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';
require_once __DIR__ . '/fixtures/page-options-stubs.php';
require_once __DIR__ . '/fixtures/preset-control-stubs.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-preset-control.php';

use Flavor\Design;
use Flavor\Preset_Control;

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

echo "=== Flavor Visual Preset Picker Contract Tests ===\n\n";

$skins = Design::skins();

echo "--- 1. Every preset has a usable swatch ---\n";

check( count( $skins ) >= 12, 'the theme ships a full set of presets (' . count( $skins ) . ')');

foreach ( $skins as $slug => $skin ) {
	$swatch = Design::skin_swatch( (string) $slug );

	check( 5 === count( $swatch ), "{$slug} exposes five swatch colours" );

	$blank = array_filter(
		$swatch,
		static fn( $color ) => ! (bool) preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', (string) $color )
	);
	check( empty( $blank ), "{$slug} has no blank or malformed swatch colour" );

	// The swatch must be the palette, not a lookalike kept in a second list.
	$tokens = Design::tokens( (string) $slug );
	check(
		( $tokens['primary'] ?? '' ) === ( $swatch['primary'] ?? null ),
		"{$slug}'s swatch primary matches its actual token"
	);
	check(
		( $tokens['bg'] ?? '' ) === ( $swatch['bg'] ?? null ),
		"{$slug}'s swatch background matches its actual token"
	);
}

echo "\n--- 2. Swatches are visually distinguishable ---\n";

$primaries = array();
foreach ( array_keys( $skins ) as $slug ) {
	$primaries[ (string) $slug ] = Design::skin_swatch( (string) $slug )['primary'];
}
$unique = array_unique( $primaries );
check(
	count( $unique ) >= 10,
	'the presets are distinguishable by primary colour alone (' . count( $unique ) . ' distinct)'
);

echo "\n--- 3. Each card renders safely and completely ---\n";

foreach ( $skins as $slug => $skin ) {
	$html = Preset_Control::card( (string) $slug, (array) $skin, false );

	check( false !== strpos( $html, 'flavor-preset__card' ), "{$slug} renders a card" );
	check( 5 === substr_count( $html, 'flavor-preset__chip' ), "{$slug} renders five colour chips" );
	check( false !== strpos( $html, (string) $skin['title'] ), "{$slug} shows its title" );
	check( false === strpos( $html, 'style="background:"' ), "{$slug} never emits an empty background" );

	// A hostile title must not break out of the markup.
	$hostile = Preset_Control::card( (string) $slug, array( 'title' => '<script>alert(1)</script>' ), false );
	check( false === strpos( $hostile, '<script>' ), "{$slug} escapes a script payload in its title" );
}

$active   = Preset_Control::card( 'modern-restaurant', array( 'title' => 'مدرن' ), true );
$inactive = Preset_Control::card( 'modern-restaurant', array( 'title' => 'مدرن' ), false );
check( false !== strpos( $active, 'is-active' ), 'the selected card is marked active' );
check( false === strpos( $inactive, 'is-active' ), 'an unselected card is not marked active' );
check( false !== strpos( $active, 'checked' ), 'the selected card checks its radio' );

echo "\n--- 4. The override notice is honest and optional ---\n";

// Start clean.
foreach ( array_keys( Design::colour_token_labels() ) as $key ) {
	remove_theme_mod( 'flavor_' . $key );
}

check( '' === Preset_Control::override_notice( Design::active_colour_overrides() ), 'with no manual colours there is no notice' );
check( empty( Design::active_colour_overrides() ), 'with no manual colours nothing is reported as overridden' );

set_theme_mod( 'flavor_primary', '#ff0000' );
$overrides = Design::active_colour_overrides();
check( 1 === count( $overrides ), 'setting one colour by hand is detected' );
check( isset( $overrides['flavor_primary'] ), 'the override is reported under its setting id' );

$notice = Preset_Control::override_notice( $overrides );
check( false !== strpos( $notice, 'رنگ اصلی' ), 'the notice names the overridden setting in Persian' );
check( false !== strpos( $notice, 'flavor-preset__clear' ), 'the notice offers a way to clear the overrides' );
check(
	false !== strpos( $notice, 'منتشر نکنید' ),
	'the notice says nothing changes until the changeset is published'
);

set_theme_mod( 'flavor_bg', '#00ff00' );
check( 2 === count( Design::active_colour_overrides() ), 'a second manual colour is detected too' );

// A blank value is not an override.
set_theme_mod( 'flavor_line', '' );
check( ! isset( Design::active_colour_overrides()['flavor_line'] ), 'an empty colour counts as "not overridden"' );

echo "\n--- 5. Clearing overrides cannot write to the database ---\n";

$js = (string) file_get_contents( FLAVOR_DIR . '/assets/js/customizer-presets.js' );

foreach ( array( 'fetch(', 'XMLHttpRequest', 'wp.ajax', 'navigator.sendBeacon', 'localStorage.setItem' ) as $persistence ) {
	check( false === strpos( $js, $persistence ), "the script contains no {$persistence}" );
}
check( false !== strpos( $js, "setting.set('')" ), 'clearing writes an empty value into the changeset' );
check( false !== strpos( $js, 'control.setting.set(' ), 'picking a preset goes through the Customizer setting API' );

echo "\n--- 6. The stylesheet matches the markup the PHP prints ---\n";

$css = (string) file_get_contents( FLAVOR_DIR . '/assets/css/customizer-presets.css' );
$html = Preset_Control::card( 'modern-restaurant', array( 'title' => 'مدرن' ), true );

preg_match_all( '/class="(flavor-preset__[a-z-]+)"/', $html, $used );
foreach ( array_unique( $used[1] ) as $class ) {
	check( false !== strpos( $css, '.' . $class ), ".{$class} is styled" );
}

preg_match_all( '/class="(flavor-preset__[a-z-]+)"/', Preset_Control::override_notice( array( 'flavor_primary' => '#f00' ) ), $notice_classes );
foreach ( array_unique( $notice_classes[1] ) as $class ) {
	check( false !== strpos( $css, '.' . $class ), "notice class .{$class} is styled" );
}

check( false !== strpos( $css, 'prefers-reduced-motion' ), 'the stylesheet respects reduced-motion' );
check( false !== strpos( $css, ':focus-within' ), 'keyboard focus is visible on the card' );

echo "\n--- 7. The setting ids match the tokens they claim to clear ---\n";

foreach ( array_keys( Design::colour_token_labels() ) as $key ) {
	check(
		false !== strpos( (string) file_get_contents( FLAVOR_DIR . '/inc/class-design.php' ), "'flavor_' . \$key" ),
		"flavor_{$key} maps back to a real resolved() token lookup"
	);
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
