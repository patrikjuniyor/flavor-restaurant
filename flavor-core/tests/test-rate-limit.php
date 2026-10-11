<?php
/**
 * Rate limiter.
 *
 * @package FlavorCore
 */

class Flavor_Rate_Limit_Test extends WP_UnitTestCase {

	public function test_allows_under_cap() {
		$key = 'test-bucket-' . wp_generate_password( 6, false );
		$this->assertTrue( \FlavorCore\Support\RateLimit::allow( $key, 2, 60 ) );
		$this->assertTrue( \FlavorCore\Support\RateLimit::allow( $key, 2, 60 ) );
		$this->assertFalse( \FlavorCore\Support\RateLimit::allow( $key, 2, 60 ) );
	}

	public function test_guard_returns_wp_error() {
		$key = 'test-guard-' . wp_generate_password( 6, false );
		\FlavorCore\Support\RateLimit::allow( $key, 1, 60 );
		$err = \FlavorCore\Support\RateLimit::guard( $key, 1, 60 );
		$this->assertInstanceOf( WP_Error::class, $err );
	}

	/**
	 * POST /orders is capped per client, and a client is the IP plus the cart
	 * token (OrderController::create_order). The scope argument is what makes
	 * those two buckets differ, so it is asserted here rather than through the
	 * whole checkout stack — the e2e suite covers that path.
	 */
	public function test_scope_keeps_two_sessions_in_separate_buckets() {
		$bucket = 'test-scoped-' . wp_generate_password( 6, false );
		$this->assertTrue( \FlavorCore\Support\RateLimit::allow( $bucket, 1, 60, 'cart-a' ) );
		$this->assertFalse( \FlavorCore\Support\RateLimit::allow( $bucket, 1, 60, 'cart-a' ) );
		$this->assertTrue( \FlavorCore\Support\RateLimit::allow( $bucket, 1, 60, 'cart-b' ) );
		$this->assertFalse( \FlavorCore\Support\RateLimit::allow( $bucket, 1, 60 ) );
	}

	public function test_reset_empties_one_bucket_and_not_the_neighbour() {
		$bucket = 'test-reset-' . wp_generate_password( 6, false );
		\FlavorCore\Support\RateLimit::allow( $bucket, 1, 60, 'cart-a' );
		\FlavorCore\Support\RateLimit::allow( $bucket, 1, 60, 'cart-b' );
		\FlavorCore\Support\RateLimit::reset( $bucket, 'cart-a' );
		$this->assertTrue( \FlavorCore\Support\RateLimit::allow( $bucket, 1, 60, 'cart-a' ) );
		$this->assertFalse( \FlavorCore\Support\RateLimit::allow( $bucket, 1, 60, 'cart-b' ) );
	}
}
