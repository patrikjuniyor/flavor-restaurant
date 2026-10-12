<?php
/**
 * Standalone runner for the R4-2 branch-scope guards (پرامپت ۳۴).
 *
 * The WP_UnitTestCase file (test-branch-scope-security.php) needs the full
 * WP test library; this runner covers the same contracts offline against the
 * mock environment so CI enforces them on every push:
 *
 *   - resolve_branch_scope semantics for scoped/global/unassigned staff;
 *   - ReportAdmin::scope_branch behavior;
 *   - source guards proving the five read surfaces stayed gated;
 *   - no API route touches roles or capabilities.
 *
 * @package FlavorCore
 */

define( 'ABSPATH', __DIR__ . '/../../' );

require_once __DIR__ . '/mock-wp-environment.php';
require_once __DIR__ . '/../includes/Support/Roles.php';
require_once __DIR__ . '/../includes/API/BaseApiController.php';
require_once __DIR__ . '/../includes/API/AnalyticsController.php';
require_once __DIR__ . '/../includes/Reporting/ReportAdmin.php';

use FlavorCore\API\AnalyticsController;
use FlavorCore\Reporting\ReportAdmin;
use FlavorCore\Support\Roles;

$passed = 0;
$failed = 0;

function assert_test( $cond, string $msg ): void {
	global $passed, $failed;
	if ( $cond ) {
		$passed++;
		echo "  [PASS] {$msg}\n";
	} else {
		$failed++;
		echo "  [FAIL] {$msg}\n";
	}
}

function make_user( int $id, array $roles, array $caps = array() ): int {
	$GLOBALS['_mock_users'][ $id ] = new WP_User(
		(object) array(
			'ID'           => $id,
			'roles'        => $roles,
			'user_login'   => "u{$id}",
			'user_email'   => "u{$id}@example.com",
			'display_name' => "User {$id}",
		)
	);
	if ( $caps ) {
		$GLOBALS['_mock_user_caps'][ $id ] = $caps;
	}
	return $id;
}

function req( array $params = array() ): WP_REST_Request {
	$r = new WP_REST_Request( 'GET', '/flavor/v1/analytics/overview' );
	foreach ( $params as $k => $v ) {
		$r->set_param( $k, $v );
	}
	return $r;
}

$ctl = new AnalyticsController();

echo "\n== 1. scoped staff are pinned to their own branches ==\n";

$bm = make_user( 71, array( Roles::BRANCH_MANAGER ) );
Roles::set_user_branches( $bm, array( 11 ) );
wp_set_current_user( $bm );
assert_test( 11 === $ctl->resolve_branch_scope( req( array( 'branch_id' => 11 ) ) ), 'own branch resolves to itself' );
$denied = $ctl->resolve_branch_scope( req( array( 'branch_id' => 12 ) ) );
assert_test( is_wp_error( $denied ) && 'flavor_branch_forbidden' === $denied->get_error_code() && 403 === $denied->get_error_data()['status'], 'foreign branch is 403' );
assert_test( 11 === $ctl->resolve_branch_scope( req() ), 'omitted branch is pinned to the single managed branch, never widened' );

$multi = make_user( 72, array( Roles::BRANCH_MANAGER ) );
Roles::set_user_branches( $multi, array( 11, 12 ) );
wp_set_current_user( $multi );
$amb = $ctl->resolve_branch_scope( req() );
assert_test( is_wp_error( $amb ) && 'flavor_branch_required' === $amb->get_error_code() && 400 === $amb->get_error_data()['status'], 'multi-branch staff must pass branch_id explicitly' );
assert_test( 12 === $ctl->resolve_branch_scope( req( array( 'branch_id' => 12 ) ) ), 'either managed branch is selectable' );

echo "\n== 2. global users keep the cross-branch view ==\n";

$admin = make_user( 73, array( 'administrator' ) );
wp_set_current_user( $admin );
assert_test( null === $ctl->resolve_branch_scope( req() ), 'admin may aggregate across branches' );
assert_test( 99 === $ctl->resolve_branch_scope( req( array( 'branch_id' => 99 ) ) ), 'admin may pick any branch' );

$gm = make_user( 74, array( Roles::GENERAL_MANAGER ), array( 'flavor_manage_all_branches' => true ) );
wp_set_current_user( $gm );
assert_test( null === $ctl->resolve_branch_scope( req() ), 'global-cap staff may aggregate too' );

$none = make_user( 75, array( Roles::KITCHEN ) );
wp_set_current_user( $none );
$err = $ctl->resolve_branch_scope( req() );
assert_test( is_wp_error( $err ), 'unassigned staff get no free pass to all data' );

echo "\n== 3. ReportAdmin scoping ==\n";

$ref = new ReflectionMethod( ReportAdmin::class, 'scope_branch' );
$ref->setAccessible( true );
wp_set_current_user( $bm );
list( $b, $e ) = $ref->invoke( null, 0 );
assert_test( 11 === $b && null === $e, 'single-branch manager is auto-pinned for reports' );
list( $b, $e ) = $ref->invoke( null, 12 );
assert_test( null !== $e, 'report for a foreign branch is refused' );
wp_set_current_user( $multi );
list( $b, $e ) = $ref->invoke( null, 0 );
assert_test( 0 === $b && null !== $e, 'multi-branch manager must pick a branch (no global report)' );
wp_set_current_user( $admin );
list( $b, $e ) = $ref->invoke( null, 0 );
assert_test( 0 === $b && null === $e, 'admin keeps the global report' );

echo "\n== 4. source contracts: the five read surfaces stay gated ==\n";

foreach ( array( 'get_overview', 'get_peak_hours', 'get_popular_dishes' ) as $m ) {
	$src  = (string) file_get_contents( __DIR__ . '/../includes/API/AnalyticsController.php' );
	$pos  = strpos( $src, 'function ' . $m );
	$end  = strpos( $src, 'public function', $pos + 10 );
	$body = substr( $src, $pos, ( $end ?: strlen( $src ) ) - $pos );
	assert_test( false !== $pos && false !== strpos( $body, 'resolve_branch_scope' ), "{$m} resolves the branch scope" );
}
$src = (string) file_get_contents( __DIR__ . '/../includes/API/AnalyticsController.php' );
assert_test( false !== strpos( $src, 'managed_branch_ids' ), 'branches-summary filters to managed branches' );
$src = (string) file_get_contents( __DIR__ . '/../includes/API/ReservationController.php' );
$pos  = strpos( $src, 'function list_reservations' );
$end  = strpos( $src, 'public function', $pos + 10 );
assert_test( false !== strpos( substr( $src, $pos, ( $end ?: strlen( $src ) ) - $pos ), 'resolve_branch_scope' ), 'reservation list is branch-scoped' );
$src = (string) file_get_contents( __DIR__ . '/../includes/Reporting/ReportAdmin.php' );
assert_test( 2 === substr_count( $src, 'self::scope_branch(' ), 'report render AND download are both scoped' );
$src = (string) file_get_contents( __DIR__ . '/../includes/API/RestController.php' );
assert_test( false !== strpos( $src, 'can_access_branch' ), 'kitchen list keeps its branch guard' );

echo "\n== 5. no REST surface touches roles or capabilities ==\n";

$clean = true;
foreach ( (array) glob( __DIR__ . '/../includes/API/*.php' ) as $f ) {
	$s = (string) file_get_contents( $f );
	foreach ( array( '->set_role(', '->add_cap(', '->remove_cap(', '->remove_role(', 'add_role(', "'roles'" ) as $needle ) {
		if ( false !== strpos( $s, $needle ) ) {
			$clean = false;
			echo "  [note] " . basename( $f ) . " contains {$needle}\n";
		}
	}
}
assert_test( $clean, 'API controllers never mutate roles/caps and never accept a roles parameter' );

echo "\n== 6. can_access_branch(…,0) contract (public data, NOT everything) ==\n";

wp_set_current_user( $none );
assert_test( Roles::can_access_branch( $none, 0 ), 'zero means branch-free public context' );
assert_test( ! Roles::can_access_branch( $none, 42 ), 'no assignment means no branch' );

echo "\n=======================================================\n";
echo $failed === 0 ? "Results: {$passed} Passed, 0 Failed\n" : "Results: {$passed} Passed, {$failed} FAILED\n";
echo "=======================================================\n";
exit( $failed > 0 ? 1 : 0 );
