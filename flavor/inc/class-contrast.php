<?php
/**
 * Readability check for the merchant's own colour choices.
 *
 * UI::variables() already corrected unreadable combinations in silence: a
 * muted grey that failed against the surface was quietly swapped for the
 * ink colour. Silent correction is the wrong shape for a Customizer. The
 * merchant picks a colour, sees nothing change, and learns nothing — then
 * wonders why the muted text is suddenly black.
 *
 * This class reports the same WCAG ratio the correction is based on, so the
 * choice stays visible. It only ever reports: correcting remains UI's job,
 * and no combination here blocks the merchant from saving.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Contrast
 */
class Contrast {

	/**
	 * WCAG 2.1 AA for body text.
	 */
	const AA_NORMAL = 4.5;

	/**
	 * WCAG 2.1 AA for large text (18.66px bold, or 24px and up).
	 */
	const AA_LARGE = 3.0;

	/**
	 * The colour pairs that carry text on this theme.
	 *
	 * Each pairing is one a reader actually meets: the description under a
	 * dish, the label on the primary button, the heading on the page
	 * background. Pairs nobody reads are not checked — a report that flags
	 * things the merchant cannot see trains them to ignore it.
	 *
	 * @return array<string, array{fg: string, bg: string, label: string, min: float, large: bool}>
	 */
	public static function pairs(): array {
		return array(
			'ink_on_bg'          => array(
				'fg'    => 'ink',
				'bg'    => 'bg',
				'label' => __( 'متن اصلی روی پس‌زمینهٔ صفحه', 'flavor' ),
				'min'   => self::AA_NORMAL,
				'large' => false,
			),
			'ink_on_surface'     => array(
				'fg'    => 'ink',
				'bg'    => 'surface',
				'label' => __( 'متن اصلی روی کارت‌ها', 'flavor' ),
				'min'   => self::AA_NORMAL,
				'large' => false,
			),
			'ink_on_surface_alt' => array(
				'fg'    => 'ink',
				'bg'    => 'surface_alt',
				'label' => __( 'متن اصلی روی بخش‌های کم‌رنگ', 'flavor' ),
				'min'   => self::AA_NORMAL,
				'large' => false,
			),
			'muted_on_surface'   => array(
				'fg'    => 'muted',
				'bg'    => 'surface',
				'label' => __( 'توضیح کوتاه روی کارت‌ها', 'flavor' ),
				'min'   => self::AA_NORMAL,
				'large' => false,
			),
			'muted_on_bg'        => array(
				'fg'    => 'muted',
				'bg'    => 'bg',
				'label' => __( 'توضیح کوتاه روی پس‌زمینه', 'flavor' ),
				'min'   => self::AA_NORMAL,
				'large' => false,
			),
			'button_ink'         => array(
				'fg'    => '@button',
				'bg'    => 'primary',
				'label' => __( 'نوشتهٔ دکمهٔ اصلی', 'flavor' ),
				'min'   => self::AA_LARGE,
				'large' => true,
			),
		);
	}

	/**
	 * WCAG contrast ratio between two hex colours.
	 *
	 * @param string $a Foreground.
	 * @param string $b Background.
	 * @return float
	 */
	public static function ratio( string $a, string $b ): float {
		$a = UI::luminance( $a );
		$b = UI::luminance( $b );

		if ( $a <= 0 && $b <= 0 ) {
			return 1.0;
		}

		return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	}

	/**
	 * The ink colour the primary button resolves to.
	 *
	 * Mirrors the choice UI::variables() makes, so the report describes the
	 * button that will actually ship rather than a hypothetical one.
	 *
	 * @param array<string, string> $tokens Resolved design tokens.
	 * @return string
	 */
	public static function button_ink( array $tokens ): string {
		$primary = (string) ( $tokens['primary'] ?? '#000000' );

		return self::ratio( $primary, '#ffffff' ) >= self::ratio( $primary, '#000000' ) ? '#ffffff' : '#000000';
	}

	/**
	 * Evaluate every text pair against the given tokens.
	 *
	 * @param array<string, string> $tokens Resolved design tokens.
	 * @return array<string, array{label: string, fg: string, bg: string, ratio: float, min: float, pass: bool}>
	 */
	public static function report( array $tokens ): array {
		$button = self::button_ink( $tokens );
		$out    = array();

		foreach ( self::pairs() as $key => $pair ) {
			$fg = '@button' === $pair['fg'] ? $button : (string) ( $tokens[ $pair['fg'] ] ?? '' );
			$bg = (string) ( $tokens[ $pair['bg'] ] ?? '' );

			// An unset token cannot be judged; skip rather than warn falsely.
			if ( '' === $fg || '' === $bg ) {
				continue;
			}

			$ratio = self::ratio( $fg, $bg );

			$out[ $key ] = array(
				'label' => $pair['label'],
				'fg'    => $fg,
				'bg'    => $bg,
				'ratio' => round( $ratio, 2 ),
				'min'   => $pair['min'],
				'pass'  => $ratio >= $pair['min'],
			);
		}

		return $out;
	}

	/**
	 * Only the pairs that fail.
	 *
	 * @param array<string, string> $tokens Resolved design tokens.
	 * @return array<string, array{label: string, fg: string, bg: string, ratio: float, min: float, pass: bool}>
	 */
	public static function failing( array $tokens ): array {
		return array_filter(
			self::report( $tokens ),
			static fn( array $row ): bool => ! $row['pass']
		);
	}

	/**
	 * A single human-readable summary, or '' when everything passes.
	 *
	 * Used for the control's resting state so the merchant is not shown six
	 * green ticks every time they open the panel.
	 *
	 * @param array<string, string> $tokens Resolved design tokens.
	 * @return string
	 */
	public static function summary( array $tokens ): string {
		$failing = self::failing( $tokens );

		if ( empty( $failing ) ) {
			return '';
		}

		/* translators: %d is the number of failing colour pairs. */
		$head = _n(
			'یک ترکیب رنگ خوانایی کافی ندارد:',
			'چند ترکیب رنگ خوانایی کافی ندارند:',
			count( $failing ),
			'flavor'
		);

		$lines = array();
		foreach ( $failing as $row ) {
			$lines[] = sprintf(
				/* translators: 1: pair name, 2: measured ratio, 3: required ratio. */
				__( '%1$s — %2$s به ۱ (حداقل %3$s به ۱)', 'flavor' ),
				$row['label'],
				number_format_i18n( $row['ratio'], 2 ),
				number_format_i18n( $row['min'], 1 )
			);
		}

		return $head . ' ' . implode( '؛ ', $lines );
	}
}
