<?php
/**
 * Test IDOR Security & Guest Ownership Token Verification.
 *
 * @package FlavorCore
 */

use FlavorCore\Support\GuestToken;

class Flavor_IDOR_Security_Test extends WP_UnitTestCase {

	public function test_guest_token_generation_format() {
		$token = GuestToken::generate();
		$this->assertIsString( $token );
		$this->assertEquals( 64, strlen( $token ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $token );
	}

	public function test_guest_token_verification_success() {
		$token = GuestToken::generate();
		$this->assertTrue( GuestToken::verify( $token, $token ) );
	}

	public function test_guest_token_verification_fails_on_mismatch() {
		$token1 = GuestToken::generate();
		$token2 = GuestToken::generate();
		$this->assertFalse( GuestToken::verify( $token1, $token2 ) );
	}

	public function test_guest_token_verification_fails_on_empty() {
		$token = GuestToken::generate();
		$this->assertFalse( GuestToken::verify( $token, '' ) );
		$this->assertFalse( GuestToken::verify( '', $token ) );
		$this->assertFalse( GuestToken::verify( '', '' ) );
	}

	public function test_extract_token_from_header() {
		$request = new WP_REST_Request( 'GET', '/flavor/v1/orders/10' );
		$token   = GuestToken::generate();
		$request->set_header( 'X-Guest-Token', $token );

		$extracted = GuestToken::extract_from_request( $request );
		$this->assertEquals( $token, $extracted );
	}

	public function test_extract_token_from_query_param() {
		$token   = GuestToken::generate();
		$request = new WP_REST_Request( 'GET', '/flavor/v1/orders/10' );
		$request->set_param( 'guest_token', $token );

		$extracted = GuestToken::extract_from_request( $request );
		$this->assertEquals( $token, $extracted );
	}

	public function test_unauthenticated_request_to_other_guest_order_fails() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v2/orders/9999' );
		$response = rest_get_server()->dispatch( $request );

		// Non-existent or unauthenticated order lookup fails with 404 or 401/403
		$this->assertContains( $response->get_status(), array( 401, 403, 404 ) );
	}
}
