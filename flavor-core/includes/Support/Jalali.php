<?php
/**
 * Gregorian ↔ Jalali conversion. Dates stay Gregorian in the database.
 *
 * Algorithm after jalaali-js / Behrooz (public domain arithmetic).
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Jalali
 */
class Jalali {

	public const MONTHS = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	public const WEEKDAYS = array(
		0 => 'شنبه',
		1 => 'یکشنبه',
		2 => 'دوشنبه',
		3 => 'سه‌شنبه',
		4 => 'چهارشنبه',
		5 => 'پنجشنبه',
		6 => 'جمعه',
	);

	/**
	 * [jy, jm, jd] from a Gregorian Y-m-d or DateTime.
	 *
	 * @return int[]
	 */
	public static function from_gregorian( int $gy, int $gm, int $gd ): array {
		$g_d_n = self::div( ( $gy + self::div( $gm - 8, 6 ) + 100100 ) * 1461, 4 )
			+ self::div( 153 * ( ( $gm + 9 ) % 12 ) + 2, 5 )
			+ $gd - 34840408;
		$g_d_n = $g_d_n - self::div( self::div( $gy + 100100 + self::div( $gm - 8, 6 ), 100 ) * 3, 4 ) + 752;
		$j_np  = self::div( $g_d_n - 79, 12053 );
		$g_d_n = ( $g_d_n - 79 ) % 12053;
		$jy    = 979 + 33 * $j_np + 4 * self::div( $g_d_n, 1461 );
		$g_d_n = $g_d_n % 1461;
		if ( $g_d_n >= 366 ) {
			$jy   += self::div( $g_d_n - 1, 365 );
			$g_d_n = ( $g_d_n - 1 ) % 365;
		}
		$jm = ( $g_d_n < 186 ) ? 1 + self::div( $g_d_n, 31 ) : 7 + self::div( $g_d_n - 186, 30 );
		$jd = 1 + ( ( $g_d_n < 186 ) ? ( $g_d_n % 31 ) : ( ( $g_d_n - 186 ) % 30 ) );
		return array( $jy, $jm, $jd );
	}

	/**
	 * [gy, gm, gd] from Jalali.
	 *
	 * @return int[]
	 */
	public static function to_gregorian( int $jy, int $jm, int $jd ): array {
		$jy -= 979;
		$jm -= 1;
		$jd -= 1;
		$j_day_no = 365 * $jy + self::div( $jy, 33 ) * 8 + self::div( ( $jy % 33 ) + 3, 4 );
		for ( $i = 0; $i < $jm; $i++ ) {
			$j_day_no += ( $i < 6 ) ? 31 : 30;
		}
		$j_day_no += $jd;
		$g_day_no  = $j_day_no + 79;
		$gy        = 1600 + 400 * self::div( $g_day_no, 146097 );
		$g_day_no  = $g_day_no % 146097;
		$leap      = true;
		if ( $g_day_no >= 36525 ) {
			$g_day_no--;
			$gy      += 100 * self::div( $g_day_no, 36524 );
			$g_day_no = $g_day_no % 36524;
			if ( $g_day_no >= 365 ) {
				$g_day_no++;
			} else {
				$leap = false;
			}
		}
		$gy       += 4 * self::div( $g_day_no, 1461 );
		$g_day_no  = $g_day_no % 1461;
		if ( $g_day_no >= 366 ) {
			$leap      = false;
			$g_day_no--;
			$gy       += self::div( $g_day_no, 365 );
			$g_day_no  = $g_day_no % 365;
		}
		$sal_a = array( 0, 31, ( $leap ? 29 : 28 ), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$gm    = 0;
		while ( $gm < 13 && $g_day_no >= $sal_a[ $gm ] ) {
			$g_day_no -= $sal_a[ $gm ];
			$gm++;
		}
		$gd = $g_day_no + 1;
		return array( $gy, $gm, $gd );
	}

	/**
	 * Parse Y-m-d (Gregorian) → Jalali parts.
	 *
	 * @return array{y:int,m:int,d:int,iso:string,label:string}
	 */
	public static function parse_gregorian( string $ymd ): array {
		$p = array_map( 'intval', explode( '-', $ymd ) );
		if ( count( $p ) < 3 ) {
			$p = array( (int) gmdate( 'Y' ), (int) gmdate( 'n' ), (int) gmdate( 'j' ) );
		}
		list( $jy, $jm, $jd ) = self::from_gregorian( $p[0], $p[1], $p[2] );
		return array(
			'y'     => $jy,
			'm'     => $jm,
			'd'     => $jd,
			'iso'   => sprintf( '%04d-%02d-%02d', $jy, $jm, $jd ),
			'label' => $jd . ' ' . ( self::MONTHS[ $jm ] ?? '' ) . ' ' . $jy,
		);
	}

	/**
	 * Jalali iso (1404-06-03) or parts → Gregorian Y-m-d.
	 */
	public static function jalali_iso_to_gregorian( string $iso ): string {
		$p = array_map( 'intval', preg_split( '/[\/\-.]/', $iso ) ?: array() );
		if ( count( $p ) < 3 ) {
			return gmdate( 'Y-m-d' );
		}
		list( $gy, $gm, $gd ) = self::to_gregorian( $p[0], $p[1], $p[2] );
		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	/**
	 * Days in a Jalali month.
	 */
	public static function month_length( int $jy, int $jm ): int {
		if ( $jm <= 6 ) {
			return 31;
		}
		if ( $jm <= 11 ) {
			return 30;
		}
		return self::is_leap( $jy ) ? 30 : 29;
	}

	/**
	 * Jalali leap year.
	 */
	public static function is_leap( int $jy ): bool {
		$breaks = array( -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178 );
		$bl     = count( $breaks );
		$gy     = $jy + 621;
		$leap_j = -14;
		$jp     = $breaks[0];
		$jump   = 0;
		for ( $i = 1; $i < $bl; $i++ ) {
			$jm   = $breaks[ $i ];
			$jump = $jm - $jp;
			if ( $jy < $jm ) {
				break;
			}
			$leap_j = $leap_j + self::div( $jump, 33 ) * 8 + self::div( ( $jump % 33 ), 4 );
			$jp     = $jm;
		}
		$n = $jy - $jp;
		if ( $n < $jump ) {
			$leap_j = $leap_j + self::div( $n, 33 ) * 8 + self::div( ( $n % 33 ) + 3, 4 );
			if ( ( $jump % 33 ) === 4 && ( $jump - $n ) === 4 ) {
				$leap_j++;
			}
		}
		$leap_g = self::div( $gy, 4 ) - self::div( ( self::div( $gy, 100 ) + 1 ) * 3, 4 ) - 150;
		return ( ( ( $leap_j + 20 ) - $leap_g ) % 2 ) === 0; // phpcs:ignore WordPress.PHP.YodaConditions
	}

	/**
	 * Iranian weekday 0=Saturday … 6=Friday for a Gregorian date.
	 */
	public static function iran_dow( string $gregorian_ymd ): int {
		$ts = strtotime( $gregorian_ymd . ' 12:00:00' );
		if ( ! $ts ) {
			return 0;
		}
		// PHP w: 0=Sun … 6=Sat. Iran: 0=Sat … 6=Fri.
		return ( (int) date( 'w', $ts ) + 1 ) % 7; // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
	}

	/**
	 * Format a Gregorian date for the storefront.
	 */
	public static function format( string $gregorian_ymd, bool $with_week = false ): string {
		$j = self::parse_gregorian( $gregorian_ymd );
		$s = $j['label'];
		if ( $with_week ) {
			$s = ( self::WEEKDAYS[ self::iran_dow( $gregorian_ymd ) ] ?? '' ) . ' ' . $s;
		}
		if ( 'persian' === Settings::get( 'digits', 'persian' ) ) {
			$s = \FlavorCore\WooCommerce\Currency::to_persian_digits( $s );
		}
		return $s;
	}

	/**
	 * Integer division toward zero.
	 */
	private static function div( int $a, int $b ): int {
		if ( 0 === $b ) {
			return 0;
		}
		return (int) ( ( $a - ( $a % $b ) ) / $b );
	}
}
