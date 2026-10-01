<?php
/** Local, read-only ownership/display checks against the disposable UI fixtures. */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Disposable opted-in WordPress only.' ); }
$file = dirname( __DIR__, 2 ) . '/.cache/ui-qa/fixtures.json';
if ( ! file_exists( $file ) ) { throw new RuntimeException( 'Prepare guarded tests/ui/fixtures.php first.' ); }
$fixture = json_decode( file_get_contents( $file ), true );
$previous_user = get_current_user_id(); $previous_key = $_GET['key'] ?? null; $currency = get_option( 'woocommerce_currency' );
function customer_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
try {
 $own = wc_get_order( $fixture['orders'][0] ); $foreign = wc_get_order( $fixture['orders'][1] ); $guest = wc_get_order( $fixture['orders'][2] );
 wp_set_current_user( $fixture['users'][0] ); unset( $_GET['key'] );
 customer_check( \Flavor\Customer_UI::can_view( $own ), 'Owner cannot view own order.' );
 customer_check( ! \Flavor\Customer_UI::can_view( $foreign ) && ! \Flavor\Customer_UI::can_view( $guest ), 'Foreign/guest order bypassed ownership.' );
 $controller = new \FlavorCore\API\OrderController();
 $detail = $controller->format_order_detail( $own );
 customer_check( false === $detail['kitchen_status_known'], 'Missing kitchen row was advertised as known.' );
 update_option( 'woocommerce_currency', 'IRT' );
 $expected = \FlavorCore\WooCommerce\Currency::format( \FlavorCore\WooCommerce\Currency::to_storage( (int) $own->get_total(), 'irr' ) );
 customer_check( $controller->format_order_summary( $own )['total_html'] === $expected, 'Historic order uses current shop currency instead of its recorded currency.' );
 wp_set_current_user( 0 ); $_GET['key'] = 'invalid';
 ob_start(); \Flavor\Customer_UI::receipt( $guest->get_id() ); $denied = ob_get_clean();
 customer_check( '' === $denied, 'Receipt hook exposed a guest secret on denied access.' );
 $_GET['key'] = $fixture['guest_order_key'];
 ob_start(); \Flavor\Customer_UI::receipt( $guest->get_id() ); $allowed = ob_get_clean();
 customer_check( false !== strpos( $allowed, 'data-ui-receipt-secret' ), 'Authorized guest receipt lacks masked tracking code.' );
 customer_check( false !== strpos( $allowed, 'type="password"' ), 'Private tracking code is not masked.' );
 echo 'PASS customer: owner/foreign/guest guards, masked authorized receipt, missing-ticket truthfulness and recorded-order currency.' . PHP_EOL;
} finally {
 wp_set_current_user( $previous_user ); update_option( 'woocommerce_currency', $currency );
 if ( null === $previous_key ) { unset( $_GET['key'] ); } else { $_GET['key'] = $previous_key; }
}
