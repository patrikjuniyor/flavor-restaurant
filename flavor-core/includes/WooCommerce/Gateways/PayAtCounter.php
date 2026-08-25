<?php
/**
 * Pay at counter — dine-in / takeaway.
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
 * Class PayAtCounter
 */
class PayAtCounter extends AbstractOfflineGateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->boot(
			'flavor_pay_at_counter',
			__( 'پرداخت پای صندوق', 'flavor-core' ),
			__( 'مبلغ را هنگام تحویل در سالن یا پیشخوان پرداخت کنید.', 'flavor-core' )
		);
	}
}
