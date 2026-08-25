<?php
/**
 * Register Flavor offline gateways with WooCommerce.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce;

use FlavorCore\Support\Settings;
use FlavorCore\WooCommerce\Gateways\CardOnDelivery;
use FlavorCore\WooCommerce\Gateways\CashOnDelivery;
use FlavorCore\WooCommerce\Gateways\PayAtCounter;

defined( 'ABSPATH' ) || exit;

/**
 * Class GatewayRegistrar
 */
class GatewayRegistrar {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register' ) );
	}

	/**
	 * Append our classes.
	 *
	 * @param string[] $gateways Gateways.
	 * @return string[]
	 */
	public function register( array $gateways ): array {
		if ( 'yes' === Settings::get( 'pay_at_counter', 'yes' ) ) {
			$gateways[] = PayAtCounter::class;
		}
		if ( 'yes' === Settings::get( 'cash_on_delivery', 'yes' ) ) {
			$gateways[] = CashOnDelivery::class;
		}
		if ( 'yes' === Settings::get( 'card_on_delivery', 'yes' ) ) {
			$gateways[] = CardOnDelivery::class;
		}
		return $gateways;
	}
}
