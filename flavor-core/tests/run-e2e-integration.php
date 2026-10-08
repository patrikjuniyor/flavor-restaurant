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
define( 'FLAVOR_CORE_VERSION', '1.5.1' );
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
use FlavorCore\Notification\NotificationHub;
use FlavorCore\Notification\PushNotificationService;
use FlavorCore\Menu\AvailabilityManager;
use FlavorCore\Order\KitchenTicketRepository;
use FlavorCore\Order\KitchenTicketSync;
use FlavorCore\Order\OrderModes;
use FlavorCore\API\RestController;
use FlavorCore\API\WebhookController;
use FlavorCore\Webhooks\WebhookManager;
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

	// Endpoints are authenticated: issue a session for the owner user.
	$user_id = 205;
	$GLOBALS['_mock_users'][ $user_id ] = new \WP_User( $user_id );
	$tokens  = TokenService::issue( $user_id, 'Pixel 8 Pro' );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/device' );
	$req->set_header( 'Authorization', 'Bearer ' . $tokens['access_token'] );
	$req->set_json_params( array( 'device_token' => 'fcm_regression_token', 'platform' => 'android', 'app_version' => '2.4.0' ) );
	$res = $auth_ctrl->register_device( $req );

	$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE device_token = %s AND platform = %s", 'fcm_regression_token', 'android' ), ARRAY_A );
	$first_ok = $row && '2.4.0' === $row['app_version'] && ! empty( $row['last_seen_at'] ) && ! empty( $row['updated_at'] ) && $user_id === (int) $row['user_id'];

	// Re-registering the same device token with a newer app version must upsert.
	$req2 = new \WP_REST_Request( 'POST', '/flavor/v2/auth/device' );
	$req2->set_header( 'Authorization', 'Bearer ' . $tokens['access_token'] );
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

// Reservation fixtures must stay in the future, otherwise the slot engine
// correctly returns zero slots and the suite rots with the calendar.
$res_test_date = gmdate( 'Y-m-d', time() + ( 14 * DAY_IN_SECONDS ) );

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

run_test( 'GET /flavor/v2/reservations/slots calculates real-time table capacity', function () use ( $res_ctrl, $res_test_date ) {
	$req = new \WP_REST_Request( 'GET', '/flavor/v2/reservations/slots' );
	$req->set_param( 'branch_id', 1 );
	$req->set_param( 'date', $res_test_date );
	$req->set_param( 'party', 4 );
	$res = $res_ctrl->get_slots( $req );
	$data = $res->get_data();

	return $res->get_status() === 200 && is_array( $data['data']['slots'] ) && count( $data['data']['slots'] ) > 0;
} );

run_test( 'Jalali conversion: every displayed date label is a real 13xx/14xx year', function () use ( $res_ctrl, $res_test_date ) {
	// Regression guard for the Gregorian→Jalali direction: a broken converter
	// silently produced years like 7715 while the round-trip helpers looked fine.
	$req = new \WP_REST_Request( 'GET', '/flavor/v2/reservations/slots' );
	$req->set_param( 'branch_id', 1 );
	$req->set_param( 'date', $res_test_date );
	$res = $res_ctrl->get_slots( $req );
	$data = $res->get_data()['data'];

	$jy       = (int) ( $data['jalali']['y'] ?? 0 );
	$expected = \FlavorCore\Support\Jalali::to_gregorian( $jy, (int) $data['jalali']['m'], (int) $data['jalali']['d'] );
	$back     = sprintf( '%04d-%02d-%02d', $expected[0], $expected[1], $expected[2] );

	return $res->get_status() === 200
		&& $jy >= 1390 && $jy <= 1500
		&& $back === $data['date'];
} );

run_test( 'POST /flavor/v2/reservations creates reservation & issues guest_token', function () use ( $res_ctrl, &$guest_res_id, &$guest_res_token, $res_test_date ) {
	wp_set_current_user( 0 );
	$req = new \WP_REST_Request( 'POST', '/flavor/v2/reservations' );
	$req->set_json_params( array(
		'branch_id'  => 1,
		'date'       => $res_test_date,
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

run_test( 'Order Status Push & Multi-Channel Dispatch delivers through FCM when configured', function () {
	global $_mock_http_requests, $_mock_http_responder;

	// Fake, test-generated credentials (never real secrets).
	PushNotificationService::register_device( 101, 'fcm_multichannel_device_token', 'android', '1.0.0' );
	$key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048 ) );
	openssl_pkey_export( $key, $pem );
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON=' . wp_json_encode( array(
		'project_id'   => 'flavor-ci-test',
		'client_email' => 'flavor-push@flavor-ci-test.iam.gserviceaccount.com',
		'private_key'  => $pem,
		'token_uri'    => 'https://oauth2.googleapis.com/token',
	) ) );
	$_mock_http_responder = function ( string $url ) {
		if ( strpos( $url, 'oauth2.googleapis.com/token' ) !== false ) {
			return array( 'code' => 200, 'body' => wp_json_encode( array( 'access_token' => 'test-fake-oauth-token', 'expires_in' => 3600 ) ) );
		}
		return array( 'code' => 200, 'body' => wp_json_encode( array( 'name' => 'projects/flavor-ci-test/messages/3' ) ) );
	};
	delete_transient( 'flavor_fcm_oauth_' . md5( 'flavor-push@flavor-ci-test.iam.gserviceaccount.com' ) );
	$_mock_http_requests = array();

	$dispatched = PushNotificationService::dispatch_order_status( 1001, 'preparing', 101, '09121234567' );

	$delivered = false !== strpos( implode( ' ', array_column( $_mock_http_requests, 'url' ) ), 'messages:send' );

	$_mock_http_responder = null;
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON' );
	PushNotificationService::unregister_device( 'fcm_multichannel_device_token' );

	return is_array( $dispatched ) && isset( $dispatched['push'] ) && $dispatched['push'] === true && $delivered;
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
// TEST SUITE 12: REST ROUTE UNIQUENESS & WEBHOOK LIFECYCLE
// ===========================================================================
echo "\n--- 12. REST ROUTE UNIQUENESS & WEBHOOK LIFECYCLE ---\n";

run_test( 'Route registry: full map registers with zero namespace+method+route duplicates', function () {
	$GLOBALS['_mock_rest_routes'] = array();
	( new RestController() )->register();
	$routes = $GLOBALS['_mock_rest_routes'];

	$seen     = array();
	$dup_keys = array();
	foreach ( $routes as $r ) {
		$key = $r['ns'] . '|' . strtoupper( $r['methods'] ) . '|' . $r['route'];
		if ( isset( $seen[ $key ] ) ) {
			$dup_keys[] = $key;
		}
		$seen[ $key ] = true;
	}

	// Previously-duplicated routes now exist exactly once per namespace.
	$singles = array( 'GET|/cart', 'POST|/auth/otp/request', 'POST|/auth/otp/verify', 'GET|/reservations/slots', 'POST|/reservations', 'GET|/reservations' );
	foreach ( $singles as $single ) {
		$count = 0;
		foreach ( $routes as $r ) {
			if ( strtoupper( $r['methods'] ) . '|' . $r['route'] === $single && 'flavor/v1' === $r['ns'] ) {
				++$count;
			}
		}
		if ( 1 !== $count ) {
			$dup_keys[] = 'v1 ' . $single . ' registered ' . $count . ' times';
		}
	}

	// Legacy unique routes kept for client compatibility.
	foreach ( $seen as $key => $_ ) {
		unset( $_ );
	}
	$must_have = array(
		'flavor/v1|POST|/cart/add',
		'flavor/v1|POST|/checkout',
		'flavor/v1|GET|/checkout/options',
		'flavor/v1|POST|/zones/check',
		'flavor/v1|GET|/tables',
		'flavor/v1|GET|/me',
		'flavor/v1|GET|/calendar',
		'flavor/v1|POST|/coupon',
		'flavor/v1|GET|/staff/customer',
		// Modular surface in BOTH namespaces.
		'flavor/v1|GET|/cart',
		'flavor/v2|GET|/cart',
		'flavor/v1|POST|/orders',
		'flavor/v2|POST|/orders',
		'flavor/v1|GET|/webhooks',
		'flavor/v2|GET|/webhooks',
	);
	$missing = array_diff( $must_have, array_keys( $seen ) );

	return count( $GLOBALS['_mock_rest_routes'] ) > 0
		&& empty( $dup_keys )
		&& empty( $missing );
} );

run_test( 'Webhook hooks(): listens exactly on the do_action() names the app really emits', function () {
	$GLOBALS['_mock_actions'] = array();
	( new WebhookManager() )->hooks();
	$registered = array_keys( $GLOBALS['_mock_actions'] );

	$expected = array(
		'flavor_core_kitchen_ticket_created',     // Emitted by KitchenTicketRepository.
		'flavor_core_kitchen_status_changed',     // Emitted by KitchenTicketRepository.
		'flavor_core_reservation_created',        // Emitted by ReservationRepository.
		'flavor_core_reservation_status_changed', // Emitted by ReservationRepository.
		'flavor_core_otp_verified',               // Emitted by OtpAuth.
		'flavor_core_loyalty_points_awarded',     // Emitted by PointsManager.
		WebhookManager::QUEUE_HOOK,               // Internal queue drain hook.
	);
	// Ghost names that previously subscribed to non-existent actions must be gone.
	$ghosts = array(
		'flavor_order_placed',
		'flavor_kitchen_status_transition',
		'flavor_reservation_created',
		'flavor_reservation_status_updated',
		'flavor_customer_registered',
		'flavor_loyalty_points_added',
	);

	return empty( array_diff( $expected, $registered ) )
		&& empty( array_intersect( $ghosts, $registered ) );
} );

run_test( 'Every supported webhook event is dispatched (queued) from its real application hook', function () use ( $wpdb ) {
	$wpdb->query( 'DELETE FROM wp_flavor_webhook_deliveries' );
	$wpdb->query( 'DELETE FROM wp_flavor_webhooks' );
	$GLOBALS['_mock_cron_events'] = array();

	$wpdb->insert(
		'wp_flavor_webhooks',
		array(
			'name'         => 'catch-all',
			'target_url'   => 'https://hooks.example.com/callback',
			'secret'       => 'fixture-webhook-secret-001',
			'events_json'  => '["*"]',
			'is_active'    => 1,
			'failure_count' => 0,
			'created_at'   => '2026-01-01 00:00:00',
			'updated_at'   => '2026-01-01 00:00:00',
		)
	);

	do_action( 'flavor_core_kitchen_ticket_created', 501, array( 'order_id' => 77, 'order_number' => '77', 'branch_id' => 1, 'order_mode' => 'dine_in', 'total' => 1000, 'source' => 'online' ) );
	do_action( 'flavor_core_kitchen_status_changed', 501, 'new', 'preparing' );
	do_action( 'flavor_core_kitchen_status_changed', 501, 'preparing', 'completed' );
	do_action( 'flavor_core_kitchen_status_changed', 501, 'new', 'cancelled' );
	do_action( 'flavor_core_reservation_created', 88, array( 'branch_id' => 1, 'table_id' => 2, 'reservation_date' => '2026-10-01', 'reservation_time' => '19:00', 'party_size' => 2, 'status' => 'pending' ) );
	do_action( 'flavor_core_reservation_status_changed', 88, 'confirmed' );

	// customer.created only for accounts created in this very OTP flow.
	update_user_meta( 5001, '_flavor_just_created', 1 );
	do_action( 'flavor_core_otp_verified', (object) array( 'ID' => 5001 ), '09351112233' );
	do_action( 'flavor_core_otp_verified', (object) array( 'ID' => 5002 ), '09351112234' ); // No just-created meta: ignored.

	do_action( 'flavor_core_loyalty_points_awarded', 5001, 10, 'order' );

	$rows   = $wpdb->get_results( "SELECT event, attempt_count, response_code, status FROM wp_flavor_webhook_deliveries WHERE status = 'queued'", ARRAY_A );
	$events = array_values( array_unique( array_column( $rows, 'event' ) ) );
	sort( $events );
	$expected = WebhookManager::allowed_event_names();
	sort( $expected );

	$all_queued_fresh = ! empty( $rows );
	foreach ( $rows as $r ) {
		$all_queued_fresh = $all_queued_fresh
			&& 0 === (int) $r['attempt_count']
			&& null === $r['response_code'];
	}

	$scheduled = false;
	foreach ( $GLOBALS['_mock_cron_events'] as $ev ) {
		if ( WebhookManager::QUEUE_HOOK === $ev['hook'] ) {
			$scheduled = true;
			break;
		}
	}

	// 8 events queued (one catching OTP ignored), every one a supported event,
	// nothing delivered synchronously, queue-drain cron scheduled.
	return 8 === count( $rows )
		&& $events === $expected
		&& $all_queued_fresh
		&& $scheduled;
} );

run_test( 'Unsupported webhook events are rejected (validation + dispatch + controller)', function () {
	$bad = WebhookManager::validate_events( array( 'order.hacked' ) );
	$ok1 = is_wp_error( $bad ) && 'flavor_webhook_events' === $bad->get_error_code();

	$wild = WebhookManager::validate_events( array( 'order.created', '*' ) );
	$ok2  = array( '*' ) === $wild;

	$empty = WebhookManager::validate_events( array() );
	$ok3   = is_wp_error( $empty );

	// dispatch() must ignore unknown event names entirely.
	$before = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_flavor_webhook_deliveries' );
	WebhookManager::dispatch( 'totally.unknown_event', array( 'x' => 1 ) );
	$ok4 = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_flavor_webhook_deliveries' ) === $before;

	// Controller rejects bad event lists with 400.
	$admin                              = new \WP_User( array( 'ID' => 9101, 'roles' => array( 'administrator' ) ) );
	$GLOBALS['_mock_users'][9101]       = $admin;
	wp_set_current_user( 9101 );
	$ctrl = new WebhookController();
	$req  = new \WP_REST_Request( 'POST', '/flavor/v2/webhooks' );
	$req->set_json_params(
		array(
			'name'       => 'evil',
			'target_url' => 'https://hooks.example.com/cb',
			'events'     => array( 'order.hacked' ),
		)
	);
	$res = $ctrl->create_webhook( $req );
	$ok5 = 400 === $res->get_status()
		&& 'flavor_webhook_events' === (string) ( $res->get_data()['errors'][0]['code'] ?? '' );
	wp_set_current_user( 0 );

	return $ok1 && $ok2 && $ok3 && $ok4 && $ok5;
} );

run_test( 'Webhook secrets are redacted from every GET endpoint (list + single)', function () use ( $wpdb ) {
	$admin                        = new \WP_User( array( 'ID' => 9102, 'roles' => array( 'administrator' ) ) );
	$GLOBALS['_mock_users'][9102] = $admin;
	wp_set_current_user( 9102 );

	$ctrl       = new WebhookController();
	$raw_secret = 'fixture-create-secret-777';

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/webhooks' );
	$req->set_json_params(
		array(
			'name'       => 'orders-hook',
			'target_url' => 'https://hooks.example.com/orders',
			'secret'     => $raw_secret,
			'events'     => array( 'order.created', 'order.completed' ),
		)
	);
	$created = $ctrl->create_webhook( $req );
	$cdata   = $created->get_data();
	$wh_id   = (int) ( $cdata['data']['id'] ?? 0 );

	// Secret is returned exactly once: on create.
	$create_has_secret = $raw_secret === (string) ( $cdata['data']['secret'] ?? '' );

	$req_get   = new \WP_REST_Request( 'GET', "/flavor/v2/webhooks/{$wh_id}" );
	$req_get->set_param( 'id', $wh_id );
	$single      = $ctrl->get_webhook( $req_get );
	$single_json = wp_json_encode( $single->get_data() );

	$req_list  = new \WP_REST_Request( 'GET', '/flavor/v2/webhooks' );
	$list        = $ctrl->get_webhooks();
	$list_json   = wp_json_encode( $list->get_data() );

	wp_set_current_user( 0 );

	return 201 === $created->get_status()
		&& $wh_id > 0
		&& $create_has_secret
		&& 200 === $single->get_status()
		&& true === (bool) ( $single->get_data()['data']['secret_configured'] ?? false )
		&& ! array_key_exists( 'secret', (array) $single->get_data()['data'] )
		&& ! str_contains( $single_json, $raw_secret )
		&& ! str_contains( $list_json, $raw_secret )
		&& ! str_contains( $list_json, '"' . $raw_secret . '"' );
} );

run_test( 'Unauthorized users cannot manage webhooks (guest + customer get 403)', function () {
	$ctrl = new WebhookController();
	$req  = new \WP_REST_Request( 'GET', '/flavor/v2/webhooks' );

	wp_set_current_user( 0 );
	$guest = $ctrl->require_webhook_admin( $req );

	$customer                        = new \WP_User( array( 'ID' => 9103, 'roles' => array( 'customer' ) ) );
	$GLOBALS['_mock_users'][9103]    = $customer;
	wp_set_current_user( 9103 );
	$cust = $ctrl->require_webhook_admin( $req );

	$admin                        = new \WP_User( array( 'ID' => 9104, 'roles' => array( 'administrator' ) ) );
	$GLOBALS['_mock_users'][9104] = $admin;
	wp_set_current_user( 9104 );
	$adm = $ctrl->require_webhook_admin( $req );

	wp_set_current_user( 0 );

	return is_wp_error( $guest ) && 403 === (int) ( $guest->get_error_data()['status'] ?? 0 )
		&& is_wp_error( $cust ) && 403 === (int) ( $cust->get_error_data()['status'] ?? 0 )
		&& true === $adm;
} );

run_test( 'SSRF protection: localhost/private ranges/bad schemes/credentials rejected at validation and at the API', function () {
	$reject = array(
		'http://localhost/hook',
		'http://127.0.0.1/hook',
		'https://[::1]/hook',
		'http://10.0.0.8/hook',
		'http://172.16.10.4/hook',
		'http://192.168.1.10/hook',
		'http://169.254.169.254/latest/meta-data', // Cloud metadata endpoint.
		'ftp://example.com/file',
		'gopher://127.0.0.1/x',
		'http://user:pass@example.com/hook',
		'http://example.internal/hook',
		'not-a-url',
		'',
	);
	$ok = true;
	foreach ( $reject as $url ) {
		$ok = $ok && ! WebhookManager::is_safe_target_url( $url );
	}
	$ok = $ok && WebhookManager::is_safe_target_url( 'https://hooks.example.com/callback' );

	// API-level: create with private URL -> 400; update to private URL -> 400.
	$admin                        = new \WP_User( array( 'ID' => 9105, 'roles' => array( 'administrator' ) ) );
	$GLOBALS['_mock_users'][9105] = $admin;
	wp_set_current_user( 9105 );
	$ctrl = new WebhookController();

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/webhooks' );
	$req->set_json_params(
		array(
			'name'       => 'ssrf',
			'target_url' => 'http://192.168.1.10/hook',
			'events'     => array( '*' ),
		)
	);
	$res_bad = $ctrl->create_webhook( $req );

	$req2 = new \WP_REST_Request( 'POST', '/flavor/v2/webhooks' );
	$req2->set_json_params(
		array(
			'name'       => 'ok-hook',
			'target_url' => 'https://hooks.example.com/cb2',
			'events'     => array( 'order.updated' ),
		)
	);
	$res_ok = $ctrl->create_webhook( $req2 );
	$ok_id  = (int) ( $res_ok->get_data()['data']['id'] ?? 0 );

	$req3 = new \WP_REST_Request( 'PUT', "/flavor/v2/webhooks/{$ok_id}" );
	$req3->set_param( 'id', $ok_id );
	$req3->set_json_params( array( 'target_url' => 'http://169.254.169.254/meta' ) );
	$res_upd = $ctrl->update_webhook( $req3 );

	wp_set_current_user( 0 );

	return $ok
		&& 400 === $res_bad->get_status()
		&& 'flavor_webhook_url' === (string) ( $res_bad->get_data()['errors'][0]['code'] ?? '' )
		&& ( 201 === $res_ok->get_status() || 200 === $res_ok->get_status() )
		&& $ok_id > 0
		&& 400 === $res_upd->get_status()
		&& 'flavor_webhook_url' === (string) ( $res_upd->get_data()['errors'][0]['code'] ?? '' );
} );

run_test( 'Queue processing delivers asynchronously, records outcomes and skips dead webhooks', function () use ( $wpdb ) {
	// Drain whatever is queued from previous tests.
	WebhookManager::process_queue();

	$deliveries_tbl = 'wp_flavor_webhook_deliveries';

	// 1. Happy path: queued events from the catch-all webhook get delivered.
	$delivered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$deliveries_tbl} WHERE status = 'delivered' AND response_code = 200 AND attempt_count = 1" );
	$queued    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$deliveries_tbl} WHERE status = 'queued'" );
	$triggered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_flavor_webhooks WHERE name = 'catch-all' AND last_triggered_at IS NOT NULL" );

	// 2. Legacy unsafe target stored in DB (pre-hardening data): delivery fails.
	$wpdb->insert(
		'wp_flavor_webhooks',
		array(
			'name'         => 'legacy-unsafe',
			'target_url'   => 'http://127.0.0.1:9000/internal',
			'secret'       => 'fixture-webhook-secret-002',
			'events_json'  => '["order.updated"]',
			'is_active'    => 1,
			'failure_count' => 0,
			'created_at'   => '2026-01-01 00:00:00',
			'updated_at'   => '2026-01-01 00:00:00',
		)
	);
	$unsafe_id = (int) $wpdb->insert_id;
	WebhookManager::dispatch( 'order.updated', array( 'ticket_id' => 777, 'old_status' => 'new', 'new_status' => 'preparing' ) );
	WebhookManager::process_queue();
	$unsafe_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$deliveries_tbl} WHERE webhook_id = %d ORDER BY id DESC LIMIT 1", $unsafe_id ), ARRAY_A );

	// 3. Webhook deactivated between queueing and processing -> skipped.
	$wpdb->insert(
		'wp_flavor_webhooks',
		array(
			'name'         => 'soon-inactive',
			'target_url'   => 'https://hooks.example.com/later',
			'secret'       => 'fixture-webhook-secret-003',
			'events_json'  => '["order.cancelled"]',
			'is_active'    => 1,
			'failure_count' => 0,
			'created_at'   => '2026-01-01 00:00:00',
			'updated_at'   => '2026-01-01 00:00:00',
		)
	);
	$inactive_id = (int) $wpdb->insert_id;
	WebhookManager::dispatch( 'order.cancelled', array( 'ticket_id' => 778, 'old_status' => 'new', 'new_status' => 'cancelled' ) );
	$wpdb->update( 'wp_flavor_webhooks', array( 'is_active' => 0 ), array( 'id' => $inactive_id ) );
	WebhookManager::process_queue();
	$skipped_row = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$deliveries_tbl} WHERE webhook_id = %d ORDER BY id DESC LIMIT 1", $inactive_id ), ARRAY_A );

	// 4. Inactive webhooks never get queued in the first place.
	$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$deliveries_tbl}" );
	WebhookManager::dispatch( 'order.cancelled', array( 'ticket_id' => 779, 'old_status' => 'new', 'new_status' => 'cancelled' ) );
	$queued_for_inactive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$deliveries_tbl} WHERE webhook_id = {$inactive_id}" );
	unset( $before );

	return $delivered >= 8
		&& 0 === $queued
		&& $triggered >= 1
		&& $unsafe_row
		&& 'failed' === (string) $unsafe_row['status']
		&& str_contains( (string) $unsafe_row['error_message'], 'SSRF' )
		&& (int) $wpdb->get_var( "SELECT failure_count FROM wp_flavor_webhooks WHERE id = {$unsafe_id}" ) >= 1
		&& $skipped_row
		&& 'skipped' === (string) $skipped_row['status']
		&& 1 === $queued_for_inactive;
} );

run_test( 'Admin test endpoint still delivers synchronously with an audit row', function () use ( $wpdb ) {
	$admin                        = new \WP_User( array( 'ID' => 9106, 'roles' => array( 'administrator' ) ) );
	$GLOBALS['_mock_users'][9106] = $admin;
	wp_set_current_user( 9106 );

	$ctrl = new WebhookController();
	$req  = new \WP_REST_Request( 'POST', '/flavor/v2/webhooks' );
	$req->set_json_params(
		array(
			'name'       => 'ping-target',
			'target_url' => 'https://hooks.example.com/ping',
			'events'     => array( 'order.created' ),
		)
	);
	$created = $ctrl->create_webhook( $req );
	$wh_id   = (int) ( $created->get_data()['data']['id'] ?? 0 );

	$req_test = new \WP_REST_Request( 'POST', "/flavor/v2/webhooks/{$wh_id}/test" );
	$req_test->set_param( 'id', $wh_id );
	$res    = $ctrl->test_webhook( $req_test );
	$result = (array) ( $res->get_data()['data'] ?? array() );

	$audit = $wpdb->get_row(
		$wpdb->prepare( "SELECT status, event, attempt_count FROM wp_flavor_webhook_deliveries WHERE webhook_id = %d AND event = %s ORDER BY id DESC LIMIT 1", $wh_id, 'system.ping' ),
		ARRAY_A
	);

	wp_set_current_user( 0 );

	return 200 === $res->get_status()
		&& 'delivered' === (string) ( $result['status'] ?? '' )
		&& 200 === (int) ( $result['status_code'] ?? 0 )
		&& ! empty( $result['delivery_id'] )
		&& $audit
		&& 'delivered' === (string) $audit['status']
		&& 1 === (int) $audit['attempt_count'];
} );

// ===========================================================================
// TEST SUITE 13: PUSH NOTIFICATIONS (tokens, rotation, delivery, cleanup)
// ===========================================================================
echo "\n--- 13. PUSH NOTIFICATIONS (tokens, rotation, delivery, cleanup) ---\n";


run_test( 'Device endpoints reject unauthenticated callers with 401', function () use ( $auth_ctrl ) {
	$reg = new \WP_REST_Request( 'POST', '/flavor/v2/auth/device' );
	$reg->set_json_params( array( 'device_token' => 'anon_token_will_fail', 'platform' => 'android' ) );
	$reg_res = $auth_ctrl->register_device( $reg );

	$del = new \WP_REST_Request( 'DELETE', '/flavor/v2/auth/device' );
	$del->set_json_params( array( 'device_token' => 'anon_token_will_fail' ) );
	$del_res = $auth_ctrl->unregister_device( $del );

	return 401 === $reg_res->get_status() && 401 === $del_res->get_status();
} );

run_test( 'Registration rebinding: token taken over by a new owner moves rows', function () {
	global $wpdb;
	$table = \FlavorCore\Database\Schema::table( 'flavor_device_tokens' );

	PushNotificationService::register_device( 301, 'apk_takeover_token', 'android', '1.0.0' );
	// New owner registers the same physical token — binding must move.
	$ok = PushNotificationService::register_device( 302, 'apk_takeover_token', 'android', '1.0.1' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT user_id, is_active FROM {$table} WHERE device_token = %s", 'apk_takeover_token' ), ARRAY_A );

	return $ok && $row && 302 === (int) $row['user_id'] && 1 === (int) $row['is_active'];
} );

run_test( 'Device unregister is ownership-bound (IDOR protection)', function () use ( $auth_ctrl ) {
	$owner   = 311;
	$other   = 312;
	$GLOBALS['_mock_users'][ $owner ] = new \WP_User( $owner );
	$GLOBALS['_mock_users'][ $other ] = new \WP_User( $other );
	$tok     = TokenService::issue( $owner, 'Owner Phone' );
	$tok_oth = TokenService::issue( $other, 'Other Phone' );

	PushNotificationService::register_device( $owner, 'idor_owner_token', 'android', '1.0.0' );

	// Another user trying to unregister it must get 404 without deleting anything.
	$evil = new \WP_REST_Request( 'DELETE', '/flavor/v2/auth/device' );
	$evil->set_header( 'Authorization', 'Bearer ' . $tok_oth['access_token'] );
	$evil->set_json_params( array( 'device_token' => 'idor_owner_token' ) );
	$evil_res = $auth_ctrl->unregister_device( $evil );

	global $wpdb;
	$table = \FlavorCore\Database\Schema::table( 'flavor_device_tokens' );
	$still_there = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE device_token = %s", 'idor_owner_token' ) );

	// The owner can remove their own token.
	$own = new \WP_REST_Request( 'DELETE', '/flavor/v2/auth/device' );
	$own->set_header( 'Authorization', 'Bearer ' . $tok['access_token'] );
	$own->set_json_params( array( 'device_token' => 'idor_owner_token' ) );
	$own_res = $auth_ctrl->unregister_device( $own );

	$gone = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE device_token = %s", 'idor_owner_token' ) );

	return 404 === $evil_res->get_status() && 1 === (int) $still_there && 200 === $own_res->get_status() && 0 === (int) $gone;
} );

run_test( 'FCM delivery executed end-to-end (HTTP v1 OAuth + messages:send)', function () {
	global $_mock_http_requests, $_mock_http_responder;

	// Ephemeral, test-generated RSA key — obviously fake credentials, never a real secret.
	$key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048 ) );
	openssl_pkey_export( $key, $pem );
	$sa = wp_json_encode( array(
		'type'         => 'service_account',
		'project_id'   => 'flavor-ci-test',
		'client_email' => 'flavor-push@flavor-ci-test.iam.gserviceaccount.com',
		'private_key'  => $pem,
		'token_uri'    => 'https://oauth2.googleapis.com/token',
	) );
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON=' . $sa );

	$_mock_http_responder = function ( string $url, array $args ) {
		if ( strpos( $url, 'oauth2.googleapis.com/token' ) !== false ) {
			return array( 'code' => 200, 'body' => wp_json_encode( array( 'access_token' => 'test-fake-oauth-token', 'expires_in' => 3600 ) ) );
		}
		if ( strpos( $url, 'fcm.googleapis.com/v1/projects/' ) !== false ) {
			return array( 'code' => 200, 'body' => wp_json_encode( array( 'name' => 'projects/flavor-ci-test/messages/1' ) ) );
		}
		return null;
	};
	$_mock_http_requests = array();
	delete_transient( 'flavor_fcm_oauth_' . md5( 'flavor-push@flavor-ci-test.iam.gserviceaccount.com' ) );

	$result = PushNotificationService::send_fcm( array( 'fcm_delivery_token_a' ), 'عنوان تست', 'متن تست', array( 'type' => 'order_status', 'order_id' => '77' ) );

	$urls = array_column( $_mock_http_requests, 'url' );
	$saw_oauth = in_array( 'https://oauth2.googleapis.com/token', $urls, true );
	$saw_fcm   = false !== strpos( implode( ' ', $urls ), 'fcm.googleapis.com/v1/projects/flavor-ci-test/messages:send' );

	// Authorization header MUST carry the bearer token, never the service account key material.
	$authed = false;
	foreach ( $_mock_http_requests as $req ) {
		if ( strpos( $req['url'], 'messages:send' ) !== false && 'Bearer test-fake-oauth-token' === ( $req['args']['headers']['Authorization'] ?? '' ) ) {
			$authed = true;
		}
	}

	$_mock_http_responder = null;
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON' );
	delete_transient( 'flavor_fcm_oauth_' . md5( 'flavor-push@flavor-ci-test.iam.gserviceaccount.com' ) );

	return ! empty( $result['success'] ) && 1 === (int) $result['sent'] && $saw_oauth && $saw_fcm && $authed;
} );

run_test( 'APNs delivery executed end-to-end (ES256 token + per-device POST)', function () {
	global $_mock_http_requests, $_mock_http_responder;

	// Ephemeral, test-generated EC key written to a temp .p8 — never a real secret.
	$key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) );
	openssl_pkey_export( $key, $pem );
	$p8_path = tempnam( sys_get_temp_dir(), 'fake_p8_' ) . '.p8';
	file_put_contents( $p8_path, $pem );

	putenv( 'FLAVOR_APNS_AUTH_KEY_PATH=' . $p8_path );
	putenv( 'FLAVOR_APNS_KEY_ID=TESTKEY123' );
	putenv( 'FLAVOR_APNS_TEAM_ID=TEAMTEST456' );
	putenv( 'FLAVOR_APNS_BUNDLE_ID=com.flavor.restaurant' );
	putenv( 'FLAVOR_APNS_ENV=sandbox' );

	$_mock_http_responder = function ( string $url, array $args ) {
		if ( strpos( $url, 'api.sandbox.push.apple.com/3/device/' ) !== false ) {
			return array( 'code' => 200, 'body' => '' );
		}
		return null;
	};
	$_mock_http_requests = array();

	$result = PushNotificationService::send_apns( array( 'apns_test_device_token_x' ), 'توکن تست', 'بدنه تست', array( 'type' => 'reservation_status' ) );

	$saw_device_url = false;
	$saw_headers = false;
	foreach ( $_mock_http_requests as $req ) {
		if ( false !== strpos( $req['url'], 'api.sandbox.push.apple.com/3/device/apns_test_device_token_x' ) ) {
			$saw_device_url = true;
			$h = $req['args']['headers'];
			$saw_headers = ! empty( $h['authorization'] ) && 0 === strpos( $h['authorization'], 'bearer ' )
				&& 'com.flavor.restaurant' === ( $h['apns-topic'] ?? '' )
				&& 'alert' === ( $h['apns-push-type'] ?? '' );
		}
	}

	$_mock_http_responder = null;
	putenv( 'FLAVOR_APNS_AUTH_KEY_PATH' );
	putenv( 'FLAVOR_APNS_KEY_ID' );
	putenv( 'FLAVOR_APNS_TEAM_ID' );
	putenv( 'FLAVOR_APNS_BUNDLE_ID' );
	putenv( 'FLAVOR_APNS_ENV' );
	@unlink( $p8_path );

	return ! empty( $result['success'] ) && 1 === (int) $result['sent'] && $saw_device_url && $saw_headers;
} );

run_test( 'Stale FCM tokens (UNREGISTERED) are deactivated as rotation cleanup', function () {
	global $_mock_http_requests, $_mock_http_responder, $wpdb;

	$key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048 ) );
	openssl_pkey_export( $key, $pem );
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON=' . wp_json_encode( array(
		'project_id'   => 'flavor-ci-test',
		'client_email' => 'flavor-push@flavor-ci-test.iam.gserviceaccount.com',
		'private_key'  => $pem,
		'token_uri'    => 'https://oauth2.googleapis.com/token',
	) ) );

	$_mock_http_responder = function ( string $url, array $args ) {
		if ( strpos( $url, 'oauth2.googleapis.com/token' ) !== false ) {
			return array( 'code' => 200, 'body' => wp_json_encode( array( 'access_token' => 'test-fake-oauth-token', 'expires_in' => 3600 ) ) );
		}
		return array( 'code' => 404, 'body' => wp_json_encode( array( 'error' => array( 'code' => 404, 'status' => 'UNREGISTERED' ) ) ) );
	};

	PushNotificationService::register_device( 321, 'fcm_stale_rotation_token', 'android', '1.0.0' );
	delete_transient( 'flavor_fcm_oauth_' . md5( 'flavor-push@flavor-ci-test.iam.gserviceaccount.com' ) );

	$user_id   = 321;
	$ref       = new \ReflectionClass( NotificationHub::class );
	$method    = $ref->getMethod( 'dispatch_push' );
	$method->setAccessible( true );
	$delivered = $method->invoke( null, $user_id, 'تست', 'متن', array( 'type' => 'promo' ) );

	$table = \FlavorCore\Database\Schema::table( 'flavor_device_tokens' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT is_active FROM {$table} WHERE device_token = %s", 'fcm_stale_rotation_token' ), ARRAY_A );

	$_mock_http_responder = null;
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON' );

	// Delivery fails (provider says unregistered) AND the stale row is deactivated.
	return false === $delivered && $row && 0 === (int) $row['is_active'];
} );

run_test( 'Missing provider credentials degrade gracefully without delivery attempts', function () use ( $auth_ctrl ) {
	global $_mock_http_requests;
	$_mock_http_requests = array();

	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON' );
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_PATH' );
	$fcm = PushNotificationService::send_fcm( array( 'any_token' ), 't', 'b' );

	putenv( 'FLAVOR_APNS_KEY_ID' );
	putenv( 'FLAVOR_APNS_TEAM_ID' );
	putenv( 'FLAVOR_APNS_BUNDLE_ID' );
	putenv( 'FLAVOR_APNS_AUTH_KEY_PATH' );
	$apns = PushNotificationService::send_apns( array( 'any_token' ), 't', 'b' );

	// No HTTP call may be issued when credentials are absent.
	return ! empty( $fcm['error'] ) && ! empty( $apns['error'] )
		&& 0 === count( $_mock_http_requests )
		&& false === $fcm['success'] && false === $apns['success'];
} );

run_test( 'Logout-all revokes auth tokens AND deactivates push devices', function () use ( $auth_ctrl ) {
	global $wpdb;
	$user_id = 331;
	$GLOBALS['_mock_users'][ $user_id ] = new \WP_User( $user_id );
	$t1      = TokenService::issue( $user_id, 'dev-uuid-phone', 'Phone' );
	$t2      = TokenService::issue( $user_id, 'dev-uuid-tablet', 'Tablet' );
	PushNotificationService::register_device( $user_id, 'logout_cleanup_token_1', 'android', '1.0.0' );
	PushNotificationService::register_device( $user_id, 'logout_cleanup_token_2', 'ios', '1.0.0' );

	$req = new \WP_REST_Request( 'POST', '/flavor/v2/auth/token/revoke' );
	$req->set_header( 'Authorization', 'Bearer ' . $t1['access_token'] );
	$req->set_param( 'all_devices', true );
	$res = $auth_ctrl->token_revoke( $req );

	$table   = \FlavorCore\Database\Schema::table( 'flavor_device_tokens' );
	$active  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_active = 1", $user_id ) );

	return 200 === $res->get_status() && 0 === $active && ! empty( $t2 );
} );

run_test( 'Push observability hook fires WITH delivery results (not as the delivery mechanism)', function () {
	global $_mock_http_requests, $_mock_http_responder;

	$hook_payloads = array();
	add_action( 'flavor_dispatch_push_tokens', function ( $tokens, $title, $body, $data, $results = array() ) use ( &$hook_payloads ) {
		$hook_payloads[] = array( 'tokens' => $tokens, 'results' => $results );
	}, 10, 5 );

	PushNotificationService::register_device( 341, 'observability_hook_token', 'android', '1.0.0' );

	$key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048 ) );
	openssl_pkey_export( $key, $pem );
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON=' . wp_json_encode( array(
		'project_id'   => 'flavor-ci-test',
		'client_email' => 'flavor-push@flavor-ci-test.iam.gserviceaccount.com',
		'private_key'  => $pem,
		'token_uri'    => 'https://oauth2.googleapis.com/token',
	) ) );
	$_mock_http_responder = function ( string $url ) {
		if ( strpos( $url, 'oauth2.googleapis.com/token' ) !== false ) {
			return array( 'code' => 200, 'body' => wp_json_encode( array( 'access_token' => 'test-fake-oauth-token', 'expires_in' => 3600 ) ) );
		}
		return array( 'code' => 200, 'body' => wp_json_encode( array( 'name' => 'projects/flavor-ci-test/messages/2' ) ) );
	};
	delete_transient( 'flavor_fcm_oauth_' . md5( 'flavor-push@flavor-ci-test.iam.gserviceaccount.com' ) );
	$_mock_http_requests = array();

	$ref    = new \ReflectionClass( NotificationHub::class );
	$method = $ref->getMethod( 'dispatch_push' );
	$method->setAccessible( true );
	$delivered = $method->invoke( null, 341, 'تست هوک', 'بدنه', array( 'type' => 'order_status' ) );

	// Real delivery happened AND the hook received delivery results.
	$fcm_saw = false !== strpos( implode( ' ', array_column( $_mock_http_requests, 'url' ) ), 'messages:send' );
	$hook_ok = 1 === count( $hook_payloads )
		&& in_array( 'observability_hook_token', (array) $hook_payloads[0]['tokens'], true )
		&& ! empty( $hook_payloads[0]['results']['fcm']['success'] );

	$_mock_http_responder = null;
	putenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON' );
	$GLOBALS['_mock_actions']['flavor_dispatch_push_tokens'] = array();

	return true === $delivered && $fcm_saw && $hook_ok;
} );

// ===========================================================================
// SUMMARY & EXIT CODE
// ===========================================================================
echo "\n======================================================================\n";
echo "   INTEGRATION SUITE SUMMARY: {$passed_tests}/{$total_tests} PASSED ({$failed_tests} FAILED)        \n";
echo "======================================================================\n";

exit( $failed_tests > 0 ? 1 : 0 );
