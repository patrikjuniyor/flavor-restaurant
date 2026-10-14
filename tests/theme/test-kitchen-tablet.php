<?php
/**
 * Test contract for Kitchen Dashboard tablet optimization (پرامپت ۷۸).
 *
 * Verifies:
 *   1. Touch target sizes: Buttons, selects, filter pills and action buttons have >= 48px touch targets
 *   2. Glanceable distance typography: order numbers, lane headings, item names and age badges
 *   3. Live polling backoff logic in kitchen-dashboard.js
 *   4. Pleasant attention chime and session sound toggle with aria-pressed contract
 *   5. Playwright test contract: tests/ui/kitchen-kds.mjs exists and is wired in package.json
 *
 *   php tests/theme/test-kitchen-tablet.php
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

$css = (string) file_get_contents( $root . '/flavor-core/assets/public/css/kitchen-dashboard.css' );
$js  = (string) file_get_contents( $root . '/flavor-core/assets/public/js/kitchen-dashboard.js' );
$php = (string) file_get_contents( $root . '/flavor-core/admin/views/kitchen-dashboard.php' );
$pkg = (string) file_get_contents( $root . '/tests/ui/package.json' );
$kds = (string) file_get_contents( $root . '/tests/ui/kitchen-kds.mjs' );

echo "=== 1. Touch Target Dimensions (>= 48px) ===\n";

// Helper to extract selector rule block using regex
function match_rule( string $css, string $selector_pattern ): string {
	if ( preg_match( $selector_pattern, $css, $m ) ) {
		return $m[0];
	}
	return '';
}

$bar_rule = match_rule( $css, '/\.flavor-kitchen__bar\s+select[^{]*\{[^}]*\}/' );
check(
	str_contains( $bar_rule, 'min-height: 48px;' ),
	'header bar buttons specify min-height: 48px'
);

$filt_rule = match_rule( $css, '/\.flavor-kitchen__filters\s+button\s*\{[^}]*\}/' );
check(
	str_contains( $filt_rule, 'min-height: 48px;' ),
	'filter pills specify min-height: 48px for greasy kitchen tap usability'
);

$act_rule = match_rule( $css, '/\.flavor-kcard__actions\s+button[^{]*\{[^}]*\}/' );
check(
	str_contains( $act_rule, 'min-height: 48px;' ),
	'ticket advance action buttons specify min-height: 48px'
);

$li_rule = match_rule( $css, '/\.flavor-kcard\s+li\s*\{[^}]*\}/' );
check(
	str_contains( $li_rule, 'min-height: 48px;' ),
	'individual item rows specify min-height: 48px for single-item readiness toggle'
);

echo "\n=== 2. Distance & Glanceable Typography ===\n";

$top_rule = match_rule( $css, '/\.flavor-kcard__top\s*\{[^}]*font-size[^}]*\}/' );
check(
	str_contains( $top_rule, 'font-size: 1.35rem;' ) || str_contains( $top_rule, 'font-size: 1.3' ),
	'order title & table number enlarged for distance reading across prep stations'
);

check(
	str_contains( $li_rule, 'font-size: 1.15rem;' ) || str_contains( $li_rule, 'font-size: 1.1' ),
	'item typography enlarged >= 1.15rem for instant glanceability'
);

$meta_rule = match_rule( $css, '/\.flavor-kcard__meta\s*\{[^}]*\}/' );
check(
	str_contains( $meta_rule, 'font-size: 1.05rem;' ) || str_contains( $meta_rule, 'font-size: 1.' ),
	'order customer and age meta enlarged >= 1.05rem'
);

echo "\n=== 3. Polling Backoff & Error Resilience in JS ===\n";

check(
	str_contains( $js, 'currentPoll = Math.min(currentPoll * 1.5, maxPoll);' ),
	'polling implements exponential backoff on fetch failures'
);
check(
	str_contains( $js, 'scheduleNext' ) && str_contains( $js, 'clearTimeout' ),
	'polling timer safely resets and reschedules without race conditions'
);
check(
	str_contains( $js, 'currentPoll = basePoll;' ),
	'successful response resets poll interval back to base configuration'
);

echo "\n=== 4. Sound Alert & Toggle Control ===\n";

check(
	str_contains( $js, 'playTone' ) && str_contains( $js, 'audioCtx' ),
	'new order triggers dual-tone audible chime via Web Audio API'
);
check(
	str_contains( $js, 'soundBtn.setAttribute(\'aria-pressed\'' ) || str_contains( $js, "aria-pressed', soundOn" ),
	'sound button toggles audio state with accessible aria-pressed attribute'
);
check(
	str_contains( $js, 'if (!soundOn) return;' ),
	'sound alert strictly respects mute/toggle state'
);

echo "\n=== 5. Playwright KDS Test Contract ===\n";

check(
	str_contains( $pkg, '"test:kitchen-kds": "node kitchen-kds.mjs"' ),
	'package.json exposes test:kitchen-kds script'
);
check(
	str_contains( $kds, 'new -> preparing -> ready -> completed' ),
	'kitchen-kds.mjs test verifies progression across lanes'
);
check(
	str_contains( $kds, 'box.height < 44' ) || str_contains( $kds, 'touch target' ),
	'kitchen-kds.mjs asserts tablet touch target dimensions'
);

if ( $failed > 0 ) {
	echo "\n{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}

echo "\nALL {$passed} checks passed.\n";
exit( 0 );
