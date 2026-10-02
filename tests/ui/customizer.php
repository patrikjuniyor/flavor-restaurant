<?php
/**
 * Real WP Customizer settings/saving checks, guarded disposable install only.
 * FLAVOR_DEMO_QA=1 wp eval-file tests/ui/customizer.php
 * Restores theme mods/user; never changes business settings or submits an order.
 */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Disposable opted-in WordPress only.' ); }
if ( ! class_exists( \Flavor\UI_Customizer::class ) || ! function_exists( 'wc_get_products' ) ) { throw new RuntimeException( 'Flavor theme and WooCommerce required.' ); }
$mods = get_theme_mods(); $old_user = get_current_user_id();
$administrators = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( ! $administrators ) { throw new RuntimeException( 'A disposable administrator is required.' ); }
wp_set_current_user( $administrators[0]->ID );
$business = get_option( \FlavorCore\Support\Settings::OPTION );
$prices = array(); foreach ( wc_get_products( array( 'limit' => -1 ) ) as $product ) { $prices[ $product->get_id() ] = $product->get_price(); }
function customize_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
try {
 require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
 $manager = new WP_Customize_Manager(); do_action( 'customize_register', $manager );
 foreach ( \Flavor\UI::schema() as $key => $definition ) {
  $id = 'flavor_ui_' . $key; $setting = $manager->get_setting( $id ); $control = $manager->get_control( $id );
  customize_check( $setting && $control && 'postMessage' === $setting->transport, 'Live setting/control missing: ' . $key );
  customize_check( $definition['choices'] === $control->choices, 'Control and frontend allowlists differ: ' . $key );
  foreach ( array_keys( $definition['choices'] ) as $value ) {
   customize_check( ! is_wp_error( $setting->validate( $value ) ), 'Valid setting rejected: ' . $id );
   customize_check( $value === $setting->sanitize( $value ), 'Valid setting sanitized incorrectly: ' . $id );
   $manager->set_post_value( $id, $value ); $setting->save();
   customize_check( $value === get_theme_mod( $id ) && $value === \Flavor\UI::settings()[ $key ], 'Saved value not applied: ' . $id );
  }
  foreach ( array( 'invalid', 'grid; background:url(https://example.invalid/)', array( 'grid' ), null, true ) as $invalid ) {
   customize_check( is_wp_error( $setting->validate( $invalid ) ), 'Invalid value passed validation: ' . $id );
   customize_check( $definition['default'] === $setting->sanitize( $invalid ), 'Unsafe value survived sanitization: ' . $id );
  }
 }
 foreach ( \Flavor\UI_Customizer::design_choices() as $id => $choices ) {
  $setting = $manager->get_setting( $id ); customize_check( (bool) $setting, 'Design setting missing: ' . $id );
  foreach ( array_keys( $choices ) as $value ) { customize_check( ! is_wp_error( $setting->validate( $value ) ) && $value === $setting->sanitize( $value ), 'Preset geometry/font not accepted: ' . $id . ' = ' . $value ); }
  customize_check( is_wp_error( $setting->validate( 'unsafe;}body{display:none' ) ), 'Existing select has no effective validator: ' . $id );
  customize_check( $setting->default === $setting->sanitize( 'unsafe;}body{display:none' ), 'Old WP filter retained unsafe value: ' . $id );
 }
 $sticky = $manager->get_setting( 'flavor_header_sticky' );
 customize_check( true === $sticky->default && true === $sticky->sanitize( true ) && false === $sticky->sanitize( false ), 'Sticky checkbox does not save booleans.' );
 set_theme_mod( 'flavor_header_sticky', 'yes' ); customize_check( true === $sticky->js_value(), 'Legacy sticky yes is shown unchecked.' );
 foreach ( \Flavor\Design::skins() as $slug => $skin ) {
  $tokens = \Flavor\Design::tokens( $slug );
  customize_check( array_key_exists( $tokens['radius'], $manager->get_control( 'flavor_radius' )->choices ), 'Imported radius cannot be selected: ' . $slug );
  customize_check( array_key_exists( $tokens['btn_radius'], $manager->get_control( 'flavor_btn_radius' )->choices ), 'Imported button radius cannot be selected: ' . $slug );
 }
 customize_check( $business === get_option( \FlavorCore\Support\Settings::OPTION ), 'Presentation changed business settings.' );
 foreach ( wc_get_products( array( 'limit' => -1 ) ) as $product ) { customize_check( $prices[ $product->get_id() ] === $product->get_price(), 'Presentation changed a product price.' ); }
 echo 'PASS Customizer: real registration/allowlists, 11 UI values saved/read, invalid values rejected, existing design filters, all 12 preset geometries, legacy sticky checkbox and no business/price changes.' . PHP_EOL;
} finally {
 update_option( 'theme_mods_' . get_stylesheet(), $mods ); wp_set_current_user( $old_user );
}
