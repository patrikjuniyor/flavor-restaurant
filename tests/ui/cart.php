<?php
/** Guarded disposable-WP regression: FLAVOR_DEMO_QA=1 wp eval-file tests/ui/cart.php */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
 throw new RuntimeException( 'Use an opted-in disposable local/development WordPress only.' );
}
if ( ! function_exists( 'wc_get_products' ) || ! defined( 'FLAVOR_CORE_VERSION' ) ) { throw new RuntimeException( 'WooCommerce/Core required.' ); }
$products = wc_get_products( array( 'limit' => 1 ) );
if ( ! $products ) { throw new RuntimeException( 'Import a demo first.' ); }
$product = $products[0];
$previous = get_post_meta( $product->get_id(), '_flavor_modifiers', true );
$extra = \FlavorCore\WooCommerce\Currency::to_storage( 12000, 'irt' );
try {
 update_post_meta( $product->get_id(), '_flavor_modifiers', array( array( 'id' => 'ui-qa-extra', 'type' => 'topping', 'name' => 'UI QA extra', 'price' => $extra ) ) );
 \FlavorCore\WooCommerce\CartSession::ensure();
 WC()->cart->empty_cart();
 $key = \FlavorCore\WooCommerce\CartSession::add( $product->get_id(), 2, array( 'ids' => array( 'ui-qa-extra' ) ) );
 if ( is_wp_error( $key ) ) { throw new RuntimeException( $key->get_error_message() ); }
 $expected = (float) $product->get_price() + \FlavorCore\WooCommerce\ProductModifiers::storage_to_wc( $extra );
 for ( $i = 0; $i < 4; $i++ ) {
  WC()->cart->calculate_totals();
  $price = (float) WC()->cart->get_cart()[ $key ]['data']->get_price();
  if ( $price !== $expected ) { throw new RuntimeException( 'Repeated totals compounded the modifier price.' ); }
 }
 $payload = ( new \FlavorCore\API\CartController() )->build_cart_payload();
 $line = $payload['items'][0];
 $wc_unit = 'IRR' === get_woocommerce_currency() ? 'irr' : 'irt';
 $stored_line = \FlavorCore\WooCommerce\Currency::to_storage( (int) round( $expected * 2 ), $wc_unit );
 if ( $line['line_total'] !== $stored_line || $line['line_total_html'] !== \FlavorCore\WooCommerce\Currency::format( $stored_line ) ) { throw new RuntimeException( 'Displayed cart line differs from authoritative server amount.' ); }
 if ( ! isset( $line['price_html'], $line['line_html'], $payload['discount_total'], $payload['tax_total'] ) ) { throw new RuntimeException( 'Legacy fields or additive totals are missing.' ); }
 echo 'PASS cart: modifier added exactly once over four recalculations, two-unit line amount, consistent Core display and legacy fields retained.' . PHP_EOL;
} finally {
 update_post_meta( $product->get_id(), '_flavor_modifiers', $previous );
 WC()->cart->empty_cart();
}
