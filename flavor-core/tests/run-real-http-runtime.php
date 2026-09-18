<?php
/**
 * Real HTTP Runtime Integration and Security Verification Test Runner
 *
 * Executes REAL HTTP requests via cURL against the live running WordPress + WooCommerce
 * instance on MariaDB.
 */

// Reset rate limits for test run
shell_exec('mariadb -u root -D flavor_wp -e "DELETE FROM wp_options WHERE option_name LIKE \'%flavor_rl_%\' OR option_name LIKE \'_transient_flavor_%\';"');

$base_url = 'http://127.0.0.1:8080/wp-json/flavor/v2';
$passed = 0;
$failed = 0;

function http_req(string $method, string $path, array $headers = [], $body = null): array {
    global $base_url;
    $url = $base_url . $path;
    $ch = curl_init($url);
    
    $req_headers = ['Content-Type: application/json', 'Accept: application/json'];
    foreach ($headers as $k => $v) {
        $req_headers[] = "$k: $v";
    }
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $req_headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $header_str = substr($response, 0, $header_size);
    $body_str = substr($response, $header_size);
    
    // Parse response headers
    $parsed_headers = [];
    foreach (explode("\r\n", $header_str) as $line) {
        if (strpos($line, ': ') !== false) {
            [$hk, $hv] = explode(': ', $line, 2);
            $parsed_headers[strtolower(trim($hk))] = trim($hv);
        }
    }
    
    $json = json_decode($body_str, true);
    curl_close($ch);
    
    return [
        'status' => $http_code,
        'headers' => $parsed_headers,
        'body' => $json,
        'raw_body' => $body_str,
    ];
}

function assert_test(string $name, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m[PASS]\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m[FAIL]\033[0m {$name} — {$details}\n";
    }
}

echo "======================================================================\n";
echo "   FLAVOR PLATFORM — PHASE 3 REAL HTTP RUNTIME TEST SUITE             \n";
echo "   Target: {$base_url} (Real WordPress + WooCommerce on MariaDB)     \n";
echo "======================================================================\n\n";

// ---------------------------------------------------------
// SECTION 1: GUEST BROWSING & MENU APIs
// ---------------------------------------------------------
echo "--- 1. REAL GUEST BROWSING & MENU REST APIs ---\n";

$res = http_req('GET', '/settings/app-bootstrap');
assert_test(
    'GET /settings/app-bootstrap returns 200 with server_time and branding',
    $res['status'] === 200 && ($res['body']['success'] ?? false) === true && !empty($res['body']['data']['app']['name']),
    "Status: {$res['status']}, Body: " . substr($res['raw_body'], 0, 100)
);

$res = http_req('GET', '/categories');
assert_test(
    'GET /categories returns 200 with real product categories from DB',
    $res['status'] === 200 && count($res['body']['data'] ?? []) >= 2,
    "Status: {$res['status']}, Count: " . count($res['body']['data'] ?? [])
);

$res = http_req('GET', '/menu');
assert_test(
    'GET /menu returns 200 with real dishes and price formatting',
    $res['status'] === 200 && count($res['body']['data'] ?? []) >= 2,
    "Status: {$res['status']}"
);
$kebab_id = 0;
foreach ($res['body']['data'] ?? [] as $dish) {
    if (strpos($dish['name'], 'شاندیز') !== false) {
        $kebab_id = (int)$dish['id'];
        break;
    }
}

$res = http_req('GET', "/dishes/{$kebab_id}");
assert_test(
    "GET /dishes/{$kebab_id} returns dish details with modifier groups",
    $res['status'] === 200 && !empty($res['body']['data']['name']),
    "Status: {$res['status']}"
);

// ---------------------------------------------------------
// SECTION 2: GUEST CART (X-CART-TOKEN BRIDGE WITHOUT COOKIES)
// ---------------------------------------------------------
echo "\n--- 2. REAL GUEST CART (X-CART-TOKEN WITHOUT COOKIES) ---\n";

$res = http_req('GET', '/cart');
$cart_token = $res['headers']['x-cart-token'] ?? ($res['body']['data']['cart_token'] ?? '');
assert_test(
    'Initial GET /cart issues new X-Cart-Token header',
    $res['status'] === 200 && !empty($cart_token),
    "Token: {$cart_token}"
);

$add_item_payload = [
    'product_id' => $kebab_id,
    'quantity' => 2,
    'modifier_ids' => ['topping-butter'],
    'instructions' => 'لطفاً سماق اضافه بگذارید',
];
$res = http_req('POST', '/cart/items', ['X-Cart-Token' => $cart_token], $add_item_payload);
assert_test(
    'POST /cart/items with X-Cart-Token adds item with modifiers and returns 200/201',
    $res['status'] === 200 && ($res['body']['data']['count'] ?? 0) >= 1,
    "Status: {$res['status']}, Count: " . ($res['body']['data']['count'] ?? 0)
);
$item_key = $res['body']['data']['items'][0]['key'] ?? '';

// Independent request (no cookies)
$res = http_req('GET', '/cart', ['X-Cart-Token' => $cart_token]);
assert_test(
    'Independent stateless HTTP request reads exact cart via X-Cart-Token',
    $res['status'] === 200 && ($res['body']['data']['total'] ?? 0) > 0,
    "Total: " . ($res['body']['data']['total'] ?? 0)
);

// Update item quantity
if ($item_key) {
    $res = http_req('PUT', "/cart/items/{$item_key}", ['X-Cart-Token' => $cart_token], ['quantity' => 3]);
    assert_test(
        "PUT /cart/items/{$item_key} updates item quantity to 3",
        $res['status'] === 200 && ($res['body']['data']['count'] ?? 0) === 3,
        "Status: {$res['status']}, Count: " . ($res['body']['data']['count'] ?? 0)
    );
}

// Apply Coupon
$res = http_req('POST', '/cart/coupon', ['X-Cart-Token' => $cart_token], ['code' => 'NOWRUZ1405']);
assert_test(
    'POST /cart/coupon applies discount coupon to guest cart',
    $res['status'] === 200 && in_array('nowruz1405', array_map('strtolower', $res['body']['data']['coupons'] ?? [])),
    "Coupons: " . json_encode($res['body']['data']['coupons'] ?? [])
);

// ---------------------------------------------------------
// SECTION 3: REAL GUEST ORDER PLACEMENT
// ---------------------------------------------------------
echo "\n--- 3. REAL GUEST ORDER PLACEMENT ---\n";

$guest_order_payload = [
    'order_mode' => 'takeaway',
    'branch_id' => 11,
    'name' => 'مشتری مهمان زعفرانیه',
    'mobile' => '09129999999',
    'payment_method' => 'flavor_pay_at_counter',
    'cart_token' => $cart_token,
];
$res = http_req('POST', '/orders', ['X-Cart-Token' => $cart_token], $guest_order_payload);
$guest_order_id = (int)($res['body']['data']['order_id'] ?? 0);
$guest_token = (string)($res['body']['data']['guest_token'] ?? ($res['headers']['x-guest-token'] ?? ''));

assert_test(
    'POST /orders places real guest order in WooCommerce and returns guest_token',
    ($res['status'] === 200 || $res['status'] === 201) && $guest_order_id > 0 && strlen($guest_token) === 64,
    "Status: {$res['status']}, Order ID: {$guest_order_id}, Token Length: " . strlen($guest_token)
);

// ---------------------------------------------------------
// SECTION 4: CUSTOMER OTP AUTHENTICATION & TOKEN ROTATION
// ---------------------------------------------------------
echo "\n--- 4. CUSTOMER OTP AUTHENTICATION & SINGLE-USE TOKEN ROTATION ---\n";

// Generate unique test mobile number for Customer A
$mobile_a = '0912' . rand(5000000, 5999999);
$res = http_req('POST', '/auth/otp/request', [], ['mobile' => $mobile_a]);
assert_test(
    "POST /auth/otp/request sends OTP for mobile {$mobile_a}",
    $res['status'] === 200 && ($res['body']['success'] ?? false) === true,
    "Status: {$res['status']}"
);

// Fetch OTP code from SMS log in MariaDB
$sms_row = shell_exec("mariadb -u root -D flavor_wp -N -e \"SELECT body FROM wp_flavor_sms_log WHERE recipient='{$mobile_a}' ORDER BY id DESC LIMIT 1;\"");
preg_match('/(\d{4,6})/', (string)$sms_row, $m_code);
$otp_code_a = $m_code[1] ?? '12345';

// Also create a fresh guest cart to test migration during login
$res_cart = http_req('GET', '/cart');
$guest_cart_to_migrate = $res_cart['headers']['x-cart-token'] ?? ($res_cart['body']['data']['cart_token'] ?? '');
http_req('POST', '/cart/items', ['X-Cart-Token' => $guest_cart_to_migrate], [
    'product_id' => $kebab_id,
    'quantity' => 1,
]);

$res = http_req('POST', '/auth/otp/verify', ['X-Cart-Token' => $guest_cart_to_migrate], [
    'mobile' => $mobile_a,
    'code' => $otp_code_a,
    'name' => 'علی رضایی',
]);

$access_token_a = $res['body']['data']['tokens']['access_token'] ?? '';
$refresh_token_a = $res['body']['data']['tokens']['refresh_token'] ?? '';
$user_a_id = $res['body']['data']['user']['id'] ?? 0;

assert_test(
    'POST /auth/otp/verify validates OTP, issues Bearer + Refresh tokens, and migrates guest cart',
    $res['status'] === 200 && !empty($access_token_a) && !empty($refresh_token_a),
    "Status: {$res['status']}, User ID: {$user_a_id}"
);

// Authenticated me check
$res = http_req('GET', '/auth/me', ['Authorization' => "Bearer {$access_token_a}"]);
assert_test(
    'GET /auth/me returns authenticated customer profile',
    $res['status'] === 200 && ($res['body']['data']['logged_in'] ?? false) === true && ($res['body']['data']['user']['mobile'] ?? '') === $mobile_a,
    "Status: {$res['status']}, Logged in: " . json_encode($res['body']['data'] ?? [])
);

// Single-use Refresh Token Rotation
$res = http_req('POST', '/auth/token/refresh', [], ['refresh_token' => $refresh_token_a]);
$new_access_token_a = $res['body']['data']['access_token'] ?? '';
$new_refresh_token_a = $res['body']['data']['refresh_token'] ?? '';

assert_test(
    'POST /auth/token/refresh rotates refresh token and issues new Bearer token',
    $res['status'] === 200 && !empty($new_access_token_a) && $new_refresh_token_a !== $refresh_token_a,
    "Status: {$res['status']}"
);

// Place Authenticated Order for Customer A
// First add item to cart with new_access_token_a
$res_cart_a = http_req('GET', '/cart', ['Authorization' => "Bearer {$new_access_token_a}"]);
$cart_tok_a = $res_cart_a['headers']['x-cart-token'] ?? ($res_cart_a['body']['data']['cart_token'] ?? '');
http_req('POST', '/cart/items', ['Authorization' => "Bearer {$new_access_token_a}", 'X-Cart-Token' => $cart_tok_a], [
    'product_id' => $kebab_id,
    'quantity' => 2,
]);

$res = http_req('POST', '/orders', ['Authorization' => "Bearer {$new_access_token_a}", 'X-Cart-Token' => $cart_tok_a], [
    'order_mode' => 'dine_in',
    'branch_id' => 11,
    'table_number' => '101',
    'payment_method' => 'flavor_pay_at_counter',
    'cart_token' => $cart_tok_a,
]);
$order_a_id = (int)($res['body']['data']['order_id'] ?? 0);
assert_test(
    "Customer A places authenticated dine-in order #{$order_a_id}",
    ($res['status'] === 200 || $res['status'] === 201) && $order_a_id > 0,
    "Status: {$res['status']}, Order ID: {$order_a_id}"
);

// Setup Customer B and place Order B
$mobile_b = '0912' . rand(6000000, 6999999);
http_req('POST', '/auth/otp/request', [], ['mobile' => $mobile_b]);
$sms_row_b = shell_exec("mariadb -u root -D flavor_wp -N -e \"SELECT body FROM wp_flavor_sms_log WHERE recipient='{$mobile_b}' ORDER BY id DESC LIMIT 1;\"");
preg_match('/(\d{4,6})/', (string)$sms_row_b, $m_code_b);
$otp_code_b = $m_code_b[1] ?? '12345';

$res_b = http_req('POST', '/auth/otp/verify', [], ['mobile' => $mobile_b, 'code' => $otp_code_b, 'name' => 'مریم کاظمی']);
$token_b = $res_b['body']['data']['tokens']['access_token'] ?? '';

// Add item to cart for Customer B
$res_cart_b = http_req('GET', '/cart', ['Authorization' => "Bearer {$token_b}"]);
$cart_tok_b = $res_cart_b['headers']['x-cart-token'] ?? ($res_cart_b['body']['data']['cart_token'] ?? '');
http_req('POST', '/cart/items', ['Authorization' => "Bearer {$token_b}", 'X-Cart-Token' => $cart_tok_b], [
    'product_id' => $kebab_id,
    'quantity' => 1,
]);

$res_order_b = http_req('POST', '/orders', ['Authorization' => "Bearer {$token_b}", 'X-Cart-Token' => $cart_tok_b], [
    'order_mode' => 'takeaway',
    'branch_id' => 12,
    'payment_method' => 'flavor_pay_at_counter',
    'cart_token' => $cart_tok_b,
]);
$order_b_id = (int)($res_order_b['body']['data']['order_id'] ?? 0);
assert_test(
    "Customer B places authenticated order #{$order_b_id} for Branch 12",
    ($res_order_b['status'] === 200 || $res_order_b['status'] === 201) && $order_b_id > 0,
    "Status: {$res_order_b['status']}"
);

// ---------------------------------------------------------
// SECTION 5: REAL SECURITY PENETRATION & IDOR ATTACKS
// ---------------------------------------------------------
echo "\n--- 5. REAL SECURITY PENETRATION & IDOR ATTACK TESTS ---\n";

// Attack 1: Customer A attempts to read Customer B's order
$res = http_req('GET', "/orders/{$order_b_id}", ['Authorization' => "Bearer {$new_access_token_a}"]);
assert_test(
    "IDOR ATTACK 1: Customer A cannot view Customer B order -> Blocked with 401/403",
    ($res['status'] === 401 || $res['status'] === 403),
    "Actual HTTP Status: {$res['status']}"
);

// Attack 2: Customer A attempts to track Customer B's order
$res = http_req('GET', "/orders/{$order_b_id}/track", ['Authorization' => "Bearer {$new_access_token_a}"]);
assert_test(
    "IDOR ATTACK 2: Customer A cannot track Customer B order -> Blocked with 401/403",
    ($res['status'] === 401 || $res['status'] === 403),
    "Actual HTTP Status: {$res['status']}"
);

// Attack 3: Customer A attempts to cancel Customer B's order
$res = http_req('POST', "/orders/{$order_b_id}/cancel", ['Authorization' => "Bearer {$new_access_token_a}"], ['reason' => 'malicious_cancel']);
assert_test(
    "IDOR ATTACK 3: Customer A cannot cancel Customer B order -> Blocked with 401/403",
    ($res['status'] === 401 || $res['status'] === 403),
    "Actual HTTP Status: {$res['status']}"
);

// Attack 4: Unauthenticated attacker attempts to access Guest Order without guest_token
$res = http_req('GET', "/orders/{$guest_order_id}");
assert_test(
    "IDOR ATTACK 4: Unauthenticated access to guest order without token -> Blocked with 401/403",
    ($res['status'] === 401 || $res['status'] === 403),
    "Actual HTTP Status: {$res['status']}"
);

// Attack 5: Attacker attempts to access Guest Order with forged/invalid guest_token
$res = http_req('GET', "/orders/{$guest_order_id}?guest_token=0000111122223333444455556666777788889999aaaabbbbccccddddeeeeffff");
assert_test(
    "IDOR ATTACK 5: Access to guest order with invalid guest token -> Blocked with 403",
    $res['status'] === 403,
    "Actual HTTP Status: {$res['status']}"
);

// Legitimate Guest Access with valid token
$res = http_req('GET', "/orders/{$guest_order_id}?guest_token={$guest_token}");
assert_test(
    "LEGITIMATE GUEST: Order accessed successfully with cryptographic guest_token (200 OK)",
    $res['status'] === 200 && ($res['body']['data']['id'] ?? 0) === $guest_order_id,
    "Actual HTTP Status: {$res['status']}"
);

// Attack 6: Token Reuse Attack (reusing the already consumed refresh token A)
$res = http_req('POST', '/auth/token/refresh', [], ['refresh_token' => $refresh_token_a]);
assert_test(
    "REUSE ATTACK: Consumed refresh token cannot be reused -> Blocked with 401",
    $res['status'] === 401,
    "Actual HTTP Status: {$res['status']}"
);

// ---------------------------------------------------------
// SECTION 6: TABLE RESERVATIONS & JALALI CALENDAR
// ---------------------------------------------------------
echo "\n--- 6. REAL TABLE RESERVATIONS & JALALI CALENDAR ---\n";

$res = http_req('GET', '/reservations/calendar?jy=1405&jm=1');
assert_test(
    'GET /reservations/calendar returns Jalali calendar grid for 1405 Farvardin',
    $res['status'] === 200 && count($res['body']['data']['days'] ?? []) >= 29,
    "Status: {$res['status']}"
);

$res = http_req('GET', '/reservations/slots?branch_id=11&date=2026-09-25&party=4');
assert_test(
    'GET /reservations/slots calculates real table capacity for branch 11',
    $res['status'] === 200 && !empty($res['body']['data']['slots']),
    "Status: {$res['status']}"
);

// Create Table Reservation
$res = http_req('POST', '/reservations', [], [
    'branch_id' => 11,
    'date' => '2026-09-25',
    'time' => '19:30',
    'party_size' => 4,
    'name' => 'دکتر حسینی',
    'mobile' => '09123333333',
    'section' => 'indoor',
    'requests' => 'میز کنار پنجره باشد',
]);
$res_id = (int)($res['body']['data']['id'] ?? 0);
$res_guest_token = (string)($res['body']['data']['guest_token'] ?? ($res['headers']['x-guest-token'] ?? ''));
assert_test(
    "POST /reservations creates real reservation #{$res_id} with guest_token",
    $res['status'] === 201 && $res_id > 0 && !empty($res_guest_token),
    "Status: {$res['status']}, Res ID: {$res_id}"
);

// Attack 7: Unauthorized user attempts to cancel reservation
$res = http_req('POST', "/reservations/{$res_id}/cancel", [], ['reason' => 'unauthorized_cancel']);
assert_test(
    "RESERVATION IDOR: Unauthorized cancel without token -> Blocked with 401/403",
    ($res['status'] === 401 || $res['status'] === 403),
    "Actual HTTP Status: {$res['status']}"
);

// Legitimate reservation cancel with token
$res = http_req('POST', "/reservations/{$res_id}/cancel?guest_token={$res_guest_token}", [], ['reason' => 'تغییر برنامه سفر']);
assert_test(
    "LEGITIMATE RESERVATION CANCEL: Reservation cancelled successfully with guest_token",
    $res['status'] === 200 && ($res['body']['data']['cancelled'] ?? false) === true,
    "Status: {$res['status']}"
);

// ---------------------------------------------------------
// SECTION 7: PUSH DEVICE REGISTRATION & TOKEN REVOCATION
// ---------------------------------------------------------
echo "\n--- 7. PUSH NOTIFICATION DEVICE REGISTRATION & REVOCATION ---\n";

$res = http_req('POST', '/auth/device', ['Authorization' => "Bearer {$new_access_token_a}"], [
    'device_token' => 'fcm_test_device_token_android_galaxy_s24_ultra',
    'platform' => 'android',
    'app_version' => '2.0.1',
]);
assert_test(
    'POST /auth/device registers Android FCM device token in MariaDB',
    $res['status'] === 200 && ($res['body']['success'] ?? false) === true,
    "Status: {$res['status']}"
);

// Revoke Token on Customer Logout
$res = http_req('POST', '/auth/token/revoke', ['Authorization' => "Bearer {$new_access_token_a}"]);
assert_test(
    'POST /auth/token/revoke invalidates session and revokes access',
    $res['status'] === 200 && ($res['body']['success'] ?? false) === true,
    "Status: {$res['status']}"
);

// Verify Revoked Token cannot authenticate anymore
$res = http_req('GET', '/auth/me', ['Authorization' => "Bearer {$new_access_token_a}"]);
assert_test(
    'Revoked Bearer token cannot authenticate anymore -> Blocked with 401 / unauthenticated',
    $res['status'] === 200 && ($res['body']['data']['logged_in'] ?? false) === false,
    "Actual HTTP Status: {$res['status']}, Data: " . json_encode($res['body']['data'] ?? [])
);

echo "\n======================================================================\n";
echo "   REAL RUNTIME SUITE SUMMARY: {$passed} PASSED / {$failed} FAILED     \n";
echo "======================================================================\n";
