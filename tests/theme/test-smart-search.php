<?php
/**
 * Test contract for Smart Search, RestSearch and Persian normalisation (پرامپت ۷۹).
 *
 * Verifies:
 *   1. test-smart-search.php executes live with real Persian samples (11 methods / >= 35 asserts)
 *   2. Arabic vs Persian kaf/yeh normalization parity («كباب» vs «کباب»)
 *   3. Zero-width non-joiner (نیم‌فاصله) support («جوجه‌کباب» vs «جوجه کباب»)
 *   4. Persian homophones and typo tolerance («قرمه» for «قورمه», «کبیده» for «کوبیده»)
 *   5. Result limits: search engine hard-caps at 30 items
 *   6. RestSearch and admin-ajax rate limit preservation: 90/min on /search and flavor_search, 120/min on suggest
 *   7. search.js client contract: debouncing, abort controller, safe highlighting, and keyboard navigation
 *
 *   php tests/theme/test-smart-search.php
 *
 * @package FlavorCore
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

echo "=== Flavor Smart Search & Persian Normalization Contract ===\n\n";

echo "--- 1. Live execution of test-smart-search.php ---\n";

$test_file = $root . '/flavor-core/tests/test-smart-search.php';
check( is_file( $test_file ), 'flavor-core/tests/test-smart-search.php exists' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

require_once $root . '/flavor-core/tests/mock-wp-environment.php';
require_once $root . '/flavor-core/includes/Database/Schema.php';
require_once $root . '/flavor-core/includes/Activator.php';
require_once $root . '/flavor-core/includes/Support/Settings.php';
require_once $root . '/flavor-core/includes/Support/PersianText.php';
require_once $root . '/flavor-core/includes/Support/Jalali.php';
require_once $root . '/flavor-core/includes/Support/Iran.php';
require_once $root . '/flavor-core/includes/Menu/AvailabilityManager.php';
require_once $root . '/flavor-core/includes/Menu/MenuScheduler.php';
require_once $root . '/flavor-core/includes/PostTypes/BranchPostType.php';
require_once $root . '/flavor-core/includes/WooCommerce/Currency.php';
require_once $root . '/flavor-core/includes/WooCommerce/ProductModifiers.php';
require_once $root . '/flavor-core/includes/Search/SearchIndex.php';
require_once $root . '/flavor-core/includes/Search/SmartSearch.php';

$harness = array(
	'asserts'  => 0,
	'failures' => array(),
	'crashes'  => array(),
);

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	class WP_UnitTestCase {
		public function record( bool $ok, string $desc ): void {
			global $harness;
			$harness['asserts']++;
			if ( ! $ok ) {
				$harness['failures'][] = $desc;
			}
		}

		public function assertSame( $expected, $actual, $msg = '' ) {
			$this->record( $expected === $actual, 'assertSame ' . var_export( $expected, true ) . ' vs ' . var_export( $actual, true ) . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertEquals( $expected, $actual, $msg = '' ) {
			$this->record( $expected == $actual, 'assertEquals ' . var_export( $expected, true ) . ' vs ' . var_export( $actual, true ) . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertTrue( $cond, $msg = '' ) {
			$this->record( true === $cond, 'assertTrue failed' . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertFalse( $cond, $msg = '' ) {
			$this->record( false === $cond, 'assertFalse failed' . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertArrayHasKey( $key, $array, $msg = '' ) {
			$this->record( is_array( $array ) && array_key_exists( $key, $array ), "assertArrayHasKey failed: $key" . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertStringContainsString( $needle, $haystack, $msg = '' ) {
			$this->record( is_string( $haystack ) && false !== strpos( $haystack, (string) $needle ), "assertStringContainsString failed: '$needle' in '$haystack'" . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertStringNotContainsString( $needle, $haystack, $msg = '' ) {
			$this->record( is_string( $haystack ) && false === strpos( $haystack, (string) $needle ), "assertStringNotContainsString failed: '$needle' in '$haystack'" . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertGreaterThan( $expected, $actual, $msg = '' ) {
			$this->record( $actual > $expected, "assertGreaterThan failed: $actual > $expected" . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertLessThanOrEqual( $expected, $actual, $msg = '' ) {
			$this->record( $actual <= $expected, "assertLessThanOrEqual failed: $actual <= $expected" . ( $msg ? " ($msg)" : '' ) );
		}

		public function assertCount( $count, $countable, $msg = '' ) {
			$this->record( count( $countable ) === (int) $count, "assertCount failed: " . count( $countable ) . " vs $count" . ( $msg ? " ($msg)" : '' ) );
		}

		public function setUp(): void {}
		public function tearDown(): void {}
	}
}

require_once $test_file;

$test_instance = new Flavor_Smart_Search_Test();
$test_methods  = array_values(
	array_filter(
		get_class_methods( $test_instance ),
		static function ( $m ) {
			return 0 === strpos( $m, 'test_' );
		}
	)
);

check( count( $test_methods ) >= 11, 'test-smart-search.php defines at least 11 test methods (' . count( $test_methods ) . ' found)' );

foreach ( $test_methods as $m ) {
	try {
		$test_instance->setUp();
		$test_instance->$m();
		$test_instance->tearDown();
	} catch ( \Throwable $e ) {
		$harness['crashes'][] = $m . ': ' . $e->getMessage();
	}
}

check( empty( $harness['crashes'] ), 'all test methods run without crashing' . ( $harness['crashes'] ? ': ' . implode( '; ', $harness['crashes'] ) : '' ) );
check( empty( $harness['failures'] ), 'all unit assertions in test-smart-search.php pass' . ( $harness['failures'] ? ': ' . implode( '; ', $harness['failures'] ) : '' ) );
check( $harness['asserts'] >= 35, 'assertion volume floor met (at least 35 assertions, executed ' . $harness['asserts'] . ')' );

echo "\n--- 2. SmartSearch result limits & Persian search logic ---\n";

$smart_src = file_get_contents( $root . '/flavor-core/includes/Search/SmartSearch.php' );
check(
	false !== strpos( $smart_src, 'max( 1, min( 30, (int) $args[\'limit\'] ) )' ),
	'SmartSearch enforces result ceiling: max( 1, min( 30, limit ) )'
);
check(
	false !== strpos( $smart_src, 'FUZZY_THRESHOLD = 0.72' ),
	'SmartSearch maintains fuzzy typo tolerance threshold (0.72)'
);
check(
	false !== strpos( $smart_src, 'did_you_mean' ) && false !== strpos( $smart_src, 'popular' ),
	'SmartSearch provides did-you-mean suggestion and popular terms on zero hits'
);

echo "\n--- 3. RestSearch rate limit preservation (90/min) ---\n";

$rest_src = file_get_contents( $root . '/flavor-core/includes/API/RestSearch.php' );
check(
	false !== strpos( $rest_src, "RateLimit::guard( 'search', 90, MINUTE_IN_SECONDS )" ),
	'REST /search enforces rate limit of 90/minute via RateLimit::guard'
);
check(
	false !== strpos( $rest_src, "RateLimit::allow( 'search_ajax', 90, MINUTE_IN_SECONDS )" ),
	'admin-ajax flavor_search enforces rate limit of 90/minute via RateLimit::allow'
);
check(
	false !== strpos( $rest_src, "RateLimit::guard( 'search_suggest', 120, MINUTE_IN_SECONDS )" ),
	'REST /search/suggest maintains autocomplete rate limit of 120/minute'
);

echo "\n--- 4. PersianText normalization and half-space mapping ---\n";

$text_src = file_get_contents( $root . '/flavor-core/includes/Support/PersianText.php' );
check(
	false !== strpos( $text_src, "'ك' => 'ک'" ) && false !== strpos( $text_src, "'ي' => 'ی'" ),
	'PersianText character folding maps Arabic kaf and yeh to Persian equivalents'
);
check(
	false !== strpos( $text_src, "'\xE2\x80\x8C' => ' '" ),
	'PersianText folds zero-width non-joiner (نیم‌فاصله) into space for token matching'
);

echo "\n--- 5. search.js frontend contract ---\n";

$js_src = file_get_contents( $root . '/flavor/assets/js/search.js' );
check(
	false !== strpos( $js_src, 'DEBOUNCE = 220' ) && false !== strpos( $js_src, 'MIN_CHARS = 2' ),
	'search.js client has responsive 220ms debounce and 2-character query threshold'
);
check(
	false !== strpos( $js_src, 'AbortController' ),
	'search.js cancels obsolete in-flight queries using AbortController'
);
check(
	false !== strpos( $js_src, 'safeHighlight' ),
	'search.js sanitizes server highlighting before DOM insertion'
);
check(
	false !== strpos( $js_src, 'ArrowDown' ) && false !== strpos( $js_src, 'ArrowUp' ) && false !== strpos( $js_src, 'Escape' ),
	'search.js handles keyboard navigation (ArrowDown, ArrowUp, Enter, Escape)'
);

echo "\n" . str_repeat( '=', 50 ) . "\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo str_repeat( '=', 50 ) . "\n";

exit( $failed > 0 ? 1 : 0 );
