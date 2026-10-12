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
	 * [jy, jm, jd] from a Gregorian Y-m-d.
	 *
	 * Exact inverse of {@see self::to_gregorian()}: both halves of the pair use
	 * the same 12053-day cycle / 33-year arithmetic so a round trip is identity.
	 * Days are counted from 1600-01-01 as in the reference implementation.
	 *
	 * @return int[]
	 */
	public static function from_gregorian( int $gy, int $gm, int $gd ): array {
		// Cumulative days before the 1st of each Gregorian month.
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

		// Leap day is only added for March onwards, so shift the year for Jan/Feb.
		$gy_leap = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;

		$g_d_n = 355666
			+ ( 365 * $gy )
			+ self::div( $gy_leap + 3, 4 )
			- self::div( $gy_leap + 99, 100 )
			+ self::div( $gy_leap + 399, 400 )
			+ $gd
			+ $g_d_m[ $gm - 1 ];

		$jy    = -1595 + ( 33 * self::div( $g_d_n, 12053 ) );
		$g_d_n = $g_d_n % 12053;
		$jy   += 4 * self::div( $g_d_n, 1461 );
		$g_d_n = $g_d_n % 1461;

		if ( $g_d_n > 365 ) {
			$jy    += self::div( $g_d_n - 1, 365 );
			// phpcs:ignore Squiz.Operators.IncrementDecrementUsage.Found -- explicit calendar arithmetic; --$g_d_n inside the modulo would obscure the algorithm.
			$g_d_n  = ( $g_d_n - 1 ) % 365;
		}

		if ( $g_d_n < 186 ) {
			$jm = 1 + self::div( $g_d_n, 31 );
			$jd = 1 + ( $g_d_n % 31 );
		} else {
			$jm = 7 + self::div( $g_d_n - 186, 30 );
			$jd = 1 + ( ( $g_d_n - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * [gy, gm, gd] from Jalali.
	 *
	 * @return int[]
	 */
	public static function to_gregorian( int $jy, int $jm, int $jd ): array {
		$jy -= 979;
		--$jm;
		--$jd;
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
			--$g_day_no;
			$gy      += 100 * self::div( $g_day_no, 36524 );
			$g_day_no = $g_day_no % 36524;
			if ( $g_day_no >= 365 ) {
				++$g_day_no;
			} else {
				$leap = false;
			}
		}
		$gy       += 4 * self::div( $g_day_no, 1461 );
		$g_day_no  = $g_day_no % 1461;
		if ( $g_day_no >= 366 ) {
			$leap      = false;
			--$g_day_no;
			$gy       += self::div( $g_day_no, 365 );
			$g_day_no  = $g_day_no % 365;
		}
		$sal_a = array( 0, 31, ( $leap ? 29 : 28 ), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$gm    = 0;
		while ( $gm < 13 && $g_day_no >= $sal_a[ $gm ] ) {
			$g_day_no -= $sal_a[ $gm ];
			++$gm;
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
			$p = array_map( 'intval', explode( '-', self::today_gregorian() ) );
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
			return self::today_gregorian();
		}
		list( $gy, $gm, $gd ) = self::to_gregorian( $p[0], $p[1], $p[2] );
		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	/**
	 * Gregorian Y-m-d for "today" in the site's own timezone.
	 *
	 * Prompt 48 (+3:30): the store lives in Asia/Tehran. Between 00:00 and
	 * 03:30 local time gmdate() still answers with the *previous* UTC day, so
	 * every fallback that printed "today" showed yesterday's Jalali date on
	 * late-night receipts and dashboards. current_time() honours the WP
	 * timezone string; the gmdate() fallback keeps pure CLI/test contexts
	 * (no WordPress loaded) deterministic.
	 */
	public static function today_gregorian(): string {
		if ( function_exists( 'current_time' ) ) {
			return substr( (string) current_time( 'mysql' ), 0, 10 );
		}
		return gmdate( 'Y-m-d' );
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
	 * Jalali leap year — faithful port of jalaali-js `isLeapJalaliYear()`
	 * (`jalCal().leap === 0`), the reference named in this file's header.
	 *
	 * Prompt 48 regression: the previous body ended in a march-parity
	 * heuristic, `((leap_j + 20) - leap_g) % 2 === 0`, which is NOT the
	 * reference algorithm and called 1407, 1411, 1415, 1419, 1423 and 1427
	 * leap. month_length() then returned 30 for Esfand of those years while
	 * this class's own from_gregorian()/to_gregorian() day arithmetic says
	 * they have 365 days — the reservation validators (RestExperience,
	 * ReservationController) would have accepted a non-existent 30 Esfand
	 * 1407 (from March 2028) and mapped it onto Nowruz 1408. The pure suite
	 * pins both the official leap set and is_leap ⇔ 366-day-year agreement
	 * with the conversions for 1380–1429.
	 */
	public static function is_leap( int $jy ): bool {
		$breaks = array( -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178 );
		$bl     = count( $breaks );
		$jp     = $breaks[0];
		$jump   = 0;
		for ( $i = 1; $i < $bl; $i++ ) {
			$jm   = $breaks[ $i ];
			$jump = $jm - $jp;
			if ( $jy < $jm ) {
				break;
			}
			$jp = $jm;
		}
		$n = $jy - $jp;
		// Fold years close to the next break back into the 33-year cycle,
		// exactly as jalCal() does, so the modulo below sees the same n.
		if ( $jump - $n < 6 ) {
			$n = $n - $jump + self::div( $jump + 4, 33 ) * 33;
		}
		$leap = ( ( ( $n + 1 ) % 33 ) - 1 ) % 4;
		if ( -1 === $leap ) {
			$leap = 4;
		}
		return 0 === $leap;
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
	 * Format Gregorian date & time (e.g. 2026-09-18 20:30:00) to Jalali string.
	 *
	 * @param string $gregorian_datetime Gregorian datetime.
	 * @return string
	 */
	public static function format_datetime( string $gregorian_datetime ): string {
		$parts = explode( ' ', $gregorian_datetime );
		$ymd   = $parts[0] ?? '';
		$time  = substr( $parts[1] ?? '', 0, 5 );
		$date_formatted = self::format( $ymd );
		if ( ! empty( $time ) ) {
			if ( 'persian' === Settings::get( 'digits', 'persian' ) ) {
				$time = \FlavorCore\WooCommerce\Currency::to_persian_digits( $time );
			}
			return $date_formatted . ' - ' . $time;
		}
		return $date_formatted;
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
