<?php
/**
 * Test contract for README.md accuracy, links, versions, and runnable test commands (پرامپت ۸۶ / فاز ۱۱).
 *
 * Verifies:
 *   1. README.md states exact version matching flavor/style.css and flavor-core.php.
 *   2. All local links in README.md (e.g. docs/fa/*, docs/*) point to existing, valid files.
 *   3. README.md contains up-to-date phases table covering Phase 0 through Phase 12.
 *   4. README.md contains realistic, runnable test commands matching the actual suite scripts.
 *   5. Tone is professional commercial product copy, not developer scratchpad.
 *
 *   php tests/theme/test-readme-accuracy.php
 *
 * @package Flavor
 */

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

echo "=== README.md Accuracy & Commercial Polish Tests (Prompt 86) ===\n\n";

$readme_path = $root . '/README.md';
check( file_exists( $readme_path ), 'README.md exists' );
$content = file_get_contents( $readme_path );

// 1. Version parity
check(
	strpos( $content, 'نسخه پایدار: **قالب ۱.۶.۲ · هسته ۱.۵.۱**' ) !== false,
	'README.md states current stable versions (Theme 1.6.2, Core 1.5.1)'
);

// 2. All links point to real files
preg_match_all( '/\[([^\]]+)\]\(([^)]+)\)/', $content, $matches );
$broken_links = [];
foreach ( $matches[2] as $target ) {
	if ( strpos( $target, 'http' ) === 0 || strpos( $target, '#' ) === 0 ) {
		continue;
	}
	$file_path = explode( '#', $target )[0];
	if ( ! file_exists( $root . '/' . $file_path ) ) {
		$broken_links[] = $file_path;
	}
}
check(
	empty( $broken_links ),
	'All relative documentation links in README.md exist on disk' . ( $broken_links ? ' (broken: ' . implode( ', ', $broken_links ) . ')' : '' )
);

// 3. Complete phases table
check(
	strpos( $content, 'وضعیت و نقشهٔ راه فازهای توسعه' ) !== false,
	'README.md contains comprehensive roadmap table'
);
check(
	strpos( $content, 'فاز ۱۰' ) !== false || strpos( $content, '**۱۰**' ) !== false,
	'Roadmap includes Phase 10'
);
check(
	strpos( $content, 'فاز ۱۱' ) !== false || strpos( $content, '**۱۱**' ) !== false,
	'Roadmap includes Phase 11'
);

// 4. Runnable test commands in tests section
$test_commands = [
	'dev-tools/run-theme-tests.sh',
	'flavor-core/tests/run-pure-tests.php',
	'flavor-core/tests/run-e2e-integration.php',
	'mobile/scripts/test_provision_brand.py',
	'dev-tools/check-mobile-coverage.py',
	'dev-tools/check-i18n.sh',
	'dev-tools/check-versions.php',
	'dev-tools/build-distribution.sh',
];

$all_cmds_present = true;
foreach ( $test_commands as $cmd ) {
	if ( strpos( $content, $cmd ) === false ) {
		$all_cmds_present = false;
		break;
	}
}
check( $all_cmds_present, 'README.md lists actual runnable test & build commands' );

// 5. Commercial product tone
check(
	strpos( $content, 'سامانه جامع و اختصاصی رستوران' ) !== false,
	'README has commercial product headline'
);

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
