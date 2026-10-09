<?php
/**
 * CSV output for reports (N-11).
 *
 * Two details matter more than they look:
 *
 *  - **UTF-8 BOM.** Excel on Windows reads a CSV without a BOM as the
 *    local codepage, which turns every Persian heading into gibberish.
 *    Three bytes at the start of the file decide whether a restaurant
 *    owner can read their own report.
 *  - **Formula injection.** A cell beginning with =, +, - or @ is
 *    evaluated as a formula by Excel and Sheets. Customer names and menu
 *    item names are user-supplied, so a row containing
 *    `=HYPERLINK("http://evil","click")` would execute on open. Those
 *    values are prefixed with an apostrophe so they stay text.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reporting;

defined( 'ABSPATH' ) || exit;

/**
 * Class CsvWriter
 */
class CsvWriter {

	/**
	 * Characters that make a spreadsheet treat a cell as a formula.
	 *
	 * @var string[]
	 */
	private const FORMULA_STARTS = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * Render rows as a CSV string.
	 *
	 * @param string[]              $headers Header row.
	 * @param array<int,array<int,string>> $rows Data rows.
	 * @return string
	 */
	public static function render( array $headers, array $rows ): string {
		$out = self::bom() . self::line( $headers );
		foreach ( $rows as $row ) {
			$out .= self::line( $row );
		}
		return $out;
	}

	/**
	 * The UTF-8 byte order mark Excel needs to read Persian correctly.
	 *
	 * @return string
	 */
	public static function bom(): string {
		return "\xEF\xBB\xBF";
	}

	/**
	 * Render one CSV line.
	 *
	 * @param array<int, string> $cells Cells.
	 * @return string
	 */
	private static function line( array $cells ): string {
		$quoted = array();
		foreach ( $cells as $cell ) {
			$quoted[] = self::quote( (string) $cell );
		}
		return implode( ',', $quoted ) . "\r\n";
	}

	/**
	 * Quote one cell, neutralising formulas.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function quote( string $value ): string {
		// Strip control characters: they are invisible in a spreadsheet and
		// can smuggle content past a reviewer.
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );

		if ( '' !== $value && in_array( $value[0], self::FORMULA_STARTS, true ) ) {
			// The apostrophe is how spreadsheets spell "this is text".
			$value = "'" . $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}

	/**
	 * A filename for the download.
	 *
	 * @param string $type      Report type.
	 * @param string $from      From date.
	 * @param string $to        To date.
	 * @param string $timezone  Timezone label.
	 * @return string
	 */
	public static function filename( string $type, string $from, string $to, string $timezone = '' ): string {
		$parts = array( 'flavor', $type );
		if ( '' !== $from || '' !== $to ) {
			$parts[] = ( '' !== $from ? $from : 'start' ) . '_to_' . ( '' !== $to ? $to : gmdate( 'Y-m-d' ) );
		}
		$parts[] = gmdate( 'Y-m-d-His' );

		// The timezone belongs in the filename: two people reading the same
		// CSV in different offices will otherwise disagree about which day
		// an order belongs to.
		if ( '' !== $timezone ) {
			// sanitize_title() alone would turn "Asia/Tehran" into
			// "asiatehran", which is unreadable in a Downloads folder.
			// Swapping the separator first keeps it legible and still safe.
			$parts[] = sanitize_title( str_replace( array( '/', '_', ' ' ), '-', $timezone ) );
		}

		return implode( '-', $parts ) . '.csv';
	}
}
