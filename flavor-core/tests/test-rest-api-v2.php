<?php
/**
 * Test Flavor REST API V2 Namespace suite.
 *
 * @package FlavorCore
 */

use FlavorCore\Customer\TokenService;

class Flavor_REST_API_V2_Test extends WP_UnitTestCase {

	public function test_v2_settings_app_bootstrap_endpoint() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v2/settings/app-bootstrap' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'app', $data['data'] );
		$this->assertArrayHasKey( 'design', $data['data'] );
		$this->assertArrayHasKey( 'currency', $data['data'] );
	}

	public function test_v2_categories_endpoint() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v2/categories' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertIsArray( $data['data'] );
	}

	public function test_v2_menu_endpoint() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v2/menu' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'meta', $data );
	}

	public function test_v2_unauthenticated_protected_route_fails() {
		$request  = new WP_REST_Request( 'GET', '/flavor/v2/orders' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}
}
