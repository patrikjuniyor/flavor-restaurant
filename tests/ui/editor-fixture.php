<?php
/**
 * Temporarily give the first imported product explicit paid/optional choices.
 * FLAVOR_DEMO_QA=1 wp eval-file tests/ui/editor-fixture.php
 * UI_QA_CLEANUP=1 FLAVOR_DEMO_QA=1 wp eval-file tests/ui/editor-fixture.php
 * Restores only the captured product metadata; no product/order/payment is created.
 */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Disposable opted-in WordPress only.' ); }
$dir = dirname( __DIR__, 2 ) . '/.cache/ui-qa'; wp_mkdir_p( $dir ); $file = $dir . '/editor.json';
if ( file_exists( $file ) ) {
 $old = json_decode( file_get_contents( $file ), true );
 if ( get_post( (int) $old['product_id'] ) && get_post_meta( (int) $old['product_id'], '_flavor_ui_editor_fixture', true ) === $old['marker'] ) {
  update_post_meta( (int) $old['product_id'], '_flavor_modifiers', $old['original'] ); delete_post_meta( (int) $old['product_id'], '_flavor_ui_editor_fixture' );
 }
 unlink( $file );
}
if ( '1' === getenv( 'UI_QA_CLEANUP' ) ) { echo 'Captured editor fixture metadata restored, if its exact marker still matches.' . PHP_EOL; return; }
$products = wc_get_products( array( 'status'=>'publish','limit'=>1,'orderby'=>'menu_order','order'=>'ASC' ) );
if ( ! $products || ! get_post_meta( $products[0]->get_id(), '_flavor_demo', true ) ) { throw new RuntimeException( 'Import a demo on the disposable site first; only its product can be a fixture.' ); }
$id = $products[0]->get_id(); $marker = wp_generate_uuid4(); $original = get_post_meta( $id, '_flavor_modifiers', true );
$extra = \FlavorCore\WooCommerce\Currency::to_storage( 12000, 'irt' );
update_post_meta( $id, '_flavor_modifiers', array(
 array('id'=>'ui-qa-standard','type'=>'size','name'=>'اندازهٔ استاندارد','price'=>0,'is_default'=>1),
 array('id'=>'ui-qa-large','type'=>'size','name'=>'اندازهٔ بزرگ آزمایشی','price'=>$extra,'is_default'=>0),
 array('id'=>'ui-qa-extra','type'=>'topping','name'=>'افزودنی آزمایشی','price'=>$extra,'is_default'=>0),
 array('id'=>'ui-qa-removal','type'=>'removal','name'=>'بدون پیاز آزمایشی','price'=>0,'is_default'=>0),
) );
update_post_meta( $id, '_flavor_ui_editor_fixture', $marker );
file_put_contents( $file, wp_json_encode( array('product_id'=>$id,'marker'=>$marker,'original'=>$original,'standard'=>'ui-qa-standard','extra'=>'ui-qa-extra','removal'=>'ui-qa-removal','extra_price'=>$extra), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ) );
echo 'Marked temporary modifier fixture ready on one imported demo product.' . PHP_EOL;
