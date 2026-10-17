<?php
/**
 * Pure suite — Jalali calendar edges and site-timezone (+3:30) policy.
 *
 * Prompt 48: leap years (1403, 1408 — and the 1407 regression this cycle
 * fixed), 29/30-day month boundaries, the 24:00 edge, and an audit pin for
 * every mktime/strtotime site that touches Jalali or site-local time.
 *
 * The suite runs with no WordPress: Jalali::today_gregorian() falls back to
 * gmdate() here by design, so "today" assertions check shape, not a pinned
 * date. The +3:30 behaviour itself is pinned at the source (current_time
 * branch) and via the pairing policy assertions in the last section.
 *
 * Loaded by run-pure-tests.php (assert_test protocol).
 *
 * @package FlavorCore
 */

// phpcs:disable WordPress.Security.EscapeOutput -- CLI harness output, not an HTTP response.

require_once __DIR__ . '/../../includes/Activator.php';
require_once __DIR__ . '/../../includes/Support/Settings.php';
require_once __DIR__ . '/../../includes/WooCommerce/Currency.php';
require_once __DIR__ . '/../../includes/Support/Jalali.php';
require_once __DIR__ . '/../../includes/Support/Clock.php';
require_once __DIR__ . '/../../includes/Reservation/SlotCalculator.php';

use FlavorCore\Reservation\SlotCalculator;
use FlavorCore\Support\Clock;
use FlavorCore\Support\Jalali;

echo "\n--- P48. Jalali leap years (the official set + the 1407 regression) ---\n";

assert_test( Jalali::is_leap( 1399 ) && Jalali::is_leap( 1403 ) && Jalali::is_leap( 1408 ), '1399, 1403 and 1408 are leap — Esfand keeps its 30th day' );
assert_test( ! Jalali::is_leap( 1400 ) && ! Jalali::is_leap( 1404 ), '1400 and 1404 are common years' );
assert_test( ! Jalali::is_leap( 1407 ), 'REGRESSION (prompt 48): 1407 is NOT leap — the old march-parity heuristic called it leap and month_length() then invented a 30 Esfand 1407 the conversions do not have' );
assert_test(
	! Jalali::is_leap( 1411 ) && ! Jalali::is_leap( 1415 ) && ! Jalali::is_leap( 1419 ) && ! Jalali::is_leap( 1423 ) && ! Jalali::is_leap( 1427 ),
	'the other five phantom-leap years of the old heuristic (1411/1415/1419/1423/1427) are common too'
);
assert_test( ! Jalali::is_leap( 1406 ) && ! Jalali::is_leap( 1405 ), 'no back-to-back leaps around 1403→1408: 1404–1407 are four consecutive common years (the 5-year gap is real Jalali behaviour)' );

echo "\n--- P48. Month lengths: the 31/30/29-30 structure ---\n";

$lens = array();
for ( $month = 1; $month <= 6; $month++ ) {
	$lens[] = Jalali::month_length( 1404, $month );
}
assert_test( array( 31, 31, 31, 31, 31, 31 ) === $lens, 'months 1–6 are 31 days' );
$lens = array();
for ( $month = 7; $month <= 11; $month++ ) {
	$lens[] = Jalali::month_length( 1404, $month );
}
assert_test( array( 30, 30, 30, 30, 30 ) === $lens, 'months 7–11 are 30 days (the 29/30 boundary of the prompt)' );
assert_test( 30 === Jalali::month_length( 1403, 12 ) && 30 === Jalali::month_length( 1408, 12 ), 'Esfand is 30 days in leap years (1403, 1408)' );
assert_test( 29 === Jalali::month_length( 1404, 12 ) && 29 === Jalali::month_length( 1407, 12 ), 'Esfand is 29 days in common years — including 1407 (regression)' );

echo "\n--- P48. is_leap agrees with the conversion arithmetic (1380–1429) ---\n";

$mismatch = array();
for ( $y = 1380; $y <= 1429; $y++ ) {
	list( $gy1, $gm1, $gd1 ) = Jalali::to_gregorian( $y, 1, 1 );
	list( $gy2, $gm2, $gd2 ) = Jalali::to_gregorian( $y + 1, 1, 1 );
	$days = ( strtotime( "{$gy2}-{$gm2}-{$gd2}" ) - strtotime( "{$gy1}-{$gm1}-{$gd1}" ) ) / 86400;
	if ( Jalali::is_leap( $y ) !== ( 366 === $days ) ) {
		$mismatch[] = $y;
	}
}
assert_test( array() === $mismatch, 'every year 1380–1429: is_leap() ⇔ Nowruz-to-Nowruz spans 366 days' . ( $mismatch ? ' (mismatch: ' . implode( ',', $mismatch ) . ')' : ' — month_length can never contradict from/to_gregorian again' ) );

echo "\n--- P48. Round-trip identity sweep (every day 2024-01-01 … 2027-12-31) ---\n";

$broken = array();
$ts     = strtotime( '2024-01-01' );
$end    = strtotime( '2027-12-31' );
for ( ; $ts <= $end; $ts += 86400 ) {
	$ymd = gmdate( 'Y-m-d', $ts );
	list( $gy, $gm, $gd ) = explode( '-', $ymd );
	$j   = Jalali::from_gregorian( (int) $gy, (int) $gm, (int) $gd );
	$g2  = Jalali::to_gregorian( $j[0], $j[1], $j[2] );
	if ( sprintf( '%04d-%02d-%02d', $g2[0], $g2[1], $g2[2] ) !== $ymd ) {
		$broken[] = $ymd;
	}
}
assert_test( array() === $broken, 'from_gregorian ∘ to_gregorian is the identity for all 1461 days (a full Gregorian leap cycle)' . ( $broken ? ' (broken: ' . implode( ',', array_slice( $broken, 0, 4 ) ) . '…)' : '' ) );

echo "\n--- P48. Anchor dates (official calendar checkpoints) ---\n";

assert_test( array( 2024, 3, 20 ) === Jalali::to_gregorian( 1403, 1, 1 ), 'Nowruz 1403 = 2024-03-20' );
assert_test( array( 2025, 3, 21 ) === Jalali::to_gregorian( 1404, 1, 1 ), 'Nowruz 1404 = 2025-03-21' );
assert_test( array( 2026, 3, 21 ) === Jalali::to_gregorian( 1405, 1, 1 ), 'Nowruz 1405 = 2026-03-21' );
assert_test( array( 2029, 3, 20 ) === Jalali::to_gregorian( 1408, 1, 1 ), 'Nowruz 1408 = 2029-03-20 (the equinox-anchored day the old is_leap contradicted)' );
assert_test( array( 2025, 3, 19 ) === Jalali::to_gregorian( 1403, 12, 29 ) && array( 2025, 3, 20 ) === Jalali::to_gregorian( 1403, 12, 30 ), 'leap Esfand: 29th = 2025-03-19 and the 30th exists, = 2025-03-20' );
assert_test( array( 2026, 3, 20 ) === Jalali::to_gregorian( 1404, 12, 29 ), 'common Esfand ends at its 29th = 2026-03-20, the day before Nowruz 1405' );
assert_test( array( 1403, 12, 30 ) === Jalali::from_gregorian( 2025, 3, 20 ) && array( 1404, 12, 29 ) === Jalali::from_gregorian( 2026, 3, 20 ), 'the inverse maps both Esfand endings back correctly' );
assert_test( array( 2024, 12, 20 ) === Jalali::to_gregorian( 1403, 9, 30 ), 'Yalda night 1403 = 30 Azar = 2024-12-20' );
assert_test( array( 2025, 4, 2 ) === Jalali::to_gregorian( 1404, 1, 13 ), 'Sizdah Bedar 1404 = 2025-04-02' );
assert_test( array( 1402, 12, 10 ) === Jalali::from_gregorian( 2024, 2, 29 ), 'the Gregorian leap day 2024-02-29 = 10 Esfand 1402 (Feb-29 handling in the g_d_m table)' );
assert_test( array( 2025, 9, 22 ) === Jalali::to_gregorian( 1404, 6, 31 ) && array( 2025, 9, 23 ) === Jalali::to_gregorian( 1404, 7, 1 ), 'the 31→30-day boundary: 31 Shahrivar and 1 Mehr are consecutive days' );
assert_test( array( 2025, 10, 22 ) === Jalali::to_gregorian( 1404, 7, 30 ) && array( 2025, 10, 23 ) === Jalali::to_gregorian( 1404, 8, 1 ), '30 Mehr is followed by 1 Aban (30-day months chain cleanly)' );

echo "\n--- P48. Parsing, iso helpers, and the +3:30 today fallback ---\n";

$p = Jalali::parse_gregorian( '2026-03-21' );
assert_test( 1405 === $p['y'] && 1 === $p['m'] && 1 === $p['d'] && '1405-01-01' === $p['iso'] && '1 فروردین 1405' === $p['label'], 'parse_gregorian builds y/m/d + zero-padded iso + Persian label' );
assert_test( Jalali::jalali_iso_to_gregorian( '1404/07/30' ) === Jalali::jalali_iso_to_gregorian( '1404.07.30' ) && '2025-10-22' === Jalali::jalali_iso_to_gregorian( '1404.07.30' ), 'the iso helper accepts dash, slash and dot separators alike' );
assert_test( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', Jalali::today_gregorian() ), 'today_gregorian always answers Y-m-d (pure context: the gmdate branch)' );
assert_test( Jalali::jalali_iso_to_gregorian( 'garbage' ) === Jalali::today_gregorian() && Jalali::parse_gregorian( 'x' )['iso'] === Jalali::parse_gregorian( Jalali::today_gregorian() )['iso'], 'both invalid-input paths fall back to the SAME today — no split-brain between the helpers' );
$j_src = file_get_contents( __DIR__ . '/../../includes/Support/Jalali.php' );
assert_test(
	false !== strpos( $j_src, "function_exists( 'current_time' )" ) && false !== strpos( $j_src, "current_time( 'mysql' )" ),
	'+3:30 fix pinned: today_gregorian prefers current_time (site zone) over gmdate — between 00:00 and 03:30 in Tehran gmdate alone prints yesterday'
);

echo "\n--- P48. Iranian weekday (iran_dow — the audited strtotime site) ---\n";

assert_test( 0 === Jalali::iran_dow( '2026-03-21' ), 'Nowruz 1405 (2026-03-21) is شنبه, Iranian weekday 0' );
assert_test( 6 === Jalali::iran_dow( '2024-12-20' ), 'Yalda 1403 (2024-12-20) is جمعه, Iranian weekday 6' );
assert_test( 0 === Jalali::iran_dow( '2026-08-22' ), '2026-08-22 is a Saturday (the pre-existing PHPUnit vector)' );
$dow_broken = array();
$ts          = strtotime( '2026-03-01 12:00:00' );
$prev        = Jalali::iran_dow( '2026-03-01' );
for ( $i = 1; $i <= 30; $i++ ) {
	$ts   += 86400;
	$cur   = Jalali::iran_dow( gmdate( 'Y-m-d', $ts ) );
	if ( $cur !== ( $prev + 1 ) % 7 ) {
		$dow_broken[] = gmdate( 'Y-m-d', $ts );
	}
	$prev = $cur;
}
assert_test( array() === $dow_broken, 'iran_dow advances by exactly one (mod 7) across 30 consecutive days, month boundary included' );
assert_test( 0 === Jalali::iran_dow( 'not-a-date' ), 'an unparseable date degrades to 0, never to a wrong-day crash' );
assert_test(
	false !== strpos( $j_src, "strtotime( \$gregorian_ymd . ' 12:00:00' )" ),
	'iran_dow keeps its noon anchor: strtotime and date() share one timezone, so the date part cannot drift (audit verdict: safe as written)'
);

echo "\n--- P48. mktime/strtotime audit pins (the prompt’s review clause) ---\n";

$mktime_hits = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../../includes' ) ) as $file ) {
	if ( $file->isFile() && 'php' === $file->getExtension() && 'qrcode.php' !== $file->getFilename() ) {
		$src = (string) file_get_contents( $file->getPathname() );
		if ( preg_match( '/\b(g?m?ktime)\s*\(/', $src ) ) {
			$mktime_hits[] = $file->getFilename();
		}
	}
}
assert_test( array() === $mktime_hits, 'zero mktime/gmmktime calls remain anywhere in flavor-core/includes' . ( $mktime_hits ? ' (' . implode( ',', $mktime_hits ) . ')' : ' — the Jalali day math never depended on them' ) );
$slot_src = file_get_contents( __DIR__ . '/../../includes/Reservation/SlotCalculator.php' );
assert_test(
	false !== strpos( $slot_src, "current_time( 'timestamp' )" )
		&& false !== strpos( $slot_src, '$slot_date = $t >= 24 * 60' )
		&& false !== strpos( $slot_src, 'DAY_IN_SECONDS' ),
	'slots(): the 24:00 edge — after-midnight slots of an overnight window advance the calendar day before the past-check (noon-anchored, DST-proof)'
);
assert_test(
	false !== strpos( $slot_src, '$close += 24 * 60' ) && false !== strpos( $slot_src, '$t % ( 24 * 60 )' ),
	'overnight hours normalise (close <= open wraps +24h) and slot display times fold back under 24:00'
);
$res_src = file_get_contents( __DIR__ . '/../../includes/Reservation/ReservationRepository.php' );
assert_test(
	false !== strpos( $res_src, "\$now = current_time( 'timestamp' );" ),
	'reminders: the naive site-local reservation datetime pairs with current_time(timestamp), not raw time() — raw pairing fired reminders 3.5 h LATE in Tehran'
);
$av_src = file_get_contents( __DIR__ . '/../../includes/Menu/AvailabilityManager.php' );
assert_test(
	false !== strpos( $av_src, "strtotime( (string) \$row['unavailable_until'] ) < current_time( 'timestamp' )" ),
	'availability: unavailable_until (site-local naive) expires against the localized epoch'
);
$ktr_src = file_get_contents( __DIR__ . '/../../includes/Order/KitchenTicketRepository.php' );
assert_test(
	false !== strpos( $ktr_src, "max( 0, current_time( 'timestamp' ) - \$placed )" ),
	'kitchen card age: placed_at (site-local naive) ages against the localized epoch — raw time() pinned fresh tickets at 0:00 for 3.5 h'
);
$wh_src = file_get_contents( __DIR__ . '/../../includes/Webhooks/WebhookManager.php' );
assert_test(
	false !== strpos( $wh_src, ". ' UTC' )" ),
	'webhook queue rows are stored UTC and parsed with an explicit UTC suffix (audit verdict: the exemplary pairing)'
);
assert_test(
	class_exists( Clock::class ) && null === Clock::timestamp( '' ) && null === Clock::timestamp( 'garbage!!' ),
	'Clock — the sanctioned display-time path (prompt 23) — refuses empty and unreadable values instead of guessing'
);
assert_test(
	'12:10' === Clock::format( '2026-10-11T15:40:00+03:30', 'H:i' ),
	'a +03:30-offset instant formats to its true UTC-face 12:10 in the pure context (inside WP, wp_date re-renders it in the site zone — the docblock’s own 15:40/12:10 example)'
);
assert_test(
	Clock::timestamp( '2026-03-21 00:00:00' ) === Clock::timestamp( '2026-03-21' ),
	'the "!" create-format anchor: a date-only value is midnight, not the current wall-clock seconds'
);

echo "\n--- P48. The 24:00 edge in slot arithmetic ---\n";

assert_test( 1440 === SlotCalculator::minutes( '24:00' ), '24:00 parses to minute 1440 — closing at midnight, not at zero' );
assert_test( 0 === SlotCalculator::minutes( '00:00' ) && 1439 === SlotCalculator::minutes( '23:59' ), 'the day runs 0…1439 in minutes; 24:00 is the exclusive end' );
assert_test( SlotCalculator::overlaps( 1380, 60, 1410, 60 ), 'a 23:00+60min booking overlaps a 23:30 one right up to midnight' );
assert_test( ! SlotCalculator::overlaps( 1380, 60, 1440, 60 ), 'a booking starting exactly at 24:00 touches, never overlaps, the closing slot' );

echo "\n--- P48. Storefront formatting (live, default Persian digits) ---\n";

assert_test( 'شنبه ۱ فروردین ۱۴۰۵' === Jalali::format( '2026-03-21', true ), 'format() with weekday: Persian digits, Iranian Saturday, Jalali label' );
assert_test( '۱ فروردین ۱۴۰۵' === Jalali::format( '2026-03-21' ), 'format() without weekday stays a pure Jalali label' );
assert_test( '۲۰ اسفند ۱۴۰۳' === Jalali::format( '2025-03-10' ), 'a leap-Esfand date formats with the right day and month name' );
assert_test( '۱ فروردین ۱۴۰۵ - ۲۰:۳۰' === Jalali::format_datetime( '2026-03-21 20:30:00' ), 'format_datetime appends the HH:MM in Persian digits (no weekday — format() governs that)' );
assert_test( '۱ فروردین ۱۴۰۵' === Jalali::format_datetime( '2026-03-21' ), 'format_datetime without a time part is just the date label' );
