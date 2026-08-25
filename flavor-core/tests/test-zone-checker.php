<?php
/**
 * Delivery zone matcher tests.
 *
 * @package FlavorCore
 */

class Flavor_Zone_Checker_Test extends WP_UnitTestCase {

	public function test_haversine_zero() {
		$this->assertEqualsWithDelta( 0, \FlavorCore\Delivery\ZoneChecker::haversine( 35.7, 51.4, 35.7, 51.4 ), 0.001 );
	}

	public function test_haversine_tehran_karaj_ballpark() {
		$km = \FlavorCore\Delivery\ZoneChecker::haversine( 35.6892, 51.3890, 35.8400, 50.9391 );
		$this->assertGreaterThan( 30, $km );
		$this->assertLessThan( 60, $km );
	}

	public function test_point_inside_radius() {
		$zone    = array(
			'zone_type'  => 'radius',
			'center_lat' => 35.7,
			'center_lng' => 51.4,
			'radius_km'  => 2,
		);
		$address = array(
			'lat' => 35.701,
			'lng' => 51.401,
		);
		$this->assertTrue( \FlavorCore\Delivery\ZoneChecker::in_radius( $zone, $address ) );
	}

	public function test_point_outside_radius() {
		$zone    = array(
			'zone_type'  => 'radius',
			'center_lat' => 35.7,
			'center_lng' => 51.4,
			'radius_km'  => 1,
		);
		$address = array(
			'lat' => 35.8,
			'lng' => 51.5,
		);
		$this->assertFalse( \FlavorCore\Delivery\ZoneChecker::in_radius( $zone, $address ) );
	}

	public function test_neighborhood_match_persian_ye() {
		$zone    = array(
			'zone_type'     => 'neighborhoods',
			'neighborhoods' => array( 'ونک' ),
		);
		$address = array(
			'neighborhood' => 'ونك',
		);
		$this->assertTrue( \FlavorCore\Delivery\ZoneChecker::in_neighborhoods( $zone, $address ) );
	}

	public function test_neighborhood_miss() {
		$zone    = array(
			'zone_type'     => 'neighborhoods',
			'neighborhoods' => array( 'جردن' ),
		);
		$address = array(
			'neighborhood' => 'نیاوران',
		);
		$this->assertFalse( \FlavorCore\Delivery\ZoneChecker::in_neighborhoods( $zone, $address ) );
	}

	public function test_minimum_order() {
		$zone = array( 'min_order' => 2000000 );
		$this->assertFalse( \FlavorCore\Delivery\ZoneChecker::meets_minimum( $zone, 1999999 ) );
		$this->assertTrue( \FlavorCore\Delivery\ZoneChecker::meets_minimum( $zone, 2000000 ) );
	}

	public function test_polygon_square_contains() {
		$zone    = array(
			'zone_type'    => 'polygon',
			'polygon_json' => wp_json_encode(
				array(
					array( 'lat' => 35.0, 'lng' => 51.0 ),
					array( 'lat' => 35.0, 'lng' => 52.0 ),
					array( 'lat' => 36.0, 'lng' => 52.0 ),
					array( 'lat' => 36.0, 'lng' => 51.0 ),
				)
			),
		);
		$inside  = array( 'lat' => 35.5, 'lng' => 51.5 );
		$outside = array( 'lat' => 34.0, 'lng' => 51.5 );
		$this->assertTrue( \FlavorCore\Delivery\ZoneChecker::in_polygon( $zone, $inside ) );
		$this->assertFalse( \FlavorCore\Delivery\ZoneChecker::in_polygon( $zone, $outside ) );
	}
}
