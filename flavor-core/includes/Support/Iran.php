<?php
/**
 * Iran geography helper.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Iran
 */
class Iran {

	/**
	 * Provinces mapped to cities.
	 *
	 * @return array<string, string[]>
	 */
	public static function provinces(): array {
		static $data = null;
		if ( null === $data ) {
			$path = FLAVOR_CORE_PATH . 'data/iran-provinces.php';
			$data = is_readable( $path ) ? require $path : array();
		}
		return $data;
	}

	/**
	 * Province names.
	 *
	 * @return string[]
	 */
	public static function province_names(): array {
		return array_keys( self::provinces() );
	}

	/**
	 * Normalize an Iranian mobile number to 09xxxxxxxxx or empty string.
	 */
	public static function normalize_mobile( string $raw ): string {
		$digits = preg_replace( '/\D+/', '', $raw );
		if ( ! is_string( $digits ) ) {
			return '';
		}
		if ( str_starts_with( $digits, '0098' ) ) {
			$digits = substr( $digits, 4 );
		} elseif ( str_starts_with( $digits, '98' ) ) {
			$digits = substr( $digits, 2 );
		}
		if ( str_starts_with( $digits, '9' ) && 10 === strlen( $digits ) ) {
			$digits = '0' . $digits;
		}
		if ( preg_match( '/^09\d{9}$/', $digits ) ) {
			return $digits;
		}
		return '';
	}
}
