<?php
/**
 * Readability report contract tests.
 *
 * The WCAG formula exists twice: in PHP (class-contrast.php) and in the
 * Customizer JavaScript, which has to recompute a ratio as the merchant
 * drags a colour picker. Two implementations of one formula is exactly how
 * a report ends up disagreeing with the page. Section 5 runs both over the
 * same fixture and asserts they agree.
 *
 *   php tests/theme/test-contrast.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';
require_once __DIR__ . '/fixtures/page-options-stubs.php';
require_once __DIR__ . '/fixtures/preset-control-stubs.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-contrast.php';
require_once FLAVOR_DIR . '/inc/class-contrast-control.php';
require_once FLAVOR_DIR . '/inc/class-dark-mode.php';

use Flavor\Contrast;
use Flavor\Contrast_Control;
use Flavor\Dark_Mode;
use Flavor\Design;
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

echo "=== Flavor Readability Report Contract Tests ===\n\n";

echo "--- 1. The ratio formula matches known WCAG values ---\n";

// Reference values from the WCAG 2.1 understanding document.
$known = array(
	array( '#000000', '#ffffff', 21.0 ),
	array( '#ffffff', '#ffffff', 1.0 ),
	array( '#000000', '#000000', 1.0 ),
	array( '#777777', '#ffffff', 4.48 ),
	array( '#767676', '#ffffff', 4.54 ),
	array( '#888888', '#ffffff', 3.54 ),
);

foreach ( $known as $case ) {
	list( $fg, $bg, $expected ) = $case;
	$actual = Contrast::ratio( $fg, $bg );
	check(
		abs( $actual - $expected ) < 0.02,
		sprintf( '%s on %s = %.2f (expected %.2f)', $fg, $bg, $actual, $expected )
	);
}

check( Contrast::ratio( '#fff', '#000' ) === Contrast::ratio( '#ffffff', '#000000' ), '3-digit and 6-digit hex agree' );
check( 1.0 === Contrast::ratio( '#zzzzzz', '#000000' ), 'a malformed colour degrades to 1.0 rather than dividing by zero' );

echo "\n--- 2. Every pair is well formed ---\n";

$pairs = Contrast::pairs();
check( count( $pairs ) >= 5, 'the report covers the pairs readers actually meet (' . count( $pairs ) . ')');

foreach ( $pairs as $key => $pair ) {
	check( '' !== (string) ( $pair['label'] ?? '' ), "{$key} has a label" );
	check( '' !== (string) ( $pair['fg'] ?? '' ), "{$key} names a foreground token" );
	check( '' !== (string) ( $pair['bg'] ?? '' ), "{$key} names a background token" );
	check( ( $pair['min'] ?? 0 ) > 0, "{$key} states a threshold" );
	check(
		'@button' === $pair['fg'] || in_array( $pair['fg'], array_keys( Design::colour_token_labels() ), true ),
		"{$key}'s foreground is a real token ({$pair['fg']})"
	);
	check(
		in_array( $pair['bg'], array_keys( Design::colour_token_labels() ), true ),
		"{$key}'s background is a real token ({$pair['bg']})"
	);
}

echo "\n--- 3. The report reflects the tokens it is given ---\n";

$good = array(
	'primary'     => '#a44c2a',
	'bg'          => '#f4efe7',
	'surface'     => '#fffaf4',
	'surface_alt' => '#ece4d9',
	'ink'         => '#201c19',
	'muted'       => '#6b625b',
);

$report = Contrast::report( $good );
check( count( $report ) === count( $pairs ), 'every pair is evaluated for a complete palette' );
check( $report['ink_on_bg']['pass'], 'dark ink on a light page passes' );
check( $report['muted_on_surface']['pass'], 'the shipped muted colour passes on its own surface' );
check( empty( Contrast::failing( $good ) ), 'the shipped palette has no failures' );
check( '' === Contrast::summary( $good ), 'a clean palette produces no summary to nag about' );

// Grey on grey: the classic mistake a merchant makes with a picker.
$bad           = $good;
$bad['muted']  = '#f0f0f0';
check( ! empty( Contrast::failing( $bad ) ), 'near-white text on a light surface fails' );
check( false !== strpos( Contrast::summary( $bad ), 'خوانایی' ), 'the summary is written in Persian' );

echo "\n--- 4. It reports, it never blocks ---\n";

$html = Contrast_Control::render_report( $bad );
check( false !== strpos( $html, 'flavor-contrast__row--fail' ), 'a failing row is marked as such' );
check( false !== strpos( $html, 'flavor-contrast__row--pass' ), 'passing rows are still shown' );
check( false !== strpos( $html, 'role="status"' ), 'the report is announced to assistive technology' );
check( false !== strpos( $html, 'flavor-contrast__sample' ), 'each row shows a live sample, not only a number' );
check( false === strpos( $html, '<script' ), 'the markup contains no script' );

// The corrected combination must still be reported as corrected, not hidden.
check( false !== strpos( $html, 'flavor-contrast__warn' ), 'the report warns that the theme auto-corrects' );

$clean_html = Contrast_Control::render_report( $good );
check( false !== strpos( $clean_html, 'flavor-contrast__ok' ), 'a clean palette gets a positive confirmation' );

// An empty palette must not produce false alarms.
check( false !== strpos( Contrast_Control::render_report( array() ), 'flavor-contrast__empty' ), 'an empty palette says so instead of warning' );

echo "\n--- 5. The two implementations of the formula agree ---\n";

// Extract the JS luminance and ratio functions and run them under Node over
// the same fixture the PHP just used, so a rewrite of either one is caught.
$js      = (string) file_get_contents( FLAVOR_DIR . '/assets/js/customizer-contrast.js' );
$fixture = array();

foreach ( array_keys( Design::colour_token_labels() ) as $token ) {
	$fixture[ $token ] = $good[ $token ] ?? '#a44c2a';
}
// Add a few deliberately awkward values.
$fixture['__edge'] = '#010203';

$harness = "
function srgbRaw(v){return v<=0.04045?v/12.92:Math.pow((v+0.055)/1.055,2.4);}
function luminanceRaw(hex){hex=String(hex||'').replace('#','');if(hex.length===3)hex=hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];if(!/^[0-9a-f]{6}\$/i.test(hex))return 0;var r=srgbRaw(parseInt(hex.substring(0,2),16)/255);var g=srgbRaw(parseInt(hex.substring(2,4),16)/255);var b=srgbRaw(parseInt(hex.substring(4,6),16)/255);return 0.2126*r+0.7152*g+0.0722*b;}
function ratioRaw(a,b){var la=luminanceRaw(a),lb=luminanceRaw(b);if(la<=0&&lb<=0)return 1;return (Math.max(la,lb)+0.05)/(Math.min(la,lb)+0.05);}
";

// Pull the real functions out of the shipped file rather than retyping them,
// so the test exercises the code that actually ships.
preg_match( '/function srgb\(value\)\s*\{.*?\n\s*\}/s', $js, $m_srgb );
preg_match( '/function luminance\(hex\)\s*\{.*?\n\s*\}/s', $js, $m_lum );
preg_match( '/function ratio\(a, b\)\s*\{.*?\n\s*\}/s', $js, $m_ratio );

check( ! empty( $m_srgb[0] ) && ! empty( $m_lum[0] ) && ! empty( $m_ratio[0] ), 'the shipped JS exposes the three formula functions' );

if ( ! empty( $m_srgb[0] ) && ! empty( $m_lum[0] ) && ! empty( $m_ratio[0] ) ) {
	$reference = '#ffffff';
	$lines     = array();
	foreach ( $fixture as $key => $hex ) {
		$lines[] = sprintf( '%s %s', $key, $hex );
	}

	$program = $harness
		. $m_srgb[0] . "\n" . $m_lum[0] . "\n" . $m_ratio[0] . "\n"
		. "var out=[];"
		. "var fixture=" . json_encode( $fixture ) . ";"
		. "Object.keys(fixture).forEach(function(k){out.push(k+' '+ratio(fixture[k],'#ffffff').toFixed(4));});"
		. "console.log(out.join('\\n'));";

	$tmp = tempnam( sys_get_temp_dir(), 'flavorc' ) . '.js';
	file_put_contents( $tmp, $program );
	$node_output = shell_exec( 'node ' . escapeshellarg( $tmp ) . ' 2>&1' );
	unlink( $tmp );

	$seen = array();
	foreach ( explode( "\n", (string) $node_output ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = preg_split( '/\s+/', $line );
		if ( 2 !== count( $parts ) ) {
			continue;
		}
		$seen[ $parts[0] ] = (float) $parts[1];
	}

	check( count( $seen ) === count( $fixture ), 'the JS harness returned a ratio for every fixture colour (' . count( $seen ) . '/' . count( $fixture ) . ')' );

	foreach ( $fixture as $key => $hex ) {
		if ( ! isset( $seen[ $key ] ) ) {
			continue;
		}
		$php  = Contrast::ratio( $hex, '#ffffff' );
		$diff = abs( $php - $seen[ $key ] );
		check(
			$diff < 0.0002,
			sprintf( '%s: PHP %.4f vs JS %.4f (Δ %.5f)', $key, $php, $seen[ $key ], $diff )
		);
	}
}

echo "\n--- 6. The shipped presets are all readable ---\n";

foreach ( array_keys( Design::skins() ) as $slug ) {
	$tokens  = Design::tokens( (string) $slug );
	$failing = Contrast::failing( $tokens );
	check(
		empty( $failing ),
		(string) $slug . ' ships with no unreadable text pair' . ( empty( $failing ) ? '' : ' — ' . implode( ', ', array_keys( $failing ) ) )
	);
}

echo "\n--- 7. All twelve skins stay readable in BOTH schemes (prompt 54) ---\n";

/*
 * The night palette is generated per skin by Dark_Mode::palette(); the day
 * palette is the preset itself. Text (ink/muted), links (primary) and the
 * button label are measured on every canvas they can land on — bg, surface
 * and surface_alt — with the WCAG formula and the 4.5:1 bar the prompt sets.
 */
foreach ( array_keys( Design::skins() ) as $slug ) {
	$schemes = array(
		'light' => Design::tokens( (string) $slug ),
		'dark'  => Dark_Mode::palette( (string) $slug ),
	);
	foreach ( $schemes as $scheme => $p ) {
		$fails = array();
		foreach ( array( 'bg', 'surface', 'surface_alt' ) as $bg_key ) {
			foreach ( array( 'ink', 'muted', 'primary' ) as $fg_key ) {
				$ratio = Dark_Mode::contrast( (string) $p[ $fg_key ], (string) $p[ $bg_key ] );
				if ( $ratio < 4.5 ) {
					$fails[] = sprintf( '%s-on-%s=%.2f', $fg_key, $bg_key, $ratio );
				}
			}
		}
		$button_ink = 'dark' === $scheme ? (string) $p['action_ink'] : Contrast::button_ink( $p );
		$ratio      = Dark_Mode::contrast( $button_ink, (string) $p['primary'] );
		if ( $ratio < 4.5 ) {
			$fails[] = sprintf( 'button-on-primary=%.2f', $ratio );
		}
		check(
			array() === $fails,
			(string) $slug . ' (' . $scheme . '): text, link and button pairs all clear 4.5:1' . ( $fails ? ' — ' . implode( ', ', $fails ) : '' )
		);

		/* Every value lands in a CSS custom property, so every colour has to
		   be a valid hex: a bare "e9e8e8" without the hash invalidates each
		   declaration consuming it at computed-value time — the night sheet
		   silently lost --flavor-ink, --flavor-muted, --flavor-accent and
		   --flavor-primary that way before prompt 54 fixed Dark_Mode::ensure. */
		$bad_format = array();
		foreach ( array( 'bg', 'surface', 'surface_alt', 'ink', 'muted', 'line', 'primary', 'accent', 'action_ink', 'theme_color' ) as $key ) {
			if ( isset( $p[ $key ] ) && is_string( $p[ $key ] ) && ! preg_match( '/^#[0-9a-f]{6}$/i', (string) $p[ $key ] ) ) {
				$bad_format[] = $key . '=' . $p[ $key ];
			}
		}
		check(
			array() === $bad_format,
			(string) $slug . ' (' . $scheme . '): every palette colour is a valid CSS hex' . ( $bad_format ? ' — ' . implode( ', ', $bad_format ) : '' )
		);
	}
}

echo "\n--- 8. The stylesheet covers the classes the PHP prints ---\n";

$css  = (string) file_get_contents( FLAVOR_DIR . '/assets/css/customizer-presets.css' );
$html = Contrast_Control::render_report( $bad );

preg_match_all( '/class="(flavor-contrast__[a-z-]+)"/', $html, $used );
foreach ( array_unique( $used[1] ) as $class ) {
	check( false !== strpos( $css, '.' . $class ), ".{$class} is styled" );
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
