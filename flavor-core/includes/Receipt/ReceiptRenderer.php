<?php
/**
 * Kitchen / cashier receipt HTML (browser print, 80mm CSS).
 *
 * @package FlavorCore
 */

namespace FlavorCore\Receipt;

use FlavorCore\Order\KitchenTicketRepository;
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
	public static function render( int $ticket_id, string $kind = 'kitchen' ): void {
		$ticket = KitchenTicketRepository::find( $ticket_id );
		if ( ! $ticket ) {
			wp_die( esc_html__( 'تیکت پیدا نشد.', 'flavor-core' ) );
		}
		$card   = KitchenTicketRepository::to_card( $ticket );
		$branch = get_the_title( (int) $ticket['branch_id'] );
		$kind   = 'cashier' === $kind ? 'cashier' : 'kitchen';

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8" />
	<title><?php echo esc_html( '#' . $card['order_number'] ); ?></title>
	<style>
		@page { size: 80mm auto; margin: 4mm; }
		body { width: 72mm; margin: 0 auto; font-family: Tahoma, Vazirmatn, sans-serif; font-size: 12px; color: #000; }
		h1 { font-size: 15px; text-align: center; margin: 0 0 6px; }
		.meta { text-align: center; margin-bottom: 8px; }
		hr { border: 0; border-top: 1px dashed #000; }
		ul { padding: 0; margin: 8px 0; list-style: none; }
		li { margin: 6px 0; }
		.mod { color: #333; font-size: 11px; }
		.note { font-weight: 700; }
		.tot { font-size: 14px; font-weight: 700; text-align: center; }
		.no-print { text-align: center; margin: 10px 0; }
		@media print { .no-print { display: none; } }
	</style>
</head>
<body onload="window.print()">
	<div class="no-print"><button onclick="window.print()"><?php esc_html_e( 'چاپ', 'flavor-core' ); ?></button></div>
	<h1><?php echo esc_html( $branch ); ?></h1>
	<div class="meta">
		<?php echo esc_html( '#' . $card['order_number'] ); ?>
		· <?php echo esc_html( self::mode_label( (string) $card['order_mode'] ) ); ?>
		<?php if ( $card['table_number'] ) : ?>
			· <?php echo esc_html( sprintf( /* translators: table */ __( 'میز %s', 'flavor-core' ), $card['table_number'] ) ); ?>
		<?php endif; ?>
		<br /><?php echo esc_html( (string) $card['placed_at'] ); ?>
	</div>
	<hr />
	<ul>
		<?php foreach ( $card['items'] as $item ) : ?>
			<li>
				<strong><?php echo esc_html( (string) $item['quantity'] ); ?>× <?php echo esc_html( (string) $item['item_name'] ); ?></strong>
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
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( 'cashier' === $kind ) : ?>
		<hr />
		<p class="tot"><?php echo esc_html( Currency::format( (int) $card['total'] ) ); ?></p>
		<p class="meta"><?php echo esc_html( (string) $card['payment_method'] ); ?></p>
	<?php endif; ?>
</body>
</html>
		<?php
		exit;
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
