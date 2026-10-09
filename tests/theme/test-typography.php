<?php
/**
 * Typography contract tests.
 *
 * The type scale multiplies the body size by a ratio to derive h2..h6. Two
 * things can go wrong that `php -l` will never notice: a ratio that produces
 * a hierarchy that is not actually descending, and a Customizer sanitizer
 * that rejects a value the CSS generator is willing to emit.
 *
 * The second one is not hypothetical. The project's shared
 * sanitize_responsive() falls back to '15px' for anything outside a hard-coded
 * list, so routing a heading weight through it would have written
 * `--flavor-heading-weight: 15px` into the stylesheet.
 *
 *   php tests/theme/test-typography.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';

require_once FLAVOR_DIR . '/inc/class-theme-setup.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-customizer.php';

use Flavor\Customizer;
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

/**
 * Pull one custom property out of a :root block.
 *
 * @param string $css  Stylesheet.
 * @param string $name Property name, e.g. "--flavor-h2".
 * @return string
 */
$var_value = static function ( string $css, string $name ): string {
	if ( preg_match( '/' . preg_quote( $name, '/' ) . ':\s*([^;]+);/i', $css, $m ) ) {
		return trim( $m[1] );
	}
	return '';
};

echo "=== Flavor Typography Contract Tests ===\n\n";

$tokens = Design::typography_tokens();

echo "--- 1. Token definitions are coherent ---\n";

check( count( $tokens ) >= 6, 'typography tokens are declared (' . count( $tokens ) . ')' );

foreach ( $tokens as $key => $token ) {
	check(
		in_array( $token['default'], $token['allowed'], true ),
		"{$key} default ({$token['default']}) is itself an allowed value"
	);
	check( count( $token['allowed'] ) >= 2, "{$key} offers a real choice (" . count( $token['allowed'] ) . ' options)' );
}

echo "\n--- 2. An untouched site emits a sane, descending hierarchy ---\n";

$css = Design::css_variables();

$sizes = array();
foreach ( array( 2, 3, 4, 5, 6 ) as $level ) {
	$raw            = $var_value( $css, '--flavor-h' . $level );
	$sizes[ $level ] = (float) rtrim( $raw, 'px' );
	check( '' !== $raw, "--flavor-h{$level} is emitted ({$raw})" );
}

$descending = true;
for ( $level = 2; $level < 6; $level++ ) {
	if ( $sizes[ $level ] <= $sizes[ $level + 1 ] ) {
		$descending = false;
	}
}
check( $descending, sprintf(
	'h2 > h3 > h4 > h5 > h6 by default (%s)',
	implode( ' > ', array_map( static fn( $v ) => $v . 'px', $sizes ) )
) );
check( $sizes[2] >= 20 && $sizes[2] <= 60, "default h2 is a plausible size ({$sizes[2]}px)" );

echo "\n--- 3. Every ratio keeps the hierarchy descending ---\n";

foreach ( $tokens['flavor_type_scale']['allowed'] as $ratio ) {
	set_theme_mod( 'flavor_type_scale', $ratio );
	$css  = Design::css_variables();
	$step = array();
	foreach ( array( 2, 3, 4, 5, 6 ) as $level ) {
		$step[ $level ] = (float) rtrim( $var_value( $css, '--flavor-h' . $level ), 'px' );
	}

	$ok = true;
	for ( $level = 2; $level < 6; $level++ ) {
		if ( $step[ $level ] <= $step[ $level + 1 ] ) {
			$ok = false;
		}
	}
	check( $ok, "ratio {$ratio} keeps h2 > h3 > h4 > h5 > h6 (" . implode( ' > ', array_map( static fn( $v ) => $v . 'px', $step ) ) . ')' );
	check( $step[2] <= 120, "ratio {$ratio} does not blow the page up (h2 = {$step[2]}px)" );
}
remove_theme_mod( 'flavor_type_scale' );

echo "\n--- 4. Scaling the body size scales the headings with it ---\n";

$baseline = array();
set_theme_mod( 'flavor_body_size', '15px' );
$css = Design::css_variables();
foreach ( array( 2, 3, 4, 5, 6 ) as $level ) {
	$baseline[ $level ] = (float) rtrim( $var_value( $css, '--flavor-h' . $level ), 'px' );
}

set_theme_mod( 'flavor_body_size', '18px' );
$css = Design::css_variables();
$grew = true;
foreach ( array( 2, 3, 4, 5, 6 ) as $level ) {
	$now = (float) rtrim( $var_value( $css, '--flavor-h' . $level ), 'px' );
	if ( $now <= $baseline[ $level ] ) {
		$grew = false;
	}
}
check( $grew, 'a larger body size produces larger headings at every level' );

set_theme_mod( 'flavor_body_size', '15px' );
$css = Design::css_variables();

echo "\n--- 5. The sanitizer accepts everything the generator can emit ---\n";

// This is the guard against the bug described at the top of this file.
foreach ( $tokens as $key => $token ) {
	$values = array_values( array_unique( array_merge( $token['allowed'], array( $token['default'] ) ) ) );
	$bad    = array();
	foreach ( $values as $value ) {
		if ( Customizer::sanitize_typography( $value ) !== $value ) {
			$bad[] = $value;
		}
	}
	check(
		array() === $bad,
		"sanitize_typography() accepts every value {$key} can produce" . ( $bad ? ' — rejected: ' . implode( ', ', $bad ) : '' )
	);
}

echo "\n--- 6. The sanitizer rejects junk ---\n";

foreach ( array( '15px', 'bold', '', '1.25; color: red', '../../etc/passwd', '999' ) as $junk ) {
	$result = Customizer::sanitize_typography( $junk );
	check(
		'' === $result || in_array( $result, array_merge( ...array_column( $tokens, 'allowed' ) ), true ),
		'sanitize_typography() neutralises ' . ( '' === $junk ? '(empty)' : "\"{$junk}\"" ) . ' -> "' . $result . '"'
	);
}

echo "\n--- 7. The emitted stylesheet actually applies the tokens ---\n";

$css = Design::css_variables();

check( false !== strpos( $css, 'font-weight: var(--flavor-body-weight)' ), 'body weight token is applied' );
check( false !== strpos( $css, 'line-height: var(--flavor-body-line-height)' ), 'body line-height token is applied' );
check( false !== strpos( $css, 'font-weight: var(--flavor-heading-weight)' ), 'heading weight token is applied' );
check( false !== strpos( $css, 'letter-spacing: var(--flavor-heading-letter-spacing)' ), 'heading letter-spacing token is applied' );
check( false !== strpos( $css, 'font-size: var(--flavor-h2)' ), 'h2 uses the computed scale' );

// The hero override must stay more specific than the h1 rule, or a scale
// change would silently break every demo's hero.
check(
	false !== strpos( $css, 'body.flavor-theme .flavor-hero h1' ),
	'the hero h1 override is preserved alongside the scale'
);

echo "\n--- 8. A Persian-first default for letter spacing ---\n";

// Persian letters join; any non-zero tracking breaks words apart. The default
// must therefore be zero, whatever the other options offer.
check( '0em' === $tokens['flavor_heading_letter_spacing']['default'], 'default heading letter-spacing is 0em' );
$untouched = Design::css_variables();
check( '0em' === $var_value( $untouched, '--flavor-heading-letter-spacing' ), 'an untouched site ships 0em tracking' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
