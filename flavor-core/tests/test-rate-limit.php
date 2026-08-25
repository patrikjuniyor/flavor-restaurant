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
}
