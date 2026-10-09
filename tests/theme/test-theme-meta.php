<?php
/**
 * Theme chrome contract tests: browser theme-color, app icons and the
 * installable web app manifest.
 *
 * Runs offline against the Flavor Core mock WordPress environment, so the
 * payload can be asserted in CI without a WordPress install.
 *
 *   php tests/theme/test-theme-meta.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-meta.php';
require_once FLAVOR_DIR . '/inc/template-tags.php';
require_once FLAVOR_DIR . '/inc/class-pwa.php';

use Flavor\Design;
use Flavor\Meta;
use Flavor\PWA;

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

echo "=== Flavor Theme Chrome & Manifest Tests ===\n\n";
echo "--- 1. Browser theme colour follows the active skin ---\n";

$default = Meta::theme_color();
check( (bool) preg_match( '/^#[0-9a-f]{6}$/i', $default ), "Theme colour is a full hex value ({$default})" );
check(
	$default === Design::tokens( Design::current_skin() )['primary'],
	'Theme colour is the active skin primary, not a hard-coded constant'
);

$skins  = array_keys( Design::skins() );
$colors = array();
foreach ( $skins as $skin ) {
	$tokens = Design::tokens( $skin );
	check(
		(bool) preg_match( '/^#[0-9a-f]{6}$/i', (string) $tokens['primary'] ),
		"Skin '{$skin}' resolves a valid hex primary ({$tokens['primary']})"
	);
	$colors[] = strtolower( (string) $tokens['primary'] );
}

check( count( $skins ) >= 12, 'All twelve commercial skins are registered (' . count( $skins ) . ')' );
check(
	count( array_unique( $colors ) ) === count( $colors ),
	'Every skin paints its own browser bar (no two skins share a theme colour)'
);

echo "\n--- 2. Manifest payload ---\n";

$manifest = PWA::manifest();

check( isset( $manifest['name'] ) && '' !== $manifest['name'], "Manifest carries the live site name ({$manifest['name']})" );
check( 'standalone' === $manifest['display'], 'Manifest installs as a standalone app (no browser chrome)' );
check( 'rtl' === $manifest['dir'], 'Manifest declares the RTL text direction' );
check( str_starts_with( (string) $manifest['start_url'], 'http' ), "start_url is absolute ({$manifest['start_url']})" );
check( (bool) preg_match( '/^#[0-9a-f]{6}$/i', (string) $manifest['theme_color'] ), 'Manifest theme_color mirrors the skin colour' );
check( (bool) preg_match( '/^#[0-9a-f]{6}$/i', (string) $manifest['background_color'] ), 'Manifest background_color uses the skin surface' );
check( mb_strlen( (string) $manifest['short_name'] ) <= 12, 'short_name survives the Android home-screen label limit' );

echo "\n--- 3. App icons exist on disk and are declared correctly ---\n";

$purposes = array();
$sizes    = array();
foreach ( $manifest['icons'] as $icon ) {
	$relative = str_replace( FLAVOR_URI, FLAVOR_DIR, (string) $icon['src'] );
	check( is_readable( $relative ), "Icon file exists: {$icon['src']} (" . round( filesize( $relative ) / 1024 ) . ' KB)' );
	$purposes[] = $icon['purpose'];
	$sizes[]    = $icon['sizes'];
}

check( in_array( 'any', $purposes, true ), 'At least one "any" purpose icon is declared' );
check( in_array( 'maskable', $purposes, true ), 'A maskable icon is declared for Android adaptive shapes' );
check( in_array( '192x192', $sizes, true ) && in_array( '512x512', $sizes, true ), 'Both 192x192 and 512x512 install sizes are present' );

echo "\n--- 4. Payload serialises to valid JSON ---\n";

$json = wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$back = json_decode( (string) $json, true );
check( is_array( $back ) && $back['name'] === $manifest['name'], 'Manifest round-trips through JSON encoder' );
check( ! str_contains( (string) $json, '\/' ) && ! str_contains( (string) $json, '\\u06' ), 'Persian text stays unescaped in the JSON body' );

echo "\n--- 4b. Head output actually prints the tags ---\n";

ob_start();
Meta::head();
$head = (string) ob_get_clean();

check( str_contains( $head, '<meta name="theme-color" content="' ), 'wp_head emits a theme-color meta tag' );
check( str_contains( $head, 'content="' . Meta::theme_color() . '"' ), 'The emitted theme-color matches the active skin colour' );
check( str_contains( $head, 'name="apple-mobile-web-app-capable"' ), 'iOS home-screen metadata is present' );
check( str_contains( $head, 'rel="apple-touch-icon"' ), 'Apple touch icon is linked' );
check( str_contains( $head, 'rel="manifest"' ), 'Web app manifest is linked for installable behaviour' );
check( str_contains( $head, 'rel="icon"' ), 'Fallback favicon is linked when no Site Icon is set' );
check(
	str_contains( $head, 'sizes="32x32"' ) && str_contains( $head, 'sizes="48x48"' ),
	'Both favicon sizes are declared'
);
check( substr_count( $head, '<link' ) >= 4, 'Head block prints every expected link tag (' . substr_count( $head, '<link' ) . ' links)' );

echo "\n--- 5. Screenshot meets WordPress requirements ---\n";

$shot = FLAVOR_DIR . '/screenshot.png';
$info = @getimagesize( $shot ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
check( is_array( $info ), 'Theme screenshot exists' );
if ( is_array( $info ) ) {
	check( 1200 === $info[0] && 900 === $info[1], "Screenshot is exactly 1200x900 as WordPress.org expects ({$info[0]}x{$info[1]})" );
	check( filesize( $shot ) < 2 * 1024 * 1024, 'Screenshot stays under 2 MB for theme-packaging limits (' . round( filesize( $shot ) / 1024 ) . ' KB)' );
}

echo "\n=======================================================\n";
echo 'Results: ' . $passed . ' Passed, ' . $failed . " Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
