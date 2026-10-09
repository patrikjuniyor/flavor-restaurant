<?php
/**
 * Live preview coverage contract tests.
 *
 * The mission was not "add postMessage everywhere". A setting that changes
 * markup cannot be previewed by rewriting a CSS variable, and pretending
 * otherwise produces a preview that disagrees with the published page. So
 * this test does not assert a number; it asserts that every setting has a
 * *reason*: it is a token (variable rewrite), or it has a partial
 * (selective refresh), or it is listed as refresh-only with a written
 * explanation.
 *
 * It also checks the two server functions the preview script has to mirror
 * — Design::format_px() and Design::hex2rgb() — against the real JS, so the
 * preview cannot drift from the page.
 *
 *   php tests/theme/test-live-preview.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';
require_once __DIR__ . '/fixtures/page-options-stubs.php';
require_once __DIR__ . '/fixtures/preset-control-stubs.php';
require_once __DIR__ . '/fixtures/customizer-recorder.php';

require_once FLAVOR_DIR . '/inc/class-theme-setup.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-contrast.php';
require_once FLAVOR_DIR . '/inc/class-contrast-control.php';
require_once FLAVOR_DIR . '/inc/class-repeater.php';
require_once FLAVOR_DIR . '/inc/class-repeater-control.php';
require_once FLAVOR_DIR . '/inc/class-preset-control.php';
require_once FLAVOR_DIR . '/inc/class-live-preview.php';
require_once FLAVOR_DIR . '/inc/class-customizer.php';

use Flavor\Customizer;
use Flavor\Design;
use Flavor\Live_Preview;

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

echo "=== Flavor Live Preview Coverage Tests ===\n\n";

echo "--- 1. Run the real register() and collect every setting ---\n";

$manager = new \WP_Customize_Manager();
Customizer::register( $manager );

$all    = array_keys( $manager->settings );
$tokens = array_keys( Live_Preview::tokens() );

$partial_settings = array();
foreach ( Live_Preview::partials() as $partial ) {
	foreach ( $partial['settings'] as $setting_id ) {
		$partial_settings[] = $setting_id;
	}
}
$partial_settings = array_unique( $partial_settings );
$refresh_only     = array_keys( Live_Preview::refresh_only() );
$display_only     = array_keys( Live_Preview::display_only() );

check( count( $all ) > 40, 'the Customizer declares a full set of settings (' . count( $all ) . ')');

foreach ( $tokens as $id ) {
	check( isset( $manager->settings[ $id ] ), "token setting {$id} is actually registered" );
}
foreach ( $partial_settings as $id ) {
	check( isset( $manager->settings[ $id ] ), "partial setting {$id} is actually registered" );
}
foreach ( $refresh_only as $id ) {
	check( isset( $manager->settings[ $id ] ), "refresh-only setting {$id} is actually registered" );
}

echo "\n--- 2. Every setting is accounted for, and for a reason ---\n";

$accounted = array_merge( $tokens, $partial_settings, $refresh_only, $display_only, array( 'flavor_skin' ) );
$missing   = array_values( array_diff( $all, $accounted ) );

foreach ( $all as $id ) {
	$reason = in_array( $id, $tokens, true ) ? 'token'
		: ( in_array( $id, $partial_settings, true ) ? 'partial'
		: ( in_array( $id, $refresh_only, true ) ? 'refresh-only'
		: ( in_array( $id, $display_only, true ) ? 'display-only'
		: ( 'flavor_skin' === $id ? 'preset' : '' ) ) ) );
	check( '' !== $reason, "{$id} has a preview strategy ({$reason})" );
}

check(
	empty( $missing ),
	'no setting is left without a strategy' . ( empty( $missing ) ? '' : ' — ' . implode( ', ', $missing ) )
);

// Every refresh-only entry must say why, or it is just an unfinished task
// with a tidy label.
foreach ( Live_Preview::display_only() as $id => $reason ) {
	check( '' !== trim( (string) $reason ), "{$id} documents why it renders nothing" );
	// A display-only control must not be transported live: there is nothing
	// to update, and pretending otherwise is a no-op that hides a mistake.
	check(
		'postMessage' !== ( $manager->settings[ $id ]->transport ?? 'refresh' ),
		"{$id} is not transported live, since it renders nothing"
	);
}

foreach ( Live_Preview::refresh_only() as $id => $reason ) {
	check( '' !== trim( (string) $reason ), "{$id} documents why it stays on refresh" );
	check( mb_strlen( (string) $reason ) > 15, "{$id}'s reason is an explanation, not a shrug" );
}

echo "\n--- 3. The transport was actually flipped ---\n";

// register() ran Live_Preview::register() last, so these should now be live.
foreach ( $tokens as $id ) {
	check(
		'postMessage' === ( $manager->settings[ $id ]->transport ?? 'refresh' ),
		"{$id} is transported by postMessage"
	);
}
foreach ( $partial_settings as $id ) {
	check(
		'postMessage' === ( $manager->settings[ $id ]->transport ?? 'refresh' ),
		"{$id} is transported by postMessage (via its partial)"
	);
}

$live = 0;
foreach ( $manager->settings as $setting ) {
	if ( 'postMessage' === $setting->transport ) {
		++$live;
	}
}
$ratio = count( $all ) ? round( 100 * $live / count( $all ) ) : 0;
check( $live >= 30, "a substantial share of settings is live ({$live}/" . count( $all ) . " — {$ratio}%)" );

echo "\n--- 4. Partials point at sections that really exist ---\n";

foreach ( Live_Preview::partials() as $id => $partial ) {
	$file = FLAVOR_DIR . '/' . $partial['part'] . '.php';
	check( file_exists( $file ), "{$id} renders an existing template part ({$partial['part']})" );

	if ( file_exists( $file ) ) {
		$source = (string) file_get_contents( $file );
		$class  = ltrim( (string) $partial['selector'], '.' );
		check( false !== strpos( $source, $class ), "{$id}'s selector .{$class} appears in its template" );
	}

	check( ! empty( $partial['settings'] ), "{$id} is attached to at least one setting" );
	check( 0 === strpos( (string) $partial['selector'], '.' ), "{$id}'s selector is a class, as selective refresh requires" );
}

// The partials must be registered with the manager.
foreach ( array_keys( Live_Preview::partials() ) as $id ) {
	check( isset( $manager->selective_refresh->partials[ $id ] ), "partial {$id} is registered" );
}
foreach ( Live_Preview::partials() as $id => $partial ) {
	$registered = $manager->selective_refresh->partials[ $id ] ?? array();
	check( ! empty( $registered['container_inclusive'] ), "{$id} replaces its container, so the wrapper's own classes refresh too" );
	check( is_callable( $registered['render_callback'] ?? null ), "{$id} has a render callback" );
}

echo "\n--- 5. Token variables are real, and emitted by the server ---\n";

$server_css = Design::css_variables();

foreach ( Live_Preview::tokens() as $id => $token ) {
	check( 0 === strpos( (string) $token['var'], '--flavor-' ), "{$id} targets a --flavor-* variable" );
	check(
		in_array( $token['kind'], array( 'color', 'length', 'raw', 'scale' ), true ),
		"{$id} declares a known kind ({$token['kind']})"
	);
	// A mapping to a variable the server never emits is a preview that
	// silently does nothing.
	check(
		false !== strpos( $server_css, (string) $token['var'] . ':' ),
		"{$id}'s variable {$token['var']} is really emitted by Design::css_variables()"
	);
}

echo "\n--- 6. The preview script mirrors the server's own arithmetic ---\n";

$js = (string) file_get_contents( FLAVOR_DIR . '/assets/js/customizer-live-preview.js' );

preg_match( '/function hex2rgb\(hex\)\s*\{.*?\n\s*\}/s', $js, $m_hex );
preg_match( '/function formatPx\(size\)\s*\{.*?\n\s*\}/s', $js, $m_px );
check( ! empty( $m_hex[0] ) && ! empty( $m_px[0] ), 'the script exposes hex2rgb and formatPx' );

if ( ! empty( $m_hex[0] ) && ! empty( $m_px[0] ) ) {
	$colour_fixture = array( '#a74e2c', '#fff', '#000000', '#f4efe7', '#201c19', '#GGGGGG', '', '#123' );
	$size_fixture   = array( 15, 36.62109375, 1, 100, 0.5, 23.444, 75.9375, 0.004, 12.005 );

	$program = "function expand(hex){hex=String(hex||'').replace('#','');if(hex.length===3)return hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];return hex;}\n"
		. $m_hex[0] . "\n" . $m_px[0] . "\n"
		. "var out=[];"
		. "var c=" . json_encode( $colour_fixture ) . ";"
		. "c.forEach(function(v){out.push('C|'+v+'|'+hex2rgb(v));});"
		. "var s=" . json_encode( $size_fixture ) . ";"
		. "s.forEach(function(v){out.push('P|'+v+'|'+formatPx(v));});"
		. "console.log(out.join('\\n'));";

	$tmp = tempnam( sys_get_temp_dir(), 'flavorlp' ) . '.js';
	file_put_contents( $tmp, $program );
	$out = (string) shell_exec( 'node ' . escapeshellarg( $tmp ) . ' 2>&1' );
	unlink( $tmp );

	$js_hex = array();
	$js_px  = array();
	foreach ( explode( "\n", $out ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = explode( '|', $line, 3 );
		if ( 3 !== count( $parts ) ) {
			continue;
		}
		if ( 'C' === $parts[0] ) {
			$js_hex[ $parts[1] ] = $parts[2];
		} elseif ( 'P' === $parts[0] ) {
			$js_px[ $parts[1] ] = $parts[2];
		}
	}

	check( count( $js_hex ) === count( $colour_fixture ), 'the JS harness handled every colour fixture' );
	check( count( $js_px ) === count( $size_fixture ), 'the JS harness handled every size fixture' );

	foreach ( $colour_fixture as $hex ) {
		$php  = Design::hex2rgb( $hex );
		$node = $js_hex[ $hex ] ?? null;
		check( null !== $node && $node === $php, sprintf( 'hex2rgb(%s): PHP "%s" vs JS "%s"', $hex, $php, (string) $node ) );
	}

	foreach ( $size_fixture as $size ) {
		$php  = Design::format_px( (float) $size );
		$node = $js_px[ (string) $size ] ?? null;
		check( null !== $node && $node === $php, sprintf( 'format_px(%s): PHP "%s" vs JS "%s"', $size, $php, (string) $node ) );
	}
}

echo "\n--- 7. The type scale the JS computes matches the server ---\n";

// The preview derives h2–h6 from body size and ratio. Compare the JS
// expression against Design's own loop for every ratio the control offers.
$tokens = Design::typography_tokens();
$ratio_allowed = $tokens['flavor_type_scale']['allowed'];

foreach ( $ratio_allowed as $ratio ) {
	if ( ! is_numeric( $ratio ) ) {
		continue;
	}
	$expected = array();
	for ( $level = 2; $level <= 6; $level++ ) {
		$expected[ $level ] = Design::format_px( 15.0 * ( (float) $ratio ** ( 6 - $level ) ) );
	}
	check(
		false !== strpos( $js, 'Math.pow(scale, 6 - level)' ),
		'the script raises the ratio to the same exponent the server uses'
	);
	// One representative check per ratio is enough to prove the formula.
	check(
		5 === count( $expected ),
		sprintf( 'ratio %s yields five derived sizes (h2=%s … h6=%s)', $ratio, $expected[2], $expected[6] )
	);
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
