<?php
/**
 * JavaScript copy contract: every string a customer-facing script renders must
 * come from the PHP catalogue, and the catalogue must contain nothing orphaned.
 *
 * This is the regression that prompt 20 exists for: menu.js shipped 74 Persian
 * literals of its own, so on an English or Arabic site the menu, cart and
 * checkout stayed Persian no matter what the site language said. The rules:
 *
 *   - a script joins the contract by declaring FLAVOR_I18N_MAP (the STR alias);
 *     every key it asks for must exist in Enqueue::menu_strings(), and every key
 *     in that map must be asked for by some script, so neither side accumulates
 *     dead entries;
 *   - placeholders are checked against the call form, because a translator
 *     losing a %s is invisible until a shopper reads "٪s عدد";
 *   - a script that joined the map may keep no Persian literal of its own
 *     outside comments — otherwise the half-migrated file still hides copy;
 *   - the remaining untranslated scripts are a fixed ledger: adding Persian to
 *     a new file fails the build, and finishing one means editing the ledger in
 *     the same commit (that is the point of prompt 21).
 *
 *   php tests/theme/test-js-i18n.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-enqueue.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';

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

$js_dir    = FLAVOR_DIR . '/assets/js';
$map_alias = 'var STR = (cfg.i18n && cfg.i18n.menu) || {};';

/**
 * Persian (and the ZWNJ it uses) inside a quoted literal.
 *
 * @param string $source JS source.
 * @return array<int,array{line:int,text:string}>
 */
function flavor_js_persian_literals( string $source ): array {
	$found   = array();
	$lines   = explode( "\n", $source );
	$literal = '/\'([^\n\']*)\'|"([^"\n]*)"|`([^`\n]*)`/';
	foreach ( $lines as $index => $line ) {
		$trim = trim( $line );
		if ( 0 === strpos( $trim, '//' ) || 0 === strpos( $trim, '*' ) || 0 === strpos( $trim, '/*' ) ) {
			continue;
		}
		if ( ! preg_match_all( $literal, $line, $matches, PREG_SET_ORDER ) ) {
			continue;
		}
		foreach ( $matches as $match ) {
			for ( $group = 1; $group <= 3; ++$group ) {
				if ( ! isset( $match[ $group ] ) || '' === $match[ $group ] ) {
					continue;
				}
				if ( preg_match( '/[\x{0600}-\x{06FF}\x{200C}]/u', $match[ $group ] ) ) {
					$found[] = array(
						'line' => $index + 1,
						'text' => $match[ $group ],
					);
				}
			}
		}
	}
	return $found;
}

$strings = \Flavor\Enqueue::menu_strings();

echo "\n--- 1. The map the scripts read ---\n";

check( is_array( $strings ) && count( $strings ) > 60, 'the localized map is populated (' . count( $strings ) . ' strings)' );
check( count( array_filter( $strings, 'is_string' ) ) === count( $strings ), 'every entry is a string' );
check( '' === implode( '', array_filter( $strings, static function ( $value ) { return '' === trim( (string) $value ); } ) ), 'no entry is blank' );

// The catalogue generator trims msgids, so a source string padded with spaces
// is stored under a key gettext will never look up. The space in " (انتخاب
// ضروری)" is markup: it has to come from the template or it silently kills the
// translation — which is what happened the first time this map was built.
$padded = array_keys( array_filter( $strings, static function ( $value ) { return (string) $value !== trim( (string) $value ); } ) );
check(
	! $padded,
	'no entry carries leading or trailing whitespace' . ( $padded ? ': ' . implode( ', ', $padded ) : '' )
);
check(
	count( array_filter( $strings, static function ( $value ) { return (bool) preg_match( '/[\x{0600}-\x{06FF}]/u', (string) $value ); } ) ) === count( $strings ),
	'the source language of every entry is Persian, as the catalogue expects'
);

echo "\n--- 2. Which scripts joined the contract ---\n";

$files       = glob( $js_dir . '/*.js' );
$using       = array();
$offenders   = array();
$admin_scope = array( 'builder-admin.js', 'customizer-chrome-preview.js', 'customizer-chrome.js', 'customizer-controls.js', 'customizer-contrast.js', 'customizer-live-preview.js', 'customizer-presets.js', 'customizer-repeater.js' );

foreach ( $files as $file ) {
	$basename = basename( $file );
	$source   = (string) file_get_contents( $file );
	if ( false !== strpos( $source, $map_alias ) ) {
		$using[] = $basename;
	}
	// Admin screens are Customizer chrome: prompt 21 leaves them a second
	// priority, so they are not counted as untranslated customer copy.
	if ( in_array( $basename, $admin_scope, true ) ) {
		continue;
	}
	if ( flavor_js_persian_literals( $source ) ) {
		$offenders[] = $basename;
	}
}

sort( $using );
sort( $offenders );

check(
	array( 'menu.js' ) === $using,
	'menu.js is the script reading the map so far (' . implode( ', ', $using ) . ')'
);

echo "\n--- 3. Key parity, both directions ---\n";

$used = array();
foreach ( $files as $file ) {
	$source = (string) file_get_contents( $file );
	if ( false === strpos( $source, $map_alias ) ) {
		continue;
	}
	preg_match_all( '/\b(?:t|fill|fill2)\(\s*\'([a-z_0-9]+)\'/', $source, $matches );
	foreach ( $matches[1] as $key ) {
		$used[ $key ][ basename( $file ) ] = true;
	}
}

$missing = array_diff( array_keys( $used ), array_keys( $strings ) );
$orphan  = array_diff( array_keys( $strings ), array_keys( $used ) );

check( ! $missing, 'no script asks for a key the map does not define' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
check( ! $orphan, 'no entry in the map is dead copy no script asks for' . ( $orphan ? ': ' . implode( ', ', $orphan ) : '' ) );
check( count( $used ) >= 60, 'the whole of menu.js went through the map, not a token few (' . count( $used ) . ' keys)' );

echo "\n--- 4. Placeholders survive the handoff ---\n";

$bad_tokens = array();
foreach ( $files as $file ) {
	$source = (string) file_get_contents( $file );
	if ( false === strpos( $source, $map_alias ) ) {
		continue;
	}
	preg_match_all( '/\b(fill2|fill|t)\(\s*\'([a-z_0-9]+)\'/', $source, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$call = $match[1];
		$key  = $match[2];
		if ( ! isset( $strings[ $key ] ) ) {
			continue;
		}
		$value     = (string) $strings[ $key ];
		$positional = (bool) preg_match( '/%\d+\$s/', $value );
		$bare      = substr_count( $value, '%s' );
		if ( 't' === $call && ( $positional || $bare > 0 ) ) {
			$bad_tokens[] = "{$key}: plain t() on a string carrying placeholders";
		}
		if ( 'fill' === $call && ( $positional || 1 !== $bare ) ) {
			$bad_tokens[] = "{$key}: fill() expects exactly one %s (found {$bare})";
		}
		if ( 'fill2' === $call && ( ! $positional || false === strpos( $value, '%1$s' ) || false === strpos( $value, '%2$s' ) ) ) {
			$bad_tokens[] = "{$key}: fill2() expects %1\$s and %2\$s";
		}
	}
}
check( ! $bad_tokens, 'every placeholder matches the call that fills it' . ( $bad_tokens ? ': ' . implode( '; ', array_unique( $bad_tokens ) ) : '' ) );

echo "\n--- 5. A migrated script keeps no copy of its own ---\n";

$leaks = array();
foreach ( $files as $file ) {
	$basename = basename( $file );
	if ( ! in_array( $basename, $using, true ) ) {
		continue;
	}
	foreach ( flavor_js_persian_literals( (string) file_get_contents( $file ) ) as $hit ) {
		$leaks[] = "{$basename}:{$hit['line']} {$hit['text']}";
	}
}
check( ! $leaks, 'menu.js holds no Persian literal outside comments' . ( $leaks ? ': ' . implode( '; ', $leaks ) : '' ) );

echo "\n--- 6. The remaining translation debt is a fixed list ---\n";

// Phase 2 (prompts 20-21) empties this ledger. It is an exact equality on
// purpose: a new Persian string in a customer-facing script fails the build,
// and finishing a file fails until the list says so.
$expected_debt = array(
	'auth.js',
	'bespoke-demos.js',
	'catering.js',
	'customer.js',
	'demo-animations.js',
	'motion.js',
	'navigation.js',
	'pages.js',
	'premium.js',
	'reservation.js',
	'search.js',
	'tracking.js',
	'ui.js',
);
check(
	$expected_debt === $offenders,
	'the scripts still holding their own copy are exactly the known list',
	);
if ( $expected_debt !== $offenders ) {
	echo '    expected: ' . implode( ', ', $expected_debt ) . "\n";
	echo '    actual:   ' . implode( ', ', $offenders ) . "\n";
}

echo "\n--- 7. Dietary chips have one source of truth ---\n";

$labels = \Flavor\UI::dietary_labels();
check( 5 === count( $labels ), 'PHP labels every dietary flag' );
$filters = (string) file_get_contents( FLAVOR_DIR . '/template-parts/menu/filters.php' );
check(
	false !== strpos( $filters, '\Flavor\UI::dietary_labels()' ),
	'the filter chips read the same map the cards do, instead of a second copy'
);
$menu_js = (string) file_get_contents( $js_dir . '/menu.js' );
check(
	false !== strpos( $menu_js, 'cfg.dietaryLabels' ) && ! preg_match( "/vegetarian:\s*'/", $menu_js ),
	'the card grid takes labels from flavorData instead of restating them'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
