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

	public function test_slot_overlap() {
		$this->assertTrue( \FlavorCore\Reservation\SlotCalculator::overlaps( 720, 90, 750, 90 ) );
		$this->assertFalse( \FlavorCore\Reservation\SlotCalculator::overlaps( 720, 90, 810, 90 ) );
	}
}
