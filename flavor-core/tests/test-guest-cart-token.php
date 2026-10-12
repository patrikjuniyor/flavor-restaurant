<?php
/**
 * Test Mobile Guest Cart Token Service.
 *
 * @package FlavorCore
 */

use FlavorCore\WooCommerce\CartTokenService;

class Flavor_Guest_Cart_Token_Test extends WP_UnitTestCase {

	public function test_cart_token_generation() {
		$token = CartTokenService::generate_token();
		$this->assertIsString( $token );
		$this->assertStringStartsWith( 'fcart_', $token );
		$this->assertGreaterThan( 30, strlen( $token ) );
	}

	public function test_cart_token_hashing() {
		$token = CartTokenService::generate_token();
		$hash1 = CartTokenService::hash_token( $token );
		$hash2 = CartTokenService::hash_token( $token );

		$this->assertEquals( 64, strlen( $hash1 ) );
		$this->assertEquals( $hash1, $hash2 );
		$this->assertEquals( hash( 'sha256', $token ), $hash1 );
	}

	public function test_extract_cart_token_from_header() {
		$request = new WP_REST_Request( 'GET', '/flavor/v1/cart' );
		$token   = CartTokenService::generate_token();
		$request->set_header( 'X-Cart-Token', $token );

		$extracted = CartTokenService::extract_token( $request );
		$this->assertEquals( $token, $extracted );
	}

	public function test_extract_cart_token_from_param() {
		$request = new WP_REST_Request( 'GET', '/flavor/v1/cart' );
		$token   = CartTokenService::generate_token();
		$request->set_param( 'cart_token', $token );

		$extracted = CartTokenService::extract_token( $request );
		$this->assertEquals( $token, $extracted );
	}
	public function test_guest_token_entropy_and_freshness() {
		$seen = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$t = GuestToken::generate();
			$this->assertSame( 64, strlen( $t ), 'guest token must be 32 CSPRNG bytes (256 bits)' );
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $t );
			$this->assertArrayNotHasKey( $t, $seen, 'guest tokens must be unique per call' );
			$seen[ $t ] = true;
		}
	}

	public function test_guest_token_has_no_time_derived_fallback() {
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/Support/GuestToken.php' );
		$this->assertStringContainsString( 'bin2hex( random_bytes( 32 ) )', $src );
		$this->assertStringNotContainsString( 'uniqid( (string) wp_rand', $src );
		$this->assertStringNotContainsString( 'microtime( true )', $src );
	}

	public function test_guest_verify_is_constant_time_and_rejects_empty() {
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/Support/GuestToken.php' );
		$this->assertStringContainsString( 'hash_equals( $known, $user )', $src );
		$this->assertStringNotContainsString( '=== $user_token', $src );
		$t = GuestToken::generate();
		$this->assertTrue( GuestToken::verify( $t, $t ) );
		$this->assertFalse( GuestToken::verify( $t, substr( $t, 0, 63 ) ) );
		$this->assertFalse( GuestToken::verify( '', '' ) );
	}

	public function test_cart_token_entropy_and_no_fallback() {
		$seen = array();
		for ( $i = 0; $i < 30; $i++ ) {
			$t = CartTokenService::generate_token();
			$this->assertMatchesRegularExpression( '/^fcart_[0-9a-f]{48}$/', $t );
			$this->assertArrayNotHasKey( $t, $seen );
			$seen[ $t ] = true;
		}
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/WooCommerce/CartTokenService.php' );
		$this->assertStringContainsString( 'bin2hex( random_bytes( 24 ) )', $src );
		$this->assertStringNotContainsString( 'uniqid( (string) wp_rand', $src );
	}
}
