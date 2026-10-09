<?php
/**
 * Customizer field guidance tests (N-14).
 *
 * The acceptance criterion was "every check leads to the help for that
 * action". Concretely for the Customizer, that means a merchant is never
 * left looking at a control with no idea what it does. The guidance lives
 * in the control's `description`, which core renders inline and which
 * travels with the translation catalogue.
 *
 * This test exists because guidance decays: someone adds a control at
 * midnight, and a year later nobody remembers why it has no help text.
 * Guarding the invariant is cheaper than re-discovering it.
 *
 *   php tests/theme/test-customizer-guidance.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

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

$customizer = FLAVOR_DIR . '/inc/class-customizer.php';

echo "=== Flavor Customizer Guidance Tests ===\n\n";

echo "--- 1. The file is readable ---\n";

check( is_readable( $customizer ), 'class-customizer.php is readable' );

$source = (string) file_get_contents( $customizer );
$lines  = explode( "\n", $source );

echo "\n--- 2. Every control carries inline guidance ---\n";

// Controls are registered with add_control(). Some are one-liners, some
// span a dozen lines, and two are generated inside foreach loops — so the
// look-ahead has to be generous enough for the longest block.
$total     = 0;
$unguided  = array();

foreach ( $lines as $i => $line ) {
	if ( false === strpos( $line, 'add_control(' ) ) {
		continue;
	}
	++$total;

	$block = implode( "\n", array_slice( $lines, $i, 14 ) );
	if ( false !== strpos( $block, "'description'" ) ) {
		continue;
	}

	// Report the line rather than the id: the generated ones have no
	// literal id to print, and the line is what someone needs to fix it.
	$unguided[] = $i + 1;
}

check( $total > 30, 'the Customizer registers a meaningful number of controls (' . $total . ')' );
check(
	empty( $unguided ),
	'every control has a description'
	. ( empty( $unguided ) ? '' : ' — missing at line(s): ' . implode( ', ', $unguided ) )
);

echo "\n--- 3. The guidance is translatable, not hardcoded ---\n";

// A description that is not wrapped in __() is invisible to translators,
// which quietly makes the theme English-only in the one place a Persian
// merchant reads most.
$descriptions = array();
preg_match_all( "/'description'\s*=>\s*__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $descriptions );
$translatable = count( $descriptions[1] );

preg_match_all( "/'description'\s*=>\s*'/", $source, $hardcoded );
$raw = count( $hardcoded[0] );

check( $translatable >= 30, 'most descriptions go through __() (' . $translatable . ')' );
check( 0 === $raw, 'no description bypasses the text domain (' . $raw . ' hardcoded)' );

echo "\n--- 4. The guidance says something ---\n";

// A description of "توضیحات" is a placeholder wearing a description's
// clothes. Require a minimum length so a stub cannot satisfy the check.
$short = array();
foreach ( $descriptions[1] as $text ) {
	if ( mb_strlen( $text ) < 25 ) {
		$short[] = $text;
	}
}

check(
	empty( $short ),
	'no description is too short to be useful'
	. ( empty( $short ) ? '' : ' — ' . implode( ' | ', array_slice( $short, 0, 3 ) ) )
);

echo "\n--- 5. The two generated loops are guided too ---\n";

// The colour and responsive loops build controls with a variable id, so
// they are the easiest to forget and the ones a future edit is most likely
// to break. Both are asserted by name.
check(
	false !== strpos( $source, "'section' => 'flavor_section_colors'" ),
	'the colour section still exists'
);
check(
	false !== strpos( $source, "'section' => 'flavor_section_layout'" ),
	'the layout section still exists'
);

// The colour loop's guidance must mention that blank falls back to the skin.
$colour_guided = preg_match(
	"/'description'\s*=>\s*__\(\s*'[^']*(پوسته|خالی)[^']*'/u",
	$source
);
check( (bool) $colour_guided, 'the generated colour controls explain the skin fallback' );

// The responsive loop's guidance must state the breakpoints are independent,
// which is N-02's acceptance criterion.
$responsive_guided = preg_match(
	"/'description'\s*=>\s*__\(\s*'[^']*(موبایل|اندازه)[^']*'/u",
	$source
);
check( (bool) $responsive_guided, 'the generated responsive controls explain breakpoint independence' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
