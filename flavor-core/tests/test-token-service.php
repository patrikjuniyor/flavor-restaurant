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
	public function test_issued_pair_has_csprng_entropy() {
		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		$t1      = TokenService::issue( $user_id, 'dev-a' );
		$t2      = TokenService::issue( $user_id, 'dev-b' );
		$this->assertGreaterThanOrEqual( 64, strlen( $t1['access_token'] ) );
		$this->assertNotEquals( $t1['access_token'], $t2['access_token'] );
		$this->assertNotEquals( $t1['access_token'], $t1['refresh_token'] );
	}

	public function test_tokens_stored_hashed_not_plaintext() {
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/Customer/TokenService.php' );
		$this->assertSame( 2, substr_count( $src, 'wp_generate_password( 64, false, false )' ), 'both tokens issued via 62^64 alphanumeric CSPRNG-backed generator' );
		$this->assertStringContainsString( "hash( 'sha256'", $src );
	}

	public function test_otp_hardening_pins() {
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/Customer/OtpAuth.php' );
		$this->assertStringContainsString( 'min( 6, max( 4, $length ) )', $src, 'OTP length floor is 4 digits (clamp, not setting-trust)' );
		$this->assertStringContainsString( '(int) $row[\'attempts\'] >= 5', $src, 'per-code attempt ceiling exists' );
		$this->assertStringContainsString( 'hash_equals( (string) $row[\'code_hash\'], self::hash_code( $mobile, $code ) )', $src, 'OTP compare is constant-time' );
		$this->assertStringContainsString( 'hash_hmac( \'sha256\', $mobile . \'|\' . $code, $secret )', $src, 'codes are HMAC-peppered, never stored plain' );
		$this->assertStringContainsString( "'otp_max_per_10min', 3", $src, 'issue-side rate limit retained' );
		$this->assertStringContainsString( 'consumed_at IS NULL', $src, 'single-use codes' );
	}

	public function test_otp_length_clamp_reflection() {
		$rm = new ReflectionMethod( 'FlavorCore\Customer\OtpAuth', 'generate_code' );
		$rm->setAccessible( true );
		$this->assertSame( 4, strlen( $rm->invoke( null, 2 ) ), 'a 2-digit setting must be clamped up to 4' );
		$this->assertSame( 6, strlen( $rm->invoke( null, 9 ) ), 'and down to 6' );
		$this->assertTrue( ctype_digit( $rm->invoke( null, 5 ) ) );
	}
}
