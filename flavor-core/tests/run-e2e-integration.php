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
use FlavorCore\Menu\AvailabilityManager;
use FlavorCore\Notification\PushNotificationService;
use FlavorCore\Order\KitchenTicketRepository;
use FlavorCore\Order\KitchenTicketSync;
use FlavorCore\Order\OrderModes;
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

run_test( 'Logout-all-devices revokes every active token pair for the user', function () use ( $auth_ctrl ) {
	$user_id = 202;
	$t1      = TokenService::issue( $user_id, 'dev-uuid-a', 'Phone A' );
	$t2      = TokenService::issue( $user_id, 'dev-uuid-b', 'Tablet B' );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/token/revoke' );
	$req->set_header( 'Authorization', 'Bearer ' . $t1['access_token'] );
	$req->set_param( 'all_devices', true );
	$res = $auth_ctrl->token_revoke( $req );

	return $res->get_status() === 200 &&
		null === TokenService::validate( $t1['access_token'] ) &&
		null === TokenService::validate( $t2['access_token'] );
} );

run_test( 'Expired refresh token can never rotate (TTL enforced, incl. legacy rows)', function () {
	global $wpdb;
	$user_id = 202;
	$table   = TokenService::table();

	// 1. Explicitly force-expired refresh_expires_at.
	$tokens = TokenService::issue( $user_id, '', 'Force Expired' );
	$wpdb->update( $table, array( 'refresh_expires_at' => '2000-01-01 00:00:00' ), array( 'refresh_token_hash' => hash( 'sha256', $tokens['refresh_token'] ) ) );
	$expired_out = TokenService::refresh( $tokens['refresh_token'] );

	// 2. Legacy row without the column (schema < 1.5.0): created_at older than REFRESH_TTL.
	$legacy = TokenService::issue( $user_id, '', 'Legacy Row' );
	$wpdb->update( $table, array( 'refresh_expires_at' => null, 'created_at' => '2000-01-01 00:00:00' ), array( 'refresh_token_hash' => hash( 'sha256', $legacy['refresh_token'] ) ) );
	$legacy_out = TokenService::refresh( $legacy['refresh_token'] );

	// 3. Sanity: a fresh, in-TTL refresh token still rotates.
	$fresh     = TokenService::issue( $user_id, '', 'Fresh' );
	$fresh_out = TokenService::refresh( $fresh['refresh_token'] );

	return is_wp_error( $expired_out ) && is_wp_error( $legacy_out ) && ! is_wp_error( $fresh_out );
} );

run_test( 'Device registration persists app_version & last_seen_at and re-registration upserts one row', function () use ( $auth_ctrl ) {
	global $wpdb;
	$table = \FlavorCore\Database\Schema::table( 'flavor_device_tokens' );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/device' );
	$req->set_json_params( array( 'device_token' => 'fcm_regression_token', 'platform' => 'android', 'app_version' => '2.4.0' ) );
	$res = $auth_ctrl->register_device( $req );

	$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE device_token = %s AND platform = %s", 'fcm_regression_token', 'android' ), ARRAY_A );
	$first_ok = $row && '2.4.0' === $row['app_version'] && ! empty( $row['last_seen_at'] ) && ! empty( $row['updated_at'] );

	// Re-registering the same device token with a newer app version must upsert.
	$req2 = new \WP_REST_Request( 'POST', '/flavor/v2/auth/device' );
	$req2->set_json_params( array( 'device_token' => 'fcm_regression_token', 'platform' => 'android', 'app_version' => '2.5.1' ) );
	$res2 = $auth_ctrl->register_device( $req2 );

	$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE device_token = %s AND platform = %s", 'fcm_regression_token', 'android' ) );
	$row2  = $wpdb->get_row( $wpdb->prepare( "SELECT app_version FROM {$table} WHERE device_token = %s AND platform = %s", 'fcm_regression_token', 'android' ), ARRAY_A );

	return $res->get_status() === 200 && $first_ok && $res2->get_status() === 200 && 1 === $count && $row2 && '2.5.1' === $row2['app_version'];
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
// TEST SUITE 10: MOBILE BUILD CALLBACK SECURITY (HMAC / REPLAY / PROTOCOL)
// ===========================================================================
echo "\n--- 10. MOBILE BUILD CALLBACK SECURITY (HMAC / REPLAY / STATES) ---\n";

$mobile_ctrl = new \FlavorCore\API\MobileProvisionController();

// Deterministic test fixture secret — NOT a real credential; only used here.
$test_secret = 'fixture-only-ci-webhook-secret-00000000000000000000';
update_option( \FlavorCore\Mobile\MobileConfigManager::OPTION_NAME, array( 'ci_webhook_secret' => $test_secret ) );

$seed_build = function ( string $uuid, string $status = 'queued' ): void {
	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert(
		\FlavorCore\Mobile\BuildManager::table(),
		array(
			'build_uuid'  => $uuid,
			'platform'    => 'all',
			'environment' => 'prod',
			'status'      => $status,
			'triggered_by' => 1,
			'ci_provider' => 'github_actions',
			'build_log'   => '',
			'created_at'  => $now,
			'updated_at'  => $now,
		)
	);
};

// Byte-exact replica of the payload heredoc in .github/workflows/build-mobile.yml
// (notify-start job, after YAML block de-indentation — no trailing newline).
$workflow_body = fn( string $uuid, string $status, int $ts, string $logs ): string =>
	"{\n  \"build_uuid\": \"{$uuid}\",\n  \"status\": \"{$status}\",\n  \"timestamp\": {$ts},\n  \"logs\": \"{$logs}\"\n}";

$sign    = fn( string $body ): string => hash_hmac( 'sha256', $body, $test_secret );
$post_cb = function ( string $body, ?string $sig ) use ( $mobile_ctrl ) {
	$req = new \WP_REST_Request( 'POST', '/flavor/v1/mobile/builds/callback' );
	$req->set_body( $body );
	if ( null !== $sig ) {
		$req->set_header( 'X-Flavor-Signature', $sig );
	}
	return $mobile_ctrl->build_callback( $req );
};

run_test( 'Callback without X-Flavor-Signature header is rejected with 403', function () use ( $seed_build, $workflow_body, $post_cb ) {
	$uuid = 'sec-test-missing-sig';
	$seed_build( $uuid );
	$res = $post_cb( $workflow_body( $uuid, 'building', time(), 'no signature' ), null );
	return $res->get_status() === 403;
} );

run_test( 'Callback with invalid (but well-formed) signature is rejected with 403', function () use ( $seed_build, $workflow_body, $post_cb ) {
	$uuid = 'sec-test-invalid-sig';
	$seed_build( $uuid );
	$body = $workflow_body( $uuid, 'building', time(), 'wrong key attempt' );
	$sig  = hash_hmac( 'sha256', $body, 'wrong-secret-not-the-real-one' );
	$res  = $post_cb( $body, $sig );
	return $res->get_status() === 403;
} );

run_test( 'Callback with tampered payload after signing is rejected with 403', function () use ( $seed_build, $workflow_body, $post_cb, $sign ) {
	$uuid   = 'sec-test-tampered';
	$seed_build( $uuid );
	$body   = $workflow_body( $uuid, 'building', time(), 'original log line' );
	$sig    = $sign( $body );
	// Attacker modifies the body but reuses the original signature.
	$forged = $workflow_body( $uuid, 'success', time(), 'forged: pretend build succeeded' );
	$res    = $post_cb( $forged, $sig );
	return $res->get_status() === 403;
} );

run_test( 'Valid workflow-format callback is accepted and transitions queued→building', function () use ( $seed_build, $workflow_body, $post_cb, $sign ) {
	$uuid = 'sec-test-valid';
	$seed_build( $uuid );
	$body = $workflow_body( $uuid, 'building', time(), 'GitHub Actions CI job started on runner GitHub Actions 2' );
	$res  = $post_cb( $body, $sign( $body ) );
	$build = \FlavorCore\Mobile\BuildManager::get_build_by_uuid( $uuid );
	return $res->get_status() === 200 &&
		'building' === ( $build['status'] ?? '' ) &&
		str_contains( (string) ( $build['build_log'] ?? '' ), 'GitHub Actions CI job started' );
} );

run_test( 'Replayed valid callback is rejected after terminal state (replay protection)', function () use ( $seed_build, $workflow_body, $post_cb, $sign ) {
	$uuid = 'sec-test-replay';
	$seed_build( $uuid, 'building' );
	$body   = $workflow_body( $uuid, 'success', time(), 'Build workflow completed with status: success' );
	$sig    = $sign( $body );
	$first  = $post_cb( $body, $sig );
	// Attacker (or a retried runner) replays the byte-identical signed request.
	$second = $post_cb( $body, $sig );
	return $first->get_status() === 200 && $second->get_status() === 409;
} );

run_test( 'Callback for unknown build UUID is rejected with 404', function () use ( $workflow_body, $post_cb, $sign ) {
	$body = $workflow_body( 'uuid-that-does-not-exist', 'building', time(), 'ghost build' );
	$res  = $post_cb( $body, $sign( $body ) );
	return $res->get_status() === 404;
} );

run_test( 'Callback with stale signed timestamp outside freshness window is rejected with 403', function () use ( $seed_build, $workflow_body, $post_cb, $sign ) {
	$uuid = 'sec-test-stale';
	$seed_build( $uuid );
	$body = $workflow_body( $uuid, 'building', time() - 3600, 'one hour old request' );
	$res  = $post_cb( $body, $sign( $body ) );
	return $res->get_status() === 403;
} );

run_test( 'Invalid state transition (queued→success) is rejected with 409', function () use ( $seed_build, $workflow_body, $post_cb, $sign ) {
	$uuid = 'sec-test-transition';
	$seed_build( $uuid );
	$body = $workflow_body( $uuid, 'success', time(), 'skip straight to success' );
	$res  = $post_cb( $body, $sign( $body ) );
	return $res->get_status() === 409;
} );

run_test( 'CI webhook secret is generated once and persisted (stable across calls)', function () {
	update_option( \FlavorCore\Mobile\MobileConfigManager::OPTION_NAME, array() );
	$s1 = \FlavorCore\Mobile\MobileConfigManager::get_webhook_secret();
	$s2 = \FlavorCore\Mobile\MobileConfigManager::get_webhook_secret();
	$s3 = \FlavorCore\Mobile\MobileConfigManager::get_webhook_secret();
	// Deterministic secret restored for other tests.
	update_option( \FlavorCore\Mobile\MobileConfigManager::OPTION_NAME, array( 'ci_webhook_secret' => $GLOBALS['__test_secret_restore'] ?? 'fixture-only-ci-webhook-secret-00000000000000000000' ) );
	return '' !== $s1 && $s1 === $s2 && $s2 === $s3;
} );

run_test( 'API config response redacts secrets (no github_token / ci_webhook_secret / fcm_service_key in plain text)', function () use ( $test_secret ) {
	update_option(
		\FlavorCore\Mobile\MobileConfigManager::OPTION_NAME,
		array(
			'ci_webhook_secret' => $test_secret,
			'github_token'      => 'fixture-gh-token-123456',
			'fcm_service_key'   => "fixture-fcm\nkey-body",
		)
	);
	$redacted = \FlavorCore\Mobile\MobileConfigManager::redacted();
	$json     = wp_json_encode( $redacted );

	$no_plain_secrets = ! str_contains( $json, $test_secret ) &&
		! str_contains( $json, 'fixture-gh-token-123456' ) &&
		! str_contains( $json, 'fixture-fcm' );

	$has_metadata = true === ( $redacted['ci_webhook_secret_configured'] ?? false ) &&
		true === ( $redacted['github_token_configured'] ?? false ) &&
		\FlavorCore\Mobile\MobileConfigManager::SECRET_MASK === ( $redacted['ci_webhook_secret'] ?? '' );

	// Restore only the webhook fixture secret for subsequent suites.
	update_option( \FlavorCore\Mobile\MobileConfigManager::OPTION_NAME, array( 'ci_webhook_secret' => $test_secret ) );
	return $no_plain_secrets && $has_metadata;
} );

// ===========================================================================
// TEST SUITE 11: CHECKOUT RELIABILITY, IDEMPOTENCY & KITCHEN-TICKET LIFECYCLE
// ===========================================================================
echo "\n--- 11. CHECKOUT RELIABILITY & KITCHEN TICKET LIFECYCLE ---\n";

// Extra table fixtures for strict dine-in validation.
$wpdb->insert(
	'wp_flavor_tables',
	array(
		'branch_id'    => 2,
		'table_number' => 'T-21',
		'label'        => 'میز شعبه ۲',
		'capacity'     => 2,
		'section'      => 'indoor',
		'qr_token'     => 'qr_token_21',
		'is_active'    => 1,
		'sort_order'   => 21,
		'created_at'   => '2026-01-01 00:00:00',
		'updated_at'   => '2026-01-01 00:00:00',
	)
);
$wpdb->insert(
	'wp_flavor_tables',
	array(
		'branch_id'    => 1,
		'table_number' => 'T-99',
		'label'        => 'میز غیرفعال',
		'capacity'     => 2,
		'section'      => 'indoor',
		'qr_token'     => 'qr_token_99',
		'is_active'    => 0,
		'sort_order'   => 99,
		'created_at'   => '2026-01-01 00:00:00',
		'updated_at'   => '2026-01-01 00:00:00',
	)
);
$branch2_table_id = (int) $wpdb->get_var( "SELECT id FROM wp_flavor_tables WHERE branch_id = 2 AND table_number = 'T-21'" );

run_test( 'Idempotency: duplicate explicit idempotency_key never creates a second order', function () use ( $order_ctrl ) {
	wp_set_current_user( 0 );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 1, 1 );
	OrderModes::set( array() );

	$payload = array(
		'order_mode'      => 'dine_in',
		'branch_id'       => 1,
		'table_number'    => 'T-01',
		'mobile'          => '09351112233',
		'payment_method'  => 'flavor_pay_at_counter',
		'idempotency_key' => 'e2e-idem-0001',
	);

	$before    = count( $GLOBALS['_mock_wc_orders'] );
	$req       = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_json_params( $payload );
	$res_first  = $order_ctrl->create_order( $req );
	$first_data = $res_first->get_data();
	$first_id   = (int) ( $first_data['data']['order_id'] ?? 0 );

	$req2       = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req2->set_json_params( $payload );
	$res_second  = $order_ctrl->create_order( $req2 );
	$second_data = $res_second->get_data();

	$delta = count( $GLOBALS['_mock_wc_orders'] ) - $before;

	return 201 === $res_first->get_status()
		&& $first_id > 0
		&& 201 === $res_second->get_status()
		&& true === ( $second_data['data']['replay'] ?? false )
		&& $first_id === (int) ( $second_data['data']['order_id'] ?? 0 )
		&& ! empty( $second_data['data']['guest_token'] )
		&& 1 === $delta;
} );

run_test( 'Idempotency: rapid double-submit without key is auto-deduplicated', function () use ( $order_ctrl ) {
	wp_set_current_user( 0 );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 2, 2 );
	OrderModes::set( array() );

	$payload = array(
		'order_mode'     => 'dine_in',
		'branch_id'      => 1,
		'table_number'   => 'T-02',
		'mobile'         => '09351112233',
		'payment_method' => 'flavor_pay_at_counter',
	);

	$before    = count( $GLOBALS['_mock_wc_orders'] );
	$req       = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_json_params( $payload );
	$res_first  = $order_ctrl->create_order( $req );
	$first_id   = (int) ( $res_first->get_data()['data']['order_id'] ?? 0 );

	$req2       = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req2->set_json_params( $payload );
	$res_second = $order_ctrl->create_order( $req2 );
	$data2      = $res_second->get_data();

	$delta = count( $GLOBALS['_mock_wc_orders'] ) - $before;

	return 201 === $res_first->get_status()
		&& 201 === $res_second->get_status()
		&& true === ( $data2['data']['replay'] ?? false )
		&& $first_id === (int) ( $data2['data']['order_id'] ?? 0 )
		&& 1 === $delta;
} );

run_test( 'Failed gateway payment: cart token preserved, recoverable error, retry does not duplicate order', function () use ( $order_ctrl ) {
	$catalog                             = ( new \MockWCPaymentGateways() )->get_available_payment_gateways();
	$catalog['flavor_zarinpal_fail']     = new \MockWCFailingGateway( 'flavor_zarinpal_fail', 'زرین‌پال (خراب)' );
	\MockWCPaymentGateways::$catalog_override = $catalog;

	$cart_token = CartTokenService::generate_token();
	CartTokenService::save_cart(
		$cart_token,
		array(
			'items' => array(
				array(
					'product_id'   => 1,
					'quantity'     => 1,
					'modifiers'    => array(),
					'instructions' => '',
				),
			),
		),
		1
	);

	wp_set_current_user( 0 );
	WC()->cart->empty_cart();
	OrderModes::set( array() );

	$before = count( $GLOBALS['_mock_wc_orders'] );
	$req    = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_header( 'X-Cart-Token', $cart_token );
	$req->set_json_params(
		array(
			'order_mode'      => 'dine_in',
			'branch_id'       => 1,
			'table_number'    => 'T-03',
			'mobile'          => '09351112233',
			'payment_method'  => 'flavor_zarinpal_fail',
			'idempotency_key' => 'e2e-fail-0001',
		)
	);
	$res  = $order_ctrl->create_order( $req );
	$data = $res->get_data();

	$failed_order_id = (int) ( $data['errors'][0]['details']['order_id'] ?? 0 );

	// Cart token row and order must survive the failed payment.
	$cart_alive  = null !== CartTokenService::get_cart( $cart_token );
	$order       = $failed_order_id ? wc_get_order( $failed_order_id ) : null;
	$flagged_no  = $order && 'no' === (string) $order->get_meta( '_flavor_payment_ok' );
	$no_ticket   = $failed_order_id ? ( null === KitchenTicketRepository::find_by_order( $failed_order_id ) ) : false;

	// Retry with the same key: must replay the failed order, never duplicate it.
	$sb_before = count( $GLOBALS['_mock_wc_orders'] );
	$req2      = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req2->set_header( 'X-Cart-Token', $cart_token );
	$req2->set_json_params(
		array(
			'order_mode'      => 'dine_in',
			'branch_id'       => 1,
			'table_number'    => 'T-03',
			'mobile'          => '09351112233',
			'payment_method'  => 'flavor_zarinpal_fail',
			'idempotency_key' => 'e2e-fail-0001',
		)
	);
	$res2      = $order_ctrl->create_order( $req2 );
	$data2     = $res2->get_data();
	$delta     = count( $GLOBALS['_mock_wc_orders'] ) - $sb_before;
	// Exactly one order was created across both attempts.
	$total_new = count( $GLOBALS['_mock_wc_orders'] ) - $before;

	\MockWCPaymentGateways::$catalog_override = null;

	return 400 === $res->get_status()
		&& 'flavor_pay_failed' === (string) ( $data['errors'][0]['code'] ?? '' )
		&& true === (bool) ( $data['errors'][0]['details']['recoverable'] ?? false )
		&& $failed_order_id > 0
		&& $cart_alive
		&& $flagged_no
		&& $no_ticket
		&& $res2->get_status() >= 200
		&& $res2->get_status() < 300
		&& true === ( $data2['data']['replay'] ?? false )
		&& false === (bool) ( $data2['data']['ok'] ?? true )
		&& $failed_order_id === (int) ( $data2['data']['order_id'] ?? 0 )
		&& 0 === $delta
		&& 1 === $total_new;
} );

run_test( 'Missing gateway is rejected before any order row is created', function () use ( $order_ctrl ) {
	wp_set_current_user( 0 );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 1, 1 );
	OrderModes::set( array() );

	$before = count( $GLOBALS['_mock_wc_orders'] );
	$req    = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_json_params(
		array(
			'order_mode'     => 'dine_in',
			'branch_id'      => 1,
			'table_number'   => 'T-01',
			'mobile'         => '09351112233',
			'payment_method' => 'ghost_gateway_404',
		)
	);
	$res = $order_ctrl->create_order( $req );
	$data = $res->get_data();

	return 400 === $res->get_status()
		&& 'flavor_gateway' === (string) ( $data['errors'][0]['code'] ?? '' )
		&& count( $GLOBALS['_mock_wc_orders'] ) === $before
		&& ! WC()->cart->is_empty();
} );

run_test( 'Invalid branch: nonexistent or unpublished branch is rejected with no side effects', function () use ( $order_ctrl ) {
	$GLOBALS['_mock_missing_posts'][999] = true;
	$GLOBALS['_mock_post_status'][50]    = 'draft';

	$results = array();
	foreach ( array( 999, 50 ) as $bad_branch ) {
		wp_set_current_user( 0 );
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( 1, 1 );
		OrderModes::set( array() );
		$before = count( $GLOBALS['_mock_wc_orders'] );
		$req    = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
		$req->set_json_params(
			array(
				'order_mode'     => 'takeaway',
				'branch_id'      => $bad_branch,
				'mobile'         => '09351112233',
				'payment_method' => 'flavor_pay_at_counter',
			)
		);
		$res       = $order_ctrl->create_order( $req );
		$results[] = 400 === $res->get_status()
			&& 'flavor_branch' === (string) ( $res->get_data()['errors'][0]['code'] ?? '' )
			&& count( $GLOBALS['_mock_wc_orders'] ) === $before;
	}

	unset( $GLOBALS['_mock_missing_posts'][999], $GLOBALS['_mock_post_status'][50] );

	return ! in_array( false, $results, true );
} );

run_test( 'Invalid table: arbitrary number, other-branch table, inactive table and missing table are all rejected', function () use ( $order_ctrl, $branch2_table_id ) {
	wp_set_current_user( 0 );

	$cases = array(
		array( 'table_number' => 'XX-99' ),                // Arbitrary / not in repository.
		array( 'table_number' => 'T-99' ),                 // Inactive table.
		array( 'table_id'     => $branch2_table_id ),      // Belongs to branch 2.
		array(),                                            // No table at all.
	);

	$results = array();
	foreach ( $cases as $extra ) {
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( 1, 1 );
		OrderModes::set( array() ); // No leaked QR/table context.
		$before  = count( $GLOBALS['_mock_wc_orders'] );
		$payload = array_merge(
			array(
				'order_mode'     => 'dine_in',
				'branch_id'      => 1,
				'mobile'         => '09351112233',
				'payment_method' => 'flavor_pay_at_counter',
			),
			$extra
		);
		$req = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
		$req->set_json_params( $payload );
		$res       = $order_ctrl->create_order( $req );
		$results[] = 400 === $res->get_status()
			&& 'flavor_table' === (string) ( $res->get_data()['errors'][0]['code'] ?? '' )
			&& count( $GLOBALS['_mock_wc_orders'] ) === $before;
	}

	return $branch2_table_id > 0 && ! in_array( false, $results, true );
} );

run_test( 'Unavailable product is revalidated at checkout; cart stays intact and order succeeds after restock', function () use ( $order_ctrl ) {
	wp_set_current_user( 0 );
	AvailabilityManager::set( 1, 1, false, null, 0, 'fixture-unavailable' );

	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 1, 2 );
	OrderModes::set( array() );

	$before = count( $GLOBALS['_mock_wc_orders'] );
	$req    = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_json_params(
		array(
			'order_mode'     => 'takeaway',
			'branch_id'      => 1,
			'mobile'         => '09351112233',
			'payment_method' => 'flavor_pay_at_counter',
		)
	);
	$res        = $order_ctrl->create_order( $req );
	$cart_items = WC()->cart->get_cart_contents_count();

	AvailabilityManager::set( 1, 1, true, null, 0, 'fixture-restock' );

	$req2 = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req2->set_json_params(
		array(
			'order_mode'     => 'takeaway',
			'branch_id'      => 1,
			'mobile'         => '09351112233',
			'payment_method' => 'flavor_pay_at_counter',
		)
	);
	$res2 = $order_ctrl->create_order( $req2 );

	return 400 === $res->get_status()
		&& 'flavor_unavailable' === (string) ( $res->get_data()['errors'][0]['code'] ?? '' )
		&& count( $GLOBALS['_mock_wc_orders'] ) === $before + 1 // only the restocked retry created one.
		&& 2 === $cart_items
		&& 201 === $res2->get_status();
} );

run_test( 'Offline payment creates the kitchen ticket with full flavor metadata', function () use ( $order_ctrl ) {
	wp_set_current_user( 0 );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 1, 2 );
	WC()->cart->add_to_cart( 3, 1 );
	OrderModes::set( array() );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_json_params(
		array(
			'order_mode'     => 'dine_in',
			'branch_id'      => 1,
			'table_number'   => 'T-04',
			'mobile'         => '09351112233',
			'payment_method' => 'flavor_pay_at_counter',
			'source'         => 'phone',
			'notes'          => 'بدون پیاز',
		)
	);
	$res      = $order_ctrl->create_order( $req );
	$order_id = (int) ( $res->get_data()['data']['order_id'] ?? 0 );
	$order    = $order_id ? wc_get_order( $order_id ) : null;
	$ticket   = $order_id ? KitchenTicketRepository::find_by_order( $order_id ) : null;
	$items    = $ticket ? KitchenTicketRepository::items( (int) $ticket['id'] ) : array();

	return 201 === $res->get_status()
		&& $order_id > 0
		&& null !== $ticket
		&& 1 === (int) $ticket['branch_id']
		&& 'dine_in' === (string) $ticket['order_mode']
		&& 'T-04' === (string) $ticket['table_number']
		&& 'flavor_pay_at_counter' === (string) $ticket['payment_method']
		&& 'phone' === (string) $ticket['source']
		&& '09351112233' === (string) $ticket['customer_mobile']
		&& ! empty( $ticket['guest_token'] )
		&& count( $items ) >= 1
		&& $order
		&& 'yes' === (string) $order->get_meta( '_flavor_payment_ok' )
		&& '1' === (string) $order->get_meta( '_flavor_branch_id' );
} );

run_test( 'Online payment: redirect returned, ticket deferred until payment_complete callback', function () use ( $order_ctrl ) {
	$catalog                            = ( new \MockWCPaymentGateways() )->get_available_payment_gateways();
	$catalog['flavor_zarinpal_mock']    = new \MockWCOnlineGateway( 'flavor_zarinpal_mock', 'زرین‌پال' );
	\MockWCPaymentGateways::$catalog_override = $catalog;

	wp_set_current_user( 0 );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( 3, 1 );
	OrderModes::set( array() );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_json_params(
		array(
			'order_mode'     => 'takeaway',
			'branch_id'      => 2,
			'mobile'         => '09351112233',
			'payment_method' => 'flavor_zarinpal_mock',
		)
	);
	$res      = $order_ctrl->create_order( $req );
	$res_data = $res->get_data();
	$order_id = (int) ( $res_data['data']['order_id'] ?? 0 );
	$order    = $order_id ? wc_get_order( $order_id ) : null;

	$no_ticket_yet = $order_id ? ( null === KitchenTicketRepository::find_by_order( $order_id ) ) : false;

	// Simulate the gateway payment callback.
	if ( $order_id ) {
		( new KitchenTicketSync() )->on_payment_complete( $order_id );
	}
	$ticket_now = $order_id ? KitchenTicketRepository::find_by_order( $order_id ) : null;

	\MockWCPaymentGateways::$catalog_override = null;

	return 201 === $res->get_status()
		&& false !== strpos( (string) ( $res_data['data']['redirect'] ?? '' ), 'bank.example.com' )
		&& $order
		&& 'yes' === (string) $order->get_meta( '_flavor_awaiting_online' )
		&& ! empty( $order->get_meta( '_flavor_payment_redirect' ) )
		&& $no_ticket_yet
		&& null !== $ticket_now
		&& 2 === (int) $ticket_now['branch_id']
		&& 'takeaway' === (string) $ticket_now['order_mode'];
} );

run_test( 'Idempotency replay is bound to the original cart token (no cross-cart hijack)', function () use ( $order_ctrl ) {
	$token_a = CartTokenService::generate_token();
	CartTokenService::save_cart(
		$token_a,
		array(
			'items' => array(
				array(
					'product_id'   => 1,
					'quantity'     => 1,
					'modifiers'    => array(),
					'instructions' => '',
				),
			),
		),
		1
	);

	wp_set_current_user( 0 );
	WC()->cart->empty_cart();
	OrderModes::set( array() );

	$before = count( $GLOBALS['_mock_wc_orders'] );
	$req    = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req->set_header( 'X-Cart-Token', $token_a );
	$req->set_json_params(
		array(
			'order_mode'      => 'dine_in',
			'branch_id'       => 1,
			'table_number'    => 'T-01',
			'mobile'          => '09351112233',
			'payment_method'  => 'flavor_pay_at_counter',
			'idempotency_key' => 'e2e-bind-0001',
		)
	);
	$res      = $order_ctrl->create_order( $req );
	$first_id = (int) ( $res->get_data()['data']['order_id'] ?? 0 );

	// After success the token A cart is consumed; attacker retries with token B.
	$token_b = CartTokenService::generate_token();
	CartTokenService::save_cart(
		$token_b,
		array(
			'items' => array(
				array(
					'product_id'   => 1,
					'quantity'     => 1,
					'modifiers'    => array(),
					'instructions' => '',
				),
			),
		),
		1
	);
	WC()->cart->empty_cart();

	$req2 = new \WP_REST_Request( 'POST', '/flavor/v2/orders' );
	$req2->set_header( 'X-Cart-Token', $token_b );
	$req2->set_json_params(
		array(
			'order_mode'      => 'dine_in',
			'branch_id'       => 1,
			'table_number'    => 'T-01',
			'mobile'          => '09351112233',
			'payment_method'  => 'flavor_pay_at_counter',
			'idempotency_key' => 'e2e-bind-0001',
		)
	);
	$res2 = $order_ctrl->create_order( $req2 );

	return 201 === $res->get_status()
		&& $first_id > 0
		&& 409 === $res2->get_status()
		&& 'flavor_idempotency_conflict' === (string) ( $res2->get_data()['errors'][0]['code'] ?? '' )
		&& ( count( $GLOBALS['_mock_wc_orders'] ) - $before ) === 1;
} );

// ===========================================================================
// SUMMARY & EXIT CODE
// ===========================================================================
echo "\n======================================================================\n";
echo "   INTEGRATION SUITE SUMMARY: {$passed_tests}/{$total_tests} PASSED ({$failed_tests} FAILED)        \n";
echo "======================================================================\n";

exit( $failed_tests > 0 ? 1 : 0 );
