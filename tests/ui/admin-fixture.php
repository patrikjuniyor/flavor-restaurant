<?php
/**
 * Create/delete a marked disposable Customizer administrator; never reset a real user.
 * FLAVOR_DEMO_QA=1 wp eval-file tests/ui/admin-fixture.php
 * UI_QA_CLEANUP=1 FLAVOR_DEMO_QA=1 wp eval-file tests/ui/admin-fixture.php
 * Credentials are written only to ignored .cache/ui-qa/admin.json with mode 0600.
 */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Disposable opted-in WordPress only.' ); }
if ( ! class_exists( \Flavor\UI_Customizer::class ) ) { throw new RuntimeException( 'Activate the Flavor theme first.' ); }
$directory = dirname( __DIR__, 2 ) . '/.cache/ui-qa'; wp_mkdir_p( $directory ); $file = $directory . '/admin.json';
if ( file_exists( $file ) ) {
 $old = json_decode( file_get_contents( $file ), true );
 if ( ! empty( $old['user_id'] ) && '1' === get_user_meta( (int) $old['user_id'], '_flavor_ui_qa_admin', true ) ) {
  require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( (int) $old['user_id'] );
 }
 unlink( $file );
}
if ( '1' === getenv( 'UI_QA_CLEANUP' ) ) { echo 'Only the marked Customizer QA administrator and its runtime credentials were cleaned.' . PHP_EOL; return; }
$password = wp_generate_password( 36, false );
$username = 'flavor_ui_qa_admin_' . strtolower( wp_generate_password( 8, false ) );
$id = wp_insert_user( array( 'user_login' => $username, 'user_pass' => $password, 'user_email' => $username . '@example.invalid', 'role' => 'administrator', 'display_name' => 'مدیر آزمایشی رابط' ) );
if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
update_user_meta( $id, '_flavor_ui_qa_admin', '1' );
file_put_contents( $file, wp_json_encode( array( 'environment' => wp_get_environment_type(), 'site_url' => home_url( '/' ), 'user_id' => $id, 'username' => $username, 'password' => $password ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
chmod( $file, 0600 );
echo 'Marked disposable Customizer administrator created. No credential is printed or committed.' . PHP_EOL;
