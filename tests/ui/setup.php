<?php
/** Real-WP missing-page provisioning/idempotence, only on an opted-in disposable site. */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Disposable opted-in WordPress only.' ); }
$backup = array(); $created = array(); $front = get_option( 'page_on_front' );
function setup_check( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
try {
 foreach ( array( 'about', 'contact', 'tracking' ) as $slug ) {
  $page = get_page_by_path( $slug );
  if ( $page ) {
   $backup[] = array( 'id'=>$page->ID,'slug'=>$page->post_name,'old_slugs'=>get_post_meta($page->ID,'_wp_old_slug',false) );
   wp_update_post( array( 'ID'=>$page->ID, 'post_name'=>'ui-qa-preserved-' . $slug . '-' . wp_generate_uuid4() ) );
  }
 }
 \Flavor\Onboarding::ensure_pages();
 foreach ( array( 'about'=>'template-about.php', 'contact'=>'template-contact.php', 'tracking'=>'template-order-tracking.php' ) as $slug=>$template ) {
  $page = get_page_by_path( $slug ); setup_check( $page && 'publish' === $page->post_status, 'Missing shared page was not published.' ); $created[$slug]=$page->ID;
  update_post_meta( $page->ID, '_flavor_ui_qa', '1' );
  setup_check( 'page-templates/'.$template === get_page_template_slug($page->ID), 'New page has the wrong template.' );
 }
 wp_update_post( array('ID'=>$created['tracking'],'post_content'=>'Custom tracking content sentinel') );
 update_post_meta( $created['tracking'], '_wp_page_template', 'custom-existing-template.php' );
 update_post_meta( $created['tracking'], '_elementor_edit_mode', 'builder' );
 \Flavor\Onboarding::ensure_pages();
 foreach ( $created as $slug=>$id ) { setup_check( $id === get_page_by_path($slug)->ID, 'Repeated setup duplicated a shared page.' ); }
 setup_check( 'custom-existing-template.php' === get_page_template_slug($created['tracking']) && 'Custom tracking content sentinel' === get_post_field('post_content',$created['tracking']), 'Existing template/content was overwritten.' );
 setup_check( $front === get_option('page_on_front'), 'Existing demo front page was replaced.' );
 echo 'PASS shared setup: missing about/contact/tracking pages, correct templates, repeat idempotence, preserved existing builder/content and demo front page.' . PHP_EOL;
} finally {
 foreach ( $created as $id ) { if ('1'===get_post_meta($id,'_flavor_ui_qa',true)) wp_delete_post($id,true); }
 foreach ( $backup as $saved ) {
  wp_update_post(array('ID'=>$saved['id'],'post_name'=>$saved['slug'])); delete_post_meta($saved['id'],'_wp_old_slug');
  foreach ($saved['old_slugs'] as $slug) add_post_meta($saved['id'],'_wp_old_slug',$slug);
 }
}
