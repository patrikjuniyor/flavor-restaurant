<?php
/**
 * Card reader at the door.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce\Gateways;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/AbstractOfflineGateway.php';

if ( ! class_exists( '\\WC_Payment_Gateway' ) ) {
	return;
}

/**
 * Class CardOnDelivery
 */
class CardOnDelivery extends AbstractOfflineGateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->boot(
			'flavor_card_on_delivery',
			__( 'کارت‌خوان دم در', 'flavor-core' ),
			__( 'پیک دستگاه کارت‌خوان می‌آورد.', 'flavor-core' )
		);
	}
}
