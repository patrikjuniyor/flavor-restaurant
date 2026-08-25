<?php
/**
 * Cash on delivery.
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
 * Class CashOnDelivery
 */
class CashOnDelivery extends AbstractOfflineGateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->boot(
			'flavor_cod',
			__( 'پرداخت در محل (نقد)', 'flavor-core' ),
			__( 'مبلغ را نقد به پیک بپردازید.', 'flavor-core' )
		);
	}
}
