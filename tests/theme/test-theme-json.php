<?php
/**
 * theme.json contract tests.
 *
 * The block editor learns the theme's palette, type scale and spacing from
 * flavor/theme.json. Every colour there is written as
 * `var(--flavor-primary, #a74e2c)` so the editor follows the active skin
 * instead of freezing one preset into the stylesheet.
 *
 * That indirection is also the risk: a typo in a token name, or a fallback
 * that no longer matches what Design emits, produces an editor that shows
 * colours the front end never renders. These tests keep the two files honest.
 *
 * Runs offline against the Flavor Core mock WordPress environment.
 *
 *   php tests/theme/test-theme-json.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-theme-setup.php';
require_once FLAVOR_DIR . '/inc/class-design.php';

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
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

$theme_json_path = FLAVOR_DIR . '/theme.json';

echo "=== Flavor theme.json Contract Tests ===\n\n";

echo "--- 1. The file exists and parses ---\n";

check( is_file( $theme_json_path ), 'flavor/theme.json exists' );

if ( ! is_file( $theme_json_path ) ) {
	echo "\n=======================================================\n";
	echo "Results: {$passed} Passed, {$failed} Failed\n";
	echo "=======================================================\n";
	exit( 1 );
}

$raw  = (string) file_get_contents( $theme_json_path );
$data = json_decode( $raw, true );

check( JSON_ERROR_NONE === json_last_error(), 'theme.json is valid JSON (' . json_last_error_msg() . ')' );
check( 3 === ( $data['version'] ?? null ), 'declares theme.json schema version 3' );
check( isset( $data['settings'], $data['styles'] ), 'declares both settings and styles' );

if ( ! is_array( $data ) ) {
	echo "\n=======================================================\n";
	echo "Results: {$passed} Passed, {$failed} Failed\n";
	echo "=======================================================\n";
	exit( 1 );
}

echo "\n--- 2. Every referenced --flavor-* token is really emitted ---\n";

$css = Design::css_variables();

/**
 * Collect every var() reference with its fallback.
 *
 * A regex cannot do this: `var(--flavor-heading-size-desktop, clamp(2.2rem,
 * 4vw, 4.5rem))` contains nested parentheses, so `[^)]+` truncates the
 * fallback at the first closing paren and every clamp() value compares
 * unequal. Scan for balanced parentheses instead.
 *
 * @param string $haystack Text to scan.
 * @return array<string, string|null> Token => fallback.
 */
$extract_var_refs = static function ( string $haystack ): array {
	$out   = array();
	$len   = strlen( $haystack );
	$token = '/^--flavor-[a-z0-9-]+/i';

	for ( $i = 0; $i < $len; $i++ ) {
		if ( 'var(' !== substr( $haystack, $i, 4 ) ) {
			continue;
		}

		$depth = 1;
		$buf   = '';
		$j     = $i + 4;
		while ( $j < $len && $depth > 0 ) {
			$c = $haystack[ $j ];
			if ( '(' === $c ) {
				++$depth;
			} elseif ( ')' === $c ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			}
			$buf .= $c;
			++$j;
		}

		// Only the first comma separates token from fallback; the rest belong
		// to the fallback itself (clamp(), rgba(), ...).
		$parts    = explode( ',', $buf, 2 );
		$candidate = trim( $parts[0] );
		if ( preg_match( $token, $candidate ) ) {
			$out[ $candidate ] = isset( $parts[1] ) ? trim( $parts[1] ) : null;
		}
		$i = $j;
	}

	return $out;
};

$referenced = $extract_var_refs( $raw );

check( count( $referenced ) > 0, 'theme.json references Flavor design tokens (' . count( $referenced ) . ')' );

$dangling = array();
foreach ( array_keys( $referenced ) as $token ) {
	if ( false === strpos( $css, $token . ':' ) ) {
		$dangling[] = $token;
	}
}
check(
	array() === $dangling,
	'no dangling tokens' . ( $dangling ? ' — missing: ' . implode( ', ', $dangling ) : ' (all ' . count( $referenced ) . ' resolve)' )
);

echo "\n--- 3. Fallbacks are true on an untouched site ---\n";

// A fallback exists so the editor still shows something sensible when the
// skin stylesheet has not been parsed yet. If it disagrees with what Design
// actually emits, the editor is quietly lying about the brand colour.
$lying = array();
foreach ( $referenced as $token => $fallback ) {
	if ( null === $fallback || '' === $fallback ) {
		continue;
	}
	if ( ! preg_match( '/' . preg_quote( $token, '/' ) . ':\s*([^;]+);/i', $css, $m ) ) {
		continue; // Already reported as dangling above.
	}
	$emitted = trim( $m[1] );
	// Some tokens are stacks or calc() expressions; compare case-insensitively.
	if ( 0 !== strcasecmp( $emitted, $fallback ) ) {
		$lying[] = sprintf( '%s: fallback "%s" but Design emits "%s"', $token, $fallback, $emitted );
	}
}

check(
	array() === $lying,
	'fallbacks match what Design emits' . ( $lying ? ' — ' . implode( '; ', array_slice( $lying, 0, 3 ) ) : ' (all consistent)' )
);

echo "\n--- 4. Palette is complete and well-formed ---\n";

$palette = $data['settings']['color']['palette'] ?? array();
check( count( $palette ) >= 9, 'palette exposes the semantic skin roles (' . count( $palette ) . ' colours)' );

$slugs        = array();
$bad_slug     = array();
$missing_name = array();
foreach ( $palette as $colour ) {
	$s = (string) ( $colour['slug'] ?? '' );
	if ( ! preg_match( '/^[a-z0-9-]+$/', $s ) ) {
		$bad_slug[] = $s;
	}
	if ( in_array( $s, $slugs, true ) ) {
		$bad_slug[] = $s . ' (duplicate)';
	}
	$slugs[] = $s;
	if ( '' === (string) ( $colour['name'] ?? '' ) ) {
		$missing_name[] = $s;
	}
}
check( array() === $bad_slug, 'every palette slug is a valid CSS identifier and unique' );
check( array() === $missing_name, 'every palette colour carries a human-readable name' );

foreach ( array( 'primary', 'secondary', 'accent', 'background', 'surface', 'ink', 'muted', 'line' ) as $role ) {
	check( in_array( $role, $slugs, true ), "palette exposes the '{$role}' role" );
}

echo "\n--- 5. Layout follows the Customizer's container width ---\n";

$content_size = (string) ( $data['settings']['layout']['contentSize'] ?? '' );
check(
	false !== strpos( $content_size, '--flavor-container-max' ),
	"contentSize tracks --flavor-container-max ({$content_size})"
);
check(
	strlen( $data['settings']['layout']['wideSize'] ?? '' ) > 0,
	'wideSize is declared so alignwide has somewhere to go'
);

echo "\n--- 6. Declared fonts are actually bundled ---\n";

$families = $data['settings']['typography']['fontFamilies'] ?? array();
check( count( $families ) >= 2, 'font families are declared (' . count( $families ) . ')' );

$font_dir = FLAVOR_DIR . '/assets/fonts';
foreach ( $families as $family ) {
	$stack = (string) ( $family['fontFamily'] ?? '' );
	// Only assert on families the theme ships; "System" needs no file.
	if ( preg_match( "/'(Vazirmatn|Estedad)'/", $stack, $m ) ) {
		$name = strtolower( $m[1] );
		check( is_dir( $font_dir . '/' . $name ), "bundled font files exist for {$m[1]} (assets/fonts/{$name})" );
	}
}

echo "\n--- 7. Spacing scale is a real, ordered scale ---\n";

$sizes = $data['settings']['spacing']['spacingSizes'] ?? array();
check( count( $sizes ) >= 8, 'spacing scale has usable resolution (' . count( $sizes ) . ' steps)' );

$ordered = true;
$prev    = -1;
foreach ( $sizes as $step ) {
	// Slugs are numeric strings; they must ascend.
	if ( ! ctype_digit( (string) ( $step['slug'] ?? '' ) ) ) {
		$ordered = false;
		break;
	}
	$n = (int) $step['slug'];
	if ( $n <= $prev ) {
		$ordered = false;
		break;
	}
	$prev = $n;
}
check( $ordered, 'spacing slugs ascend, so the editor scale reads in order' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
