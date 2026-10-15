<?php
/**
 * Test contract for Mobile Test Coverage CI Gate (پرامپت ۸۳ / فاز ۱۰).
 *
 * Verifies:
 *   1. ci.yml flutter-mobile-ci job runs flutter test --coverage.
 *   2. ci.yml enforces the coverage threshold (60%) via check-mobile-coverage.py or equivalent.
 *   3. ci.yml uploads mobile coverage artifacts (lcov.info and summary).
 *   4. dev-tools/check-mobile-coverage.py exists, is executable, and properly enforces threshold.
 *   5. Real mobile test coverage calculation passes >= 60.0%.
 *
 *   php tests/theme/test-mobile-ci-coverage.php
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

echo "=== Mobile CI Coverage Gate Contract Tests (Prompt 83) ===\n\n";

// 1. Check CI workflow configuration
$ci_path = $root . '/.github/workflows/ci.yml';
check( file_exists( $ci_path ), 'ci.yml exists' );
$ci_content = file_get_contents( $ci_path );

check( strpos( $ci_content, 'flutter-mobile-ci:' ) !== false, 'flutter-mobile-ci job defined in ci.yml' );
check( strpos( $ci_content, 'flutter test --coverage' ) !== false, 'runs flutter test with --coverage flag' );
check( strpos( $ci_content, 'check-mobile-coverage.py' ) !== false, 'ci.yml invokes check-mobile-coverage.py' );
check( strpos( $ci_content, '--threshold 60' ) !== false || strpos( $ci_content, '--threshold=60' ) !== false || strpos( $ci_content, '60.0' ) !== false, 'ci.yml enforces 60% coverage threshold' );
check( (bool) preg_match( '/actions\/upload-artifact@(?:v4|[a-f0-9]+[ \t]*#.*v4)/i', $ci_content ), 'ci.yml uses upload-artifact@v4' );
check( strpos( $ci_content, 'mobile-test-coverage' ) !== false, 'ci.yml archives mobile-test-coverage artifact' );

// 2. Check coverage checker tool
$tool_path = $root . '/dev-tools/check-mobile-coverage.py';
check( file_exists( $tool_path ), 'dev-tools/check-mobile-coverage.py exists' );
check( is_executable( $tool_path ), 'dev-tools/check-mobile-coverage.py is executable' );

// 3. Test the tool behavior against fake lcov data
$tmp_lcov = sys_get_temp_dir() . '/test-lcov-' . uniqid() . '.info';
// 70% coverage fixture
file_put_contents(
	$tmp_lcov,
	"SF:lib/sample.dart\n" .
	"DA:1,1\n" .
	"DA:2,1\n" .
	"DA:3,1\n" .
	"DA:4,1\n" .
	"DA:5,1\n" .
	"DA:6,1\n" .
	"DA:7,1\n" .
	"DA:8,0\n" .
	"DA:9,0\n" .
	"DA:10,0\n" .
	"end_of_record\n"
);

$output = [];
$exit_code = 0;
exec( escapeshellcmd( "python3 {$tool_path} --lcov {$tmp_lcov} --threshold 60.0" ), $output, $exit_code );
check( 0 === $exit_code, 'check-mobile-coverage.py succeeds when coverage (70%) >= threshold (60%)' );

$output = [];
$exit_code = 0;
exec( escapeshellcmd( "python3 {$tool_path} --lcov {$tmp_lcov} --threshold 80.0" ), $output, $exit_code );
check( 1 === $exit_code, 'check-mobile-coverage.py fails when coverage (70%) < threshold (80%)' );

unlink( $tmp_lcov );

// 4. Verify actual lcov if it exists
$actual_lcov = $root . '/mobile/coverage/lcov.info';
if ( file_exists( $actual_lcov ) ) {
	$output = [];
	$exit_code = 0;
	exec( escapeshellcmd( "python3 {$tool_path} --lcov {$actual_lcov} --threshold 60.0" ), $output, $exit_code );
	check( 0 === $exit_code, 'current mobile codebase passes real 60% coverage threshold' );
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ( $failed > 0 ) {
	exit( 1 );
}
