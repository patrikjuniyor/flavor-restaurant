<?php
/**
 * Export the canonical mobile white-label provisioning payload.
 *
 * Regenerates the fixture consumed by the provisioning-tool contract tests:
 *
 *     php flavor-core/tests/export-mobile-config.php \
 *         > mobile/assets/branding/fixtures/server_payload.json
 *
 * The payload is produced by the REAL FlavorCore\Mobile\MobileConfigManager
 * running against the WordPress mock environment (no WordPress needed), so
 * the fixture always reflects the exact server-side contract
 * (flavor-mobile-branding@1, see docs/MOBILE-WHITELABEL-CONTRACT.md).
 *
 * @package FlavorCore\Tests
 */

namespace FlavorCore\Tests;

if ( php_sapi_name() !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'FLAVOR_CORE_PATH', dirname( __DIR__ ) . '/' );
define( 'FLAVOR_CORE_VERSION', '1.5.0' );
defined( 'FLAVOR_CORE_REST_NAMESPACE' ) || define( 'FLAVOR_CORE_REST_NAMESPACE', 'flavor/v1' );

require_once __DIR__ . '/mock-wp-environment.php';
require_once FLAVOR_CORE_PATH . 'includes/Autoloader.php';
\FlavorCore\Autoloader::register();

$payload = \FlavorCore\Mobile\MobileConfigManager::get_ci_provision_payload();

// Deterministic fixture: normalize volatile server-side values.
$payload['api_base_url']     = 'https://wp.demos.example/wp-json/flavor/v1';
$payload['contact']['website'] = 'https://wp.demos.example';
$payload['legal']['privacy_policy'] = 'https://wp.demos.example/privacy-policy/';
$payload['legal']['terms']          = 'https://wp.demos.example/terms/';
$payload['server_timestamp'] = 1735689600;
// The mock's sanitize_title() does not transliterate Persian; pin a stable ASCII slug.
$payload['tenant_id'] = 'demo_tenant';

echo json_encode( $payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE ) . \PHP_EOL;
