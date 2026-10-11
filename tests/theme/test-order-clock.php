<?php
/**
 * The customer-facing clock must follow the *site* timezone (prompt 23).
 *
 * The tracking screen used to build every kitchen stage time with
 * `date( 'H:i', strtotime( $ticket['accepted_at'] ) )`. That pair is a naive
 * round-trip: a value with no timezone in it comes out with the same digits it
 * went in with, which is why the bug was invisible on a server set to Tehran.
 * It breaks the moment the value *does* carry an offset — an ISO-8601 timestamp
 * from the mobile app or a kiosk — and then the number is rendered in the
 * server's zone, so a shop on Asia/Tehran hosted on a UTC box shows "17:00"
 * for an order the customer placed at 20:30, next to an order time that WooCommerce
 * rendered correctly. Two frames in one receipt: the kitchen looks like it
 * accepted the order three and a half hours before it was placed.
 *
 * FlavorCore\Support\Clock is the fix, and this file pins both halves of it: the
 * naive values must not change (that is the regression to avoid), and the
 * offset-carrying ones must land in the site zone (that is the bug being fixed).
 *
 * Run: php tests/theme/test-order-clock.php
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

// The theme's own tests resolve the plugin by path rather than by a constant:
// FLAVOR_CORE_DIR is an *environment* override here, not a defined constant.
$core = dirname( __DIR__, 2 ) . '/flavor-core';
if ( ! defined( 'FLAVOR_CORE_PATH' ) ) {
	define( 'FLAVOR_CORE_PATH', $core . '/' );
}
require_once $core . '/includes/Support/Clock.php';
require_once $core . '/includes/Support/Jalali.php';
require_once $core . '/includes/Support/Settings.php';
require_once $core . '/includes/Autoloader.php';
// Jalali reads the shop's digit setting through Settings, which falls back to
// Activator's defaults — let the plugin's own autoloader supply that chain
// instead of requiring half the includes/ tree from a test.
\FlavorCore\Autoloader::register();

$passed = 0;
$failed = 0;

function check( bool $condition, string $label ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$label}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$label}\n";
	}
}

function same( string $got, string $want, string $label ): void {
	check( $got === $want, $label . ( $got === $want ? '' : " (got '{$got}', want '{$want}')" ) );
}

$server_tz       = date_default_timezone_get();
$site_option_key = 'timezone_string';
$stored_option   = get_option( $site_option_key, '+00:00' );

/**
 * Put the two clocks out of step: a shop in Tehran on a box that thinks it is
 * somewhere else. This is the whole point of the test.
 */
function put_them_out_of_step( string $site, string $server ): void {
	update_option( 'timezone_string', $site );
	date_default_timezone_set( $server );
}

echo "\n--- 1. A stored site-local time is never re-shaped ---\n";

put_them_out_of_step( 'Asia/Tehran', 'UTC' );
// KitchenTicketRepository writes with current_time( 'mysql' ), i.e. site-local
// wall clock, and the DATETIME column keeps no offset. Formatting it must give
// back the digits the kitchen staff typed into their own screen.
foreach ( array( 'UTC', 'America/Los_Angeles', 'Australia/Sydney', 'Asia/Tehran' ) as $server ) {
	date_default_timezone_set( $server );
	same(
		\FlavorCore\Support\Clock::format( '2026-10-11 20:30:00' ),
		'20:30',
		"stored 20:30 stays 20:30 whatever the server clock says ({$server})"
	);
	same(
		\FlavorCore\Support\Clock::format( '2026-10-11 20:30:00', 'Y-m-d H:i' ),
		'2026-10-11 20:30',
		"the date part does not slide a day across the ocean ({$server})"
	);
	same( \FlavorCore\Support\Clock::format( '2026-10-11' ), '00:00', "a date-only value reads as midnight, not as 'now' ({$server})" );
}

echo "\n--- 2. A value that names its own offset lands in site time ---\n";

put_them_out_of_step( 'Asia/Tehran', 'UTC' );
$iso = '2026-10-11T17:00:00+00:00'; // the mobile app sends instants, not wall clock
same( \FlavorCore\Support\Clock::format( $iso ), '20:30', '17:00Z is rendered as 20:30 for a Tehran shop' );

$old_way = date( 'H:i', strtotime( $iso ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions
check(
	'17:00' === $old_way,
	'the old pair really did print the server zone here — this is the bug, not a theory (old: ' . $old_way . ')'
);
check(
	\FlavorCore\Support\Clock::format( $iso ) !== $old_way,
	'the fix and the old code disagree on exactly the input that was broken'
);
same( \FlavorCore\Support\Clock::format( '2026-10-11T20:30:00+03:30' ), '20:30', 'an offset that already matches the shop is left alone' );

echo "\n--- 3. One frame for the whole receipt ---\n";

put_them_out_of_step( 'Asia/Tehran', 'UTC' );
$stamp = \FlavorCore\Support\Clock::timestamp( '2026-10-11 20:30:00' );
check( is_int( $stamp ), 'timestamp() returns an int for a stored value' );
// The order time comes from WC_DateTime, which carries the store timezone; the
// stage times now go through the same frame, so the two can be compared by hand
// and by the customer.
same( wp_date( 'H:i', (int) $stamp ), \FlavorCore\Support\Clock::format( '2026-10-11 20:30:00' ), 'Clock agrees with wp_date() for the same instant' );
same(
	\FlavorCore\Support\Jalali::format_datetime( '2026-10-11 20:30:00' ),
	\FlavorCore\Support\Jalali::format_datetime( '2026-10-11 20:30:00' ),
	'and the Jalali receipt line is stable (it slices the stored wall clock, so no zone maths is involved)'
);
// format_datetime() renders the shop's digits, so compare against the same
// Persian digits the tracking screen produces rather than against Latin text.
$jalali   = \FlavorCore\Support\Jalali::format_datetime( '2026-10-11 20:30:00' );
$persian  = preg_replace_callback(
	'/\d/',
	static function ( array $m ): string {
		return mb_chr( 0x6f0 + (int) $m[0], 'UTF-8' );
	},
	'20:30'
);
check(
	str_contains( $jalali, (string) $persian ),
	'the Jalali line carries the same minutes the tracking screen shows (' . $jalali . ')'
);

echo "\n--- 4. A shop that did not pick a zone behaves as before ---\n";

put_them_out_of_step( '+00:00', 'America/Los_Angeles' );
same( \FlavorCore\Support\Clock::format( '2026-10-11 20:30:00' ), '20:30', 'a UTC shop on a Pacific box keeps its stored times' );
same( \FlavorCore\Support\Clock::format( $iso ), '17:00', 'and reads an instant as its own zone' );

echo "\n--- 5. Missing and unreadable never become a wrong time ---\n";

put_them_out_of_step( 'Asia/Tehran', 'UTC' );
foreach ( array( '', '   ', 'not a time', '0000-00-00 00:00:00 QA' ) as $junk ) {
	$got = \FlavorCore\Support\Clock::format( $junk );
	check(
		'' === $got || (bool) preg_match( '/^\d{2}:\d{2}$/', $got ),
		"garbage input ({$junk}) yields either nothing or a real clock time, never a notice"
	);
}
same( \FlavorCore\Support\Clock::format( '' ), '', 'an empty stage time stays empty, so the row is hidden instead of showing 00:00' );

echo "\n--- 6. The wiring: autoloadable, and used where the customer looks ---\n";

$before = class_exists( 'FlavorCore\\Support\\Clock', false );
\FlavorCore\Autoloader::load( 'FlavorCore\\Support\\Clock' );
check( $before || class_exists( 'FlavorCore\\Support\\Clock', false ), 'the core autoloader resolves FlavorCore\Support\Clock from its path' );

$controller = (string) file_get_contents( $core . '/includes/API/OrderController.php' );
check(
	(bool) preg_match( '/use FlavorCore\\\\Support\\\\Clock;/', $controller ),
	'OrderController imports the clock'
);
foreach ( array( 'accepted_at', 'ready_at', 'completed_at' ) as $field ) {
	check(
		(bool) preg_match( "/Clock::format\(\s*\(string\)\s*\(?\s*\\\$ticket\['{$field}'/", $controller ),
		"the track response formats {$field} through Clock"
	);
}
check(
	! preg_match( "/date\(\s*'H:i',\s*strtotime\(/", $controller ),
	'no date()/strtotime() pair is left in the customer-facing controller'
);

echo "\n--- 7. Nothing else on the display path went unconverted ---\n";

// Files whose *customer-visible* strings carry a time. Storage and token maths
// are intentionally excluded: they compare naive strings against naive strings
// and the offsets cancel, so moving them would change behaviour for no benefit.
$display_files = array(
	'includes/API/OrderController.php',
	'includes/Receipt/ReceiptRenderer.php',
	'includes/SMS/Templates.php',
	'includes/SMS/SmsManager.php',
);
$leftovers = array();
foreach ( $display_files as $relative ) {
	$path = $core . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		continue;
	}
	$source = (string) file_get_contents( $path );
	$found  = array();
	/*
	 * Only the *global* date() is a problem here. A method call such as
	 * $order->get_date_created()->date( 'H:i' ) is WooCommerce's own WC_DateTime,
	 * which already carries the store timezone, and gmdate() writes a UTC stamp
	 * into a payload rather than reading a wall clock.
	 */
	if ( preg_match( '/(?<!->)(?<!::)(?<!m)\bdate\(\s*.[Hh]:i/', $source ) ) {
		$found[] = "raw date( 'H:i' …)";
	}
	if ( preg_match( '/strtotime\(/', $source ) && preg_match( '/(?<!->)(?<!::)(?<!m)\bdate\(\s*.[YMDHh]:/', $source ) ) {
		$found[] = 'strtotime() next to a formatter';
	}
	if ( $found ) {
		$leftovers[] = $relative . ' (' . implode( ', ', $found ) . ')';
	}
}
check(
	! $leftovers,
	'no raw date( \'H:i\' …) is left in the receipt/SMS/tracking renderers' . ( $leftovers ? ': ' . implode( ', ', $leftovers ) : '' )
);

// Restore, so a later test file in the same process is not shaped by this one.
date_default_timezone_set( $server_tz );
update_option( $site_option_key, $stored_option );

echo "\n=======================================================\n";
printf( "Results: %d Passed, %d Failed\n", $passed, $failed );
echo "=======================================================\n";
exit( $failed > 0 ? 1 : 0 );
