<?php
/**
 * Reporting tests (N-11).
 *
 * The acceptance criteria were: branch and date filters, an explicit
 * timezone, and no disclosure of OTP or other sensitive data. The third is
 * the one that matters, so it is tested from several directions — a
 * reporting feature that leaks a login code is worse than no reporting
 * feature.
 *
 *   php flavor-core/tests/test-reporting.php
 *
 * @package FlavorCore
 */

// The reporting classes bail via defined( 'ABSPATH' ) || exit;, and the
// mock does not define it — so it has to be set before they are loaded.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

require_once __DIR__ . '/mock-wp-environment.php';
require_once __DIR__ . '/../includes/Reporting/ReportQuery.php';
require_once __DIR__ . '/../includes/Reporting/CsvWriter.php';
require_once __DIR__ . '/../includes/Reporting/ReportAdmin.php';

use FlavorCore\Reporting\CsvWriter;
use FlavorCore\Reporting\ReportQuery;

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

echo "=== Flavor Reporting Tests ===\n\n";

echo "--- 1. Request normalisation rejects nonsense rather than trusting it ---\n";

$ok = ReportQuery::normalise( array( 'type' => 'orders', 'from' => '2026-01-01', 'to' => '2026-01-31', 'branch' => 2 ) );
check( $ok['ok'], 'a well-formed request is accepted' );
check( 'orders' === $ok['type'], 'the type survives' );
check( 2 === $ok['branch'], 'the branch filter survives' );

$bad_type = ReportQuery::normalise( array( 'type' => 'drop_table' ) );
check( ! $bad_type['ok'], 'an unknown report type is rejected' );
check( 'orders' === $bad_type['type'], 'an unknown type falls back to orders, not to the input' );

$bad_date = ReportQuery::normalise( array( 'type' => 'orders', 'from' => 'yesterday' ) );
check( ! $bad_date['ok'], 'a non-date is rejected' );
check( '' === $bad_date['from'], 'a bad date is dropped rather than passed to SQL' );

$impossible = ReportQuery::normalise( array( 'type' => 'orders', 'from' => '2026-13-45' ) );
check( ! $impossible['ok'], 'an impossible calendar date is rejected' );

// A backwards range is a typo, not an empty result.
$swapped = ReportQuery::normalise( array( 'type' => 'orders', 'from' => '2026-05-01', 'to' => '2026-01-01' ) );
check( '2026-01-01' === $swapped['from'] && '2026-05-01' === $swapped['to'], 'a backwards range is swapped, not silently emptied' );

echo "\n--- 2. Date validation ---\n";

check( ReportQuery::is_date( '2026-02-28' ), 'a valid date is accepted' );
check( ! ReportQuery::is_date( '2026-02-30' ), 'February 30th is rejected' );
check( ! ReportQuery::is_date( '2026-1-1' ), 'a sloppy format is rejected' );
check( ! ReportQuery::is_date( '2026-01-01; DROP TABLE' ), 'an injection attempt is rejected' );

echo "\n--- 3. Phone masking ---\n";

check( '*******1234' === ReportQuery::mask_phone( '09121231234' ), 'an 11-digit mobile keeps only its last four digits' );
check( '****' === ReportQuery::mask_phone( '1234' ), 'a four-digit value is fully masked' );
check( '' === ReportQuery::mask_phone( '' ), 'an empty value stays empty' );
check( false === strpos( ReportQuery::mask_phone( '09121231234' ), '0912' ), 'the prefix is not recoverable' );

echo "\n--- 4. The OTP table is never touched ---\n";

// The strongest version of this check is over the source, not the output:
// if the OTP table cannot be reached at all, no future edit can leak it by
// accident.
$reporting_dir = dirname( __DIR__ ) . '/includes/Reporting';
$offenders     = array();

foreach ( glob( $reporting_dir . '/*.php' ) as $file ) {
	$source = (string) file_get_contents( $file );

	// Strip comments: a docblock explaining why OTP is excluded is exactly
	// the prose that should exist, and a blunt grep would forbid it. What
	// must not exist is code that can reach the table.
	$code = preg_replace( '#/\\*.*?\\*/#s', '', $source );
	$code = preg_replace( '#(^|[;\\s{])//[^\n]*#m', '$1', (string) $code );
	$code = preg_replace( '#^\\s*\\*[^\n]*#m', '', (string) $code );

	if ( preg_match( '/flavor_otp|[\[\'"\s]otp\b|->\s*otp/i', (string) $code ) ) {
		$offenders[] = basename( $file );
	}
}

check(
	empty( $offenders ),
	'no reporting file references OTP at all'
	. ( empty( $offenders ) ? '' : ' — ' . implode( ', ', $offenders ) )
);

$tables = ReportQuery::tables();
check( isset( $tables['reservations'], $tables['loyalty'] ), 'the report tables are exposed' );

echo "\n--- 5. Headers match the data shape ---\n";

foreach ( ReportQuery::TYPES as $type ) {
	$headers = ReportQuery::headers( $type );
	check( ! empty( $headers ), "{$type} has headers (" . count( $headers ) . ')' );
	check( count( $headers ) === count( array_unique( $headers ) ), "{$type} headers contain no duplicates" );
}

$reservation_headers = ReportQuery::headers( 'reservations' );
check( in_array( 'customer_mobile', $reservation_headers, true ), 'reservations expose a mobile column, which is masked by capability' );
check( ! in_array( 'guest_token', $reservation_headers, true ), 'the guest token is not exported' );
check( ! in_array( 'special_requests', $reservation_headers, true ), 'free-text special requests are not exported' );

$loyalty_headers = ReportQuery::headers( 'loyalty' );
check( ! in_array( 'note', $loyalty_headers, true ), 'free-text loyalty notes are not exported' );

echo "\n--- 6. CSV output is correct and safe ---\n";

$csv = CsvWriter::render( array( 'name', 'total' ), array( array( 'رضا', '120000' ), array( 'سارا', '85000' ) ) );

check( 0 === strpos( $csv, CsvWriter::bom() ), 'the file starts with a UTF-8 BOM so Excel reads Persian' );
check( false !== strpos( $csv, 'رضا' ), 'Persian text survives' );
check( substr_count( $csv, "\r\n" ) === 3, 'there is one CRLF per row (3 rows)' );

// Quoting.
check( false !== strpos( CsvWriter::quote( 'a,b' ), '"a,b"' ), 'a comma is quoted' );
check( false !== strpos( CsvWriter::quote( 'say "hi"' ), '""hi""' ), 'a quote is doubled' );

echo "\n--- 7. Formula injection is neutralised ---\n";

// Customer and menu names are user-supplied, so a row can arrive
// containing a formula. Excel and Sheets execute those on open.
foreach ( array( '=1+1', '+1', '-1', '@SUM', "\t=1", "\r=1" ) as $payload ) {
	$quoted = CsvWriter::quote( $payload );
	check( 0 === strpos( $quoted, '"\'' ), 'the payload ' . json_encode( $payload ) . ' is prefixed so it stays text' );
}

check( false === strpos( CsvWriter::quote( '=HYPERLINK("http://evil","x")' ), '=HYPERLINK' ) || 0 === strpos( CsvWriter::quote( '=HYPERLINK("x","y")' ), '"\'' ), 'a HYPERLINK payload cannot execute' );

// Control characters are stripped, since they are invisible in a
// spreadsheet and can hide content from a reviewer.
check( false === strpos( CsvWriter::quote( "abc\x00def" ), "\x00" ), 'a null byte is stripped' );
check( false === strpos( CsvWriter::quote( "abc\x07def" ), "\x07" ), 'a bell character is stripped' );

// An ordinary value must not be mangled by the defence.
check( '"120000"' === CsvWriter::quote( '120000' ), 'an ordinary number is untouched' );
check( '"رضا"' === CsvWriter::quote( 'رضا' ), 'ordinary Persian is untouched' );

echo "\n--- 8. Filenames carry the range and the timezone ---\n";

$name = CsvWriter::filename( 'orders', '2026-01-01', '2026-01-31', 'Asia/Tehran' );
check( false !== strpos( $name, 'orders' ), 'the type is in the filename' );
check( false !== strpos( $name, '2026-01-01' ), 'the start date is in the filename' );
check( false !== strpos( $name, 'asia-tehran' ), 'the timezone is in the filename, so two readers agree on the day' );
check( substr( $name, -4 ) === '.csv', 'the extension is csv' );
check( 1 === preg_match( '/^[\w.\-]+$/', $name ), 'the filename has no path characters or spaces' );

// A timezone with spaces or slashes must not break the download header.
check( 1 === preg_match( '/^[\w.\-]+$/', CsvWriter::filename( 'orders', '', '', 'Asia/Tehran' ) ), 'an open-ended range still yields a safe filename' );

echo "\n--- 9. The PII capability is separate from seeing reports ---\n";

check( 'flavor_export_pii' === ReportQuery::PII_CAP, 'the PII capability is named as documented' );

$roles_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Support/Roles.php' );
check( false !== strpos( $roles_source, 'flavor_export_pii' ), 'the capability is registered' );
check(
	false !== strpos( $roles_source, "array_diff( self::caps(), array( 'flavor_export_pii' ) )" ),
	'the blanket owner grant explicitly excludes PII export'
);

// A branch manager must be able to run reports without holding the PII cap.
check( false === strpos( $roles_source, "'flavor_view_reports'         => true,\n\t\t\t\t\t'flavor_export_pii'" ), 'viewing reports does not imply exporting PII' );

echo "\n--- 10. A row cap exists ---\n";

check( ReportQuery::MAX_ROWS > 1000, 'the cap is large enough for a real report (' . ReportQuery::MAX_ROWS . ')' );
check( ReportQuery::MAX_ROWS <= 100000, 'the cap is small enough that one export cannot take the site down' );

$result = ReportQuery::run( 'orders', '', '', 0 );
check( isset( $result['rows'], $result['count'], $result['truncated'] ), 'run() returns rows, count and a truncation flag' );
check( is_array( $result['rows'] ), 'rows is an array' );
check( $result['count'] <= ReportQuery::MAX_ROWS, 'the count never exceeds the cap' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
