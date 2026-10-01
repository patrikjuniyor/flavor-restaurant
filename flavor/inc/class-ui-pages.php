<?php
/** Internal pages read published content/configuration; no contact/booking simulation. @package Flavor */
namespace Flavor;
defined( 'ABSPATH' ) || exit;
class UI_Pages {
 public static function init(): void {
  add_filter( 'request', array( self::class, 'request' ) );
  add_filter( 'template_include', array( self::class, 'template' ), 30 );
  add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ), 35 );
 }
 public static function request( array $vars ): array {
  // Core's numeric storefront branch selector shares the CPT query-var name.
  // On a normal page URL, do not let WP reinterpret it as a branch slug.
  $branch = isset( $_GET['flavor_branch'] ) && is_scalar( $_GET['flavor_branch'] ) ? (string) wp_unslash( $_GET['flavor_branch'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page context.
  if ( preg_match( '/^\d+$/', $branch ) && ! empty( $vars['pagename'] ) ) {
   unset( $vars['flavor_branch'] );
   if ( 'flavor_branch' === ( $vars['post_type'] ?? '' ) ) { unset( $vars['post_type'] ); if ( $branch === (string) ( $vars['name'] ?? '' ) ) { unset( $vars['name'] ); } }
  }
  return $vars;
 }
 public static function template( string $template ): string {
  if ( ! is_page() || get_post_meta( get_queried_object_id(), '_flavor_builder_enabled', true ) || 'builder' === get_post_meta( get_queried_object_id(), '_elementor_edit_mode', true ) || ! in_array( get_page_template_slug(), array( '', 'default' ), true ) ) { return $template; }
  foreach ( array( 'about', 'contact' ) as $slug ) {
   if ( is_page( $slug ) ) { return FLAVOR_DIR . '/page-templates/template-' . $slug . '.php'; }
  }
  return $template;
 }
 public static function assets(): void {
  if ( ! UI::inner() ) { return; }
  wp_enqueue_style( 'flavor-ui-pages', FLAVOR_URI . '/assets/css/ui-pages.css', array( 'flavor-ui' ), (string) filemtime( FLAVOR_DIR . '/assets/css/ui-pages.css' ) );
  if ( is_page_template( 'page-templates/template-branches.php' ) ) {
   wp_enqueue_script( 'flavor-ui-pages-js', FLAVOR_URI . '/assets/js/pages.js', array( 'flavor-ui-js' ), (string) filemtime( FLAVOR_DIR . '/assets/js/pages.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
  }
 }
 public static function latin( string $text ): string {
  return strtr( $text, array( '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9' ) );
 }
 public static function tel( string $phone ): string { return 'tel:' . preg_replace( '/[^0-9+]/', '', self::latin( $phone ) ); }
 public static function branch( int $id ): array {
  $post = get_post( $id );
  if ( ! $post || 'publish' !== $post->post_status || 'flavor_branch' !== $post->post_type ) { return array(); }
  $modes = get_post_meta( $id, '_flavor_order_modes', true );
  $lat = get_post_meta( $id, '_flavor_lat', true ); $lng = get_post_meta( $id, '_flavor_lng', true );
  $map = '';
  if ( is_numeric( $lat ) && is_numeric( $lng ) && abs( (float) $lat ) <= 90 && abs( (float) $lng ) <= 180 ) {
   $map = 'https://www.openstreetmap.org/?mlat=' . rawurlencode( (string) $lat ) . '&mlon=' . rawurlencode( (string) $lng ) . '#map=16/' . rawurlencode( (string) $lat ) . '/' . rawurlencode( (string) $lng );
  }
  return array( 'id'=>$id, 'name'=>$post->post_title, 'city'=>(string)get_post_meta($id,'_flavor_city',true), 'phone'=>(string)get_post_meta($id,'_flavor_phone',true), 'address'=>(string)get_post_meta($id,'_flavor_address',true), 'modes'=>is_array($modes)?array_values(array_intersect($modes,array('dine_in','takeaway','delivery'))):array(), 'map'=>$map );
 }
 /** Only explicitly configured branch hours, never the API's sample fallback. */
 public static function hours( int $id ): array {
  if ( ! $id || ! class_exists( \FlavorCore\Database\Schema::class ) ) { return array(); }
  global $wpdb;
  return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT day_of_week,open_time,close_time,is_closed FROM ' . \FlavorCore\Database\Schema::table( 'flavor_branch_hours' ) . ' WHERE branch_id = %d AND mode = %s ORDER BY day_of_week ASC', $id, 'all' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- published configuration.
 }
 public static function render_hours( int $id ): void {
  $hours = self::hours( $id );
  $days = array( 'شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه' );
  if ( ! $hours ) { echo '<p class="flavor-ui-note">' . esc_html__( 'ساعات ثبت‌شده‌ای برای نمایش وجود ندارد؛ پیش از مراجعه هماهنگ کنید.', 'flavor' ) . '</p>'; return; }
  echo '<dl class="flavor-branch-hours">';
  foreach ( $hours as $row ) {
   echo '<div><dt>' . esc_html( $days[ (int) $row['day_of_week'] ] ?? '' ) . '</dt><dd>' . esc_html( $row['is_closed'] ? __( 'تعطیل', 'flavor' ) : Bespoke_Demos::digits( substr( $row['open_time'], 0, 5 ) . ' تا ' . substr( $row['close_time'], 0, 5 ) ) ) . '</dd></div>';
  }
  echo '</dl>';
 }
 public static function breadcrumb( string $label ): void {
  echo '<nav class="flavor-ui-breadcrumb" aria-label="' . esc_attr__( 'مسیر صفحه', 'flavor' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'خانه', 'flavor' ) . '</a><span aria-hidden="true">/</span><span aria-current="page">' . esc_html( $label ) . '</span></nav>';
 }
 /** Keep custom/editor/builder content; skip only unchanged imported contact copy. */
 public static function content( bool $skip_demo_contact = false ): void {
  $content = get_post_field( 'post_content', get_the_ID() );
  if ( ! trim( $content ) ) { return; }
  if ( $skip_demo_contact && get_post_meta( get_the_ID(), '_flavor_demo', true ) ) {
   $pack = Bespoke_Demos::demo();
   $expected = '<p>' . esc_html( $pack['address'] ?? '' ) . '</p><p dir="ltr">' . esc_html( $pack['phone'] ?? '' ) . '</p>';
   if ( trim( $content ) === trim( $expected ) ) { return; }
  }
  echo '<div class="flavor-ui-editor-content">'; the_content(); echo '</div>';
 }
}
