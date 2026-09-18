<?php
/**
 * Test TokenService for Bearer / Refresh token authentication.
 *
 * @package FlavorCore
 */

use FlavorCore\Customer\TokenService;

class Flavor_Token_Service_Test extends WP_UnitTestCase {

	public function test_issue_and_validate_access_token() {
		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		$tokens  = TokenService::issue( $user_id, 'iPhone 15 Pro' );

		$this->assertArrayHasKey( 'access_token', $tokens );
		$this->assertArrayHasKey( 'refresh_token', $tokens );
		$this->assertEquals( 'Bearer', $tokens['token_type'] );
		$this->assertGreaterThan( 0, $tokens['expires_in'] );

		$validated_uid = TokenService::validate( $tokens['access_token'] );
		$this->assertEquals( $user_id, $validated_uid );
	}

	public function test_invalid_token_returns_null() {
		$this->assertNull( TokenService::validate( 'invalid.random.token' ) );
	}

	public function test_token_rotation() {
		$user_id   = $this->factory->user->create( array( 'role' => 'customer' ) );
		$initial   = TokenService::issue( $user_id, 'Android Device' );
		$refreshed = TokenService::refresh( $initial['refresh_token'] );

		$this->assertNotWPError( $refreshed );
		$this->assertNotEquals( $initial['access_token'], $refreshed['access_token'] );
		$this->assertNotEquals( $initial['refresh_token'], $refreshed['refresh_token'] );

		// Old refresh token must be invalidated (single-use rotation)
		$retry_old = TokenService::refresh( $initial['refresh_token'] );
		$this->assertWPError( $retry_old );
	}

	public function test_token_revocation() {
		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		$tokens  = TokenService::issue( $user_id, 'Web Client' );

		$this->assertEquals( $user_id, TokenService::validate( $tokens['access_token'] ) );

		TokenService::revoke( $tokens['access_token'] );
		$this->assertNull( TokenService::validate( $tokens['access_token'] ) );
	}
}
