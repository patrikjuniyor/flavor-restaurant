<?php
/**
 * Site-timezone clock for anything the customer reads.
 *
 * Why this exists (prompt 23): the tracking screen printed the time a kitchen
 * ticket was accepted with `date( 'H:i', strtotime( $row['accepted_at'] ) )`.
 * WordPress stores those DATETIME columns as *site-local* wall clock, but
 * strtotime() and date() both speak the *server* timezone. When a shared host
 * runs UTC while the shop's WordPress timezone is Asia/Tehran, every stage of
 * the order read three-and-a-half hours off — the customer watched a "12:10"
 * that was really 15:40, and blamed the kitchen.
 *
 * This class is the only sanctioned way to turn a stored time into displayed
 * text: it anchors a naive string in the site timezone instead of the server's,
 * honours an offset when the value carries one, and formats through wp_date() so
 * translations, `date_format` and the timezone string all keep working.
 *
 * It deliberately does not touch token expiry or menu-window maths, which compare
 * naive strings against naive strings and cancel out — see tests/theme/
 * test-order-clock.php for the boundary this class is responsible for.
 *
 * @package FlavorCore\Support
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Clock
 */
final class Clock {

	/**
	 * The site's timezone, with a fixed-UTC fallback for contexts without
	 * WordPress (the class is used from WP-CLI scripts and the test harness).
	 */
	private static function site_zone(): \DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
			$zone = wp_timezone();
			if ( $zone instanceof \DateTimeZone ) {
				return $zone;
			}
		}
		if ( function_exists( 'wp_timezone_string' ) ) {
			try {
				return new \DateTimeZone( wp_timezone_string() );
			} catch ( \Exception $e ) {
				/* Fall through: a broken option must not break a receipt. */
			}
		}
		return new \DateTimeZone( 'UTC' );
	}

	/**
	 * Timestamp of a stored value, or null when it is not a time at all.
	 *
	 * Accepted shapes: `Y-m-d H:i:s` / `Y-m-d H:i` / `Y-m-d` from the database
	 * (read as site-local), and anything with an explicit offset such as
	 * `2026-10-11T15:40:00+03:30` (read as the instant it names). A bare
	 * `strtotime()` is used only as the last resort it deserves to be.
	 */
	public static function timestamp( string $value ): ?int {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		$zone  = self::site_zone();
		// The leading "!" resets the fields a format does not mention, so a
		// date-only value lands on midnight instead of inheriting the current
		// seconds from the wall clock.
		foreach ( array( '!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d' ) as $shape ) {
			$parsed = \DateTimeImmutable::createFromFormat( $shape, $value, $zone );
			if ( $parsed ) {
				return $parsed->getTimestamp();
			}
		}
		// Anything else is expected to name its own offset (ISO-8601 from a mobile
		// client, a webhook payload). PHP resolves that correctly on its own; the
		// site zone is only the fallback for a naive string that reached here.
		try {
			return ( new \DateTimeImmutable( $value, $zone ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Formats a stored value for the customer, in the site timezone.
	 *
	 * @param string $value   A DATETIME as stored, or an ISO-8601 string.
	 * @param string $format  PHP date format, filtered through WP when available.
	 * @return string Empty when the value is missing or unreadable — a receipt
	 *                with a wrong time is worse than one without a time.
	 */
	public static function format( string $value, string $format = 'H:i' ): string {
		$timestamp = self::timestamp( $value );
		if ( null === $timestamp ) {
			return '';
		}
		if ( function_exists( 'wp_date' ) ) {
			$formatted = wp_date( $format, $timestamp );
			if ( is_string( $formatted ) && '' !== $formatted ) {
				return $formatted;
			}
		}
		return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( self::site_zone() )->format( $format );
	}

}
