<?php
/**
 * Kitchen / cashier receipt HTML (browser print, 80mm CSS).
 *
 * Prompt 62: the document now wears the theme's print sheet
 * (flavor/assets/css/print-receipt.css, inlined below so the standalone
 * route stays self-contained), prints every number in the storefront digit
 * style (Persian by default), dates in Jalali, the cashier totals breakdown
 * (subtotal / discount / delivery / payable) straight from the ticket row,
 * and — for dine-in tickets — the table's monochrome QR so the printed
 * receipt scans back to the table menu. Ink policy: no backgrounds, ever.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Receipt;

use FlavorCore\Order\KitchenTicketRepository;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\Settings;
use FlavorCore\Table\QrGenerator;
use FlavorCore\Table\TableRepository;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReceiptRenderer
 */
class ReceiptRenderer {

	/**
	 * Render a ticket as a printable HTML document.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param string $kind      kitchen|cashier.
	 */
	public static function render( int $ticket_id, string $kind = 'cashier' ): void {
		$ticket = KitchenTicketRepository::find( $ticket_id );
		if ( ! $ticket ) {
			wp_die( esc_html__( 'تیکت پیدا نشد.', 'flavor-core' ) );
		}
		$card   = KitchenTicketRepository::to_card( $ticket );
		$branch = get_the_title( (int) $ticket['branch_id'] );
		$kind   = 'cashier' === $kind ? 'cashier' : 'kitchen';

		$qr_svg = '';
		if ( 'cashier' === $kind && ! empty( $card['table_id'] ) ) {
			$table = TableRepository::find( (int) $card['table_id'] );
			if ( $table ) {
				$qr_svg = QrGenerator::svg( TableRepository::public_url( $table ), 256 );
			}
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8" />
	<title><?php echo esc_html( '#' . self::num( (string) $card['order_number'] ) ); ?></title>
	<style>
<?php echo self::print_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme print sheet or built-in fallback, no user input. ?>
	</style>
</head>
<body class="flavor-receipt" onload="window.print()">
	<div class="no-print"><button onclick="window.print()"><?php esc_html_e( 'چاپ', 'flavor-core' ); ?></button></div>
	<div class="flavor-receipt__brand"><?php echo esc_html( $branch ); ?></div>
	<div class="flavor-receipt__meta">
		<?php echo esc_html( '#' . self::num( (string) $card['order_number'] ) ); ?>
		· <?php echo esc_html( self::mode_label( (string) $card['order_mode'] ) ); ?>
		<?php if ( $card['table_number'] ) : ?>
			· <?php echo esc_html( sprintf( /* translators: %s: table number */ __( 'میز %s', 'flavor-core' ), self::num( (string) $card['table_number'] ) ) ); ?>
		<?php endif; ?>
		<br /><?php echo esc_html( Jalali::format_datetime( (string) $card['placed_at'] ) ); ?>
	</div>
	<hr />
	<table class="flavor-receipt__items">
		<tbody>
		<?php foreach ( $card['items'] as $item ) : ?>
			<tr>
				<td class="qty"><?php echo esc_html( self::num( (string) $item['quantity'] ) . '×' ); ?></td>
				<td>
					<strong><?php echo esc_html( (string) $item['item_name'] ); ?></strong>
					<?php if ( ! empty( $item['modifiers'] ) ) : ?>
						<div class="mod">
							<?php
							$bits = array();
							foreach ( $item['modifiers'] as $mod ) {
								$bits[] = ( $mod['name'] ?? '' );
							}
							echo esc_html( implode( '، ', $bits ) );
							?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $item['special_instructions'] ) ) : ?>
						<div class="note"><?php echo esc_html( (string) $item['special_instructions'] ); ?></div>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( 'cashier' === $kind ) : ?>
		<hr />
		<table class="flavor-receipt__totals">
			<tbody>
				<tr><td><?php esc_html_e( 'جمع جزئی', 'flavor-core' ); ?></td><td><?php echo esc_html( Currency::format( (int) $card['subtotal'] ) ); ?></td></tr>
				<?php if ( (int) $card['discount_total'] > 0 ) : ?>
					<tr><td><?php esc_html_e( 'تخفیف', 'flavor-core' ); ?></td><td><?php echo esc_html( '−' . Currency::format( (int) $card['discount_total'] ) ); ?></td></tr>
				<?php endif; ?>
				<?php if ( (int) $card['delivery_fee'] > 0 ) : ?>
					<tr><td><?php esc_html_e( 'هزینهٔ ارسال', 'flavor-core' ); ?></td><td><?php echo esc_html( Currency::format( (int) $card['delivery_fee'] ) ); ?></td></tr>
				<?php endif; ?>
				<tr class="tot"><td><?php esc_html_e( 'قابل پرداخت', 'flavor-core' ); ?></td><td><?php echo esc_html( Currency::format( (int) $card['total'] ) ); ?></td></tr>
				<tr><td colspan="2" class="mod"><?php echo esc_html( (string) $card['payment_method'] ); ?></td></tr>
			</tbody>
		</table>
		<?php if ( '' !== $qr_svg ) : ?>
			<div class="flavor-receipt__qr">
				<?php echo $qr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- monochrome SVG from the bundled encoder, no user input. ?>
				<div class="flavor-receipt__qr-cap"><?php echo esc_html( sprintf( /* translators: %s: table number */ __( 'اسکن برای منوی میز %s', 'flavor-core' ), self::num( (string) $card['table_number'] ) ) ); ?></div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Storefront digit style for raw numbers (order no, qty, table).
	 *
	 * @param string $value Digits.
	 */
	public static function num( string $value ): string {
		if ( 'persian' !== Settings::get( 'digits', 'persian' ) ) {
			return $value;
		}
		return Currency::to_persian_digits( $value );
	}

	/**
	 * The theme print sheet when the companion theme is present, else a
	 * built-in fallback with the same anatomy.
	 */
	public static function print_css(): string {
		if ( defined( 'FLAVOR_DIR' ) && is_readable( FLAVOR_DIR . '/assets/css/print-receipt.css' ) ) {
			$css = file_get_contents( FLAVOR_DIR . '/assets/css/print-receipt.css' );
			if ( is_string( $css ) && '' !== trim( $css ) ) {
				return $css;
			}
		}
		return "@page { size: 80mm auto; margin: 4mm; }\n"
			. "body.flavor-receipt { width: 72mm; margin: 0 auto; font-family: Tahoma, Vazirmatn, sans-serif; font-size: 12px; direction: rtl; color: #000; }\n"
			. ".flavor-receipt__brand { text-align: center; font-weight: 700; font-size: 15px; margin: 0 0 6px; }\n"
			. ".flavor-receipt__meta { text-align: center; font-size: 11px; margin: 0 0 8px; }\n"
			. ".flavor-receipt hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }\n"
			. ".flavor-receipt table { width: 100%; border-collapse: collapse; table-layout: fixed; }\n"
			. ".flavor-receipt th, .flavor-receipt td { text-align: right; padding: 2px 0; vertical-align: top; overflow-wrap: break-word; }\n"
			. ".flavor-receipt__items .qty { width: 12mm; white-space: nowrap; }\n"
			. ".flavor-receipt .mod { color: #333; font-size: 11px; }\n"
			. ".flavor-receipt .note { font-weight: 700; }\n"
			. ".flavor-receipt__totals .tot { font-size: 14px; font-weight: 700; }\n"
			. ".flavor-receipt__qr { text-align: center; margin-top: 8px; }\n"
			. ".flavor-receipt__qr svg { width: 28mm; height: 28mm; }\n"
			. ".flavor-receipt__qr-cap { font-size: 11px; margin-top: 2px; }\n"
			. ".no-print { text-align: center; margin: 10px 0; }\n"
			. "@media print { .no-print { display: none; } }\n";
	}

	/**
	 * Human mode.
	 */
	public static function mode_label( string $mode ): string {
		$map = array(
			'dine_in'  => __( 'سالن', 'flavor-core' ),
			'takeaway' => __( 'بیرون‌بر', 'flavor-core' ),
			'delivery' => __( 'ارسال', 'flavor-core' ),
		);
		return $map[ $mode ] ?? $mode;
	}
}
