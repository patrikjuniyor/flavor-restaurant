<?php
/**
 * Response security headers guard (R4-6 / پرامپت ۳۹).
 *
 * Every flavor/v1 REST response must carry nosniff + Referrer-Policy and
 * must NOT be cacheable unless its controller explicitly opted into a
 * public cache policy — so order/reservation/auth/cart payloads can never
 * be stored by browsers or shared caches. Front-end HTML gets the same
 * two headers plus a soft Report-Only CSP (never the enforcing header,
 * because the theme ships inline payloads and Elementor markup).
 *
 *   1. rest_headers purity — scope, default no-store, explicit opt-outs.
 *   2. Live middleware   — decorate a real mock WP_REST_Response through
 *      on_rest_dispatch, including non-response pass-through and route
 *      fallback when get_namespace() is unavailable.
 *   3. HTML headers      — wp_headers decoration, admin untouched,
 *      disable-filter path, and the Report-Only (never enforcing) pin.
 *   4. Pins              — SecurityHeaders is wired in Plugin::hooks,
 *      registered with 3 accepted args, and the CSP stays soft
 *      (unsafe-inline kept, no report-uri sink added).
 *
 * Runs in the global namespace so the is_admin() polyfill below is
 * visible to the plugin's global-namespace call site.
 *
 *   php tests/theme/test-security-headers.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );

define( 'ABSPATH', $root . '/flavor-core/' );
require_once $root . '/flavor-core/tests/mock-wp-environment.php';
require_once $root . '/flavor-core/includes/Support/SecurityHeaders.php';

use FlavorCore\Support\SecurityHeaders;

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Mock environment has no is_admin(); tests toggle $GLOBALS['_test_is_admin'].
	 *
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['_test_is_admin'] );
	}
}

/**
 * Request double exposing only get_route() (namespace_of fallback path).
 */
class RouteOnlyRequest extends \WP_REST_Request {
	/**
	 * Route accessor matching WP_REST_Request.
	 *
	 * @return string
	 */
	public function get_route(): string {
		return $this->route;
	}
}

/**
 * Request double with full WP get_namespace() support.
 */
class NamespacedRequest extends \WP_REST_Request {
	/**
	 * Namespace.
	 *
	 * @var string
	 */
	private $ns;

	/**
	 * Ctor.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route path.
	 * @param string $ns     Namespace.
	 */
	public function __construct( string $method, string $route, string $ns ) {
		parent::__construct( $method, $route );
		$this->ns = $ns;
	}

	/**
	 * Namespace accessor matching WP_REST_Request.
	 *
	 * @return string
	 */
	public function get_namespace(): string {
		return $this->ns;
	}
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

/* ------------------------------------------------------------------ */
/* 1. Pure REST header computation                                     */
/* ------------------------------------------------------------------ */
echo "--- 1. rest_headers purity ---\n";

$h = SecurityHeaders::rest_headers( array(), 'flavor/v1' );
check(
	( $h['X-Content-Type-Options'] ?? '' ) === 'nosniff'
		&& ( $h['Referrer-Policy'] ?? '' ) === 'strict-origin-when-cross-origin',
	'flavor REST responses get nosniff + strict-origin-when-cross-origin'
);
check(
	( $h['Cache-Control'] ?? '' ) === 'no-store, must-revalidate',
	'default-deny: customer data (orders/reservation/auth) is no-store'
);

$h = SecurityHeaders::rest_headers( array( 'Cache-Control' => 'public, max-age=30' ), 'flavor/v1' );
check(
	$h['Cache-Control'] === 'public, max-age=30',
	'explicit public opt-in (menu/search) is preserved, not clobbered'
);

$h = SecurityHeaders::rest_headers( array( 'Cache-Control' => 'private, no-store' ), 'flavor/v1' );
check(
	$h['Cache-Control'] === 'private, no-store' && isset( $h['X-Content-Type-Options'] ),
	'already-hardened controllers keep their exact policy'
);

check(
	SecurityHeaders::rest_headers( array(), 'wp/v2' ) === array(),
	'core REST namespaces are left completely untouched'
);
check(
	SecurityHeaders::rest_headers( array(), 'flavorx/v1' ) === array(),
	'lookalike namespace prefix (flavorx/) is NOT captured by flavor/ scope'
);

/* ------------------------------------------------------------------ */
/* 2. Live middleware over mock responses                              */
/* ------------------------------------------------------------------ */
echo "\n--- 2. rest_post_dispatch middleware ---\n";

$mw = new SecurityHeaders();

$resp = new \WP_REST_Response( array( 'success' => true ), 200 );
$resp = $mw->on_rest_dispatch( $resp, null, new RouteOnlyRequest( 'GET', '/flavor/v1/orders/42' ) );
$hdrs = $resp->get_headers();
check(
	( $hdrs['X-Content-Type-Options'] ?? '' ) === 'nosniff'
		&& ( $hdrs['Cache-Control'] ?? '' ) === 'no-store, must-revalidate',
	'route-based fallback decorates /flavor/v1/orders responses live'
);

$resp2 = new \WP_REST_Response( array(), 200 );
$resp2->header( 'Cache-Control', 'public, max-age=30' );
$resp2 = $mw->on_rest_dispatch( $resp2, null, new NamespacedRequest( 'GET', '/flavor/v1/menu', 'flavor/v1' ) );
check(
	$resp2->get_headers()['Cache-Control'] === 'public, max-age=30'
		&& isset( $resp2->get_headers()['Referrer-Policy'] ),
	'get_namespace() wins over route parsing; cache opt-in survives dispatch'
);

$resp3 = new \WP_REST_Response( array(), 401 );
$resp3 = $mw->on_rest_dispatch( $resp3, null, new RouteOnlyRequest( 'POST', '/flavor/v1/auth/login' ) );
check(
	( $resp3->get_headers()['Cache-Control'] ?? '' ) === 'no-store, must-revalidate',
	'auth error envelopes (401) are no-store too — nothing cacheable on probes'
);

$passthru = new \WP_Error( 'x', 'y' );
check(
	$mw->on_rest_dispatch( $passthru, null, new RouteOnlyRequest( 'GET', '/flavor/v1/x' ) ) instanceof \WP_Error,
	'non-WP_REST_Response dispatch results pass through untouched'
);
$raw_arr = array( 'data' => 1 );
check(
	$mw->on_rest_dispatch( $raw_arr, null, null ) === $raw_arr,
	'raw arrays / null requests never fatal the middleware'
);

$resp4 = new \WP_REST_Response( array(), 200 );
$resp4 = $mw->on_rest_dispatch( $resp4, null, new RouteOnlyRequest( 'GET', '/wp/v2/posts' ) );
check(
	$resp4->get_headers() === array(),
	'core REST routes are not decorated from the middleware'
);

/* ------------------------------------------------------------------ */
/* 3. HTML / wp_headers decoration                                     */
/* ------------------------------------------------------------------ */
echo "\n--- 3. front-end HTML headers ---\n";

$hh = SecurityHeaders::html_headers( array(), SecurityHeaders::csp_report_only() );
check(
	( $hh['X-Content-Type-Options'] ?? '' ) === 'nosniff'
		&& ( $hh['Referrer-Policy'] ?? '' ) === 'strict-origin-when-cross-origin',
	'public HTML pages get nosniff + Referrer-Policy'
);
check(
	isset( $hh['Content-Security-Policy-Report-Only'] )
		&& ! isset( $hh['Content-Security-Policy'] ),
	'CSP is Report-Only only — nothing is enforced/broken on day one'
);

$hh2 = SecurityHeaders::html_headers( array(), '' );
check(
	! isset( $hh2['Content-Security-Policy-Report-Only'] ) && isset( $hh2['X-Content-Type-Options'] ),
	'empty filtered CSP disables only the CSP header, not the rest'
);

$GLOBALS['_test_is_admin'] = true;
$admin                     = $mw->on_wp_headers( array( 'X-Robots-Tag' => 'noindex' ) );
$GLOBALS['_test_is_admin'] = false;
check(
	$admin === array( 'X-Robots-Tag' => 'noindex' ),
	'wp-admin HTML is left alone (its own header policy)'
);

$public_html = $mw->on_wp_headers( array() );
check(
	isset( $public_html['Content-Security-Policy-Report-Only'] )
		&& strpos( $public_html['Content-Security-Policy-Report-Only'], "default-src 'self'" ) === 0,
	'live on_wp_headers() emits the soft policy starting at default-src self'
);

/* ------------------------------------------------------------------ */
/* 4. Source pins                                                       */
/* ------------------------------------------------------------------ */
echo "\n--- 4. wiring & policy pins ---\n";

$plugin = file_get_contents( $root . '/flavor-core/includes/Plugin.php' );
check(
	false !== strpos( $plugin, '( new Support\SecurityHeaders() )->hooks();' ),
	'SecurityHeaders middleware is wired into Plugin boot'
);

$src = file_get_contents( $root . '/flavor-core/includes/Support/SecurityHeaders.php' );
check(
	false !== strpos( $src, "add_filter( 'rest_post_dispatch', array( \$this, 'on_rest_dispatch' ), 10, 3 )" ),
	'REST hook is registered with the 3-argument dispatch signature'
);
check(
	false !== strpos( $src, "add_filter( 'wp_headers', array( \$this, 'on_wp_headers' ) )" ),
	'HTML decoration is registered on wp_headers'
);

$csp = SecurityHeaders::csp_report_only();
check(
	false !== strpos( $csp, "style-src 'self' 'unsafe-inline' https:" )
		&& false !== strpos( $csp, "script-src 'self' 'unsafe-inline' https:" ),
	'CSP keeps unsafe-inline — Elementor/inline payloads would break otherwise (soft policy)'
);
check(
	false === strpos( $csp, 'report-uri' ) && false === strpos( $csp, 'report-to' ),
	'no violation-report sink (avoids a new anonymous REST route by design)'
);
check(
	false !== strpos( $csp, "object-src 'none'" ) && false !== strpos( $csp, "base-uri 'self'" ),
	'plugs object/base even while only reporting'
);

foreach ( array( 'RestController', 'RestStore', 'RestExperience' ) as $ctrl ) {
	$csrc = file_get_contents( $root . '/flavor-core/includes/API/' . $ctrl . '.php' );
	check(
		false !== strpos( $csrc, "'private, no-store'" ),
		"{$ctrl} keeps its explicit private, no-store cache policy"
	);
}

if ( $failed > 0 ) {
	echo "\n{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}
echo "\nALL " . $passed . " checks passed.\n";
exit( 0 );
