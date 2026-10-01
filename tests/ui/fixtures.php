<?php
/**
 * Disposable UI fixtures, never on a live site. No checkout/payment/SMS call.
 * FLAVOR_DEMO_QA=1 wp eval-file tests/ui/fixtures.php
 * UI_QA_CLEANUP=1 FLAVOR_DEMO_QA=1 wp eval-file tests/ui/fixtures.php
 */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Disposable opted-in WordPress only.' ); }
if ( ! function_exists( 'wc_get_products' ) || ! defined( 'FLAVOR_CORE_VERSION' ) ) { throw new RuntimeException( 'WooCommerce/Core required.' ); }
$dir = dirname( __DIR__, 2 ) . '/.cache/ui-qa'; wp_mkdir_p( $dir ); $file = $dir . '/fixtures.json';
if ( file_exists( $file ) ) {
 $old = json_decode( file_get_contents( $file ), true );
 foreach ( $old['orders'] ?? array() as $id ) { $order = wc_get_order( $id ); if ( $order && '1' === $order->get_meta( '_flavor_ui_qa' ) ) { global $wpdb; $wpdb->delete( \FlavorCore\Database\Schema::table( 'flavor_kitchen_tickets' ), array( 'order_id' => $id ) ); $order->delete( true ); } }
 require_once ABSPATH . 'wp-admin/includes/user.php';
 foreach ( $old['users'] ?? array() as $id ) { if ( '1' === get_user_meta( $id, '_flavor_ui_qa', true ) ) { global $wpdb; $wpdb->delete( \FlavorCore\Database\Schema::table( 'flavor_loyalty_ledger' ), array( 'customer_id' => $id ) ); wp_delete_user( $id ); } }
 unlink( $file );
}
if ( '1' === getenv( 'UI_QA_CLEANUP' ) ) { echo 'UI QA fixtures cleaned.' . PHP_EOL; return; }
$user_ids = array(); $suffix = strtolower( wp_generate_password( 6, false ) ); $password = wp_generate_password( 32 );
foreach ( array( 'a', 'b' ) as $letter ) {
 $id = wp_insert_user( array( 'user_login' => 'ui_qa_' . $suffix . '_' . $letter, 'user_pass' => $password, 'user_email' => 'ui_qa_' . $suffix . '_' . $letter . '@example.invalid', 'display_name' => 'مشتری آزمایشی UI ' . strtoupper( $letter ), 'role' => 'customer' ) );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 update_user_meta( $id, '_flavor_ui_qa', '1' ); $user_ids[] = $id;
}
$mobile = '09120001001'; update_user_meta( $user_ids[0], '_flavor_mobile', $mobile );
$products = wc_get_products( array( 'limit' => 1 ) ); $product = $products[0]; $ids = array(); $guest_token = \FlavorCore\Support\GuestToken::generate();
foreach ( array( $user_ids[0], $user_ids[1], 0 ) as $i => $customer ) {
 $order = wc_create_order( array( 'customer_id' => $customer, 'status' => 'pending', 'created_via' => 'ui_qa_fixture' ) );
 $order->set_currency( 'IRR' ); $order->set_billing_first_name( 'Fixture UI' ); $order->set_billing_email( 'ui-qa-fixture@example.invalid' );
 $order->add_product( $product, 1 ); $order->set_payment_method( 'flavor_pay_at_counter' ); $order->set_payment_method_title( 'پرداخت هنگام دریافت — Fixture' );
 $order->update_meta_data( '_flavor_ui_qa', '1' ); $order->update_meta_data( '_flavor_branch_id', \FlavorCore\PostTypes\BranchPostType::default_id() ); $order->update_meta_data( '_flavor_order_mode', 'takeaway' );
 if ( 2 === $i ) { $order->update_meta_data( '_flavor_guest_token', $guest_token ); }
 $order->calculate_totals(); $order->save(); $ids[] = $order->get_id();
}
$guest_order = wc_get_order( $ids[2] ); $guest_order->set_status( 'processing' ); $guest_order->save();
// Seed a real row without triggering notifications or fulfillment actions.
global $wpdb; $now = current_time( 'mysql' );
$existing_ticket = \FlavorCore\Order\KitchenTicketRepository::find_by_order( $ids[2] );
if ( $existing_ticket ) {
 $wpdb->update( \FlavorCore\Database\Schema::table( 'flavor_kitchen_tickets' ), array( 'kitchen_status' => 'ready', 'ready_at' => $now ), array( 'id' => $existing_ticket['id'] ) );
} else {
 $wpdb->insert( \FlavorCore\Database\Schema::table( 'flavor_kitchen_tickets' ), array( 'order_id' => $ids[2], 'order_number' => (string) $ids[2], 'branch_id' => \FlavorCore\PostTypes\BranchPostType::default_id(), 'order_mode' => 'takeaway', 'kitchen_status' => 'ready', 'payment_status' => 'pending', 'source' => 'ui_qa_fixture', 'placed_at' => $now, 'created_at' => $now, 'updated_at' => $now ) );
}
// Seed a known one-time hash, not a send request; the browser tests the actual
// verify/cookie flow without contacting an SMS provider.
$code = '12345';
$wpdb->insert( \FlavorCore\Database\Schema::table( 'flavor_otp_codes' ), array( 'mobile' => $mobile, 'code_hash' => hash_hmac( 'sha256', $mobile . '|' . $code, AUTH_SALT ), 'attempts' => 0, 'expires_at' => date( 'Y-m-d H:i:s', strtotime( $now ) + 1800 ), 'created_at' => $now, 'ip' => '127.0.0.1' ) );
$page = get_page_by_path( 'tracking' );
if ( ! $page ) { $page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'tracking', 'post_title' => 'پیگیری سفارش', 'meta_input' => array( '_wp_page_template' => 'page-templates/template-order-tracking.php', '_flavor_ui_qa_page' => '1' ) ) ); }
else { $page_id = $page->ID; }
$fixture = array( 'users' => $user_ids, 'orders' => $ids, 'username' => get_userdata( $user_ids[0] )->user_login, 'password' => $password, 'mobile' => $mobile, 'code' => $code, 'guest_token' => $guest_token, 'guest_order_key' => $guest_order->get_order_key(), 'tracking_page' => $page_id );
// Runtime-only test credentials, in a git-ignored/snapshot-excluded directory.
file_put_contents( $file, wp_json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
echo 'Disposable UI fixtures ready. No checkout, payment or SMS request performed.' . PHP_EOL;
