<?php
/**
 * Toman / Rial conversion and display.
 *
 * Storage default: IRR (Rial). Display default: IRT (Toman).
 * 1 Toman = 10 Rials.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce;

use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Currency
 */
class Currency {

	public const RIAL  = 'irr';
	public const TOMAN = 'irt';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'woocommerce_currency_symbol', array( $this, 'symbol' ), 20, 2 );
		add_filter( 'woocommerce_currencies', array( $this, 'register_currencies' ) );
	}

	/**
	 * Register IRT if WooCommerce does not have it.
	 *
	 * @param array<string, string> $currencies Currencies.
	 * @return array<string, string>
	 */
	public function register_currencies( array $currencies ): array {
		if ( ! isset( $currencies['IRT'] ) ) {
			$currencies['IRT'] = __( 'تومان ایران', 'flavor-core' );
		}
		if ( ! isset( $currencies['IRR'] ) ) {
			$currencies['IRR'] = __( 'ریال ایران', 'flavor-core' );
		}
		return $currencies;
	}

	/**
	 * Force a Persian symbol for IRR / IRT.
	 *
	 * @param string $symbol   Current symbol.
	 * @param string $currency Currency code.
	 */
	public function symbol( string $symbol, string $currency ): string {
		$code = strtoupper( $currency );
		if ( 'IRT' === $code ) {
			return __( 'تومان', 'flavor-core' );
		}
		if ( 'IRR' === $code ) {
			return __( 'ریال', 'flavor-core' );
		}
		return $symbol;
	}

	/**
	 * Storage unit: irr | irt.
	 */
	public static function storage_unit(): string {
		$unit = (string) Settings::get( 'currency_storage', self::RIAL );
		return in_array( $unit, array( self::RIAL, self::TOMAN ), true ) ? $unit : self::RIAL;
	}

	/**
	 * Display unit: irr | irt.
	 */
	public static function display_unit(): string {
		$unit = (string) Settings::get( 'currency_display', self::TOMAN );
		return in_array( $unit, array( self::RIAL, self::TOMAN ), true ) ? $unit : self::TOMAN;
	}

	/**
	 * Convert an integer amount between units.
	 *
	 * @param int    $amount Integer amount in $from.
	 * @param string $from   irr|irt.
	 * @param string $to     irr|irt.
	 */
	public static function convert( int $amount, string $from, string $to ): int {
		if ( $from === $to ) {
			return $amount;
		}
		if ( self::RIAL === $from && self::TOMAN === $to ) {
			return (int) floor( $amount / 10 );
		}
		if ( self::TOMAN === $from && self::RIAL === $to ) {
			return $amount * 10;
		}
		return $amount;
	}

	/**
	 * Normalize any incoming amount into storage units (integer).
	 */
	public static function to_storage( int $amount, string $from_unit ): int {
		return self::convert( $amount, $from_unit, self::storage_unit() );
	}

	/**
	 * Convert a stored amount into display units.
	 */
	public static function to_display( int $stored ): int {
		return self::convert( $stored, self::storage_unit(), self::display_unit() );
	}

	/**
	 * Human label for the display unit.
	 */
	public static function display_label(): string {
		return self::TOMAN === self::display_unit()
			? __( 'تومان', 'flavor-core' )
			: __( 'ریال', 'flavor-core' );
	}

	/**
	 * Format a stored integer for the storefront.
	 *
	 * @param int  $stored        Amount in storage units.
	 * @param bool $include_label Append تومان/ریال.
	 */
	public static function format( int $stored, bool $include_label = true ): string {
		$display = self::to_display( $stored );
		$number  = number_format_i18n( $display );
		if ( 'persian' === Settings::get( 'digits', 'persian' ) ) {
			$number = self::to_persian_digits( (string) $number );
		}
		if ( ! $include_label ) {
			return $number;
		}
		return trim( $number . ' ' . self::display_label() );
	}

	/**
	 * Latin → Persian digits.
	 */
	public static function to_persian_digits( string $value ): string {
		return strtr(
			$value,
			array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			)
		);
	}

	/**
	 * Persian / Arabic digits → Latin.
	 */
	public static function to_latin_digits( string $value ): string {
		return strtr(
			$value,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
	}
}
