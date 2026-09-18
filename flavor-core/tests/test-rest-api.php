<?php
/**
 * Test Flavor REST API suite.
 *
 * @package FlavorCore
 */

use FlavorCore\Customer\TokenService;

class Flavor_REST_API_Test extends WP_UnitTestCase {

	public function test_settings_app_bootstrap_endpoint() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v1/settings/app-bootstrap' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'app', $data['data'] );
		$this->assertArrayHasKey( 'design', $data['data'] );
		$this->assertArrayHasKey( 'currency', $data['data'] );
		$this->assertArrayHasKey( 'ordering', $data['data'] );
		$this->assertArrayHasKey( 'branches', $data['data'] );
	}

	public function test_categories_endpoint() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v1/categories' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertIsArray( $data['data'] );
	}

	public function test_menu_endpoint_returns_standard_envelope() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v1/menu' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'pagination', $data['meta'] );
	}

	public function test_authenticated_me_with_bearer_token() {
		$user_id = $this->factory->user->create(
			array(
				'role'         => 'customer',
				'display_name' => 'علی احمدی',
			)
		);
		$tokens = TokenService::issue( $user_id );

		$request = new WP_REST_Request( 'GET', '/flavor/v1/auth/me' );
		$request->set_header( 'Authorization', 'Bearer ' . $tokens['access_token'] );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertTrue( $data['data']['logged_in'] );
		$this->assertEquals( 'علی احمدی', $data['data']['user']['display_name'] );
	}

	public function test_unauthenticated_protected_route_fails() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v1/orders' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}
}
