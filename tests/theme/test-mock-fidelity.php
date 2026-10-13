<?php
/**
 * P50 gate — the mock must simulate WordPress as it ACTUALLY behaves.
 *
 * Prompt 50: mocks must not simulate things real WordPress does not do, and
 * wherever a mock diverged and tests went blind-green, the divergence had to
 * be removed — with run-e2e-integration as the final reference. Every vector
 * below pins one production semantic the mock got wrong before P50; each one
 * fails loudly if the fidelity fix is reverted (sabotage-detected):
 *
 *   Section 1  wpdb::prepare grammar ported verbatim from WP 6.8: NULL
 *              collapses to '' (never a bare NULL token), too-few arguments
 *              yield an EMPTY string (never a half-built query), %f is
 *              locale-unaware, %i backticks, literal %% survives, quoted
 *              '%s' normalises, LIKE '%%%s%%' stays unquoted, values keep
 *              their own percents, mysqli's escape character set.
 *   Section 2  storage semantics: CUD returns int|false ROW COUNTS (0 is not
 *              success), get_var keeps '0'/0 and only maps '' to null,
 *              $wpdb->last_error records failures, ARRAY_N is a numeric
 *              list, $wpdb->options/postmeta resolve and those tables exist.
 *   Section 3  site clock: current_time() shifts by the SITE offset (Tehran,
 *              +3:30 — not time()), and the naive-string pairing the
 *              TokenService relies on cancels exactly.
 *   Section 4  nonces: the real tick|action|uid|token HMAC recipe, returning
 *              1 / 2 / false — not "any non-empty string".
 *   Section 5  hooks: add_filter is real (shared registry), apply_filters
 *              chains values in priority order with accepted_args slicing,
 *              and 'the_content' runs wpautop like a live site.
 *   Section 6  sanitizers: text fields strip octets and collapse whitespace,
 *              textarea keeps newlines, sanitize_title STRIPS Persian (real
 *              WP is ASCII-only), hex colors reject with NULL, esc_url_raw
 *              kills javascript:.
 *   Section 7  data layer: get_userdata/get_user_by/wc_get_order return
 *              FALSE (never null), get_user_meta single is '' when missing,
 *              get_posts returns objects unless fields=ids, term meta and
 *              the object cache are real stores, transients expire, wp_mail
 *              records and can fail, WP_Error accepts mixed data.
 *   Section 8  WooCommerce: order totals come from the cart items (not a
 *              constant), get_date_created() is a WC_DateTime rendering in
 *              the SITE timezone (the placed_at contract), wc_get_orders
 *              honours status and date_created filters.
 *   Section 9  LIVE runs: run-e2e-integration.php (the prompt's final
 *              reference) must exit 0 with 81+ passes / 0 failures, and the
 *              branch-scope guards must stay green — under the faithful mock.
 *
 * Run: php tests/theme/test-mock-fidelity.php
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
 * Run a callable with E_USER_WARNING capture (prepare's _doing_it_wrong path).
 *
 * @param callable $fn Callable to run.
 * @return array{0: mixed, 1: string[]} Result and captured warnings.
 */
function mockfid_capture( callable $fn ): array {
	$captured = array();
	set_error_handler(
		static function ( $no, $str ) use ( &$captured ) {
			$captured[] = (string) $str;
			return true;
		},
		E_USER_WARNING
	);
	try {
		$result = $fn();
	} finally {
		restore_error_handler();
	}
	return array( $result, $captured );
}

require_once $root . '/flavor-core/tests/mock-wp-environment.php';

/** @var MockWPDB $wpdb The mock's own singleton, exactly as consumers boot it. */
global $wpdb;

echo "\n--- 1. wpdb::prepare follows the real WP 6.8 grammar ---\n";

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'SELECT %s', null ) );
check( "SELECT ''" === $r, 'NULL under %s becomes an empty string, never a bare NULL token' );
check( 0 === count( $w ), 'NULL under %s is legal and warns about nothing' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'SELECT %d', null ) );
check( 'SELECT 0' === $r, 'NULL under %d becomes 0' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( "SELECT '%s'", 'a' ) );
check( "SELECT 'a'" === $r, "a quoted '%s' template normalises to a single-quoted value" );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( "LIKE '%%%s%%'", 'x' ) );
check( "LIKE '%x%'" === $r, "LIKE '%%%s%%' keeps the caller's quotes (value inserted unquoted inside them)" );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'v=%f', 1.5 ) );
check( 'v=1.500000' === $r, '%f renders through sprintf semantics (locale-unaware dot, six decimals, unquoted)' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'c=%i', 'col`x' ) );
check( 'c=`col``x`' === $r, '%i backticks the identifier and DOUBLES inner backticks, like _escape_identifier_value' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( '100%% off: %s', 'x' ) );
check( "100% off: 'x'" === $r, 'a literal %% in the template survives as one %' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'SELECT %s', '50%off' ) );
check( "SELECT '50%off'" === $r, 'a percent INSIDE a value survives literally' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( "SELECT %s", "va'lue\\\n" ) );
check( "SELECT 'va\\'lue\\\\\\n'" === $r, "values escape through mysqli's set (quote, backslash, newline)" );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'SELECT %s %s', 'a' ) );
check( '' === $r && count( $w ) > 0, 'too few arguments returns an EMPTY string and warns (never a half-built query)' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'SELECT %s', 'a', 'b' ) );
check( "SELECT 'a'" === $r && count( $w ) > 0, 'too many arguments still substitutes, but warns' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'SELECT 1' ) );
check( 'SELECT 1' === $r && count( $w ) > 0, 'prepare without a placeholder returns the query and warns' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'SELECT %2$s %1$s', 'a', 'b' ) );
check( 'SELECT b a' === $r, 'numbered placeholders map arguments positionally (unquoted, like real WP)' );

list( $r, $w ) = mockfid_capture( static fn() => $wpdb->prepare( 'SELECT %s %s', array( 'a', 'b' ) ) );
check( "SELECT 'a' 'b'" === $r, 'the legacy single-array calling style still works' );

echo "\n--- 2. storage semantics: counts, zero-vs-null, last_error ---\n";

$ins = $wpdb->insert( 'wp_options', array( 'option_name' => 'fid_zero', 'option_value' => '0' ) );
check( 1 === $ins, 'insert returns the ROW COUNT (1), not bool true' );

$zero = $wpdb->get_var( "SELECT option_value FROM wp_options WHERE option_name='fid_zero'" );
check( '0' === $zero, "get_var keeps the string '0' as a VALUE (the old ?: swallowed it to null)" );

$no_row = $wpdb->get_var( "SELECT option_value FROM wp_options WHERE option_name='fid_ghost'" );
check( null === $no_row, "get_var maps ''/no-row to null, exactly like real wpdb" );

$count0 = $wpdb->get_var( "SELECT COUNT(*) FROM wp_options WHERE option_name='fid_ghost'" );
check( null !== $count0 && 0 === (int) $count0, 'get_var(COUNT(*)) of zero rows returns 0, not null' );

$upd0 = $wpdb->update( 'wp_options', array( 'option_value' => 'x' ), array( 'option_name' => 'fid_ghost' ) );
check( 0 === $upd0, 'update touching zero rows returns 0 — NOT true (no-op writes can no longer masquerade as success)' );

$upd1 = $wpdb->update( 'wp_options', array( 'option_value' => 'x' ), array( 'option_name' => 'fid_zero' ) );
check( 1 === $upd1, 'update touching one row returns 1' );

$dup = $wpdb->insert( 'wp_options', array( 'option_name' => 'fid_zero', 'option_value' => 'boom' ) );
check( false === $dup && '' !== $wpdb->last_error, 'a failed write returns false AND records $wpdb->last_error' );

$wpdb->query( "DELETE FROM wp_options WHERE option_name='fid_ghost2'" );
check( '' === $wpdb->last_error, 'last_error is cleared again on the next successful statement' );

$row_n = $wpdb->get_row( "SELECT option_name, option_value FROM wp_options WHERE option_name='fid_zero'", ARRAY_N );
check( is_array( $row_n ) && array_is_list( $row_n ) && 'fid_zero' === $row_n[0], 'get_row(..., ARRAY_N) returns a NUMERIC list, not an assoc map' );

check( 'wp_options' === $wpdb->options && 'wp_postmeta' === $wpdb->postmeta, '$wpdb->options / $wpdb->postmeta resolve like the real table properties' );
$interpolated = $wpdb->get_results( "SELECT option_name FROM {$wpdb->options} WHERE option_name='fid_zero'" );
check( is_array( $interpolated ) && 1 === count( $interpolated ), 'the {$wpdb->options} interpolation path (SearchIndex) really queries a table' );

$del1 = $wpdb->delete( 'wp_options', array( 'option_name' => 'fid_zero' ) );
$del0 = $wpdb->delete( 'wp_options', array( 'option_name' => 'fid_zero' ) );
check( 1 === $del1 && 0 === $del0, 'delete returns 1 then 0 — the second call is observably a no-op' );

echo "\n--- 3. the site clock is Tehran, and naive pairings cancel ---\n";

$tz = wp_timezone();
check( 'Asia/Tehran' === $tz->getName(), 'the mock site runs on Asia/Tehran, like production' );
check( 12600 === current_time( 'timestamp' ) - time(), "current_time('timestamp') = time() + site offset (+3:30), not raw time()" );
check( gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) === current_time( 'mysql' ), "current_time('mysql') is the shifted epoch rendered through gmdate" );
check( abs( current_time( 'timestamp', true ) - time() ) <= 1, "current_time('timestamp', true) is the untouched epoch" );
check( current_datetime() instanceof DateTimeImmutable && 'Asia/Tehran' === current_datetime()->getTimezone()->getName(), 'current_datetime() carries the site timezone' );

$naive    = current_time( 'mysql' );
$plus_ttl = date( 'Y-m-d H:i:s', strtotime( $naive ) + 600 );
check(
	600 === (new DateTimeImmutable( $plus_ttl, $tz ))->getTimestamp() - (new DateTimeImmutable( $naive, $tz ))->getTimestamp(),
	'the TokenService pairing date(strtotime(current_time)+TTL) cancels to exactly +TTL under the site clock'
);

echo "\n--- 4. nonces follow the real recipe and return 1|2|false ---\n";

wp_set_current_user( 1 );
$n = wp_create_nonce( 'fid_action' );
check( 1 === wp_verify_nonce( $n, 'fid_action' ), 'a fresh nonce verifies as int 1 (not bool true)' );
check( false === wp_verify_nonce( $n, 'other_action' ), 'a nonce is bound to its action' );
check( false === wp_verify_nonce( 'deadbeefca', 'fid_action' ), 'a garbage nonce is false' );
check( false === wp_verify_nonce( '', 'fid_action' ), 'an empty nonce is false (the old mock accepted ANY non-empty string)' );
$prev_tick = substr( wp_hash( ( wp_nonce_tick() - 1 ) . '|fid_action|' . get_current_user_id() . '|' . wp_get_session_token(), 'nonce' ), -12, 10 );
check( 2 === wp_verify_nonce( $prev_tick, 'fid_action' ), 'a previous-tick nonce verifies as int 2' );
wp_set_current_user( 2 );
$n2 = wp_create_nonce( 'fid_action' );
wp_set_current_user( 3 );
check( false === wp_verify_nonce( $n2, 'fid_action' ), 'a nonce is bound to the user id that created it' );
wp_set_current_user( 1 );

echo "\n--- 5. hooks: real registry, priority order, chained filters ---\n";

add_filter( 'fid_chain', static fn( $v ) => $v . 'a', 10, 1 );
add_filter( 'fid_chain', static fn( $v ) => $v . 'b', 5, 1 );
check( 'xba' === apply_filters( 'fid_chain', 'x' ), 'apply_filters chains registered listeners in priority order (5 before 10)' );

add_filter( 'fid_args', static fn( $v, $extra ) => $v . $extra, 10, 2 );
check( 'x-y' === apply_filters( 'fid_args', 'x', '-y' ), 'filter listeners receive extra args per accepted_args' );

$GLOBALS['fid_action_log'] = array();
add_action( 'fid_act', static function () { $GLOBALS['fid_action_log'][] = 'second'; }, 20 );
add_action( 'fid_act', static function () { $GLOBALS['fid_action_log'][] = 'first'; }, 10 );
do_action( 'fid_act' );
check( array( 'first', 'second' ) === $GLOBALS['fid_action_log'], 'do_action runs callbacks in priority order, not registration order' );

check( true === has_filter( 'the_content' ), "'the_content' has a registered filter (wpautop), like a live site" );
check( "<p>hi</p>\n" === apply_filters( 'the_content', 'hi' ), "apply_filters('the_content', ...) wraps plain text in <p> — REST descriptions are HTML in production" );

echo "\n--- 6. sanitizers behave like core, not like conveniences ---\n";

check( 'A B C' === sanitize_text_field( "A\r\nB\t C" ), 'sanitize_text_field collapses whitespace runs to single spaces' );
check( 'ab' === sanitize_text_field( 'a%41b' ), 'sanitize_text_field removes percent-encoded octets' );
check( 'hi' === sanitize_text_field( '<script>alert(1)</script>hi' ), 'sanitize_text_field strips script blocks entirely' );
check( "keep\nlines" === sanitize_textarea_field( "keep\nlines" ), 'sanitize_textarea_field KEEPS line breaks (the old body collapsed them like text_field)' );
check( '' === sanitize_title( 'شاندیز' ), 'sanitize_title of pure Persian is EMPTY — real WP strips non-ASCII (the old mock kept it, hiding a live-site breakage)' );
check( 'hello-world' === sanitize_title( 'Hello World!' ), 'sanitize_title ascii path unchanged' );
check( null === sanitize_hex_color( 'red' ), 'sanitize_hex_color rejects a named colour with NULL (Customizer keeps the stored value)' );
check( '#0a0' === sanitize_hex_color( '#0a0' ), 'a valid 3-digit hex passes through verbatim' );
check( '' === sanitize_hex_color( '' ), 'an empty colour stays empty' );
check( '' === esc_url_raw( 'javascript:alert(1)' ), 'esc_url_raw rejects a javascript: URL outright' );
check( array( 'b' => 2, 'a' => '1' ) === wp_parse_args( 'a=1', array( 'b' => 2 ) ), 'wp_parse_args parses query strings like core' );
check( array( "a'b" ) === wp_unslash( array( "a\\'b" ) ), 'wp_unslash recurses into arrays' );

echo "\n--- 7. data layer: false-not-null, ''-not-null, real stores ---\n";

check( false === get_userdata( 999999 ), 'get_userdata of a missing user is FALSE, never null' );
check( false === get_user_by( 'login', 'ghost' ), 'get_user_by of a missing user is FALSE, never null' );
check( '' === get_user_meta( 1, 'fid_ghost_meta', true ), 'get_user_meta single for a missing key is an EMPTY STRING, never null' );

$default_posts = get_posts( array() );
check( is_array( $default_posts ) && is_object( $default_posts[0] ) && 1 === $default_posts[0]->ID, 'get_posts returns post OBJECTS by default (the old ints fataled $b->ID on a live site)' );
$id_posts = get_posts( array( 'fields' => 'ids' ) );
check( array( 1, 2 ) === $id_posts, "get_posts with fields=ids returns the bare int IDs (BranchSeeder/uninstall path)" );

$GLOBALS['_mock_post_type'][55] = 'page';
check( 'page' === get_post_type( 55 ), 'get_post_type honours the seam so guards can observe non-branch posts' );
unset( $GLOBALS['_mock_post_type'][55] );

check( get_option( 'blogname' ) === get_bloginfo( 'name' ), "get_bloginfo('name') answers from the blogname option" );
check( get_option( 'blogdescription' ) === get_bloginfo( 'description' ), "get_bloginfo('description') answers per key (the old constant ignored the key)" );
check( get_bloginfo( 'name' ) !== get_bloginfo( 'description' ), 'name and description are distinct values' );

update_term_meta( 7, '_fid', 'v' );
check( 'v' === get_term_meta( 7, '_fid', true ), 'term meta is a real store (write then read)' );
check( '' === get_term_meta( 8, '_fid', true ), 'missing single term meta is an empty string' );
check( array( 'v' ) === get_term_meta( 7, '_fid' ), 'multi term meta returns the array form' );

wp_cache_set( 'fid_k', 'fid_v', 'fid_g' );
check( 'fid_v' === wp_cache_get( 'fid_k', 'fid_g' ), 'wp_cache_get returns what wp_cache_set stored (the old stub always missed)' );
wp_cache_delete( 'fid_k', 'fid_g' );
check( false === wp_cache_get( 'fid_k', 'fid_g' ), 'wp_cache_delete removes the entry' );

set_transient( 'fid_t', 'v', 100 );
$GLOBALS['_mock_transients']['fid_t']['expires'] = time() - 1;
check( false === get_transient( 'fid_t' ), 'transients EXPIRE (the old store kept RateLimit buckets alive forever)' );

$mail_before = count( $GLOBALS['_mock_sent_mail'] );
$GLOBALS['_mock_mail_fail'] = true;
$mail_fail = wp_mail( 'a@b.c', 's', 'body', '', array( 'f.csv' ) );
unset( $GLOBALS['_mock_mail_fail'] );
$mail_ok = wp_mail( 'a@b.c', 's2', 'body2' );
check( false === $mail_fail && true === $mail_ok, 'wp_mail can FAIL when the seam says so (ScheduledReport::deliver branches on it)' );
$last_mail = $GLOBALS['_mock_sent_mail'][ count( $GLOBALS['_mock_sent_mail'] ) - 2 ];
check( $mail_before + 2 === count( $GLOBALS['_mock_sent_mail'] ) && 's' === $last_mail['subject'] && array( 'f.csv' ) === $last_mail['attachments'], 'wp_mail records to/subject/body/headers/attachments with the real 5-param signature' );

$err = new WP_Error( 'fid_code', 'msg', 'plain-string-data' );
check( 'plain-string-data' === $err->get_error_data(), 'WP_Error accepts mixed data like core (the typed ctor fataled on shapes production allows)' );

$rest = new WP_REST_Request( 'GET', '/fid' );
check( null === $rest->get_header( 'X-Fid' ), 'a missing REST header is NULL, not an empty string' );

check( '2 KB' === size_format( 1536 ) && '500 B' === size_format( 500 ) && '1 GB' === size_format( 1073741824 ), 'size_format defaults to 0 decimals and walks the real tier list' );
check( 'https://x.test/p?b=2&a=1' === add_query_arg( array( 'a' => '1' ), 'https://x.test/p?b=2' ), 'add_query_arg merges into the given URL (the old constant ignored every argument)' );

echo "\n--- 8. WooCommerce objects: computed totals, site-tz dates, filters ---\n";

check( false === wc_get_order( 424242 ), 'wc_get_order of a missing order is FALSE, never null' );

if ( ! WC()->cart instanceof MockWCCart ) {
	WC()->cart = new MockWCCart();
}
$cart        = WC()->cart;
$cart->items = array( array( 'quantity' => 1, 'line_total' => 280000 ) );
$cart->fees  = array( (object) array( 'amount' => 25000 ) );
check( 280000.0 === $cart->get_subtotal() && 305000.0 === $cart->get_total(), 'cart totals are computed from the real items and fees (the old constants ignored every product price)' );

$order = new WC_Order( 999001 );
check( 305000.0 === $order->get_total() && 280000.0 === $order->get_subtotal(), 'order totals come from the populated cart items, not a hardcoded 350000' );
check( $order->get_date_created() instanceof WC_DateTime, 'get_date_created() is a WC_DateTime (extends DateTime, has ->date())' );
$placed  = $order->get_date_created()->date( 'Y-m-d H:i:s' );
$drift   = abs( strtotime( $placed ) - strtotime( current_time( 'mysql' ) ) );
check( $drift <= 5, "the order date renders in the SITE timezone — placed_at and current_time('mysql') agree (KitchenTicketSync contract)" );

$order->status = 'completed';
$GLOBALS['_mock_wc_orders'][999001] = $order;
$GLOBALS['_mock_wc_orders'][999002] = new WC_Order( 999002 );
$completed = wc_get_orders( array( 'status' => 'wc-completed' ) );
check( 1 === count( $completed ) && 999001 === $completed[0]->get_id(), "wc_get_orders honours the status filter with the wc- prefix stripped (the old double ignored it)" );
$today = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
$in_window  = wc_get_orders( array( 'date_created' => $today . '...' . $today ) );
$out_window = wc_get_orders( array( 'date_created' => '2020-01-01...2020-01-31' ) );
check( 2 === count( $in_window ) && 0 === count( $out_window ), 'wc_get_orders honours date_created from...to windows (report queries depend on it)' );
unset( $GLOBALS['_mock_wc_orders'][999001], $GLOBALS['_mock_wc_orders'][999002] );
$cart->empty_cart();

echo "\n--- 9. HTTP transport failures reach callers as WP_Error ---\n";

$GLOBALS['_mock_http_responder'] = static fn() => new WP_Error( 'http_request_failed', 'boom' );
$remote = wp_remote_post( 'https://example.test/hook', array() );
check( $remote instanceof WP_Error && is_wp_error( $remote ), 'wp_remote_post can return WP_Error so is_wp_error() branches are reachable (WebhookManager)' );
$GLOBALS['_mock_http_responder'] = null;

echo "\n--- 10. LIVE: the prompt's final reference, under the faithful mock ---\n";

$e2e_out = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/flavor-core/tests/run-e2e-integration.php' ) . ' 2>&1' );
$e2e_ok  = preg_match( '/INTEGRATION SUITE SUMMARY:\s*(\d+)\/(\d+)\s*PASSED\s*\((\d+)\s*FAILED\)/', $e2e_out, $m );
check( $e2e_ok && (int) $m[3] === 0 && (int) $m[1] >= 81, 'run-e2e-integration passes 81+/81 with 0 failures under the faithful mock' );

$guards_out = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/flavor-core/tests/run-branch-scope-guards.php' ) . ' 2>&1' );
$guards_ok  = preg_match( '/Results:\s*(\d+)\s*Passed,\s*(\d+)\s*Failed/', $guards_out, $gm );
check( $guards_ok && (int) $gm[2] === 0 && (int) $gm[1] >= 23, 'branch-scope guards stay 23+/0 under the faithful mock' );

echo "\n=======================================================\n";
echo "Results: $pass Passed, $fail Failed\n";
echo "=======================================================\n";

exit( $fail ? 1 : 0 );
