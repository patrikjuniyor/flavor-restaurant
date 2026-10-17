<?php
/**
 * Contract test for the Iranian payment gateway layer.
 *
 * The feature touches five surfaces that can drift apart silently:
 *
 *   1. the catalog in Flavor Core,
 *   2. the admin screen and its capability/nonce gate,
 *   3. the 11-step setup wizard (gateway step, persistence),
 *   4. the Launch Center check,
 *   5. the dashboard strip and the Persian documentation.
 *
 * This suite pins each one, plus the "never enable a gateway without merchant
 * credentials" rule that the whole flow rests on.
 *
 * @package Flavor
 */

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'FLAVOR_CORE_PATH' ) ) {
	define( 'FLAVOR_CORE_PATH', dirname( __DIR__, 2 ) . '/flavor-core/' );
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Admin URL double.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	/**
	 * Nonce URL double.
	 *
	 * @param string $url  URL.
	 * @param string $args Nonce action.
	 * @return string
	 */
	function wp_nonce_url( string $url, string $args = '' ): string {
		return $url . '&_wpnonce=test';
	}
}

if ( ! function_exists( 'get_plugins' ) ) {
	/**
	 * No third-party plugins in the fixture site.
	 *
	 * @return array<string, array<string, string>>
	 */
	function get_plugins(): array {
		return array();
	}
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	/**
	 * Active-plugin double.
	 *
	 * @param string $file Plugin basename.
	 * @return bool
	 */
	function is_plugin_active( string $file ): bool {
		return in_array( $file, (array) get_option( 'active_plugins', array() ), true );
	}
}

require_once FLAVOR_CORE_PATH . 'includes/WooCommerce/Gateways/IranianGateways.php';
require_once FLAVOR_CORE_PATH . 'includes/WooCommerce/Gateways/IranianGatewayManager.php';

use FlavorCore\WooCommerce\Gateways\IranianGatewayManager;
use FlavorCore\WooCommerce\Gateways\IranianGateways;

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
function gw_check( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

echo "=== Iranian Payment Gateways Contract Test ===\n\n";

/* -------------------------------------------------------------------------
 * 1. Catalog shape.
 * ---------------------------------------------------------------------- */

$catalog = IranianGateways::catalog();
gw_check( count( $catalog ) >= 12, 'catalog ships at least twelve Iranian gateways (' . count( $catalog ) . ')' );
gw_check( array_keys( $catalog ) === IranianGateways::keys(), 'keys() mirrors the catalog order' );

$types = array_keys( IranianGateways::type_labels() );
$shape_ok = true;
foreach ( $catalog as $key => $entry ) {
	foreach ( array( 'name', 'latin', 'description', 'type', 'website', 'install_slug', 'folders', 'gateway_ids' ) as $field ) {
		if ( ! array_key_exists( $field, $entry ) ) {
			$shape_ok = false;
		}
	}
	if ( ! is_array( $entry['gateway_ids'] ) || array() === $entry['gateway_ids'] ) {
		$shape_ok = false;
	}
	if ( ! in_array( $entry['type'], $types, true ) ) {
		$shape_ok = false;
	}
	if ( ! is_string( $key ) || '' === $key ) {
		$shape_ok = false;
	}
}
gw_check( $shape_ok, 'every catalog entry has the documented fields and a known type' );
gw_check( in_array( 'zarinpal', IranianGateways::keys(), true ), 'ZarinPal is in the catalog' );
gw_check( in_array( 'zibal', IranianGateways::keys(), true ), 'Zibal is in the catalog' );

$zarinpal = IranianGateways::get( 'zarinpal' );
gw_check( null !== $zarinpal && 'zarinpal-woocommerce-payment-gateway' === $zarinpal['install_slug'], 'ZarinPal installs from its WordPress.org slug' );
gw_check( null !== $zarinpal && in_array( 'WC_ZPal', $zarinpal['gateway_ids'], true ), 'ZarinPal matches the real WC_ZPal gateway id' );

$mellat = IranianGateways::get( 'mellat' );
gw_check( null !== $mellat && in_array( 'bankmellat', $mellat['gateway_ids'], true ), 'Bank Mellat matches the bankmellat gateway id' );

/* -------------------------------------------------------------------------
 * 2. Live status against the fixture site (no Iranian gateway installed).
 * ---------------------------------------------------------------------- */

$status  = IranianGateways::status();
$summary = IranianGateways::summary();

gw_check( count( $status ) === count( $catalog ), 'status() covers every catalog entry' );
gw_check( 0 === (int) $summary['ready'] && 0 === (int) $summary['enabled'], 'an unconfigured site reports zero ready gateways' );
gw_check( isset( $summary['ready_names'], $summary['enabled_names'], $summary['preferred'], $summary['currency_ok'] ), 'summary() exposes the keys the UI reads' );
gw_check( array() === IranianGateways::enabled_ids( 'zarinpal' ), 'enabled_ids() returns nothing when no online gateway is registered' );
gw_check( null === IranianGateways::get( 'not_a_gateway' ), 'unknown catalog keys are rejected' );

/* -------------------------------------------------------------------------
 * 3. The "never enable without credentials" rule.
 * ---------------------------------------------------------------------- */

$pending_before = IranianGatewayManager::pending();
gw_check( array() === $pending_before, 'no pending gateway on a clean site' );

$result = IranianGatewayManager::connect( 'zarinpal' );
gw_check( 'pending' === $result, 'connecting an uninstalled gateway is recorded as pending, not enabled' );
$pending = IranianGatewayManager::pending();
gw_check( 'zarinpal' === ( $pending['key'] ?? '' ), 'the pending option remembers the chosen gateway' );
gw_check( array() === IranianGateways::enabled_ids( 'zarinpal' ), 'nothing is switched on while the plugin is missing' );
gw_check( 'unknown' === IranianGatewayManager::connect( 'not_a_gateway' ), 'connect() rejects unknown keys' );

IranianGatewayManager::clear_pending();
gw_check( array() === IranianGatewayManager::pending(), 'clear_pending() removes the pending marker' );

/* -------------------------------------------------------------------------
 * 4. Sources: admin screen, wizard, launch center, dashboard, docs.
 * ---------------------------------------------------------------------- */

$admin_src = (string) file_get_contents( FLAVOR_CORE_PATH . 'includes/Admin/PaymentGatewaysAdmin.php' );
gw_check( false !== strpos( $admin_src, "const PAGE = 'flavor-payments'" ), 'admin page slug is flavor-payments' );
gw_check( false !== strpos( $admin_src, "add_submenu_page(\n\t\t\t'flavor-core'" ), 'the page sits under the Flavor Core menu' );
gw_check( false !== strpos( $admin_src, 'check_admin_referer' ), 'state-changing buttons verify a nonce' );
gw_check( false !== strpos( $admin_src, "'flavor_manage_settings'" ), 'the page checks the restaurant-manager capability' );

$view_src = (string) file_get_contents( FLAVOR_CORE_PATH . 'admin/views/payment-gateways.php' );
gw_check( false !== strpos( $view_src, 'flavor-gateway-grid' ), 'admin view renders the gateway grid' );
gw_check( false !== strpos( $view_src, 'flavor-flow' ), 'admin view renders the four-step connection rail' );

$wizard = (string) file_get_contents( FLAVOR_DIR . '/inc/class-onboarding.php' );
gw_check( 1 === preg_match( '/\$\w+\s+=\s+min\(\s*11,\s*max\(\s*1,/', $wizard ), 'wizard clamps its draft step to eleven' );
gw_check( false !== strpos( $wizard, 'var totalSteps = 11;' ), 'wizard JS walks eleven steps' );
gw_check( false !== strpos( $wizard, 'data-pane="9"' ) && false !== strpos( $wizard, 'gateway_provider' ), 'step nine is the payment gateway step' );
gw_check( false !== strpos( $wizard, 'IranianGatewayManager::connect' ), 'wizard connects the gateway after saving settings' );
gw_check( false !== strpos( $wizard, "'payment_online'" ), 'wizard sanitizes the online-payment toggle' );

$launch = (string) file_get_contents( FLAVOR_DIR . '/inc/class-launch-center.php' );
gw_check( false !== strpos( $launch, "'iranian_gateway'" ), 'Launch Center adds an Iranian gateway check' );
gw_check( false !== strpos( $launch, 'page=flavor-payments' ) || false !== strpos( $launch, "page=' . 'flavor-payments" ), 'Launch Center links to the gateway screen' );

$dashboard = (string) file_get_contents( FLAVOR_CORE_PATH . 'admin/views/executive-dashboard.php' );
gw_check( false !== strpos( $dashboard, 'flavor-payments' ), 'main dashboard links to the gateway screen' );
gw_check( false !== strpos( $dashboard, 'flavor_gateway_summary' ), 'main dashboard renders a payment summary' );

$settings_src = (string) file_get_contents( FLAVOR_CORE_PATH . 'includes/Admin/AdminMenus.php' );
gw_check( false !== strpos( $settings_src, 'preferred_gateway' ), 'main settings screen exposes the preferred gateway' );
gw_check( false !== strpos( $settings_src, 'hide_offline_when_online' ), 'main settings screen exposes the offline-visibility toggle' );

gw_check( is_readable( dirname( __DIR__, 2 ) . '/docs/fa/12-darghah-pardakht.md' ), 'Persian gateway guide exists' );

/* -------------------------------------------------------------------------
 * 5. Report.
 * ---------------------------------------------------------------------- */

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
