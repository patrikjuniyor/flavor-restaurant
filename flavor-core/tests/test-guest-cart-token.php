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
}
