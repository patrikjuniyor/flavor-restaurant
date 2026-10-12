<?php
/**
 * Jalali conversion tests.
 *
 * @package FlavorCore
 */

class Flavor_Jalali_Test extends WP_UnitTestCase {

	public function test_nowruz_2026() {
		// 21 March 2026 = 1 Farvardin 1405.
		list( $y, $m, $d ) = \FlavorCore\Support\Jalali::from_gregorian( 2026, 3, 21 );
		$this->assertSame( 1405, $y );
		$this->assertSame( 1, $m );
		$this->assertSame( 1, $d );
	}

	public function test_roundtrip() {
		list( $gy, $gm, $gd ) = \FlavorCore\Support\Jalali::to_gregorian( 1403, 11, 22 );
		list( $jy, $jm, $jd ) = \FlavorCore\Support\Jalali::from_gregorian( $gy, $gm, $gd );
		$this->assertSame( 1403, $jy );
		$this->assertSame( 11, $jm );
		$this->assertSame( 22, $jd );
	}

	public function test_iso_helper() {
		$g = \FlavorCore\Support\Jalali::jalali_iso_to_gregorian( '1404-01-01' );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $g );
		$j = \FlavorCore\Support\Jalali::parse_gregorian( $g );
		$this->assertSame( 1404, $j['y'] );
		$this->assertSame( 1, $j['m'] );
		$this->assertSame( 1, $j['d'] );
	}

	public function test_iran_saturday() {
		// 2026-08-22 is a Saturday.
		$this->assertSame( 0, \FlavorCore\Support\Jalali::iran_dow( '2026-08-22' ) );
	}

	/**
	 * Prompt 48 — leap-year edges. 1407 is the regression: the old
	 * march-parity heuristic called it (and 1411/1415/1419/1423/1427) leap,
	 * inventing a 30 Esfand the conversions do not have.
	 */
	public function test_leap_years() {
		$this->assertTrue( \FlavorCore\Support\Jalali::is_leap( 1399 ) );
		$this->assertTrue( \FlavorCore\Support\Jalali::is_leap( 1403 ) );
		$this->assertTrue( \FlavorCore\Support\Jalali::is_leap( 1408 ) );
		$this->assertFalse( \FlavorCore\Support\Jalali::is_leap( 1400 ) );
		$this->assertFalse( \FlavorCore\Support\Jalali::is_leap( 1404 ) );
		$this->assertFalse( \FlavorCore\Support\Jalali::is_leap( 1407 ) );
		$this->assertFalse( \FlavorCore\Support\Jalali::is_leap( 1411 ) );
		$this->assertSame( 30, \FlavorCore\Support\Jalali::month_length( 1403, 12 ) );
		$this->assertSame( 30, \FlavorCore\Support\Jalali::month_length( 1408, 12 ) );
		$this->assertSame( 29, \FlavorCore\Support\Jalali::month_length( 1404, 12 ) );
		$this->assertSame( 29, \FlavorCore\Support\Jalali::month_length( 1407, 12 ) );
	}

	/**
	 * Prompt 48 — is_leap must never contradict the conversion arithmetic:
	 * a leap year is exactly a Nowruz-to-Nowruz span of 366 days.
	 */
	public function test_leap_agrees_with_conversions() {
		for ( $y = 1395; $y <= 1415; $y++ ) {
			list( $gy1, $gm1, $gd1 ) = \FlavorCore\Support\Jalali::to_gregorian( $y, 1, 1 );
			list( $gy2, $gm2, $gd2 ) = \FlavorCore\Support\Jalali::to_gregorian( $y + 1, 1, 1 );
			$days = ( strtotime( "$gy2-$gm2-$gd2" ) - strtotime( "$gy1-$gm1-$gd1" ) ) / 86400;
			$this->assertSame( \FlavorCore\Support\Jalali::is_leap( $y ), 366 === $days, "leap mismatch for $y" );
		}
	}

	/**
	 * Prompt 48 — Esfand and month-boundary conversions.
	 */
	public function test_month_boundaries() {
		$this->assertSame( array( 2025, 3, 20 ), \FlavorCore\Support\Jalali::to_gregorian( 1403, 12, 30 ) );
		$this->assertSame( array( 1403, 12, 30 ), \FlavorCore\Support\Jalali::from_gregorian( 2025, 3, 20 ) );
		$this->assertSame( array( 2026, 3, 20 ), \FlavorCore\Support\Jalali::to_gregorian( 1404, 12, 29 ) );
		$this->assertSame( array( 2025, 9, 22 ), \FlavorCore\Support\Jalali::to_gregorian( 1404, 6, 31 ) );
		$this->assertSame( array( 2025, 9, 23 ), \FlavorCore\Support\Jalali::to_gregorian( 1404, 7, 1 ) );
		$this->assertSame( array( 2025, 10, 22 ), \FlavorCore\Support\Jalali::to_gregorian( 1404, 7, 30 ) );
		$this->assertSame( array( 2025, 10, 23 ), \FlavorCore\Support\Jalali::to_gregorian( 1404, 8, 1 ) );
		$this->assertSame( array( 1402, 12, 10 ), \FlavorCore\Support\Jalali::from_gregorian( 2024, 2, 29 ) );
	}

	/**
	 * Prompt 48 — round trip is the identity across a full Gregorian leap cycle.
	 */
	public function test_roundtrip_sweep() {
		$ts  = strtotime( '2024-01-01' );
		$end = strtotime( '2027-12-31' );
		for ( ; $ts <= $end; $ts += 86400 ) {
			$ymd = gmdate( 'Y-m-d', $ts );
			list( $gy, $gm, $gd ) = explode( '-', $ymd );
			$j  = \FlavorCore\Support\Jalali::from_gregorian( (int) $gy, (int) $gm, (int) $gd );
			$g2 = \FlavorCore\Support\Jalali::to_gregorian( $j[0], $j[1], $j[2] );
			$this->assertSame( $ymd, sprintf( '%04d-%02d-%02d', $g2[0], $g2[1], $g2[2] ), "round trip broke at $ymd" );
		}
	}

	/**
	 * Prompt 48 — the 24:00 edge and weekday progression.
	 */
	public function test_time_edges() {
		$this->assertSame( 1440, \FlavorCore\Reservation\SlotCalculator::minutes( '24:00' ) );
		$this->assertFalse( \FlavorCore\Reservation\SlotCalculator::overlaps( 1380, 60, 1440, 60 ) );
		$dow  = \FlavorCore\Support\Jalali::iran_dow( '2026-03-01' );
		$ts   = strtotime( '2026-03-01 12:00:00' );
		for ( $i = 0; $i < 14; $i++ ) {
			$this->assertSame( ( $dow + $i ) % 7, \FlavorCore\Support\Jalali::iran_dow( gmdate( 'Y-m-d', $ts + $i * 86400 ) ) );
		}
	}

	public function test_slot_overlap() {
		$this->assertTrue( \FlavorCore\Reservation\SlotCalculator::overlaps( 720, 90, 750, 90 ) );
		$this->assertFalse( \FlavorCore\Reservation\SlotCalculator::overlaps( 720, 90, 810, 90 ) );
	}
}
