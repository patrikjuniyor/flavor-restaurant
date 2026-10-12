<?php
/**
 * Public-endpoint hardening guard (R4-5 / پرامپت ۳۷).
 *
 * Every route registered with `permission_callback => '__return_true'` is
 * readable by anonymous visitors. This gate keeps them that way safely:
 *
 *   1. Manifest  — the exact set of public routes is pinned; a new public
 *      route fails the suite until it is reviewed and added deliberately.
 *   2. Canary    — real secret values (FCM key, CI webhook secret, GitHub
 *      token, APNs paths) are seeded through the SAME setters production
 *      uses, then the three audited public callbacks (app-bootstrap,
 *      mobile/config, system/health) are executed live against the mock WP
 *      environment. No seeded value may appear in any payload.
 *   3. Health    — the anonymous health report is status-only (no PHP
 *      version, memory numbers, missing-table names); the same endpoint
 *      run AS an admin does expose them, proving the redaction is real and
 *      not just a broken payload.
 *   4. Callbacks — the source of each public callback (plus its one-level
 *      static builders) never names a secret option; full-config dumps
 *      (`return $all` / `return $cfg`) are forbidden in public builders.
 *   5. HMAC      — the unsigned-by-permission build callback must keep its
 *      hash_equals verification chain (route + secret + format guard).
 *
 *   php tests/theme/test-public-endpoints.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

$root = dirname( __DIR__, 2 );

define( 'ABSPATH', $root . '/flavor-core/' );
require_once $root . '/flavor-core/tests/mock-wp-environment.php';
foreach ( array(
	'FLAVOR_CORE_VERSION'          => '0.0.0-test',
	'FLAVOR_CORE_REST_NAMESPACE'   => 'flavor/v1',
	'FLAVOR_CORE_FILE'             => $root . '/flavor-core/flavor-core.php',
) as $cname => $cval ) {
	if ( ! defined( $cname ) ) {
		define( $cname, $cval );
	}
}

foreach ( array(
	'/flavor-core/includes/Support/Roles.php',
	'/flavor-core/includes/Activator.php',
	'/flavor-core/includes/Support/Settings.php',
	'/flavor-core/includes/Support/Iran.php',
	'/flavor-core/includes/Support/Jalali.php',
	'/flavor-core/includes/Database/Schema.php',
	'/flavor-core/includes/WooCommerce/Currency.php',
	'/flavor-core/includes/PostTypes/BranchPostType.php',
	'/flavor-core/includes/Observability/HealthCheck.php',
	'/flavor-core/includes/SMS/ProviderInterface.php',
	'/flavor-core/includes/SMS/Providers/DevProvider.php',
	'/flavor-core/includes/SMS/Providers/MelipayamakProvider.php',
	'/flavor-core/includes/SMS/Providers/FarazProvider.php',
	'/flavor-core/includes/SMS/Providers/KavenegarProvider.php',
	'/flavor-core/includes/SMS/SmsManager.php',
	'/flavor-core/includes/Mobile/MobileConfigManager.php',
	'/flavor-core/includes/API/BaseApiController.php',
	'/flavor-core/includes/API/SettingsController.php',
	'/flavor-core/includes/API/ObservabilityController.php',
	'/flavor-core/includes/API/MobileProvisionController.php',
) as $rel ) {
	$file = $root . $rel;
	if ( ! is_readable( $file ) ) {
		echo "  [SETUP FAIL] missing {$rel}\n";
		exit( 1 );
	}
	require_once $file;
}

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
function check( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

/**
 * Extract a class function body by name (brace-matched).
 *
 * @param string $src  File source.
 * @param string $name Function name.
 * @return string|null
 */
function extract_function( string $src, string $name ): ?string {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)[^;{]*\{/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}
	$i     = $m[0][1] + strlen( $m[0][0] ) - 1;
	$depth = 0;
	$len   = strlen( $src );
	for ( ; $i < $len; ++$i ) {
		if ( '{' === $src[ $i ] ) {
			++$depth;
		} elseif ( '}' === $src[ $i ] ) {
			--$depth;
			if ( 0 === $depth ) {
				return substr( $src, $m[0][1], $i - $m[0][1] + 1 );
			}
		}
	}
	return substr( $src, $m[0][1] );
}

/* ------------------------------------------------------------------ */
/* 1. Public-route manifest                                            */
/* ------------------------------------------------------------------ */
echo "--- 1. public-route manifest ---\n";

$PINNED_PUBLIC = array(
	'AuthController.php|/auth/me|me',
	'AuthController.php|/auth/otp/request|otp_request',
	'AuthController.php|/auth/otp/verify|otp_verify',
	'AuthController.php|/auth/token/refresh|token_refresh',
	'BranchController.php|/branches|get_branches',
	'BranchController.php|/branches/(?P<id>\d+)|get_branch',
	'BranchController.php|/branches/(?P<id>\d+)/schedule|get_schedule',
	'BranchController.php|/branches/(?P<id>\d+)/tables|get_tables',
	'BranchController.php|/branches/(?P<id>\d+)/zones|get_zones',
	'CartController.php|/cart|get_cart',
	'CartController.php|/cart/coupon|apply_coupon',
	'CartController.php|/cart/items|add_item',
	'CartController.php|/cart/items/(?P<key>[a-zA-Z0-9_]+)|update_item',
	'MenuController.php|/categories|get_categories',
	'MenuController.php|/dishes/featured|get_featured',
	'MenuController.php|/dishes/special-offers|get_special_offers',
	'MenuController.php|/dishes/(?P<id>\d+)|get_dish',
	'MenuController.php|/menu|get_menu',
	'MobileProvisionController.php|/mobile/builds/callback|build_callback',
	'MobileProvisionController.php|/mobile/config|get_config',
	'ObservabilityController.php|/system/health|get_health',
	'OrderController.php|/orders|create_order',
	'RestExperience.php|/calendar|calendar',
	'RestController.php|/context|get_context',
	'RestSearch.php|/search|search',
	'RestSearch.php|/search/popular|popular',
	'RestSearch.php|/search/suggest|suggest',
	'RestStore.php|/checkout/options|checkout_options',
	'RestStore.php|/me|me',
	'RestStore.php|/tables|tables',
	'RestStore.php|/zones/check|zone_check',
	'ReviewController.php|/dishes/(?P<id>\d+)/reviews|get_dish_reviews',
	'ReviewController.php|/reviews/recent|get_recent_reviews',
	'SettingsController.php|/settings/app-bootstrap|get_bootstrap',
	'OrderController.php|/orders/(?P<id>\d+)|get_order',
	'OrderController.php|/orders/(?P<id>\d+)/track|track_order',
	'OrderController.php|/orders/(?P<id>\d+)/cancel|cancel_order',
	'OrderController.php|/orders/(?P<id>\d+)/reorder|reorder',
	'ReservationController.php|/reservations|book_table',
	'ReservationController.php|/reservations/calendar|get_calendar',
	'ReservationController.php|/reservations/slots|get_slots',
	'ReservationController.php|/reservations/(?P<id>\d+)|get_reservation',
	'ReservationController.php|/reservations/(?P<id>\d+)/cancel|cancel_reservation',
);

$api_dir = $root . '/flavor-core/includes/API';
$found   = array();
foreach ( glob( $api_dir . '/*.php' ) as $file ) {
	$f   = basename( $file );
	$src = file_get_contents( $file );
	// Each route block: the non-greedy window up to the first `);` is the full
	// register_rest_route(...) call — inner arrays only ever close with `),`.
	if ( preg_match_all( '/register_rest_route\s*\((.*?)\);/s', $src, $blocks, PREG_SET_ORDER ) ) {
		foreach ( $blocks as $blk ) {
			if ( false === strpos( $blk[1], '__return_true' ) ) {
				continue;
			}
			if ( preg_match( "/'((?:\/|<)[^']*)'/", $blk[1], $mp ) && preg_match( "/'callback'\s*=>\s*array\(\s*\\\$this,\s*'([a-z_]+)'\s*\)/", $blk[1], $mc ) ) {
				$found[] = $f . '|' . $mp[1] . '|' . $mc[1];
			}
		}
	}
}
sort( $found );
$pinned = $PINNED_PUBLIC;
sort( $pinned );
$new    = array_diff( $found, $pinned );
$removed = array_diff( $pinned, $found );
check( count( $found ) >= 40, 'public-route scan sees the whole surface (routes: ' . count( $found ) . ')' );
check( array() === $new, 'no unreviewed NEW public route (new: ' . implode( ', ', $new ) . ' — add deliberately after review)' );
check( array() === $removed, 'no public route silently re-secured while still listed (removed: ' . implode( ', ', $removed ) . ')' );

/* ------------------------------------------------------------------ */
/* 2. Source scan: public callbacks never name secrets                 */
/* ------------------------------------------------------------------ */
echo "\n--- 2. public-callback sources are secret-free ---\n";

$SECRET_MARKERS = array(
	'fcm_service_key',
	'ci_webhook_secret',
	'github_token',
	'flavor_apns_auth_key_path',
	'flavor_apns_key_id',
	'keystore_password',
	'get_webhook_secret',
	'SECRET_MASK',
);

// Builders whose whole job is the secret store itself; their public CALLERS
// must not name them (checked), the store files are exempt from the scan.
$SCAN_SKIP = array( 'MobileConfigManager.php', 'SmsManager.php' );

function body_has_secret( string $body ): ?string {
	global $SECRET_MARKERS;
	foreach ( $SECRET_MARKERS as $m ) {
		if ( false !== strpos( $body, $m ) ) {
			return $m;
		}
	}
	return null;
}

$leaks = array();
foreach ( $found as $tuple ) {
	list( $file, $path, $cb ) = explode( '|', $tuple );
	if ( in_array( $file, $SCAN_SKIP, true ) ) {
		continue;
	}
	$src  = file_get_contents( $api_dir . '/' . $file );
	$fn   = extract_function( $src, $cb );
	if ( null === $fn ) {
		$leaks[] = "$file::$cb (function not found for $path)";
		continue;
	}
	// Full config dumps are forbidden in public builders.
	if ( preg_match( '/return\s+\$(?:all|cfg)\s*;/', $fn ) ) {
		$leaks[] = "$file::$cb returns a raw config array for $path";
	}
	$hit = body_has_secret( $fn );
	if ( $hit ) {
		$leaks[] = "$file::$cb names {$hit} for $path";
	}
	// One level of static builders: Class::method( calls inside the callback.
	foreach ( ( function () use ( $fn ) { $o = array(); if ( preg_match_all( '/(?:\\\\?FlavorCore\\\\[A-Za-z\\\\]*\\\\)?([A-Z][A-Za-z]+)::([a-z_]+)\s*\(/', $fn, $mm, PREG_SET_ORDER ) ) { foreach ( $mm as $x ) { $o[ $x[1] . '::' . $x[2] ] = $x[2]; } } return $o; } )() as $label => $mname ) {
		if ( in_array( $mname, array( 'get_all', 'redacted', 'default_id', 'check' ), true ) ) {
			continue; // schema store, masking store, trivial accessors
		}
		// HMAC verifier: reads the secret to COMPARE it (hash_equals),
		// never returns it — the response is only {updated:true}. Audited.
		if ( 'BuildManager::update_build_from_ci' === $label ) {
			continue;
		}
		foreach ( glob( $root . '/flavor-core/includes/*/*.php' ) as $cand ) {
			if ( in_array( basename( $cand ), $SCAN_SKIP, true ) ) {
				continue;
			}
			$cs   = file_get_contents( $cand );
			$mbuf = ( false !== strpos( $cs, 'class ' . $label[0] ) || preg_match( '/class\s+' . preg_quote( explode( '::', $label )[0], '/' ) . '\b/', $cs ) )
				? extract_function( $cs, $mname )
				: null;
			if ( null === $mbuf ) {
				continue;
			}
			$hit = body_has_secret( $mbuf );
			if ( $hit ) {
				$leaks[] = "$file::$cb -> {$label} names {$hit} for $path";
			}
			if ( preg_match( '/return\s+\$(?:all|cfg)\s*;/', $mbuf ) ) {
				$leaks[] = "$file::$cb -> {$label} dumps raw config for $path";
			}
			break;
		}
	}
}
check( array() === $leaks, 'no public callback or its builder names a secret / dumps config (leaks: ' . count( $leaks ) . ( $leaks ? ":\n    " . implode( "\n    ", array_slice( $leaks, 0, 8 ) ) : '' ) . ')' );

/* ------------------------------------------------------------------ */
/* 3. Live canary: seeded secrets never reach public payloads          */
/* ------------------------------------------------------------------ */
echo "\n--- 3. live canary (mock WP, real builders) ---\n";

// Seed through the production setter.
\FlavorCore\Mobile\MobileConfigManager::update(
	array(
		'app_name'        => 'Flavor Canary',
		'fcm_service_key' => 'SEED_FCMKEY_9f3a',
		'ci_webhook_secret' => 'SEED_CIWEB_7c21',
		'github_token'    => 'SEED_GHTOKEN_58de',
	)
);
update_option( 'flavor_apns_key_id', 'SEED_APNSID_12ab' );
update_option( 'flavor_apns_auth_key_path', '/keys/SEED_APNSKEY_34cd.p8' );

// Reachability: if these fail, sections below would pass vacuously.
$cfg = \FlavorCore\Mobile\MobileConfigManager::get_all();
check(
	'SEED_GHTOKEN_58de' === $cfg['github_token'] && 'SEED_FCMKEY_9f3a' === $cfg['fcm_service_key'] && 'SEED_CIWEB_7c21' === $cfg['ci_webhook_secret'],
	'canary values really are stored on the server (not a no-op update)'
);
check( 'SEED_APNSID_12ab' === get_option( 'flavor_apns_key_id' ), 'canary APNs option is set' );

$seedy = static function ( $payload ): bool {
	return false !== strpos( (string) json_encode( $payload ), 'SEED_' );
};

$settings = new \FlavorCore\API\SettingsController();
$resp     = $settings->get_bootstrap();
$boot     = is_object( $resp ) && method_exists( $resp, 'get_data' ) ? $resp->get_data() : (array) $resp;
$boot_d = isset( $boot['data'] ) && is_array( $boot['data'] ) ? $boot['data'] : $boot;
check( isset( $boot_d['app']['name'] ), 'app-bootstrap executed and returned real data (non-vacuous)' );
check( ! $seedy( $boot ), 'app-bootstrap payload is free of seeded secrets' );

$mprov = new \FlavorCore\API\MobileProvisionController();
$resp2 = $mprov->get_config();
$conf  = is_object( $resp2 ) && method_exists( $resp2, 'get_data' ) ? $resp2->get_data() : (array) $resp2;
check( isset( $conf['data']['has_fcm'] ) || isset( $conf['has_fcm'] ), 'mobile/config executed and returned branding payload' );
check( ! $seedy( $conf ), 'mobile/config payload is free of seeded secrets' );

$obs = new \FlavorCore\API\ObservabilityController();
$resp3 = $obs->get_health();
$health = is_object( $resp3 ) && method_exists( $resp3, 'get_data' ) ? $resp3->get_data() : (array) $resp3;
$health_d = isset( $health['data'] ) && is_array( $health['data'] ) ? $health['data'] : $health;
$serialized = (string) json_encode( $health );
check( isset( $health_d['status'] ), 'system/health executed and returned a status (non-vacuous)' );
check( ! $seedy( $health ), 'anonymous health payload is free of seeded secrets' );
foreach ( array( 'php_version', 'memory_limit', 'memory_usage_mb', 'missing_tables', 'db_version' ) as $leaky ) {
	if ( false !== strpos( $serialized, $leaky ) ) {
		check( false, "anonymous health must not expose server detail: {$leaky}" );
	}
}
check( false === strpos( $serialized, 'php_version' ) && false === strpos( $serialized, 'missing_tables' ), 'anonymous health is status-only (no versions/paths/table names)' );

// Same endpoint AS admin: details must appear (proves redaction is conditional).
$GLOBALS['_mock_users'][77]     = new \WP_User( (object) array( 'ID' => 77, 'roles' => array( 'administrator' ), 'user_login' => 'admin', 'user_email' => 'a@e.com', 'display_name' => 'Admin' ) );
$GLOBALS['_mock_user_caps'][77] = array( 'manage_options' => true );
wp_set_current_user( 77 );
$resp4  = $obs->get_health();
$adminh = is_object( $resp4 ) && method_exists( $resp4, 'get_data' ) ? $resp4->get_data() : (array) $resp4;
if ( isset( $adminh['data'] ) && is_array( $adminh['data'] ) ) { $adminh = $adminh['data']; }
$adminS = (string) json_encode( $adminh );
check( false !== strpos( $adminS, 'php_version' ), 'admin health DOES include the details (redaction is real, not a crash)' );
check( ! $seedy( $adminh ), 'even admin health never prints seeded secret values' );
wp_set_current_user( 0 );

/* ------------------------------------------------------------------ */
/* 4. Health redaction invariant in source                             */
/* ------------------------------------------------------------------ */
echo "\n--- 4. structural invariants ---\n";

$hc = file_get_contents( $root . '/flavor-core/includes/Observability/HealthCheck.php' );
$pub = extract_function( $hc, 'public_checks' );
check( null !== $pub && preg_match_all( "/=>\\s*array\\(\\s*'status'\\s*\\)/", $pub ) >= 4, 'public_checks keeps only status keys for every listed check' );
check( null !== $pub && false !== strpos( $pub, "\$allowed[ \$name ] ?? array( 'status' )" ) || ( null !== $pub && strpos( $pub, "?? array( 'status' )" ) ), 'unknown/future checks default to status-only (fail-safe)' );
check( false !== strpos( $hc, "if ( ! current_user_can( 'manage_options' ) )" ) && preg_match( '/current_user_can\( \'manage_options\' \)[^}]*public_checks/s', $hc ) === 1, 'redaction is applied to exactly the unauthenticated branch' );

$bm = file_get_contents( $root . '/flavor-core/includes/Mobile/BuildManager.php' );
check( false !== strpos( $bm, "hash_equals( \$expected_sig, \$signature )" ), 'build callback verifies HMAC with a timing-safe compare' );
check( false !== strpos( $bm, 'missing_server_secret' ) && false !== strpos( $bm, 'missing_signature' ) && false !== strpos( $bm, 'malformed_signature' ), 'unsigned/malformed callbacks are rejected before verification' );

/* ------------------------------------------------------------------ */
/* 5. The leak scanner itself works                                    */
/* ------------------------------------------------------------------ */
echo "\n--- 5. scanner self-probe ---\n";

$planted = "<?php\nclass X { public function get_config() {\n\treturn array( 'token' => get_option('github_token') );\n} }";
check( null !== body_has_secret( $planted ), 'scanner flags a planted github_token leak in a public builder' );
$safe = "<?php\nclass X { public function get_config() {\n\treturn array( 'app_name' => 'ok' );\n} }";
check( null === body_has_secret( $safe ), 'scanner stays quiet on an honest branding payload' );

if ( $failed > 0 ) {
	echo "\n{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}
echo "\nALL " . $passed . " checks passed.\n";
exit( 0 );
