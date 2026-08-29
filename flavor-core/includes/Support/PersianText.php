<?php
/**
 * Persian/Arabic text normalisation helpers for search.
 *
 * Iranian users type the same word many ways: «کباب کوبیده» / «كباب كوبيده»,
 * «پيتزا» با ي عربی، ارقام فارسی، نیم‌فاصله، کشیده و اعراب. برای اینکه
 * جست‌وجو «هوشمند» باشد همه به یک شکل کانونی تبدیل می‌شوند.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class PersianText
 */
class PersianText {

	/**
	 * Character folding map.
	 *
	 * @var array<string, string>
	 */
	private const FOLD = array(
		// Arabic yeh/kaf → Persian.
		'ي' => 'ی',
		'ى' => 'ی',
		'ﻯ' => 'ی',
		'ك' => 'ک',
		'ﻙ' => 'ک',
		// Alef variants.
		'أ' => 'ا',
		'إ' => 'ا',
		'آ' => 'ا',
		'ٱ' => 'ا',
		// Heh / teh marbuta.
		'ة' => 'ه',
		'ۀ' => 'ه',
		// Waw variants.
		'ؤ' => 'و',
		'ئ' => 'ی',
		// Kashida + diacritics.
		'ـ' => '',
		'ً' => '',
		'ٌ' => '',
		'ٍ' => '',
		'َ' => '',
		'ُ' => '',
		'ِ' => '',
		'ّ' => '',
		'ْ' => '',
		'ٓ' => '',
		'ٔ' => '',
		'ٰ' => '',
		// Zero-width joiners → space (نیم‌فاصله).
		'‌' => ' ',
		'‍' => '',
		'‎' => '',
		'‏' => '',
	);

	/**
	 * Digit map (Persian + Arabic-Indic → ASCII).
	 *
	 * @var array<string, string>
	 */
	private const DIGITS = array(
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
	);

	/**
	 * Persian stop words that add no signal to a menu query.
	 *
	 * @var string[]
	 */
	private const STOP_WORDS = array(
		'و', 'با', 'بی', 'در', 'از', 'به', 'یک', 'the', 'a', 'of',
		'می', 'خواهم', 'میخوام', 'میخواهم', 'برای', 'را', 'رو', 'هم',
	);

	/**
	 * Canonical form of a string: folded characters, ASCII digits, single spaces.
	 *
	 * @param string $text Raw text.
	 */
	public static function normalize( string $text ): string {
		$text = strtr( $text, self::FOLD );
		$text = strtr( $text, self::DIGITS );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Persian digits for display.
	 *
	 * @param string $text Text with ASCII digits.
	 */
	public static function to_persian_digits( string $text ): string {
		return strtr( $text, array_flip( array_slice( self::DIGITS, 0, 10 ) ) );
	}

	/**
	 * Split a normalised query into meaningful tokens.
	 *
	 * @param string $text Raw text.
	 * @return string[]
	 */
	public static function tokens( string $text ): array {
		$parts  = preg_split( '/\s+/u', self::normalize( $text ) ) ?: array();
		$tokens = array();

		foreach ( $parts as $part ) {
			$part = self::stem( $part );
			if ( '' === $part ) {
				continue;
			}
			if ( in_array( $part, self::STOP_WORDS, true ) ) {
				continue;
			}
			if ( self::length( $part ) < 2 ) {
				continue;
			}
			$tokens[] = $part;
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Very light Persian stemmer: strips common plural/possessive suffixes.
	 *
	 * @param string $word Normalised word.
	 */
	public static function stem( string $word ): string {
		$suffixes = array( 'هایی', 'هایم', 'هایت', 'هایش', 'های', 'ها', 'تری', 'ترین', 'تر' );

		foreach ( $suffixes as $suffix ) {
			$len = self::length( $suffix );
			if ( self::length( $word ) > $len + 2 && self::ends_with( $word, $suffix ) ) {
				return self::substr( $word, 0, self::length( $word ) - $len );
			}
		}

		return $word;
	}

	/**
	 * Multibyte-safe length.
	 *
	 * @param string $text Text.
	 */
	public static function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Multibyte-safe substr.
	 *
	 * @param string   $text   Text.
	 * @param int      $start  Start.
	 * @param int|null $length Length.
	 */
	public static function substr( string $text, int $start, ?int $length = null ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, $start, $length, 'UTF-8' );
		}
		return null === $length ? substr( $text, $start ) : substr( $text, $start, $length );
	}

	/**
	 * Suffix test.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 */
	private static function ends_with( string $haystack, string $needle ): bool {
		return '' !== $needle && str_ends_with( $haystack, $needle );
	}

	/**
	 * Similarity between two normalised words, 0..1 (typo tolerance).
	 *
	 * @param string $a First word.
	 * @param string $b Second word.
	 */
	public static function similarity( string $a, string $b ): float {
		if ( $a === $b ) {
			return 1.0;
		}
		if ( '' === $a || '' === $b ) {
			return 0.0;
		}

		// levenshtein() is byte based; compare transliterated-safe by codepoint arrays.
		$distance = self::levenshtein_utf8( $a, $b );
		$longest  = max( self::length( $a ), self::length( $b ) );

		return $longest > 0 ? max( 0.0, 1 - ( $distance / $longest ) ) : 0.0;
	}

	/**
	 * UTF-8 aware Levenshtein distance.
	 *
	 * @param string $a First word.
	 * @param string $b Second word.
	 */
	public static function levenshtein_utf8( string $a, string $b ): int {
		$x = preg_split( '//u', $a, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
		$y = preg_split( '//u', $b, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
		$m = count( $x );
		$n = count( $y );

		if ( 0 === $m ) {
			return $n;
		}
		if ( 0 === $n ) {
			return $m;
		}

		$prev = range( 0, $n );
		$curr = array_fill( 0, $n + 1, 0 );

		for ( $i = 1; $i <= $m; $i++ ) {
			$curr[0] = $i;
			for ( $j = 1; $j <= $n; $j++ ) {
				$cost    = $x[ $i - 1 ] === $y[ $j - 1 ] ? 0 : 1;
				$curr[ $j ] = min( $curr[ $j - 1 ] + 1, $prev[ $j ] + 1, $prev[ $j - 1 ] + $cost );
			}
			$prev = $curr;
		}

		return (int) $prev[ $n ];
	}
}
