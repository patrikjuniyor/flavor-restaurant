<?php
/**
 * Guarded real-WP atomic cart editing and currency regression. No checkout/order/SMS.
 * FLAVOR_DEMO_QA=1 wp eval-file tests/ui/cart-edit.php
 */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Disposable opted-in WordPress only.' ); }
if ( ! function_exists( 'wc_get_product' ) || ! class_exists( \FlavorCore\WooCommerce\CartSession::class ) ) { throw new RuntimeException( 'WooCommerce/Core required.' ); }
\FlavorCore\WooCommerce\CartSession::ensure();
$old_settings = get_option( \FlavorCore\Support\Settings::OPTION ); $old_currency = get_option( 'woocommerce_currency' );
$old_cart = WC()->cart->get_cart(); $old_totals = WC()->cart->get_totals(); $old_user = get_current_user_id();
wp_set_current_user( 0 ); $products = array(); $tokens = array();
function edit_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function edit_request( string $key, array $params ): array {
 $request = new WP_REST_Request( 'PUT', '/flavor/v1/cart/items/' . $key );
 foreach ( $params as $name => $value ) { $request->set_param( $name, $value ); }
 $response = rest_do_request( $request );
 $data = $response->get_data();
 if ( ! empty( $data['data']['cart_token'] ) ) { $GLOBALS['ui_edit_test_tokens'][] = $data['data']['cart_token']; }
 return array( $response->get_status(), $data );
}
try {
 foreach ( array( 'irr', 'irt' ) as $storage ) {
  foreach ( array( 'irr', 'irt' ) as $display ) {
   WC()->cart->empty_cart();
   \FlavorCore\Support\Settings::update( array( 'currency_storage' => $storage, 'currency_display' => $display ) ); update_option( 'woocommerce_currency', strtoupper( $storage ) );
   $base = \FlavorCore\WooCommerce\Currency::to_storage( 100000, 'irt' ); $extra = \FlavorCore\WooCommerce\Currency::to_storage( 12000, 'irt' );
   $product = new WC_Product_Simple(); $product->set_name( 'Atomic cart QA fixture' ); $product->set_status( 'publish' ); $product->set_regular_price( (string) $base ); $product->save(); $id = $product->get_id(); $products[] = $id;
   update_post_meta( $id, '_flavor_ui_qa', '1' );
   update_post_meta( $id, '_flavor_modifiers', array(
    array( 'id'=>'qa-standard','type'=>'size','name'=>'اندازهٔ استاندارد','price'=>0 ),
    array( 'id'=>'qa-large','type'=>'size','name'=>'اندازهٔ بزرگ','price'=>$extra ),
    array( 'id'=>'qa-addon','type'=>'topping','name'=>'افزودنی آزمایشی','price'=>$extra ),
    array( 'id'=>'qa-removal','type'=>'removal','name'=>'بدون پیاز','price'=>0 ),
   ) );
   $key = \FlavorCore\WooCommerce\CartSession::add( $id, 2, array( 'ids'=>array('qa-standard','qa-addon'), 'instructions'=>'یادداشت قبلی' ) );
   edit_check( is_string( $key ), 'Fixture add failed.' ); WC()->cart->calculate_totals();
   $before = WC()->cart->get_cart(); $before_total = WC()->cart->get_total( 'edit' );
   foreach ( array( array('unknown'), array('qa-standard','qa-large'), array('qa-addon'), array( array('qa-standard') ) ) as $invalid ) {
    $result = \FlavorCore\WooCommerce\CartSession::update_selection( $key, 2, array( 'ids'=>$invalid, 'instructions'=>'نباید ذخیره شود' ) );
    edit_check( is_wp_error( $result ), 'Invalid/stale/missing-size selection accepted.' );
    edit_check( array_keys( $before ) === array_keys( WC()->cart->get_cart() ) && WC()->cart->get_cart_item( $key )['flavor_instructions'] === 'یادداشت قبلی' && $before_total === WC()->cart->get_total( 'edit' ), 'Rejected edit mutated original row/amount.' );
   }
   $reject = static function () { return false; }; add_filter( 'woocommerce_update_cart_validation', $reject );
   edit_check( is_wp_error( \FlavorCore\WooCommerce\CartSession::update_selection( $key, 2, array( 'ids'=>array('qa-standard'), 'instructions'=>'' ) ) ), 'Native validation ignored.' ); remove_filter( 'woocommerce_update_cart_validation', $reject );
   $fail = static function () { throw new RuntimeException( 'Fixture hook failure, not a real store error.' ); }; add_action( 'woocommerce_after_cart_item_quantity_update', $fail );
   edit_check( is_wp_error( \FlavorCore\WooCommerce\CartSession::update_selection( $key, 2, array( 'ids'=>array('qa-standard'), 'instructions'=>'' ) ) ), 'Hook failure not caught.' ); remove_action( 'woocommerce_after_cart_item_quantity_update', $fail );
   edit_check( WC()->cart->get_cart_item( $key )['flavor_instructions'] === 'یادداشت قبلی' && $before_total === WC()->cart->get_total( 'edit' ), 'Hook-failure rollback lost original cart.' );
   list( $status, $body ) = edit_request( $key, array( 'quantity'=>3, 'modifier_ids'=>array('qa-standard'), 'instructions'=>'یادداشت جدید', 'price'=>1, 'product_id'=>999999 ) );
   edit_check( 200 === $status && $body['success'] && $body['data']['supports_item_edit'], 'Actual PUT failed.' ); $key = $body['data']['last_key'];
   edit_check( count( WC()->cart->get_cart() ) === 1 && 3 === WC()->cart->get_cart_item( $key )['quantity'], 'Edit duplicated/dropped units.' );
   edit_check( abs( (float) WC()->cart->get_cart_item( $key )['data']->get_price() - $base ) < .001, 'Removing the last paid addon retained its old price or trusted client price.' );
   edit_check( 'یادداشت جدید' === WC()->cart->get_cart_item( $key )['flavor_instructions'] && $body['data']['total_html'] === \FlavorCore\WooCommerce\Currency::format( $base * 3 ), 'Notes/display currency mismatch.' );
   list( $status, $body ) = edit_request( $key, array( 'quantity'=>3, 'modifier_ids'=>array('qa-standard','qa-addon','qa-addon') ) );
   edit_check( 200 === $status, 'Deduplicated valid addon rejected.' ); $key = $body['data']['last_key'];
   for ( $i = 0; $i < 4; $i++ ) { WC()->cart->calculate_totals(); edit_check( abs( (float) WC()->cart->get_cart_item( $key )['data']->get_price() - ( $base + $extra ) ) < .001, 'Edited modifier compounded or was charged twice.' ); }
   edit_check( 'یادداشت جدید' === WC()->cart->get_cart_item( $key )['flavor_instructions'], 'Omitted instructions did not retain notes.' );
   list( $status, $body ) = edit_request( $key, array( 'quantity'=>2 ) ); edit_check( 200 === $status && 2 === $body['data']['count'], 'Legacy quantity-only PUT broken.' );
   edit_check( 2 === count( WC()->cart->get_cart_item( $key )['flavor_modifiers'] ), 'Legacy quantity-only update erased modifiers.' );
   $original_again = \FlavorCore\WooCommerce\CartSession::add( $id, 1, array( 'ids'=>array('qa-standard','qa-addon'), 'instructions'=>'یادداشت قبلی' ) );
   edit_check( $key !== $original_again && count( WC()->cart->get_cart() ) === 2, 'Old cart hash merged different notes after editing.' );
   $merged = \FlavorCore\WooCommerce\CartSession::update_selection( $original_again, 1, array( 'ids'=>array('qa-standard','qa-addon'), 'instructions'=>'یادداشت جدید' ) );
   edit_check( $key === $merged && count( WC()->cart->get_cart() ) === 1 && 3 === WC()->cart->get_cart_item( $key )['quantity'], 'Identical selection did not merge without losing units.' );
   WC()->cart->empty_cart();
   $plain = \FlavorCore\WooCommerce\CartSession::add( $id, 20, array('ids'=>array('qa-standard'),'instructions'=>'same') );
   $paid = \FlavorCore\WooCommerce\CartSession::add( $id, 1, array('ids'=>array('qa-standard','qa-addon'),'instructions'=>'same') );
   edit_check( is_wp_error( \FlavorCore\WooCommerce\CartSession::update_selection( $paid, 1, array('ids'=>array('qa-standard'),'instructions'=>'same') ) ) && count(WC()->cart->get_cart())===2 && WC()->cart->get_cart_item($plain)['quantity']===20, 'Over-limit merge altered the original rows.' );
   WC()->cart->empty_cart();
   $key = \FlavorCore\WooCommerce\CartSession::add( $id, 3, array('ids'=>array('qa-standard','qa-addon'),'instructions'=>'یادداشت جدید') );
   $product->set_manage_stock(true); $product->set_stock_quantity(3); $product->save();
   edit_check( is_wp_error( \FlavorCore\WooCommerce\CartSession::update_selection( $key, 4, array('ids'=>array('qa-standard'),'instructions'=>'') ) ) && WC()->cart->get_cart_item($key)['quantity']===3, 'Insufficient native stock allowed an edit.' );
   $product->set_manage_stock(false); $product->save();
   $slash_note = 'یادداشت با \\ نشانه';
   $slash_key = \FlavorCore\WooCommerce\CartSession::add( $id, 1, array('ids'=>array('qa-standard'),'instructions'=>$slash_note) );
   edit_check( WC()->cart->get_cart_item($slash_key)['flavor_instructions']===$slash_note, 'Native request slashing lost a kitchen-note character.' );
   WC()->cart->remove_cart_item($slash_key);
   $token = \FlavorCore\WooCommerce\CartTokenService::generate_token(); $tokens[] = $token; \FlavorCore\WooCommerce\CartTokenService::sync_session_to_database( $token );
   WC()->cart->empty_cart(); edit_check( \FlavorCore\WooCommerce\CartTokenService::restore_into_session( $token ), 'Updated guest cart restore failed.' );
   $restored = reset( WC()->cart->cart_contents ); edit_check( 3 === $restored['quantity'] && 'یادداشت جدید' === $restored['flavor_instructions'] && 2 === count( $restored['flavor_modifiers'] ), 'Guest restore lost updated selection/notes/quantity.' );
   list( $status ) = edit_request( 'foreign_cart_key', array( 'quantity'=>1, 'modifier_ids'=>array('qa-standard') ) ); edit_check( 404 === $status, 'Foreign/unknown row can be edited.' );
   $text = \FlavorCore\WooCommerce\ProductModifiers::clean_instructions( str_repeat( 'الف', 100 ) . '<script>unsafe</script>' );
   edit_check( mb_strlen( $text, 'UTF-8' ) <= 200 && mb_check_encoding( $text, 'UTF-8' ) && false === strpos( $text, '<script>' ), 'Persian kitchen-note boundary broken.' );
   echo 'PASS atomic cart edit ' . $storage . ' → ' . $display . ': selection/notes, rejection/rollback, price reset, 4 recalculations, quantity-only compatibility, hash/merge, guest restore and foreign-key denial.' . PHP_EOL;
  }
 }
} finally {
 foreach ( array_merge( $tokens, $GLOBALS['ui_edit_test_tokens'] ?? array() ) as $token ) { \FlavorCore\WooCommerce\CartTokenService::delete_cart( $token ); }
 WC()->cart->empty_cart(); WC()->cart->cart_contents = $old_cart; WC()->cart->set_totals( $old_totals );
 foreach ( $products as $id ) { if ( '1' === get_post_meta( $id, '_flavor_ui_qa', true ) ) { wp_delete_post( $id, true ); } }
 if ( false === $old_settings ) { delete_option( \FlavorCore\Support\Settings::OPTION ); } else { update_option( \FlavorCore\Support\Settings::OPTION, $old_settings ); }
 update_option( 'woocommerce_currency', $old_currency ); wp_set_current_user( $old_user );
}
