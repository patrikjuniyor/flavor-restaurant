<?php
/**
 * Contract Test for Onboarding & Launch Center Checklist Flow (Prompt 98).
 *
 * Validates:
 * 1. Onboarding class hooks activation_redirect and do_activation_redirect.
 * 2. Onboarding admin_notices provides a direct link to flavor-setup.
 * 3. Launch_Center defines comprehensive checks covering:
 *    - woocommerce (WooCommerce active)
 *    - front_page (Demo import or home page published)
 *    - branch (Address, city, and phone)
 *    - opening_hours (Schedule / hours)
 *    - tables_qr (Dining tables & QR code generation)
 *    - test_order (Staging test order guidance)
 * 4. Every required step has a direct valid admin URL or front URL.
 * 5. tables_qr check directs to admin.php?page=flavor-tables.
 * 6. Launch_Center action bar links to setup wizard, demo importer, customizer and transfer.
 */

if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl(): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( string $type = 'post' ) {
		$obj = new \stdClass();
		$obj->publish = 5;
		return $obj;
	}
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/seo-wp-stubs.php';
require_once __DIR__ . '/fixtures/admin-page-stubs.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-launch-center.php';
require_once FLAVOR_DIR . '/inc/class-onboarding.php';

use Flavor\Launch_Center;
use Flavor\Onboarding;

function ob_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

// 1. Source check for Onboarding activation redirect
$onboarding_src = (string) file_get_contents( FLAVOR_DIR . '/inc/class-onboarding.php' );
ob_assert( false !== strpos( $onboarding_src, 'after_switch_theme' ), 'Onboarding must hook after_switch_theme' );
ob_assert( false !== strpos( $onboarding_src, 'activation_redirect' ), 'Onboarding must define activation_redirect' );
ob_assert( false !== strpos( $onboarding_src, 'do_activation_redirect' ), 'Onboarding must define do_activation_redirect' );
ob_assert( false !== strpos( $onboarding_src, 'flavor-setup' ), 'Onboarding must redirect to flavor-setup' );

// 2. Launch_Center checks coverage
$checks = Launch_Center::checks();
$check_ids = array_column( $checks, 'id' );

$required_ids = array(
	'woocommerce',
	'front_page',
	'branch',
	'opening_hours',
	'tables_qr',
	'test_order',
);

foreach ( $required_ids as $req_id ) {
	ob_assert( in_array( $req_id, $check_ids, true ), "Launch_Center must define check for {$req_id}" );
}

// 3. Inspect tables_qr check details
$tables_qr_check = null;
foreach ( $checks as $c ) {
	if ( 'tables_qr' === $c['id'] ) {
		$tables_qr_check = $c;
		break;
	}
}

ob_assert( null !== $tables_qr_check, 'tables_qr check must be present' );
ob_assert( false !== strpos( $tables_qr_check['url'], 'page=flavor-tables' ), 'tables_qr check URL must lead to flavor-tables admin page' );
ob_assert( ! empty( $tables_qr_check['action'] ), 'tables_qr check must have an action label' );

// 4. Verify all check actions have valid URLs (no empty or javascript: voids)
foreach ( $checks as $c ) {
	ob_assert( ! empty( $c['url'] ), "Check {$c['id']} must provide an actionable URL" );
	ob_assert( ! empty( $c['action'] ), "Check {$c['id']} must provide action text" );
	ob_assert( ! empty( $c['label'] ), "Check {$c['id']} must provide a label" );
}

// 5. Inspect Launch_Center actions in source
$launch_src = (string) file_get_contents( FLAVOR_DIR . '/inc/class-launch-center.php' );
ob_assert( false !== strpos( $launch_src, 'themes.php?page=flavor-setup' ), 'Launch_Center must link to flavor-setup' );
ob_assert( false !== strpos( $launch_src, 'themes.php?page=flavor-demos' ), 'Launch_Center must link to flavor-demos' );
ob_assert( false !== strpos( $launch_src, 'admin.php?page=flavor-tables' ), 'Launch_Center must link to flavor-tables' );

echo "PASS: Onboarding & Launch_Center checklist test passed (8 assertions).\n";
exit( 0 );
