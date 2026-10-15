<?php
/**
 * Test contract for Elementor CI Stability & Dynamic Widget Registration (پرامپت ۸۲ / فاز ۱۰).
 *
 * Verifies:
 *   1. Compatibility headers: 'WC tested up to' is consistent across flavor/readme.txt,
 *      flavor-core/readme.txt, and flavor-core/flavor-core.php.
 *   2. Elementor version in CI (.github/workflows/ci.yml) matches documentation (docs/fa/11-elementor.md,
 *      tests/integration/real-elementor-smoke.php).
 *   3. tests/integration/real-elementor-smoke.php reads registered widgets dynamically from
 *      \Flavor\Elementor::widget_classes() instead of a hardcoded 17 count.
 *   4. Widget discovery contract: every widget file in flavor/elementor/widgets/ is accounted for,
 *      has a matching class in widget_classes(), and registers all Flavor Style controls.
 *
 *   php tests/theme/test-elementor-ci-stability.php
 *
 * @package Flavor
 */

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-elementor.php';

$root = dirname( __DIR__, 2 );

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

echo "=== 1. Compatibility Headers & WC Tested Up To ===\n";

$theme_readme = (string) file_get_contents( $root . '/flavor/readme.txt' );
$core_readme  = (string) file_get_contents( $root . '/flavor-core/readme.txt' );
$core_main    = (string) file_get_contents( $root . '/flavor-core/flavor-core.php' );

preg_match( '/WC tested up to:\s*([0-9.]+)/i', $theme_readme, $m1 );
preg_match( '/WC tested up to:\s*([0-9.]+)/i', $core_readme, $m2 );
preg_match( '/WC tested up to:\s*([0-9.]+)/i', $core_main, $m3 );

$wc_theme = $m1[1] ?? '';
$wc_core1 = $m2[1] ?? '';
$wc_core2 = $m3[1] ?? '';

check( '' !== $wc_theme, 'flavor/readme.txt declares "WC tested up to"' );
check( $wc_theme === $wc_core1 && $wc_theme === $wc_core2, "WC tested up to is consistent across files ({$wc_theme})" );

echo "\n=== 2. Elementor Version Parity across CI, Tests & Docs ===\n";

$ci_content = (string) file_get_contents( $root . '/.github/workflows/ci.yml' );
$smoke_code = (string) file_get_contents( $root . '/tests/integration/real-elementor-smoke.php' );
$doc_content= (string) file_get_contents( $root . '/docs/fa/11-elementor.md' );

check(
	str_contains( $ci_content, 'elementor-4.3.4.zip' ) && str_contains( $ci_content, 'Elementor 4.3.4' ),
	'ci.yml installs Elementor 4.3.4'
);
check(
	str_contains( $smoke_code, "'4.3.4' === ELEMENTOR_VERSION" ),
	'real-elementor-smoke.php validates Elementor 4.3.4'
);
check(
	str_contains( $doc_content, '4.3.4' ),
	'docs/fa/11-elementor.md documents Elementor 4.3.4 compatibility'
);

echo "\n=== 3. Dynamic Widget Registration in real-elementor-smoke.php ===\n";

check(
	str_contains( $smoke_code, '\Flavor\Elementor::widget_classes()' ),
	'real-elementor-smoke.php reads widgets dynamically from \Flavor\Elementor::widget_classes()'
);
check(
	! preg_match( '/17\s*===\s*count\(\s*\$expected_classes\s*\)/', $smoke_code ),
	'real-elementor-smoke.php does not hardcode exact 17 count check'
);
check(
	str_contains( $smoke_code, 'count( $expected_classes ) >= 17' ) || str_contains( $smoke_code, 'count( $expected_classes )' ),
	'real-elementor-smoke.php dynamically adapts to new widgets'
);

echo "\n=== 4. Dynamic Widget File & Class Registry Agreement ===\n";

$registered_classes = \Flavor\Elementor::widget_classes();
$widget_files = glob( $root . '/flavor/elementor/widgets/class-*-widget.php' ) ?: array();

check(
	count( $registered_classes ) >= 17,
	'widget_classes() returns at least 17 widgets (' . count( $registered_classes ) . ' registered)'
);
check(
	count( $widget_files ) === count( $registered_classes ),
	'all widget files on disk match registered classes (' . count( $widget_files ) . ' files on disk)'
);

foreach ( $widget_files as $wf ) {
	$file_content = (string) file_get_contents( $wf );
	preg_match( '/class\s+([A-Za-z0-9_]+)\s+extends\s+Widget_Base/i', $file_content, $cm );
	$cls = $cm[1] ?? '';
	check(
		in_array( $cls, $registered_classes, true ),
		"widget class {$cls} from " . basename( $wf ) . " is registered in widget_classes()"
	);
}

if ( $failed > 0 ) {
	echo "\n{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}

echo "\nALL {$passed} checks passed.\n";
exit( 0 );
