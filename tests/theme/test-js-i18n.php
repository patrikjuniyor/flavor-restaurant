<?php
/**
 * JavaScript copy contract: every string a customer-facing script renders must
 * come from a PHP catalogue, and the catalogue must not rot.
 *
 * This is the regression prompts 20-21 exist for. menu.js used to ship 74
 * Persian literals and premium.js carried Persian fallbacks, so on an English or
 * Arabic site the menu, cart, checkout, wishlist and cookie bar stayed Persian
 * no matter what the site language said — and nothing in the build noticed.
 *
 * A script joins the contract by carrying a marker comment naming the map it
 * reads. Each contract then checks:
 *
 *   - the marker is still there, so a file cannot silently stop being covered;
 *   - every key the script asks for exists in the PHP map;
 *   - the map has no blank, padded or whitespace-padded entry (xgettext trims
 *     msgids, so " (foo)" is stored as "(foo)" and gettext would never look the
 *     padded key up — the translation silently never applies);
 *   - placeholders survive the handoff: a string used through a single-value
 *     call must carry exactly one %s, a two-value call needs %1$s and %2$s, and
 *     a plain lookup must carry none. A translator dropping a token is invisible
 *     until a shopper reads "%s" on the cart button;
 *   - a file that joined the map keeps no Persian literal of its own outside
 *     comments, which is the acceptance criterion of prompt 20;
 *   - Enqueue::menu_strings() keeps no dead entry. It exists only for JS, so an
 *     unused key is payload nobody renders. The other maps are also read by PHP
 *     markup, so they are checked one direction only.
 *
 * The scripts still holding their own copy are a fixed ledger: adding Persian to
 * a new file fails the build, and finishing one means editing the ledger in the
 * same commit.
 *
 *   php tests/theme/test-js-i18n.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-enqueue.php';
require_once FLAVOR_DIR . '/inc/class-premium-assets.php';
require_once FLAVOR_DIR . '/inc/class-wishlist.php';
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

/**
 * Persian (and the ZWNJ it uses) inside a quoted JS literal, comments excluded.
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

/**
 * Keys a script asks from one map, by its lookup function names.
 *
 * @param string    $source JS source.
 * @param string[]  $calls  Function names that take the key as first argument.
 * @return string[]
 */
function flavor_js_used_keys( string $source, array $calls ): array {
	$names = implode( '|', array_map( 'preg_quote', $calls ) );
	preg_match_all( '/\b(?:' . $names . ')\(\s*\'([A-Za-z_0-9]+)\'/', $source, $matches );
	return array_values( array_unique( $matches[1] ) );
}

$js_dir = FLAVOR_DIR . '/assets/js';

$contracts = array(
	array(
		'file'   => 'menu.js',
		'marker' => 'flavor-i18n-map: flavorData.i18n.menu',
		'calls'  => array( 't', 'fill', 'fill2' ),
		'one'    => 'fill',
		'two'    => 'fill2',
		'map'    => '\Flavor\Enqueue::menu_strings',
		'strict' => true, // the map exists only for this script: no dead entries
	),
	array(
		'file'   => 'premium.js',
		'marker' => 'flavor-i18n-map: flavorPremium.i18n',
		'calls'  => array( 't', 'fmt', 'fmt2' ),
		'one'    => 'fmt',
		'two'    => 'fmt2',
		'map'    => '\Flavor\Premium_Assets::strings',
		'strict' => false,
	),
	array(
		'file'   => 'premium.js',
		'marker' => 'flavor-i18n-map: wishlist.i18n',
		'calls'  => array( 'wt' ),
		'one'    => '',
		'two'    => '',
		'map'    => '\Flavor\Wishlist::strings',
		'strict' => false,
	),
);

echo "\n--- 1. Each declared script still declares its map ---\n";

$sources = array();
foreach ( $contracts as $name => $contract ) {
	$path          = $js_dir . '/' . $contract['file'];
	$sources[ $name ] = (string) file_get_contents( $path );
	check(
		false !== strpos( $sources[ $name ], $contract['marker'] ),
		$contract['file'] . ' declares ' . $contract['marker']
	);
}

echo "\n--- 2. Key parity against the PHP maps ---\n";

$maps = array();
foreach ( $contracts as $name => $contract ) {
	$map          = call_user_func( $contract['map'] );
	$maps[ $name ] = $map;
	$used         = flavor_js_used_keys( $sources[ $name ], $contract['calls'] );

	check( is_array( $map ) && ! empty( $map ), $contract['file'] . ': its map is populated (' . count( $map ) . ' strings)' );
	check(
		count( array_filter( $map, 'is_string' ) ) === count( $map ),
		$contract['file'] . ': every entry is a string'
	);
	check(
		'' === implode( '', array_filter( $map, static function ( $value ) { return '' === trim( (string) $value ); } ) ),
		$contract['file'] . ': no entry is blank'
	);

	// The catalogue generator trims msgids, so a source string padded with
	// spaces is filed under a key gettext will never look up. Spacing around a
	// message is markup, not text — that is how " (انتخاب ضروری)" first lost its
	// Arabic translation.
	$padded = array_keys( array_filter( $map, static function ( $value ) { return (string) $value !== trim( (string) $value ); } ) );
	check(
		! $padded,
		$contract['file'] . ': no entry carries leading or trailing whitespace' . ( $padded ? ': ' . implode( ', ', $padded ) : '' )
	);

	sort( $used );
	$missing = array_diff( $used, array_keys( $map ) );
	check(
		! $missing,
		$contract['file'] . ': no key is asked for that the map does not define' . ( $missing ? ': ' . implode( ', ', $missing ) : '' )
	);
	check(
		count( $used ) > 5,
		$contract['file'] . ': the map is really being used, not a token few (' . count( $used ) . ' keys)'
	);

	if ( ! empty( $contract['strict'] ) ) {
		$orphan = array_diff( array_keys( $map ), $used );
		check(
			! $orphan,
			$contract['file'] . ': the map holds no dead copy no script asks for' . ( $orphan ? ': ' . implode( ', ', $orphan ) : '' )
		);
	}
	$contracts[ $name ]['used'] = $used;
}

echo "\n--- 3. Placeholders survive the handoff ---\n";

foreach ( $contracts as $name => $contract ) {
	$map    = $maps[ $name ];
	$bad    = array();
	$calls  = implode( '|', array_map( 'preg_quote', $contract['calls'] ) );
	preg_match_all( '/\b(' . $calls . ')\(\s*\'([A-Za-z_0-9]+)\'/', $sources[ $name ], $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$call = $match[1];
		$key  = $match[2];
		if ( ! isset( $map[ $key ] ) ) {
			continue;
		}
		$value      = (string) $map[ $key ];
		$positional = (bool) preg_match( '/%\d+\$s/', $value );
		$bare       = substr_count( $value, '%s' );
		if ( $call === $contract['one'] && ( $positional || 1 !== $bare ) ) {
			$bad[] = "{$key}: a single-value call needs exactly one %s (found {$bare})";
		} elseif ( $call === $contract['two'] && ( ! $positional || false === strpos( $value, '%1$s' ) || false === strpos( $value, '%2$s' ) ) ) {
			$bad[] = "{$key}: a two-value call needs %1\$s and %2\$s";
		} elseif ( ! in_array( $call, array( $contract['one'], $contract['two'] ), true ) && ( $positional || $bare > 0 ) ) {
			$bad[] = "{$key}: looked up plain but the string carries placeholders";
		}
	}
	check(
		! $bad,
		$contract['file'] . ': every placeholder matches the call that fills it' . ( $bad ? ': ' . implode( '; ', array_unique( $bad ) ) : '' )
	);
}

echo "\n--- 4. A migrated script keeps no copy of its own ---\n";

$migrated = array_unique( array_column( $contracts, 'file' ) );
sort( $migrated );
foreach ( $migrated as $file ) {
	$leaks = array();
	foreach ( flavor_js_persian_literals( (string) file_get_contents( $js_dir . '/' . $file ) ) as $hit ) {
		$leaks[] = $file . ':' . $hit['line'] . ' ' . $hit['text'];
	}
	check(
		! $leaks,
		$file . ' holds no Persian literal outside comments' . ( $leaks ? ': ' . implode( '; ', $leaks ) : '' )
	);
}

echo "\n--- 5. The remaining translation debt is a fixed list ---\n";

// Prompts 20-21 empty this ledger. It is exact equality on purpose: a new
// Persian string in a customer-facing script fails the build, and finishing a
// file fails until the list says so. Admin/Customizer chrome is out of scope
// (prompt 21 leaves it a second priority).
$expected_debt = array(
	'auth.js',
	'bespoke-demos.js',
	'catering.js',
	'customer.js',
	'demo-animations.js',
	'motion.js',
	'navigation.js',
	'pages.js',
	'reservation.js',
	'search.js',
	'tracking.js',
	'ui.js',
);

$admin_scope = array(
	'builder-admin.js',
	'customizer-chrome-preview.js',
	'customizer-chrome.js',
	'customizer-controls.js',
	'customizer-contrast.js',
	'customizer-live-preview.js',
	'customizer-presets.js',
	'customizer-repeater.js',
);

$offenders = array();
foreach ( glob( $js_dir . '/*.js' ) as $path ) {
	$basename = basename( $path );
	if ( in_array( $basename, $admin_scope, true ) || in_array( $basename, $migrated, true ) ) {
		continue;
	}
	if ( flavor_js_persian_literals( (string) file_get_contents( $path ) ) ) {
		$offenders[] = $basename;
	}
}
sort( $offenders );
check( $expected_debt === $offenders, 'the scripts still holding their own copy are exactly the known list' );
if ( $expected_debt !== $offenders ) {
	echo '    expected: ' . implode( ', ', $expected_debt ) . "\n";
	echo '    actual:   ' . implode( ', ', $offenders ) . "\n";
}

echo "\n--- 6. Dietary chips have one source of truth ---\n";

$labels  = \Flavor\UI::dietary_labels();
$filters = (string) file_get_contents( FLAVOR_DIR . '/template-parts/menu/filters.php' );
$menu_js = (string) file_get_contents( $js_dir . '/menu.js' );

check( 5 === count( $labels ), 'PHP labels every dietary flag' );
check(
	false !== strpos( $filters, '\Flavor\UI::dietary_labels()' ),
	'the filter chips read the same map the cards do, instead of a second copy'
);
check(
	false !== strpos( $menu_js, 'cfg.dietaryLabels' ) && ! preg_match( "/vegetarian:\s*'/", $menu_js ),
	'the card grid takes labels from flavorData instead of restating them'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
