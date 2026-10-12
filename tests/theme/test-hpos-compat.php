<?php
/**
 * P49 gate — WooCommerce 11 / HPOS compatibility cannot silently rot.
 *
 * Prompt 49 asked for a compatibility patrol with three clauses:
 *   1. no direct queries against wp_posts for orders — everything must go
 *      through wc_get_orders / wc_get_order / the WC CRUD objects,
 *   2. the declare_compatibility calls are present (before_woocommerce_init),
 *   3. gateways register through GatewayRegistrar without touching
 *      deprecated classes or instantiating anything themselves,
 * plus: test-price-calculator and run-e2e-integration must stay green.
 *
 * The patrol found the codebase compliant, so this gate's job is to keep it
 * that way — every invariant below is a tripwire:
 *   Section 1  source scans: zero 'shop_order' literals, zero new WC_Order,
 *              zero hard post.php?post= links, an order-file ↔ post-meta
 *              firewall, and zero raw SQL against either order store.
 *   Section 2  the guarded declare_compatibility pair is pinned verbatim.
 *   Section 3  the registrar appends class-name strings (WooCommerce owns
 *              instantiation); the gateways extend the current
 *              \WC_Payment_Gateway base behind class_exists guards.
 *   Section 4  the order lifecycle (create → stamp → persist → read) rides
 *              only store-API calls, in the plugin AND the theme.
 *   Section 5  LIVE runs: the WP_UnitTestCase-era price-calculator file is
 *              executed here through a shim (5 methods / ≥11 assertions),
 *              and run-e2e-integration.php must exit 0 with ≥81 passes —
 *              "stays green" as a machine fact, not a memory.
 *
 * Run: php tests/theme/test-hpos-compat.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );
$pass = 0;
$fail = 0;
function check( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  [PASS] $label\n";
	} else {
		$fail++;
		echo "  [FAIL] $label\n";
	}
}

/**
 * Collect every production PHP file the patrol covers.
 *
 * Scan scope: plugin bootstrap + includes + admin views (flavor-core) and
 * the theme root + inc (flavor). Tests, docs and dev-tools are out — they
 * may quote forbidden tokens while pinning them.
 *
 * @param string $root Repo root.
 * @return string[]
 */
function hpos_source_files( string $root ): array {
	$files = array();
	foreach ( array( '/flavor-core/*.php', '/flavor/*.php' ) as $glob ) {
		$found = glob( $root . $glob );
		$files = array_merge( $files, is_array( $found ) ? $found : array() );
	}
	foreach ( array( '/flavor-core/includes', '/flavor-core/admin', '/flavor/inc' ) as $area ) {
		if ( ! is_dir( $root . $area ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root . $area, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $it as $file ) {
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
				$files[] = $file->getPathname();
			}
		}
	}
	sort( $files );
	return $files;
}

$source_files = hpos_source_files( $root );
$contents     = array();
foreach ( $source_files as $f ) {
	$contents[ $f ] = (string) file_get_contents( $f );
}

/**
 * Files whose contents contain any of the needles.
 *
 * @param array<string,string> $contents File map.
 * @param string[]             $needles  Tokens.
 * @return string[]
 */
function hpos_files_containing( array $contents, array $needles ): array {
	$hits = array();
	foreach ( $contents as $file => $src ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $src, $needle ) ) {
				$hits[] = basename( dirname( $file ) ) . '/' . basename( $file ) . ' [' . $needle . ']';
				break;
			}
		}
	}
	return $hits;
}

echo "--- 0. the patrol scanner itself is not vacuous ---\n";
check( count( $source_files ) >= 100, 'the scanner sees the production tree (' . count( $source_files ) . ' files ≥ 100 floor)' );

echo "\n--- 1. no direct wp_posts queries for orders (clause 1) ---\n";
$hits = hpos_files_containing( $contents, array( 'shop_order' ) );
check(
	array() === $hits,
	"zero 'shop_order' literals in production code — orders are never queried as a post type" . ( $hits ? ': ' . implode( ', ', $hits ) : '' )
);
$hits = hpos_files_containing( $contents, array( 'new \WC_Order(', 'new WC_Order(' ) );
check(
	array() === $hits,
	'zero direct WC_Order instantiation — order objects come from wc_get_order / wc_get_orders / WC_Checkout' . ( $hits ? ': ' . implode( ', ', $hits ) : '' )
);
$hits = hpos_files_containing( $contents, array( 'post.php?post=' ) );
check(
	array() === $hits,
	'zero hard-coded post.php?post= admin links (HPOS order admin lives on wc-orders screens)' . ( $hits ? ': ' . implode( ', ', $hits ) : '' )
);

$violators = array();
foreach ( $contents as $file => $src ) {
	$reads_orders = false !== strpos( $src, 'wc_get_order(' ) || false !== strpos( $src, 'wc_get_orders(' );
	if ( ! $reads_orders ) {
		continue;
	}
	foreach ( array( 'get_post_meta(', 'update_post_meta(', 'add_post_meta(', 'delete_post_meta(' ) as $meta_fn ) {
		if ( false !== strpos( $src, $meta_fn ) ) {
			$violators[] = basename( $file ) . ' [' . $meta_fn . ']';
		}
	}
}
check(
	array() === $violators,
	'firewall: every file reading orders through the store API keeps meta on CRUD ($order->get_meta/update_meta_data), never post-meta functions' . ( $violators ? ': ' . implode( ', ', $violators ) : '' )
);
$hits = hpos_files_containing(
	$contents,
	array( 'wp_posts', '{$wpdb->prefix}posts', "prefix . 'posts'", 'wp_wc_orders', '{$wpdb->prefix}wc_orders', "prefix . 'wc_orders'" )
);
check(
	array() === $hits,
	'zero raw SQL against the posts table or either order store (wp_posts / wp_wc_orders)' . ( $hits ? ': ' . implode( ', ', $hits ) : '' )
);

echo "\n--- 2. declare_compatibility is present and guarded (clause 2) ---\n";
$boot = (string) file_get_contents( $root . '/flavor-core/flavor-core.php' );
check(
	false !== strpos( $boot, "'before_woocommerce_init'" )
		&& false !== strpos( $boot, 'class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class )' )
		&& false !== strpos( $boot, "declare_compatibility( 'custom_order_tables', FLAVOR_CORE_FILE, true )" ),
	"before_woocommerce_init declares 'custom_order_tables' behind a FeaturesUtil class_exists guard"
);
check(
	false !== strpos( $boot, "declare_compatibility( 'cart_checkout_blocks', FLAVOR_CORE_FILE, true )" ),
	"'cart_checkout_blocks' declared alongside — the blocks checkout stays enabled for the plugin"
);

echo "\n--- 3. GatewayRegistrar without deprecated classes (clause 3) ---\n";
$registrar = (string) file_get_contents( $root . '/flavor-core/includes/WooCommerce/GatewayRegistrar.php' );
check(
	false !== strpos( $registrar, "add_filter( 'woocommerce_payment_gateways', array( \$this, 'register' ) )" ),
	'the registrar hooks the woocommerce_payment_gateways filter (the supported registration point)'
);
check(
	false !== strpos( $registrar, '$gateways[] = PayAtCounter::class;' )
		&& false !== strpos( $registrar, '$gateways[] = CashOnDelivery::class;' )
		&& false !== strpos( $registrar, '$gateways[] = CardOnDelivery::class;' )
		&& 3 === substr_count( $registrar, 'Settings::get(' ),
	'all three gateways are appended as class-name strings, each behind a Settings toggle'
);
$register_body = false !== strpos( $registrar, 'public function register' )
	? substr( $registrar, (int) strpos( $registrar, 'public function register' ) )
	: '';
check(
	'' !== $register_body
		&& false === strpos( $register_body, 'new ' )
		&& false === strpos( $register_body, 'WC()' ),
	'register() never instantiates gateways or pokes WC()->payment_gateways — WooCommerce owns instantiation (no deprecated class calls)'
);

$abstract_src = (string) file_get_contents( $root . '/flavor-core/includes/WooCommerce/Gateways/AbstractOfflineGateway.php' );
check(
	false !== strpos( $abstract_src, 'abstract class AbstractOfflineGateway extends \WC_Payment_Gateway' )
		&& false !== strpos( $abstract_src, "class_exists( '\\\\WC_Payment_Gateway' )" ),
	'the shared base extends the current \WC_Payment_Gateway behind a class_exists guard'
);
$gw_dir_hits = array();
foreach ( glob( $root . '/flavor-core/includes/WooCommerce/Gateways/*.php' ) as $gw ) {
	$src = (string) file_get_contents( $gw );
	foreach ( array( 'wp_update_post', 'update_post_meta', 'get_post_meta', 'new \WC_Order(' ) as $bad ) {
		if ( false !== strpos( $src, $bad ) ) {
			$gw_dir_hits[] = basename( $gw ) . ' [' . $bad . ']';
		}
	}
}
check(
	array() === $gw_dir_hits,
	'gateway classes touch no post APIs at all — payment processing is order-CRUD only' . ( $gw_dir_hits ? ': ' . implode( ', ', $gw_dir_hits ) : '' )
);
check(
	false !== strpos( $abstract_src, 'wc_get_order( $order_id )' )
		&& false !== strpos( $abstract_src, '$order->update_status(' )
		&& false !== strpos( $abstract_src, 'get_return_url( $order )' ),
	'process_payment loads via wc_get_order, moves status via update_status, redirects via get_return_url'
);

$concretes = array(
	'PayAtCounter.php'  => 'flavor_pay_at_counter',
	'CashOnDelivery.php' => 'flavor_cod',
	'CardOnDelivery.php' => 'flavor_card_on_delivery',
);
$bad_concretes = array();
foreach ( $concretes as $file => $method_id ) {
	$src = (string) file_get_contents( $root . '/flavor-core/includes/WooCommerce/Gateways/' . $file );
	if ( false === strpos( $src, 'extends AbstractOfflineGateway' )
		|| false === strpos( $src, "require_once __DIR__ . '/AbstractOfflineGateway.php'" )
		|| false === strpos( $src, "class_exists( '\\\\WC_Payment_Gateway' )" )
		|| false === strpos( $src, "'" . $method_id . "'" ) ) {
		$bad_concretes[] = $file;
	}
}
check(
	array() === $bad_concretes,
	'each concrete gateway extends the abstract, guard-loads it, and owns its unique method id' . ( $bad_concretes ? ': ' . implode( ', ', $bad_concretes ) : '' )
);

echo "\n--- 4. the order lifecycle rides the store API end to end ---\n";
$checkout = (string) file_get_contents( $root . '/flavor-core/includes/WooCommerce/CheckoutService.php' );
check(
	false !== strpos( $checkout, '$checkout->create_order(' )
		&& false !== strpos( $checkout, 'wc_get_order( $order_id )' )
		&& substr_count( $checkout, '$order->save()' ) >= 2
		&& false !== strpos( $checkout, 'wc_get_orders(' )
		&& false !== strpos( $checkout, "'_flavor_idempotency_key'" ),
	'REST checkout creates through WC_Checkout, reloads through wc_get_order, persists through $order->save(), dedupes through a wc_get_orders meta lookup'
);
$modes = (string) file_get_contents( $root . '/flavor-core/includes/Order/OrderModes.php' );
check(
	false !== strpos( $modes, "add_action( 'woocommerce_checkout_create_order', array( \$this, 'stamp_order' )" )
		&& substr_count( $modes, 'update_meta_data(' ) >= 5,
	'stamp_order rides woocommerce_checkout_create_order (WC persists inside that lifecycle) and writes only through update_meta_data'
);
$report_query = (string) file_get_contents( $root . '/flavor-core/includes/Reporting/ReportQuery.php' );
$report_hits  = hpos_files_containing(
	array( $root . '/flavor-core/includes/Reporting/ReportQuery.php' => $report_query ),
	array( 'get_posts(' )
);
check(
	false !== strpos( $report_query, "function_exists( 'wc_get_orders' )" )
		&& false !== strpos( $report_query, '$orders = wc_get_orders( $args );' )
		&& array() === $report_hits,
	'reporting reads orders through the guarded wc_get_orders store API — zero get_posts in the order path'
);
$analytics = (string) file_get_contents( $root . '/flavor-core/includes/Analytics/AnalyticsManager.php' );
check(
	false !== strpos( $analytics, "Schema::table( 'flavor_kitchen_tickets' )" )
		&& false !== strpos( $analytics, "Schema::table( 'flavor_reservations' )" )
		&& substr_count( $analytics, "'post_type'" ) === substr_count( $analytics, "'post_type' => 'flavor_branch'" ),
	"executive analytics reads the flavor kitchen/reservation tables only — its sole get_posts is the branch CPT, never an order post type"
);
$customer_ui = (string) file_get_contents( $root . '/flavor/inc/class-customer-ui.php' );
check(
	false !== strpos( $customer_ui, "function_exists( 'wc_get_orders' )" )
		&& false !== strpos( $customer_ui, '$order->get_view_order_url()' )
		&& false === strpos( $customer_ui, 'get_permalink(' ),
	'theme order history reads through wc_get_orders and links through get_view_order_url — never post permalinks'
);
$ci = (string) file_get_contents( $root . '/.github/workflows/ci.yml' );
check(
	false !== strpos( $ci, 'run-e2e-integration.php' ),
	'CI still executes run-e2e-integration.php on every push'
);

echo "\n--- 5. live green runs (the prompt's explicit ask) ---\n";
// 5a. test-price-calculator.php — a WP_UnitTestCase file that CI's PHPUnit
// lane never runs. Execute it here through a shim so "stays green" is a
// machine fact: real classes, real assertions, zero WordPress.
$pc_file = $root . '/flavor-core/tests/test-price-calculator.php';
check( is_file( $pc_file ), 'test-price-calculator.php exists' );
$pc = array(
	'methods'  => 0,
	'asserts'  => 0,
	'failures' => array(),
	'crashes'  => array(),
);
if ( is_file( $pc_file ) ) {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $root . '/' );
	}
	$GLOBALS['_pc_harness'] = &$pc;
	require_once $root . '/flavor-core/tests/pure/_stubs.php';
	if ( ! class_exists( 'WP_UnitTestCase' ) ) {
		/**
		 * Minimal shim: records every assertion instead of trusting PHPUnit.
		 */
		class WP_UnitTestCase {
			/**
			 * Record one assertion outcome.
			 *
			 * @param bool   $ok   Passed.
			 * @param string $desc Failure description.
			 */
			protected function record( bool $ok, string $desc ): void {
				$GLOBALS['_pc_harness']['asserts']++;
				if ( ! $ok ) {
					$GLOBALS['_pc_harness']['failures'][] = $desc;
				}
			}
			public function assertSame( $expected, $actual, $msg = '' ) {
				$this->record( $expected === $actual, 'assertSame ' . var_export( $expected, true ) . ' vs ' . var_export( $actual, true ) . ( $msg ? " ($msg)" : '' ) );
			}
			public function assertEquals( $expected, $actual, $msg = '' ) {
				$this->record( $expected == $actual, 'assertEquals ' . var_export( $expected, true ) . ' vs ' . var_export( $actual, true ) . ( $msg ? " ($msg)" : '' ) );
			}
			public function assertTrue( $cond, $msg = '' ) {
				$this->record( true === $cond, 'assertTrue failed' . ( $msg ? " ($msg)" : '' ) );
			}
			public function assertFalse( $cond, $msg = '' ) {
				$this->record( false === $cond, 'assertFalse failed' . ( $msg ? " ($msg)" : '' ) );
			}
			public function assertNotNull( $v, $msg = '' ) {
				$this->record( null !== $v, 'assertNotNull failed' . ( $msg ? " ($msg)" : '' ) );
			}
			public function assertCount( $n, $v, $msg = '' ) {
				$this->record( is_countable( $v ) && (int) $n === count( $v ), 'assertCount failed' . ( $msg ? " ($msg)" : '' ) );
			}
			public function setUp(): void {}
			public function tearDown(): void {}
		}
	}
	require_once $root . '/flavor-core/includes/Activator.php';
	require_once $root . '/flavor-core/includes/Support/Settings.php';
	require_once $root . '/flavor-core/includes/WooCommerce/Currency.php';
	require_once $root . '/flavor-core/includes/Support/PersianText.php';
	require_once $root . '/flavor-core/includes/Support/Iran.php';
	require_once $root . '/flavor-core/includes/WooCommerce/ProductModifiers.php';
	require_once $pc_file;
	$case         = new Flavor_Price_Calculator_Test();
	$test_methods = array_values(
		array_filter(
			get_class_methods( $case ),
			static function ( $m ) {
				return 0 === strpos( $m, 'test_' );
			}
		)
	);
	$pc['methods'] = count( $test_methods );
	foreach ( $test_methods as $m ) {
		try {
			$case->$m();
		} catch ( \Throwable $e ) {
			$pc['crashes'][] = $m . ': ' . $e->getMessage();
		}
	}
}
check( 5 === $pc['methods'], 'the price-calculator case exposes all 5 test methods (' . $pc['methods'] . ')' );
check( array() === $pc['crashes'], 'every test method runs to completion' . ( $pc['crashes'] ? ': ' . implode( '; ', $pc['crashes'] ) : '' ) );
check(
	array() === $pc['failures'],
	'all price-calculator assertions pass live (' . $pc['asserts'] . ' asserts)' . ( $pc['failures'] ? ': ' . implode( '; ', $pc['failures'] ) : '' )
);
check( $pc['asserts'] >= 11, 'assertion volume floor: ≥11 live asserts (now ' . $pc['asserts'] . ') — gutting the file trips this' );

// 5b. run-e2e-integration.php — executed live, exit code + summary parsed.
$e2e  = $root . '/flavor-core/tests/run-e2e-integration.php';
$out  = (string) shell_exec( 'php ' . escapeshellarg( $e2e ) . ' 2>&1' );
exec( 'php ' . escapeshellarg( $e2e ) . ' > /dev/null 2>&1', $discard, $rc );
check( 0 === $rc, 'run-e2e-integration.php exits 0' );
$e2e_passed = preg_match( '/SUMMARY: (\d+)\/(\d+) PASSED \((\d+) FAILED\)/', $out, $m ) ? (int) $m[1] : 0;
$e2e_failed = isset( $m[3] ) ? (int) $m[3] : -1;
check( 0 === $e2e_failed, 'the e2e summary reports zero failures (' . $e2e_failed . ')' );
check( $e2e_passed >= 81, 'e2e volume floor: ≥81 passing checks (now ' . $e2e_passed . ')' );

echo str_repeat( '=', 55 ) . "\nResults: $pass Passed, $fail Failed\n";
exit( $fail ? 1 : 0 );
