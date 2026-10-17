<?php
/**
 * Wires Iranian gateways into Flavor's checkout flow.
 *
 * Three jobs, all reversible:
 *
 *  1. The merchant's preferred gateway is offered first (classic checkout and
 *     the mobile app both read the filtered list in this order).
 *  2. Optionally, cash/counter methods disappear once an online gateway is
 *     actually available, so guests are not offered "pay cash" on a prepaid
 *     online order.
 *  3. A gateway chosen in the setup wizard but not ready yet (plugin missing
 *     or merchant code empty) is remembered as "pending" and switched on by
 *     itself the moment its credentials appear.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce\Gateways;

use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class IranianGatewayManager
 */
class IranianGatewayManager {

	/**
	 * Option holding the gateway that is waiting for install/credentials.
	 */
	public const PENDING_OPTION = 'flavor_core_gateway_pending';

	/**
	 * Flavor's own offline methods.
	 *
	 * @return string[]
	 */
	public static function offline_ids(): array {
		return array( 'flavor_pay_at_counter', 'flavor_cod', 'flavor_card_on_delivery' );
	}

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'prioritize' ), 20 );
		add_action( 'admin_init', array( self::class, 'maybe_finalize_pending' ) );
	}

	/**
	 * Put the preferred gateway first and, when the merchant asked for it,
	 * hide offline methods while an online gateway is available.
	 *
	 * @param array<string, object> $gateways Available gateways.
	 * @return array<string, object>
	 */
	public function prioritize( array $gateways ): array {
		$first = array();
		$key   = IranianGateways::preferred_key();

		if ( '' !== $key ) {
			$ids = array_map( 'strtolower', IranianGateways::enabled_ids( $key ) );
			foreach ( $gateways as $id => $gateway ) {
				if ( in_array( strtolower( (string) $id ), $ids, true ) ) {
					$first[ $id ] = $gateway;
				}
			}
			if ( array() !== $first ) {
				foreach ( $first as $id => $gateway ) {
					unset( $gateways[ $id ] );
				}
				$gateways = $first + $gateways;
			}
		}

		$hide_offline = class_exists( Settings::class ) && 'yes' === (string) Settings::get( 'hide_offline_when_online', 'no' );

		if ( array() !== $first && $hide_offline ) {
			foreach ( self::offline_ids() as $offline ) {
				unset( $gateways[ $offline ] );
			}
		}

		return $gateways;
	}

	/**
	 * Turn a WooCommerce gateway on or off without touching its other settings.
	 *
	 * @param string $gateway_id Registered gateway id.
	 * @param bool   $enabled    Desired state.
	 * @return bool
	 */
	public static function set_enabled( string $gateway_id, bool $enabled ): bool {
		if ( '' === $gateway_id ) {
			return false;
		}

		$option = IranianGateways::gateway_settings( $gateway_id );
		$option['enabled'] = $enabled ? 'yes' : 'no';

		return (bool) update_option( 'woocommerce_' . $gateway_id . '_settings', $option );
	}

	/**
	 * Remember a gateway that the merchant picked but that is not ready yet.
	 *
	 * @param string $key Catalog key.
	 */
	public static function mark_pending( string $key ): void {
		update_option(
			self::PENDING_OPTION,
			array(
				'key'   => $key,
				'since' => time(),
			),
			false
		);
	}

	/**
	 * The pending gateway, or an empty array.
	 *
	 * @return array{key:string,since:int}|array{}
	 */
	public static function pending(): array {
		$pending = get_option( self::PENDING_OPTION, array() );
		if ( ! is_array( $pending ) || empty( $pending['key'] ) ) {
			return array();
		}

		$key = sanitize_key( (string) $pending['key'] );
		if ( null === IranianGateways::get( $key ) ) {
			return array();
		}

		return array(
			'key'   => $key,
			'since' => isset( $pending['since'] ) ? absint( $pending['since'] ) : 0,
		);
	}

	/**
	 * Forget the pending gateway.
	 */
	public static function clear_pending(): void {
		delete_option( self::PENDING_OPTION );
	}

	/**
	 * Connect a catalog gateway.
	 *
	 * - enabled: registered, has credentials and is now switched on.
	 * - pending: saved as the preference, but the plugin or its merchant
	 *   credentials are still missing. Nothing is switched on, because a
	 *   gateway without credentials accepts an order and fails at the bank.
	 * - unknown: not a catalog key.
	 *
	 * @param string $key Catalog key.
	 * @return string enabled|pending|unknown
	 */
	public static function connect( string $key ): string {
		$entry = IranianGateways::get( $key );
		if ( null === $entry ) {
			return 'unknown';
		}

		if ( class_exists( Settings::class ) ) {
			Settings::update( array( 'preferred_gateway' => $key ) );
		}

		$ids = IranianGateways::enabled_ids( $key );
		if ( array() === $ids ) {
			self::mark_pending( $key );
			return 'pending';
		}

		$primary = $ids[0];
		if ( false === IranianGateways::credentials_present( $primary ) ) {
			self::mark_pending( $key );
			return 'pending';
		}

		self::set_enabled( $primary, true );
		self::clear_pending();

		return 'enabled';
	}

	/**
	 * Finish a pending connection once the plugin and its credentials exist.
	 *
	 * Runs on admin_init, which is also the request that saves the gateway's
	 * own settings page.
	 */
	public static function maybe_finalize_pending(): void {
		$pending = self::pending();
		if ( array() === $pending ) {
			return;
		}

		$key = $pending['key'];
		$ids = IranianGateways::enabled_ids( $key );
		if ( array() === $ids ) {
			return;
		}

		$primary = $ids[0];
		if ( false === IranianGateways::credentials_present( $primary ) ) {
			return;
		}

		self::set_enabled( $primary, true );
		if ( class_exists( Settings::class ) ) {
			Settings::update( array( 'preferred_gateway' => $key ) );
		}
		self::clear_pending();
	}
}
