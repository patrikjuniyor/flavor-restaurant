<?php
/**
 * Resolve an address / point to a delivery zone.
 *
 * V1 types: radius (haversine) and neighborhoods (string match).
 * Overlapping zones: smallest delivery fee, then smallest radius / first match.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Delivery;

defined( 'ABSPATH' ) || exit;

/**
 * Class ZoneChecker
 */
class ZoneChecker {

	/**
	 * Find the best matching zone for a branch.
	 *
	 * @param int                  $branch_id Branch.
	 * @param array<string, mixed> $address   Keys: neighborhood, city, lat, lng.
	 * @return array<string, mixed>|null Hydrated zone or null.
	 */
	public static function match( int $branch_id, array $address ): ?array {
		$zones = ZoneRepository::for_branch( $branch_id, true );
		$hits  = array();
		foreach ( $zones as $zone ) {
			if ( self::contains( $zone, $address ) ) {
				$hits[] = $zone;
			}
		}
		if ( empty( $hits ) ) {
			return null;
		}
		usort(
			$hits,
			static function ( $a, $b ) {
				if ( (int) $a['delivery_fee'] === (int) $b['delivery_fee'] ) {
					return (int) $a['id'] <=> (int) $b['id'];
				}
				return (int) $a['delivery_fee'] <=> (int) $b['delivery_fee'];
			}
		);
		return $hits[0];
	}

	/**
	 * Whether the address sits inside the zone.
	 *
	 * @param array<string, mixed> $zone    Zone.
	 * @param array<string, mixed> $address Address.
	 */
	public static function contains( array $zone, array $address ): bool {
		$type = (string) ( $zone['zone_type'] ?? 'neighborhoods' );
		if ( 'radius' === $type ) {
			return self::in_radius( $zone, $address );
		}
		if ( 'polygon' === $type ) {
			return self::in_polygon( $zone, $address );
		}
		return self::in_neighborhoods( $zone, $address );
	}

	/**
	 * Cart subtotal (storage units) vs zone minimum.
	 *
	 * @param array<string, mixed> $zone    Zone.
	 * @param int                  $subtotal Storage units.
	 */
	public static function meets_minimum( array $zone, int $subtotal ): bool {
		return $subtotal >= (int) $zone['min_order'];
	}

	/**
	 * Delivery fee in storage units.
	 *
	 * @param array<string, mixed> $zone Zone.
	 */
	public static function fee( array $zone ): int {
		return (int) $zone['delivery_fee'];
	}

	/**
	 * Haversine radius check. Requires lat/lng on both sides.
	 *
	 * @param array<string, mixed> $zone    Zone.
	 * @param array<string, mixed> $address Address.
	 */
	public static function in_radius( array $zone, array $address ): bool {
		if ( ! isset( $zone['center_lat'], $zone['center_lng'], $zone['radius_km'], $address['lat'], $address['lng'] ) ) {
			return false;
		}
		if ( '' === (string) $address['lat'] || '' === (string) $address['lng'] ) {
			return false;
		}
		$km = self::haversine(
			(float) $zone['center_lat'],
			(float) $zone['center_lng'],
			(float) $address['lat'],
			(float) $address['lng']
		);
		return $km <= (float) $zone['radius_km'] + 0.0001;
	}

	/**
	 * Case-insensitive neighborhood / city match.
	 *
	 * @param array<string, mixed> $zone    Zone.
	 * @param array<string, mixed> $address Address.
	 */
	public static function in_neighborhoods( array $zone, array $address ): bool {
		$needles = array();
		foreach ( array( 'neighborhood', 'city' ) as $key ) {
			if ( ! empty( $address[ $key ] ) ) {
				$needles[] = self::norm( (string) $address[ $key ] );
			}
		}
		if ( empty( $needles ) ) {
			return false;
		}
		$list = $zone['neighborhoods'] ?? array();
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		foreach ( $list as $name ) {
			$hay = self::norm( (string) $name );
			if ( '' === $hay ) {
				continue;
			}
			foreach ( $needles as $n ) {
				if ( $n === $hay || false !== strpos( $n, $hay ) || false !== strpos( $hay, $n ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Ray-casting point-in-polygon (reserved for Pro; used if polygon_json is set).
	 *
	 * @param array<string, mixed> $zone    Zone.
	 * @param array<string, mixed> $address Address.
	 */
	public static function in_polygon( array $zone, array $address ): bool {
		if ( empty( $address['lat'] ) || empty( $address['lng'] ) || empty( $zone['polygon_json'] ) ) {
			return false;
		}
		$poly = json_decode( (string) $zone['polygon_json'], true );
		if ( ! is_array( $poly ) || count( $poly ) < 3 ) {
			return false;
		}
		$x = (float) $address['lng'];
		$y = (float) $address['lat'];
		$inside = false;
		$j      = count( $poly ) - 1;
		for ( $i = 0, $n = count( $poly ); $i < $n; $j = $i++ ) {
			$xi = (float) ( $poly[ $i ]['lng'] ?? $poly[ $i ][0] ?? 0 );
			$yi = (float) ( $poly[ $i ]['lat'] ?? $poly[ $i ][1] ?? 0 );
			$xj = (float) ( $poly[ $j ]['lng'] ?? $poly[ $j ][0] ?? 0 );
			$yj = (float) ( $poly[ $j ]['lat'] ?? $poly[ $j ][1] ?? 0 );
			$intersect = ( ( $yi > $y ) !== ( $yj > $y ) )
				&& ( $x < ( $xj - $xi ) * ( $y - $yi ) / ( ( $yj - $yi ) ?: 0.0000001 ) + $xi );
			if ( $intersect ) {
				$inside = ! $inside;
			}
		}
		return $inside;
	}

	/**
	 * Distance in kilometres.
	 */
	public static function haversine( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$earth = 6371.0;
		$dlat  = deg2rad( $lat2 - $lat1 );
		$dlng  = deg2rad( $lng2 - $lng1 );
		$a     = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
		return $earth * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
	}

	/**
	 * Normalize Persian/Arabic text for comparison.
	 */
	public static function norm( string $s ): string {
		$s = trim( mb_strtolower( $s, 'UTF-8' ) );
		$s = strtr(
			$s,
			array(
				'ي' => 'ی',
				'ك' => 'ک',
				'ة' => 'ه',
				'‌' => '',
				' ' => '',
			)
		);
		return $s;
	}
}
