<?php
/** Guarded local QR-presentation regression; no order or reservation created. */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Disposable opted-in WordPress only.' ); }
\FlavorCore\WooCommerce\CartSession::ensure();
$original = \FlavorCore\Order\OrderModes::get();
$id = wp_insert_post( array( 'post_type' => 'flavor_branch', 'post_status' => 'publish', 'post_title' => 'UI QA dining branch', 'meta_input' => array( '_flavor_order_modes' => array( 'dine_in', 'takeaway' ) ) ) );
$table_id = \FlavorCore\Table\TableRepository::create( array( 'branch_id' => $id, 'table_number' => '7', 'capacity' => 4, 'section' => 'indoor', 'is_active' => 1 ) );
try {
 $table = \FlavorCore\Table\TableRepository::find( $table_id );
 \FlavorCore\Order\OrderModes::set( array( 'branch_id' => $id, 'table_token' => $table['qr_token'], 'order_mode' => 'dine_in' ) );
 $context = \Flavor\UI::context();
 if ( $context['table_id'] !== (int) $table_id || '7' !== $context['table'] || ! \Flavor\UI::reservation_available() ) { throw new RuntimeException( 'Active QR table did not resolve to the correct published branch.' ); }
 \FlavorCore\Order\OrderModes::set( array( 'branch_id' => $id, 'table_token' => 'invalid-token', 'order_mode' => 'dine_in' ) );
 if ( \Flavor\UI::context()['table_id'] ) { throw new RuntimeException( 'Invalid QR advertised a real table.' ); }
 update_post_meta( $id, '_flavor_order_modes', array( 'takeaway' ) );
 \FlavorCore\Order\OrderModes::set( array( 'branch_id' => $id, 'table_token' => $table['qr_token'] ) );
 if ( \Flavor\UI::context()['table_id'] || \Flavor\UI::reservation_available() ) { throw new RuntimeException( 'Pickup-only branch advertised dining/reservations.' ); }
 wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
 if ( \Flavor\Enqueue::current_branch_id() === (int) $id ) { throw new RuntimeException( 'Unpublished branch was advertised as frontend context.' ); }
 echo 'PASS QR context: active table/branch, invalid token, pickup-only service and unpublished branch guards.' . PHP_EOL;
} finally {
 \FlavorCore\Table\TableRepository::delete( $table_id );
 wp_delete_post( $id, true );
 \FlavorCore\Order\OrderModes::set( $original );
}
