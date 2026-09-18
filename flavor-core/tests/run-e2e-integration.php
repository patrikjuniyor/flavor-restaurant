<?php
/**
 * Flavor Platform Phase 2 — Comprehensive End-to-End Integration Test Suite.
 * Executes live simulated REST API requests, database operations, auth workflows,
 * cart bridging, IDOR security boundaries, multi-branch isolation, and white-label checks.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Tests;

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'FLAVOR_CORE_PATH', dirname( __DIR__ ) . '/' );
define( 'FLAVOR_CORE_VERSION', '1.4.0' );
define( 'FLAVOR_CORE_REST_NAMESPACE', 'flavor/v1' );
define( 'FLAVOR_CORE_REST_V2_NAMESPACE', 'flavor/v2' );

// Mock WordPress / WooCommerce globals & environment for offline integration testing
require_once __DIR__ . '/mock-wp-environment.php';

// Register autoloader
require_once FLAVOR_CORE_PATH . 'includes/Autoloader.php';
\FlavorCore\Autoloader::register();

use FlavorCore\API\AuthController;
use FlavorCore\API\BranchController;
use FlavorCore\API\CartController;
use FlavorCore\API\MenuController;
use FlavorCore\API\OrderController;
use FlavorCore\API\ReservationController;
use FlavorCore\API\SettingsController;
use FlavorCore\Customer\OtpAuth;
use FlavorCore\Customer\TokenService;
use FlavorCore\Notification\PushNotificationService;
use FlavorCore\Order\KitchenTicketRepository;
use FlavorCore\Reservation\ReservationRepository;
use FlavorCore\Reservation\ReservationService;
use FlavorCore\Support\GuestToken;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Roles;
use FlavorCore\WooCommerce\CartSession;
use FlavorCore\WooCommerce\CartTokenService;
use FlavorCore\WooCommerce\CheckoutService;

$total_tests  = 0;
$passed_tests = 0;
$failed_tests = 0;

function run_test( string $description, callable $test_fn ): void {
	global $total_tests, $passed_tests, $failed_tests;
	$total_tests++;
	try {
		$result = $test_fn();
		if ( false !== $result ) {
			$passed_tests++;
			echo "  [PASS] {$description}\n";
		} else {
			$failed_tests++;
			echo "  [FAIL] {$description} (Assertion evaluated to false)\n";
		}
	} catch ( \Throwable $e ) {
		$failed_tests++;
		echo "  [FAIL] {$description}\n";
		echo "         Exception: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n";
	}
}

echo "======================================================================\n";
echo "   FLAVOR PLATFORM — PHASE 2 END-TO-END INTEGRATION TEST RUNNER       \n";
echo "======================================================================\n\n";

// ===========================================================================
// TEST SUITE 1: CUSTOMER REGISTRATION & TOKEN LIFECYCLE
// ===========================================================================
echo "--- 1. CUSTOMER REGISTRATION & TOKEN LIFECYCLE ---\n";

$auth_ctrl = new AuthController();
$otp_mobile = '09123456789';

run_test( 'Guest requests OTP SMS code for mobile 09123456789', function () use ( $auth_ctrl, $otp_mobile ) {
	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/otp/request' );
	$req->set_json_params( array( 'mobile' => $otp_mobile ) );
	$res = $auth_ctrl->otp_request( $req );
	$data = $res->get_data();
	return $res->get_status() === 200 && ! empty( $data['success'] ) && ! empty( $data['data']['ttl'] );
} );

run_test( 'Verify valid OTP code and issue JWT Bearer & Refresh Tokens', function () use ( $auth_ctrl, $otp_mobile ) {
	$sent_code = $GLOBALS['_last_sent_otp_code'] ?? '';
	if ( empty( $sent_code ) ) return false;

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/otp/verify' );
	$req->set_json_params( array(
		'mobile'      => $otp_mobile,
		'code'        => $sent_code,
		'name'        => 'علی رضایی',
		'device_name' => 'Pixel 8 Pro',
	) );
	$res = $auth_ctrl->otp_verify( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 &&
		! empty( $data['data']['tokens']['access_token'] ) &&
		! empty( $data['data']['tokens']['refresh_token'] ) &&
		$data['data']['user']['mobile'] === $otp_mobile;
} );

run_test( 'Verify invalid OTP code fails with 400 Bad Request', function () use ( $auth_ctrl, $otp_mobile ) {
	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/otp/verify' );
	$req->set_json_params( array(
		'mobile' => $otp_mobile,
		'code'   => '00000', // Wrong code
	) );
	$res = $auth_ctrl->otp_verify( $req );
	return $res->get_status() === 400 && ! $res->get_data()['success'];
} );

run_test( 'Verify expired OTP code fails with 400 Bad Request', function () use ( $auth_ctrl, $otp_mobile ) {
	global $wpdb;
	$wpdb->update(
		'wp_flavor_otp_codes',
		array( 'expires_at' => date( 'Y-m-d H:i:s', time() - 3600 ) ),
		array( 'mobile' => $otp_mobile )
	);

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/otp/verify' );
	$req->set_json_params( array(
		'mobile' => $otp_mobile,
		'code'   => $GLOBALS['_last_sent_otp_code'],
	) );
	$res = $auth_ctrl->otp_verify( $req );
	return $res->get_status() === 400 && ! $res->get_data()['success'];
} );

$customer_a_tokens = null;
run_test( 'Issue and rotate Refresh Token (Single-Use Token Rotation)', function () use ( $auth_ctrl, &$customer_a_tokens ) {
	$user_id = 101; // Mock Customer A
	$tokens1 = TokenService::issue( $user_id, 'iPhone 15' );
	$customer_a_tokens = $tokens1;

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/token/refresh' );
	$req->set_json_params( array( 'refresh_token' => $tokens1['refresh_token'] ) );
	$res = $auth_ctrl->token_refresh( $req );
	$tokens2 = $res->get_data()['data'];

	// Verify new tokens issued and old refresh token revoked
	$rotated_ok = ( $res->get_status() === 200 && $tokens2['access_token'] !== $tokens1['access_token'] );

	// Attempting to reuse old refresh token must fail
	$reuse_req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/token/refresh' );
	$reuse_req->set_json_params( array( 'refresh_token' => $tokens1['refresh_token'] ) );
	$reuse_res = $auth_ctrl->token_refresh( $reuse_req );
	$reuse_failed = ( $reuse_res->get_status() === 401 );

	$customer_a_tokens = $tokens2;
	return $rotated_ok && $reuse_failed;
} );

run_test( 'Revoke session token and verify invalidated token cannot authenticate', function () use ( $auth_ctrl ) {
	$user_id = 102;
	$tokens  = TokenService::issue( $user_id, 'Web Client' );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/token/revoke' );
	$req->set_header( 'Authorization', 'Bearer ' . $tokens['access_token'] );
	$res = $auth_ctrl->token_revoke( $req );

	$revoked_valid = TokenService::validate( $tokens['access_token'] );
	return $res->get_status() === 200 && null === $revoked_valid;
} );

// ===========================================================================
// TEST SUITE 2: REST API V2 RESTAURANT CONFIGURATION & MENU
// ===========================================================================
echo "\n--- 2. REST API V2 RESTAURANT CONFIGURATION & MENU ---\n";

$settings_ctrl = new SettingsController();
$menu_ctrl     = new MenuController();
$branch_ctrl   = new BranchController();

run_test( 'GET /flavor/v2/settings/app-bootstrap returns branding, theme tokens & Jalali calendar', function () use ( $settings_ctrl ) {
	$req = new \WP_REST_Request( 'GET', '/flavor/v2/settings/app-bootstrap' );
	$res = $settings_ctrl->get_bootstrap();
	$data = $res->get_data();

	return $res->get_status() === 200 &&
		$data['success'] === true &&
		! empty( $data['data']['app']['name'] ) &&
		! empty( $data['data']['design']['primary_color'] ) &&
		! empty( $data['data']['currency']['display_unit'] ) &&
		! empty( $data['data']['calendar']['jalali_today'] );
} );

run_test( 'GET /flavor/v2/categories returns food categories with icons and counts', function () use ( $menu_ctrl ) {
	$res = $menu_ctrl->get_categories();
	$data = $res->get_data();
	return $res->get_status() === 200 && $data['success'] === true && is_array( $data['data'] ) && count( $data['data'] ) >= 3;
} );

run_test( 'GET /flavor/v2/menu returns branch-filtered dishes with modifiers and schedule states', function () use ( $menu_ctrl ) {
	$req = new \WP_REST_Request( 'GET', '/flavor/v2/menu' );
	$req->set_param( 'branch_id', 1 );
	$res = $menu_ctrl->get_menu( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 &&
		$data['success'] === true &&
		! empty( $data['meta']['pagination'] ) &&
		count( $data['data'] ) > 0 &&
		isset( $data['data'][0]['price_html'] );
} );

run_test( 'GET /flavor/v2/dishes/{id} returns full dish detail with modifier groups & calories', function () use ( $menu_ctrl ) {
	$req = new \WP_REST_Request( 'GET', '/flavor/v2/dishes/1' );
	$req->set_param( 'id', 1 );
	$req->set_param( 'branch_id', 1 );
	$res = $menu_ctrl->get_dish( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 &&
		$data['success'] === true &&
		$data['data']['id'] === 1;
} );

// ===========================================================================
// TEST SUITE 3: MOBILE GUEST CART (X-CART-TOKEN BRIDGE WITHOUT COOKIES)
// ===========================================================================
echo "\n--- 3. MOBILE GUEST CART (X-CART-TOKEN BRIDGE WITHOUT COOKIES) ---\n";

$cart_ctrl = new CartController();
$guest_cart_token = '';

run_test( 'Initial GET /flavor/v2/cart generates new X-Cart-Token header and token', function () use ( $cart_ctrl, &$guest_cart_token ) {
	wp_set_current_user( 0 );
	WC()->cart->empty_cart();

	$req = new \WP_REST_Request( 'GET', '/flavor/v2/cart' );
	$res = $cart_ctrl->get_cart( $req );
	$data = $res->get_data();

	$guest_cart_token = $res->get_headers()['X-Cart-Token'] ?? $data['data']['cart_token'] ?? '';
	return $res->get_status() === 200 &&
		! empty( $guest_cart_token ) &&
		str_starts_with( $guest_cart_token, 'fcart_' );
} );

run_test( 'POST /flavor/v2/cart/items with X-Cart-Token adds item with modifiers', function () use ( $cart_ctrl, $guest_cart_token ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'POST', '/flavor/v2/cart/items' );
	$req->set_header( 'X-Cart-Token', $guest_cart_token );
	$req->set_json_params( array(
		'product_id'   => 1, // کباب کوبیده زعفرانی
		'quantity'     => 2,
		'modifier_ids' => array( 'extra_rice' ),
		'instructions' => 'لطفاً سماق قرمز جداگانه ارسال شود.',
	) );
	$res = $cart_ctrl->add_item( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 &&
		$data['data']['count'] === 2 &&
		$data['data']['total'] > 0;
} );

run_test( 'Independent HTTP request (no cookies) reads exact cart using X-Cart-Token', function () use ( $cart_ctrl, $guest_cart_token ) {
	// Wipe memory session
	wp_set_current_user( 0 );
	WC()->cart->empty_cart();

	$req = new \WP_REST_Request( 'GET', '/flavor/v2/cart' );
	$req->set_header( 'X-Cart-Token', $guest_cart_token );
	$res = $cart_ctrl->get_cart( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 &&
		$data['data']['count'] === 2 &&
		count( $data['data']['items'] ) === 1;
} );

run_test( 'PUT /flavor/v2/cart/items/{key} updates item quantity to 3', function () use ( $cart_ctrl, $guest_cart_token ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'PUT', '/flavor/v2/cart/items/mock_item_key_1' );
	$req->set_header( 'X-Cart-Token', $guest_cart_token );
	$req->set_param( 'key', 'mock_item_key_1' );
	$req->set_json_params( array( 'quantity' => 3 ) );
	$res = $cart_ctrl->update_item( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 && $data['data']['count'] === 3;
} );

run_test( 'POST /flavor/v2/cart/coupon applies discount code to guest cart', function () use ( $cart_ctrl, $guest_cart_token ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'POST', '/flavor/v2/cart/coupon' );
	$req->set_header( 'X-Cart-Token', $guest_cart_token );
	$req->set_json_params( array( 'code' => 'NOROOZ1403' ) );
	$res = $cart_ctrl->apply_coupon( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 && in_array( 'NOROOZ1403', $data['data']['coupons'], true );
} );

// ===========================================================================
// TEST SUITE 4: GUEST CART TO AUTHENTICATED CUSTOMER MIGRATION
// ===========================================================================
echo "\n--- 4. GUEST CART TO AUTHENTICATED CUSTOMER MIGRATION ---\n";

run_test( 'Login with OTP and X-Cart-Token migrates guest cart to authenticated user', function () use ( $auth_ctrl, $cart_ctrl, $guest_cart_token ) {
	// Request OTP first for migration test user
	$mobile = '09129998877';
	$req_otp = new \WP_REST_Request( 'POST', '/flavor/v2/auth/otp/request' );
	$req_otp->set_json_params( array( 'mobile' => $mobile ) );
	$auth_ctrl->otp_request( $req_otp );
	$sent_code = $GLOBALS['_last_sent_otp_code'];

	// Perform login passing X-Cart-Token
	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/otp/verify' );
	$req->set_header( 'X-Cart-Token', $guest_cart_token );
	$req->set_json_params( array(
		'mobile' => $mobile,
		'code'   => $sent_code,
		'name'   => 'حسین محمدی',
	) );
	$res = $auth_ctrl->otp_verify( $req );
	$access_token = $res->get_data()['data']['tokens']['access_token'];

	// Verify authenticated user's cart now has the items
	$user_cart_req = new \WP_REST_Request( 'GET', '/flavor/v2/cart' );
	$user_cart_req->set_header( 'Authorization', 'Bearer ' . $access_token );
	$user_cart_res = $cart_ctrl->get_cart( $user_cart_req );
	$user_cart_data = $user_cart_res->get_data()['data'];

	$migrated_items_ok = ( $user_cart_data['count'] === 3 );

	// Verify old guest cart token is deleted/invalidated
	$old_guest_cart = CartTokenService::get_cart( $guest_cart_token );
	$old_invalidated = ( null === $old_guest_cart );

	return $migrated_items_ok && $old_invalidated;
} );

// ===========================================================================
// TEST SUITE 5: REAL ORDERS (GUEST & AUTHENTICATED)
// ===========================================================================
echo "\n--- 5. REAL ORDERS (GUEST & AUTHENTICATED) ---\n";

$order_ctrl = new OrderController();
$guest_order_id    = 0;
$guest_order_token = '';
$user_a_order_id   = 0;
$user_b_order_id   = 0;

run_test( 'Place Guest Delivery Order -> receives order ID & secure guest_token', function () use ( $order_ctrl, &$guest_order_id, &$guest_order_token ) {
	wp_set_current_user( 0 );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 1, 2 );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_json_params( array(
		'order_mode'     => 'delivery',
		'branch_id'      => 1,
		'mobile'         => '09351112233',
		'name'           => 'مشتری مهمان',
		'payment_method' => 'flavor_cod',
		'address'        => array(
			'province'     => 'تهران',
			'city'         => 'تهران',
			'neighborhood' => 'ونک',
			'line'         => 'خیابان ونک، کوچه بیست و سوم، پلاک ۴',
		),
		'notes'          => 'تحویل بدون تماس درب واحد',
	) );

	$res = $order_ctrl->create_order( $req );
	$data = $res->get_data();

	$guest_order_id    = (int) ( $data['data']['order_id'] ?? 0 );
	$guest_order_token = (string) ( $data['data']['guest_token'] ?? $res->get_headers()['X-Guest-Token'] ?? '' );

	return $res->get_status() === 201 &&
		$guest_order_id > 0 &&
		strlen( $guest_order_token ) === 64;
} );

run_test( 'Place Authenticated Customer A Order -> associated with user_id', function () use ( $order_ctrl, $customer_a_tokens, &$user_a_order_id ) {
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 2, 1 );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_header( 'Authorization', 'Bearer ' . $customer_a_tokens['access_token'] );
	$req->set_json_params( array(
		'order_mode'     => 'dine_in',
		'branch_id'      => 1,
		'table_number'   => 'T-04',
		'payment_method' => 'flavor_pay_at_counter',
	) );

	$res = $order_ctrl->create_order( $req );
	$data = $res->get_data();
	$user_a_order_id = (int) ( $data['data']['order_id'] ?? 0 );

	return $res->get_status() === 201 && $user_a_order_id > 0;
} );

run_test( 'Place Authenticated Customer B Order for Branch 2', function () use ( $order_ctrl, &$user_b_order_id ) {
	$tokens_b = TokenService::issue( 202, 'Customer B Device' );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 3, 2 );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_header( 'Authorization', 'Bearer ' . $tokens_b['access_token'] );
	$req->set_json_params( array(
		'order_mode'     => 'takeaway',
		'branch_id'      => 2,
		'payment_method' => 'flavor_pay_at_counter',
	) );

	$res = $order_ctrl->create_order( $req );
	$data = $res->get_data();
	$user_b_order_id = (int) ( $data['data']['order_id'] ?? 0 );

	return $res->get_status() === 201 && $user_b_order_id > 0;
} );

// ===========================================================================
// TEST SUITE 6: IDOR (INSECURE DIRECT OBJECT REFERENCE) SECURITY TESTS
// ===========================================================================
echo "\n--- 6. IDOR (INSECURE DIRECT OBJECT REFERENCE) SECURITY TESTS ---\n";

run_test( 'Guest Order IDOR: Unauthenticated request without guest_token fails with 401/403', function () use ( $order_ctrl, $guest_order_id ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'GET', "/flavor/v2/orders/{$guest_order_id}" );
	$req->set_param( 'id', $guest_order_id );
	$res = $order_ctrl->get_order( $req );
	return in_array( $res->get_status(), array( 401, 403 ), true );
} );

run_test( 'Guest Order IDOR: Request with invalid guest_token fails with 403 Forbidden', function () use ( $order_ctrl, $guest_order_id ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'GET', "/flavor/v2/orders/{$guest_order_id}" );
	$req->set_param( 'id', $guest_order_id );
	$req->set_header( 'X-Guest-Token', 'wrong_fake_token_123456789012345678901234567890123456789012345678' );
	$res = $order_ctrl->get_order( $req );
	return $res->get_status() === 403;
} );

run_test( 'Guest Order IDOR: Request with valid guest_token succeeds', function () use ( $order_ctrl, $guest_order_id, $guest_order_token ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'GET', "/flavor/v2/orders/{$guest_order_id}" );
	$req->set_param( 'id', $guest_order_id );
	$req->set_header( 'X-Guest-Token', $guest_order_token );
	$res = $order_ctrl->get_order( $req );
	return $res->get_status() === 200 && $res->get_data()['data']['id'] === $guest_order_id;
} );

run_test( 'Customer Cross-Account IDOR: Customer A cannot read Customer B order', function () use ( $order_ctrl, $customer_a_tokens, $user_b_order_id ) {
	$req = new \WP_REST_Request( 'GET', "/flavor/v2/orders/{$user_b_order_id}" );
	$req->set_param( 'id', $user_b_order_id );
	$req->set_header( 'Authorization', 'Bearer ' . $customer_a_tokens['access_token'] );
	$res = $order_ctrl->get_order( $req );
	return $res->get_status() === 403;
} );

run_test( 'Customer Cross-Account IDOR: Customer A cannot track Customer B order', function () use ( $order_ctrl, $customer_a_tokens, $user_b_order_id ) {
	$req = new \WP_REST_Request( 'GET', "/flavor/v2/orders/{$user_b_order_id}/track" );
	$req->set_param( 'id', $user_b_order_id );
	$req->set_header( 'Authorization', 'Bearer ' . $customer_a_tokens['access_token'] );
	$res = $order_ctrl->track_order( $req );
	return $res->get_status() === 403;
} );

run_test( 'Customer Cross-Account IDOR: Customer A cannot cancel Customer B order', function () use ( $order_ctrl, $customer_a_tokens, $user_b_order_id ) {
	$req = new \WP_REST_Request( 'POST', "/flavor/v2/orders/{$user_b_order_id}/cancel" );
	$req->set_param( 'id', $user_b_order_id );
	$req->set_header( 'Authorization', 'Bearer ' . $customer_a_tokens['access_token'] );
	$res = $order_ctrl->cancel_order( $req );
	return $res->get_status() === 403;
} );

run_test( 'Branch Staff Isolation: Staff from Branch 1 cannot access Branch 2 Kitchen Tickets', function () {
	$staff_b1_id = 301; // Branch 1 Chef
	Roles::set_user_branches( $staff_b1_id, array( 1 ) );

	$req = new \WP_REST_Request( 'GET', '/flavor/v2/kitchen/tickets' );
	$req->set_param( 'branch_id', 2 ); // Request Branch 2

	wp_set_current_user( $staff_b1_id );
	$master_ctrl = new \FlavorCore\API\RestController();
	$res = $master_ctrl->kitchen_list( $req );

	return is_wp_error( $res ) && $res->get_error_data()['status'] === 403;
} );

run_test( 'Admin Access: Administrator can view and manage all orders and branches', function () use ( $order_ctrl, $user_b_order_id ) {
	$admin_id = 1;
	wp_set_current_user( $admin_id );

	$req = new \WP_REST_Request( 'GET', "/flavor/v2/orders/{$user_b_order_id}" );
	$req->set_param( 'id', $user_b_order_id );
	$res = $order_ctrl->get_order( $req );

	return $res->get_status() === 200 && $res->get_data()['data']['id'] === $user_b_order_id;
} );

// ===========================================================================
// TEST SUITE 7: TABLE RESERVATIONS & JALALI CALENDAR
// ===========================================================================
echo "\n--- 7. TABLE RESERVATIONS & JALALI CALENDAR ---\n";

$res_ctrl = new ReservationController();
$guest_res_id    = 0;
$guest_res_token = '';

run_test( 'GET /flavor/v2/reservations/calendar returns Jalali month grid', function () use ( $res_ctrl ) {
	$req = new \WP_REST_Request( 'GET', '/flavor/v2/reservations/calendar' );
	$req->set_param( 'jy', 1403 );
	$req->set_param( 'jm', 7 );
	$res = $res_ctrl->get_calendar( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 &&
		$data['success'] === true &&
		$data['data']['month'] === 7 &&
		count( $data['data']['days'] ) === 30;
} );

run_test( 'GET /flavor/v2/reservations/slots calculates real-time table capacity', function () use ( $res_ctrl ) {
	$req = new \WP_REST_Request( 'GET', '/flavor/v2/reservations/slots' );
	$req->set_param( 'branch_id', 1 );
	$req->set_param( 'date', '2026-09-22' );
	$req->set_param( 'party', 4 );
	$res = $res_ctrl->get_slots( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 && is_array( $data['data']['slots'] ) && count( $data['data']['slots'] ) > 0;
} );

run_test( 'POST /flavor/v2/reservations creates reservation & issues guest_token', function () use ( $res_ctrl, &$guest_res_id, &$guest_res_token ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'POST', '/flavor/v2/reservations' );
	$req->set_json_params( array(
		'branch_id'  => 1,
		'date'       => '2026-09-22',
		'time'       => '20:30',
		'party_size' => 4,
		'mobile'     => '09127776655',
		'name'       => 'مهدی کمالی',
		'section'    => 'indoor',
		'requests'   => 'میز نزدیک پنجره',
	) );

	$res = $res_ctrl->book_table( $req );
	$data = $res->get_data();

	$guest_res_id    = (int) ( $data['data']['id'] ?? 0 );
	$guest_res_token = (string) ( $data['data']['guest_token'] ?? $res->get_headers()['X-Guest-Token'] ?? '' );

	return $res->get_status() === 201 &&
		$guest_res_id > 0 &&
		strlen( $guest_res_token ) === 64;
} );

run_test( 'GET /flavor/v2/reservations/{id} with valid guest_token retrieves reservation', function () use ( $res_ctrl, $guest_res_id, $guest_res_token ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'GET', "/flavor/v2/reservations/{$guest_res_id}" );
	$req->set_param( 'id', $guest_res_id );
	$req->set_header( 'X-Guest-Token', $guest_res_token );
	$res = $res_ctrl->get_reservation( $req );

	return $res->get_status() === 200 && $res->get_data()['data']['id'] === $guest_res_id;
} );

run_test( 'Reservation IDOR: Unauthorized user without token cannot cancel reservation', function () use ( $res_ctrl, $guest_res_id ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'POST', "/flavor/v2/reservations/{$guest_res_id}/cancel" );
	$req->set_param( 'id', $guest_res_id );
	$res = $res_ctrl->cancel_reservation( $req );

	return in_array( $res->get_status(), array( 401, 403 ), true );
} );

run_test( 'POST /flavor/v2/reservations/{id}/cancel with valid guest_token cancels reservation', function () use ( $res_ctrl, $guest_res_id, $guest_res_token ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'POST', "/flavor/v2/reservations/{$guest_res_id}/cancel" );
	$req->set_param( 'id', $guest_res_id );
	$req->set_header( 'X-Guest-Token', $guest_res_token );
	$res = $res_ctrl->cancel_reservation( $req );

	return $res->get_status() === 200 && ! empty( $res->get_data()['data']['cancelled'] );
} );

// ===========================================================================
// TEST SUITE 8: PUSH NOTIFICATION DISPATCHING & DEVICE REGISTRATION
// ===========================================================================
echo "\n--- 8. PUSH NOTIFICATION ARCHITECTURE & DEVICE REGISTRATION ---\n";

run_test( 'Register Android & iOS FCM device tokens for Customer A', function () {
	$user_id = 101;
	$reg1 = PushNotificationService::register_device( $user_id, 'fcm_token_android_pixel_8', 'android', '1.1.0' );
	$reg2 = PushNotificationService::register_device( $user_id, 'fcm_token_ios_ipad_pro', 'ios', '1.1.0' );

	$devices = PushNotificationService::get_user_devices( $user_id );
	return $reg1 && $reg2 &&
		in_array( 'fcm_token_android_pixel_8', $devices['android'], true ) &&
		in_array( 'fcm_token_ios_ipad_pro', $devices['ios'], true );
} );

run_test( 'User Notification Preferences update and query', function () {
	$user_id = 101;
	$updated = PushNotificationService::update_preferences( $user_id, array( 'order_status' => true, 'promotions' => false ) );
	$prefs   = PushNotificationService::get_preferences( $user_id );

	return $updated && $prefs['order_status'] === true && $prefs['promotions'] === false;
} );

run_test( 'Order Status Push & Multi-Channel Dispatch', function () {
	$dispatched = PushNotificationService::dispatch_order_status( 1001, 'preparing', 101, '09121234567' );
	return is_array( $dispatched ) && isset( $dispatched['push'] ) && $dispatched['push'] === true;
} );

run_test( 'Deactivate / Revoke devices on customer logout', function () {
	$user_id = 101;
	PushNotificationService::revoke_user_devices( $user_id );
	$devices = PushNotificationService::get_user_devices( $user_id );
	return empty( $devices['android'] ) && empty( $devices['ios'] );
} );

// ===========================================================================
// TEST SUITE 9: WHITE-LABEL MULTI-TENANT ISOLATION
// ===========================================================================
echo "\n--- 9. WHITE-LABEL MULTI-TENANT ISOLATION ---\n";

run_test( 'Verify Tenant A and Tenant B configuration separation', function () {
	$tenant_a = array(
		'restaurant_id'   => 'shandiz_mashhad',
		'app_name'        => 'شاندیز مشهد',
		'primary_color'   => '#C62828',
		'package_name'    => 'com.shandiz.restaurant',
		'deep_link_scheme'=> 'shandiz',
	);

	$tenant_b = array(
		'restaurant_id'   => 'nayeb_tehran',
		'app_name'        => 'رستوران نایب',
		'primary_color'   => '#1B5E20',
		'package_name'    => 'com.nayeb.restaurant',
		'deep_link_scheme'=> 'nayeb',
	);

	return $tenant_a['restaurant_id'] !== $tenant_b['restaurant_id'] &&
		$tenant_a['package_name'] !== $tenant_b['package_name'] &&
		$tenant_a['deep_link_scheme'] !== $tenant_b['deep_link_scheme'];
} );

// ===========================================================================
// SUMMARY & EXIT CODE
// ===========================================================================
echo "\n======================================================================\n";
echo "   INTEGRATION SUITE SUMMARY: {$passed_tests}/{$total_tests} PASSED ({$failed_tests} FAILED)        \n";
echo "======================================================================\n";

exit( $failed_tests > 0 ? 1 : 0 );
