<?php
/**
 * Shared offline (no-redirect) gateway.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce\Gateways;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WC_Payment_Gateway' ) ) {
	return;
}

/**
 * Class AbstractOfflineGateway
 */
abstract class AbstractOfflineGateway extends \WC_Payment_Gateway {

	/**
	 * Order status after place (on-hold = kitchen sees it, unpaid).
	 */
	protected string $paid_status = 'on-hold';

	/**
	 * Common setup.
	 *
	 * @param string $id    Method id.
	 * @param string $title Title.
	 * @param string $desc  Description.
	 */
	protected function boot( string $id, string $title, string $desc ): void {
		$this->id                 = $id;
		$this->method_title       = $title;
		$this->method_description = $desc;
		$this->has_fields         = false;
		$this->title              = $title;
		$this->description        = $desc;
		$this->enabled            = 'yes';
		$this->supports           = array( 'products' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $order_id Order.
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}
		$order->update_status( $this->paid_status, __( 'پرداخت آفلاین رستوران مستقیم.', 'flavor-core' ) );
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
