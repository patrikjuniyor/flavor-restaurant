<?php
/**
 * Role & branch-scope security tests (R4-2 / پرامپت ۳۴).
 *
 * Branch-scoped staff (branch manager, cashier, kitchen) must never widen a
 * read to "all branches" by omitting branch_id, and no REST route may change
 * roles or capabilities. Pattern follows tests/test-idor-security.php.
 *
 * @package FlavorCore
 */

use FlavorCore\API\AnalyticsController;
use FlavorCore\Support\Roles;

class Flavor_Branch_Scope_Security_Test extends WP_UnitTestCase {

	/**
	 * Create a branch post.
	 *
	 * @param string $title Title.
	 * @return int
	 */
	private function make_branch( string $title ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'flavor_branch',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Branch manager assigned to the given branches.
	 *
	 * @param int[] $branches Branch IDs.
	 * @return int User ID.
	 */
	private function make_manager( array $branches ): int {
		$uid = self::factory()->user->create( array( 'role' => Roles::BRANCH_MANAGER ) );
		Roles::set_user_branches( $uid, $branches );
		return $uid;
	}

	/**
	 * Request factory.
	 *
	 * @param array $params Params.
	 * @return WP_REST_Request
	 */
	private function rest_request( array $params = array() ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/flavor/v1/analytics/overview' );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return $request;
	}

	public function test_manager_can_read_own_branch_only() {
		$b1 = $this->make_branch( 'B1' );
		$b2 = $this->make_branch( 'B2' );
		$u  = $this->make_manager( array( $b1 ) );
		wp_set_current_user( $u );

		$c = new AnalyticsController();

		$own = $c->resolve_branch_scope( $this->rest_request( array( 'branch_id' => $b1 ) ) );
		$this->assertSame( $b1, $own, 'own branch must resolve to itself' );

		$other = $c->resolve_branch_scope( $this->rest_request( array( 'branch_id' => $b2 ) ) );
		$this->assertTrue( is_wp_error( $other ), 'foreign branch must be denied' );
		if ( is_wp_error( $other ) ) {
			$this->assertSame( 'flavor_branch_forbidden', $other->get_error_code() );
			$this->assertSame( 403, $other->get_error_data()['status'] );
		}
	}

	public function test_omitted_branch_never_widens_for_scoped_staff() {
		$b1 = $this->make_branch( 'Solo' );
		$b2 = $this->make_branch( 'Other' );
		$c  = new AnalyticsController();

		// Exactly one managed branch: the omission is pinned to it, not to "all".
		$solo = $this->make_manager( array( $b1 ) );
		wp_set_current_user( $solo );
		$this->assertSame( $b1, $c->resolve_branch_scope( $this->rest_request() ), 'single-branch staff must be pinned to their branch' );

		// Several managed branches without branch_id: refuse to guess, demand it.
		$multi = $this->make_manager( array( $b1, $b2 ) );
		wp_set_current_user( $multi );
		$r = $c->resolve_branch_scope( $this->rest_request() );
		$this->assertTrue( is_wp_error( $r ), 'multi-branch staff must pass branch_id explicitly' );
		if ( is_wp_error( $r ) ) {
			$this->assertSame( 'flavor_branch_required', $r->get_error_code() );
			$this->assertSame( 400, $r->get_error_data()['status'] );
		}
	}

	public function test_global_users_keep_cross_branch_view() {
		$b1 = $this->make_branch( 'G1' );
		$u  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $u );
		$c = new AnalyticsController();

		$this->assertNull( $c->resolve_branch_scope( $this->rest_request() ), 'global users may aggregate across branches' );
		$this->assertSame( $b1, $c->resolve_branch_scope( $this->rest_request( array( 'branch_id' => $b1 ) ) ) );
	}

	public function test_no_rest_route_changes_roles_or_caps() {
		foreach ( glob( dirname( __DIR__ ) . '/includes/API/*.php' ) as $file ) {
			$src = (string) file_get_contents( $file );
			foreach ( array( '->set_role(', '->add_cap(', '->remove_cap(', '->remove_role(', 'add_role(', "'roles'" ) as $needle ) {
				$this->assertFalse(
					false !== strpos( $src, $needle ),
					basename( $file ) . ' must not touch role/cap state (' . $needle . ')'
				);
			}
		}
	}

	public function test_staff_read_surfaces_resolve_branch_scope() {
		$analytics = (string) file_get_contents( dirname( __DIR__ ) . '/includes/API/AnalyticsController.php' );
		foreach ( array( 'get_overview', 'get_peak_hours', 'get_popular_dishes' ) as $method ) {
			$pos = strpos( $analytics, 'function ' . $method );
			$this->assertIsInt( $pos, $method . ' exists' );
			$end  = strpos( $analytics, 'public function', $pos + 10 );
			$body = substr( $analytics, $pos, ( $end ?: strlen( $analytics ) ) - $pos );
			$this->assertStringContainsString( 'resolve_branch_scope', $body, $method . ' must resolve the branch scope' );
		}
		$this->assertStringContainsString( 'managed_branch_ids', $analytics, 'branches-summary must be filtered to managed branches' );

		$res = (string) file_get_contents( dirname( __DIR__ ) . '/includes/API/ReservationController.php' );
		$pos = strpos( $res, 'function list_reservations' );
		$this->assertIsInt( $pos );
		$end  = strpos( $res, 'public function', $pos + 10 );
		$body = substr( $res, $pos, ( $end ?: strlen( $res ) ) - $pos );
		$this->assertStringContainsString( 'resolve_branch_scope', $body, 'reservation list must be branch-scoped' );

		$rep = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Reporting/ReportAdmin.php' );
		$this->assertStringContainsString( 'self::scope_branch( (int) $request[', $rep, 'report renderer must scope the branch' );
		$this->assertSame( 2, substr_count( $rep, 'self::scope_branch(' ), 'both render and CSV download must be scoped' );
	}

	public function test_can_access_branch_zero_contract() {
		// branch 0 is "no branch-specific object" (public data), NOT "everything".
		// Cross-branch reads are only reachable through endpoints that take a
		// branch_id; those now go through resolve_branch_scope. This pins that
		// contract so nobody "simplifies" the two helpers into one.
		$u = $this->make_manager( array() );
		wp_set_current_user( $u );
		$this->assertTrue( Roles::can_access_branch( $u, 0 ), 'zero means public, documented' );
		$this->assertFalse( Roles::can_access_branch( $u, 42 ), 'no assignment means no branch' );

		$c = new AnalyticsController();
		$r = $c->resolve_branch_scope( $this->rest_request() );
		$this->assertTrue( is_wp_error( $r ), 'unassigned staff must not get a free pass to all data' );
	}
}
