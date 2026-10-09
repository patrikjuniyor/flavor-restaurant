<?php
/**
 * Template-tag contract tests.
 *
 * flavour_substr() exists because the theme called mb_substr() directly.
 * WordPress polyfills that function, so it worked — but relying on a
 * polyfill loaded by some other component is a hidden dependency whose
 * failure mode is a fatal error, not a wrong character.
 *
 * The test that matters is the one that fails on a byte-based substr(): a
 * two-byte Persian character cut in half yields a replacement character,
 * so the avatar initial becomes "�" instead of "س".
 *
 *   php tests/theme/test-template-tags.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/template-tags.php';

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

echo "=== Flavor Template Tag Tests ===\n\n";

echo "--- 1. Multi-byte text is cut on character boundaries ---\n";

// Every one of these is at least two bytes per character in UTF-8, so a
// byte-based substr() returns half a character.
$cases = array(
	array( 'سارا محمدی', 0, 1, 'س' ),
	array( 'سارا محمدی', 0, 4, 'سارا' ),
	array( 'کیان رضایی', 5, 5, 'رضایی' ),
	array( 'مریم شفیعی', 0, 0, 'مریم شفیعی' ),
	array( 'کباب کوبیده', 0, 4, 'کباب' ),
);

foreach ( $cases as $case ) {
	list( $text, $start, $length, $expected ) = $case;
	$actual = flavor_substr( $text, $start, $length );
	check(
		$actual === $expected,
		sprintf( 'flavor_substr(%s, %d, %d) = %s', $text, $start, $length, $actual )
	);
	// A broken cut produces U+FFFD; assert it never appears.
	check( false === strpos( $actual, "\u{FFFD}" ), 'no replacement character in the result' );
}

// The proof that this test has teeth: a byte-based cut is genuinely wrong here.
$byte_cut = substr( 'سارا محمدی', 0, 1 );
check( 1 === strlen( $byte_cut ) && '' !== $byte_cut, 'a byte-based cut returns one byte (the bug this guards against)' );
check( $byte_cut !== flavor_substr( 'سارا محمدی', 0, 1 ), 'the helper does not behave like substr()' );

echo "\n--- 2. Latin and mixed text behave too ---\n";

check( 'Fla' === flavor_substr( 'Flavor', 0, 3 ), 'Latin text cuts normally' );
check( 'vor' === flavor_substr( 'Flavor', 3, 3 ), 'a non-zero start works' );
check( 'Flavor' === flavor_substr( 'Flavor', 0, 0 ), 'a zero length means the rest' );
check( 'سارا Sara' === flavor_substr( 'سارا Sara', 0, 0 ), 'mixed-script text round-trips' );
check( '' === flavor_substr( '', 0, 5 ), 'an empty string stays empty' );

// Offsets past either end must not warn or throw, and must follow the same
// convention mb_substr() does: a negative start counts back from the end.
check( '' === flavor_substr( 'سارا', 99, 3 ), 'an offset past the end yields an empty string' );
check( 'سار' === flavor_substr( 'سارا', -99, 3 ), 'a negative offset far past the start clamps to the beginning' );
check( 'ا' === flavor_substr( 'سارا', -1, 1 ), 'a negative offset counts back from the end' );
check( 'را' === flavor_substr( 'سارا', -2, 2 ), 'a negative offset with a length works' );

echo "\n--- 3. Emoji and combining marks survive ---\n";

// A four-byte emoji is the easiest thing to split by accident.
check( '🍕' === flavor_substr( '🍕🍔', 0, 1 ), 'a four-byte emoji stays whole' );
check( '🍔' === flavor_substr( '🍕🍔', 1, 1 ), 'the second emoji stays whole' );
check( false === strpos( flavor_substr( '🍕🍔', 0, 1 ), "\u{FFFD}" ), 'no replacement character from an emoji cut' );

echo "\n--- 4. Both code paths agree ---\n";

// The fallback runs only where mbstring is missing — the least-tested
// machine in the fleet. Running it here over the same fixtures is the only
// way it gets exercised at all.
$fixtures = array(
	array( 'سارا محمدی', 0, 4 ),
	array( 'سارا محمدی', 5, 5 ),
	array( 'کیان رضایی', 0, 0 ),
	array( 'مریم شفیعی', 2, 3 ),
	array( 'Flavor', 0, 3 ),
	array( 'Flavor', 3, 3 ),
	array( 'Flavor', 0, 0 ),
	array( '🍕🍔', 0, 1 ),
	array( '🍕🍔', 1, 1 ),
	array( 'سارا', -2, 2 ),
	array( 'سارا', 99, 3 ),
	array( '', 0, 5 ),
);

foreach ( $fixtures as $case ) {
	list( $text, $start, $length ) = $case;
	$via_mb        = flavor_substr( $text, $start, $length );
	$via_fallback  = flavor_substr_fallback( $text, $start, $length );
	check(
		$via_mb === $via_fallback,
		sprintf(
			'both paths agree on (%s, %d, %d): "%s" vs "%s"',
			$text,
			$start,
			$length,
			$via_mb,
			$via_fallback
		)
	);
}

echo "\n--- 5. The helper is what the templates now call ---\n";

// T-01 was about the call sites, not just the helper: every unguarded
// mb_substr() in the theme had to be routed through it.
$directories = array( FLAVOR_DIR, FLAVOR_DIR . '/inc', FLAVOR_DIR . '/template-parts' );
$offenders   = array();

$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( FLAVOR_DIR ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}
	// The helper itself is allowed to call mb_substr behind a guard.
	if ( basename( $file->getPathname() ) === 'template-tags.php' ) {
		continue;
	}
	$source = (string) file_get_contents( $file->getPathname() );
	if ( preg_match( '/(?<!function_exists\(\s.)\bmb_substr\s*\(/', $source ) ) {
		$offenders[] = str_replace( FLAVOR_DIR . '/', '', $file->getPathname() );
	}
}

check(
	empty( $offenders ),
	'no unguarded mb_substr() remains in the theme' . ( empty( $offenders ) ? '' : ' — ' . implode( ', ', $offenders ) )
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
