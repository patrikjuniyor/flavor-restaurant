<?php
/**
 * REST surface smoke test — run against a real WordPress.
 *
 * The N-08 lesson was that a passing suite is compatible with code that
 * does not run. Endpoints are the worst case of that: a route can be
 * registered, documented, and reviewed, and still fatal the first time
 * anyone calls it, because registration proves nothing about execution.
 *
 * This test walks every registered flavor route and dispatches a real
 * request at it, then asserts the response is a valid REST response rather
 * than a fatal. It found `HealthCheck` calling
 * `SmsManager::active_provider()`, a method that has never existed.
 *
 *   php tests/integration/real-rest-smoke.php --wp=/path/to/wordpress
 *
 * @package Flavor
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( "Run from the command line.\n" );
}

$opts    = getopt( '', array( 'wp:' ) );
$wp_root = rtrim( (string) ( $opts['wp'] ?? '' ), '/' );

if ( '' === $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/integration/real-rest-smoke.php --wp=/path/to/wordpress\n" );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require $wp_root . '/wp-load.php';

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

echo "=== Flavor REST Surface Smoke Test ===\n\n";
printf( "WordPress %s, WooCommerce %s\n\n", get_bloginfo( 'version' ), ( defined( 'WC_VERSION' ) ? WC_VERSION : 'not active' ) );

/** Collect every route belonging to this plugin. */
$routes = array();
$server = rest_get_server();
foreach ( $server->get_routes() as $route => $handlers ) {
	if ( false === strpos( $route, '/flavor' ) ) {
		continue;
	}
	foreach ( $handlers as $handler ) {
		$methods = array_keys( $handler['methods'] );
		foreach ( $methods as $method ) {
			// Every method, not just GET: the mutating routes are where a
			// fatal costs most and where an authorisation gap matters, and
			// they are the least likely ever to have been dispatched.
			if ( in_array( $method, array( 'HEAD' ), true ) ) {
				continue;
			}
			$routes[] = array( $method, $route );
		}
	}
}

echo '--- 1. Routes are registered ---', "\n";
check( ! empty( $routes ), 'flavor routes registered (' . count( $routes ) . ' method-route pairs)' );

$by_method = array();
foreach ( $routes as $r ) {
	$by_method[ $r[0] ] = ( $by_method[ $r[0] ] ?? 0 ) + 1;
}
foreach ( $by_method as $method => $n ) {
	printf( "    %-6s %d\n", $method, $n );
}
check( ! empty( $by_method['POST'] ?? 0 ), 'POST routes exist and are covered too (' . ( $by_method['POST'] ?? 0 ) . ')' );

echo "\n--- 2. Every route answers without fataling ---\n";

// Numbered placeholders are filled so the request reaches the callback;
// a route like /orders/(?P<id>\d+) is dispatched as /orders/1.
$fill = static function ( string $route ): string {
	$route = preg_replace( '/\(\?P<[a-zA-Z_]+>\\\\d\+\)/', '1', $route );
	$route = preg_replace( '/\(\?P<[a-zA-Z_]+>[^)]+\)/', 'test', (string) $route );
	return (string) $route;
};

$broken = array();
$codes  = array();

foreach ( $routes as $list ) {
	list( $method, $route ) = $list;
	$path    = $fill( $route );
	$request = new WP_REST_Request( $method, $path );

	$error_before = error_get_last();

	try {
		$response = $server->dispatch( $request );
		$status   = $response->get_status();
		$codes[ $route ] = $status;

		// 5xx from a smoke request means the callback blew up rather than
		// answered. A 4xx is a legitimate refusal.
		if ( $status >= 500 ) {
			$broken[] = $route . ' -> ' . $status;
		}
	} catch ( \Throwable $e ) {
		$broken[] = $route . ' -> threw: ' . $e->getMessage();
	}
}

check(
	empty( $broken ),
	'no route returns a server error or throws'
	. ( empty( $broken ) ? ' (' . count( $codes ) . ' dispatched)' : ' — ' . implode( '; ', $broken ) )
);

echo "\n--- 3. The health endpoint is honest about what it exposes ---\n";

$health = $server->dispatch( new WP_REST_Request( 'GET', '/flavor/v1/system/health' ) );
check( 200 === $health->get_status(), 'the health endpoint answers' );

$health_data = $health->get_data();
$payload     = wp_json_encode( $health_data );

// An anonymous visitor should not learn the PHP version, memory ceiling or
// internal schema state. That is not health, that is reconnaissance.
check( false === strpos( (string) $payload, 'php_version' ), 'the anonymous health response omits the PHP version' );
check( false === strpos( (string) $payload, 'memory_limit' ), 'the anonymous health response omits the memory ceiling' );
check( false === strpos( (string) $payload, 'db_version' ), 'the anonymous health response omits the schema version' );
check( false !== strpos( (string) $payload, 'healthy' ), 'the health endpoint still reports a status' );

echo "\n--- 4. The logs endpoint stays behind a capability ---\n";

$logs = $server->dispatch( new WP_REST_Request( 'GET', '/flavor/v1/system/logs' ) );
check( $logs->get_status() >= 400, 'an anonymous request for logs is refused (' . $logs->get_status() . ')' );

echo "\n--- 5. A sample of public routes answer coherently ---\n";

$sample = array( '/flavor/v1/branches', '/flavor/v1/categories', '/flavor/v1/dishes/featured', '/flavor/v1/context' );
foreach ( $sample as $path ) {
	if ( ! array_key_exists( str_replace( '/flavor/v1', '', $path ), $codes ) && ! in_array( $path, array_keys( $codes ), true ) ) {
		// Not registered under an expected namespace; skip rather than guess.
		continue;
	}
	$response = $server->dispatch( new WP_REST_Request( 'GET', $path ) );
	check( $response->get_status() < 500, "{$path} answers (" . $response->get_status() . ')' );
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
