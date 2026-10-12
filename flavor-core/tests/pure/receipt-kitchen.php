<?php
/**
 * Pure suite — ReceiptRenderer (the printable ticket) and KitchenTicketSync
 * (the WC-order → kitchen-ticket bridge).
 *
 * render() itself needs a database row and an HTTP context (it is the
 * prompt-62 wiring target), so its safety posture is pinned at the source;
 * mode_label and the sync's suspension short-circuit are exercised live.
 *
 * Loaded by run-pure-tests.php (assert_test protocol).
 *
 * @package FlavorCore
 */

// phpcs:disable WordPress.Security.EscapeOutput -- CLI harness output, not an HTTP response.

require_once __DIR__ . '/../../includes/Receipt/ReceiptRenderer.php';
require_once __DIR__ . '/../../includes/Order/KitchenTicketSync.php';

use FlavorCore\Order\KitchenTicketSync;
use FlavorCore\Receipt\ReceiptRenderer;

echo "\n--- P47. Receipt rendering (ReceiptRenderer) ---\n";

assert_test( 'سالن' === ReceiptRenderer::mode_label( 'dine_in' ), 'dine_in prints as سالن' );
assert_test( 'بیرون‌بر' === ReceiptRenderer::mode_label( 'takeaway' ), 'takeaway prints as بیرون‌بر' );
assert_test( 'ارسال' === ReceiptRenderer::mode_label( 'delivery' ), 'delivery prints as ارسال' );
assert_test( 'weird_mode' === ReceiptRenderer::mode_label( 'weird_mode' ), 'an unknown mode passes through instead of printing a blank label' );

$rr_src = file_get_contents( __DIR__ . '/../../includes/Receipt/ReceiptRenderer.php' );
assert_test(
	false !== strpos( $rr_src, "'cashier' === \$kind ? 'cashier' : 'kitchen'" ),
	'the receipt kind is an allowlist coercion — any other value prints the kitchen receipt'
);
assert_test(
	false !== strpos( $rr_src, 'wp_die(' ) && false !== strpos( $rr_src, 'exit;' ),
	'a missing ticket dies with a message and the template exits (no fall-through rendering)'
);
assert_test(
	false !== strpos( $rr_src, 'lang="fa" dir="rtl"' ),
	'the printed receipt is an RTL Persian document'
);
assert_test(
	substr_count( $rr_src, 'esc_html' ) >= 6 && false === strpos( $rr_src, '<?php echo $card' ),
	'every dynamic print in the template goes through esc_html (no raw $card echoes)'
);
assert_test(
	false !== strpos( $rr_src, 'no-print' ) && false !== strpos( $rr_src, 'window.print()' ),
	'the print control exists and is kept off the paper (no-print)'
);

echo "\n--- P47. Kitchen sync bridge (KitchenTicketSync) ---\n";

assert_test(
	array( 'flavor_pay_at_counter', 'flavor_cod', 'flavor_card_on_delivery', 'cod' ) === KitchenTicketSync::OFFLINE_METHODS,
	'the offline-payment list (money already in hand) stays exactly the four registered methods'
);

// Live: while suspended, on_order_processed must short-circuit BEFORE any
// WooCommerce call — running it here, with no WC loaded at all, is the
// proof: had it reached wc_get_order() this suite would fatal.
KitchenTicketSync::suspend( true );
$sync = new KitchenTicketSync();
$sync->on_order_processed( 999999, array() );
assert_test( true, 'suspension short-circuits the checkout listener before any WC/DB touch (no fatal with WC absent)' );
KitchenTicketSync::suspend( false );

$ks_src = file_get_contents( __DIR__ . '/../../includes/Order/KitchenTicketSync.php' );
assert_test(
	(bool) preg_match( "/array\( 'cancelled', 'refunded', 'failed' \)/", $ks_src )
		&& false !== strpos( $ks_src, "\$update['kitchen_status'] = 'cancelled'" ),
	'cancelled/refunded/failed orders cancel the kitchen ticket (cooks stop cooking dead orders)'
);
assert_test(
	(bool) preg_match( "/array\( 'processing', 'completed', 'on-hold' \)/", $ks_src ),
	'a paid-but-ticketless order spawns its ticket late, on the paid statuses only'
);
assert_test(
	false !== strpos( $ks_src, "'woocommerce_checkout_order_processed'" )
		&& false !== strpos( $ks_src, "'woocommerce_payment_complete'" )
		&& false !== strpos( $ks_src, "'woocommerce_order_status_changed'" ),
	'the three WooCommerce hooks stay wired by their real names (drift = silent ticket death)'
);
assert_test(
	false !== strpos( $ks_src, 'KitchenTicketSync::suspend' ) || false !== strpos(
		file_get_contents( __DIR__ . '/../../includes/WooCommerce/CheckoutService.php' ),
		'KitchenTicketSync::suspend'
	),
	'the programmatic checkout path still suspends the legacy listener (metadata-after-create ordering)'
);
