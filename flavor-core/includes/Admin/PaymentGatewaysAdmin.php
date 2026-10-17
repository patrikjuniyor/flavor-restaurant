<?php
/**
 * Admin screen for Iranian payment gateways.
 *
 * One page that answers the only three questions a restaurant owner has:
 * which gateway do I use, is it actually connected, and what is still
 * missing. Every button either installs, activates, enables or deep-links
 * into the gateway's own WooCommerce settings — nothing here moves money.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Admin;

use FlavorCore\Support\Settings;
use FlavorCore\WooCommerce\Gateways\IranianGatewayManager;
use FlavorCore\WooCommerce\Gateways\IranianGateways;

defined( 'ABSPATH' ) || exit;

/**
 * Class PaymentGatewaysAdmin
 */
class PaymentGatewaysAdmin {

	/**
	 * Admin page slug.
	 */
	public const PAGE = 'flavor-payments';

	/**
	 * Nonce action for every button on the page.
	 */
	public const NONCE = 'flavor_gateway_action';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 12 );
		add_action( 'admin_post_flavor_gateway_action', array( $this, 'handle_action' ) );
		add_action( 'admin_notices', array( $this, 'pending_notice' ) );
	}

	/**
	 * Register the submenu under the Flavor Core top-level menu.
	 */
	public function menu(): void {
		add_submenu_page(
			'flavor-core',
			__( 'درگاه‌های پرداخت ایرانی', 'flavor-core' ),
			__( 'درگاه پرداخت', 'flavor-core' ),
			'flavor_manage_settings',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Can the current user change payment settings?
	 */
	public static function can_manage(): bool {
		return current_user_can( 'flavor_manage_settings' )
			|| current_user_can( 'manage_options' )
			|| current_user_can( 'manage_woocommerce' );
	}

	/**
	 * URL of this page.
	 */
	public static function page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Render the screen.
	 */
	public function render(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}

		$statuses = IranianGateways::status();
		$summary  = IranianGateways::summary();
		$settings = Settings::all();
		$pending  = IranianGatewayManager::pending();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice slug from our own redirect.
		$notice = isset( $_GET['flavor_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['flavor_notice'] ) ) : '';

		$view = FLAVOR_CORE_PATH . 'admin/views/payment-gateways.php';
		if ( is_readable( $view ) ) {
			include $view;
		}
	}

	/**
	 * Handle install/enable/prefer buttons.
	 *
	 * Install and activation themselves are ordinary WordPress admin links
	 * (nonce-protected by core). This handler covers the state changes core
	 * has no screen for: enabling a gateway, choosing the preferred one and
	 * cancelling a pending connection.
	 */
	public function handle_action(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}

		check_admin_referer( self::NONCE );

		$op     = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( (string) $_POST['op'] ) ) : '';
		$target = isset( $_POST['gateway'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['gateway'] ) ) : '';
		$notice = 'invalid';

		if ( in_array( $op, array( 'enable', 'disable' ), true ) ) {
			$real = $this->resolve_registered_id( $target );
			if ( '' !== $real ) {
				IranianGatewayManager::set_enabled( $real, 'enable' === $op );
				$notice = 'enable' === $op ? 'enabled' : 'disabled';
			}
		} elseif ( 'prefer' === $op ) {
			if ( null !== IranianGateways::get( $target ) ) {
				Settings::update( array( 'preferred_gateway' => $target ) );
				$notice = 'preferred';
			}
		} elseif ( 'connect' === $op ) {
			$result = IranianGatewayManager::connect( $target );
			if ( 'enabled' === $result ) {
				$notice = 'connected';
			} elseif ( 'pending' === $result ) {
				$notice = 'pending';
			}
		} elseif ( 'disconnect' === $op ) {
			if ( null !== IranianGateways::get( $target ) ) {
				IranianGatewayManager::clear_pending();
				Settings::update( array( 'preferred_gateway' => '' ) );
				$notice = 'disconnected';
			}
		} elseif ( 'save_settings' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() above.
			$preferred = isset( $_POST['preferred_gateway'] ) ? sanitize_key( wp_unslash( (string) $_POST['preferred_gateway'] ) ) : '';
			if ( '' !== $preferred && null === IranianGateways::get( $preferred ) ) {
				$preferred = '';
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$hide = empty( $_POST['hide_offline_when_online'] ) ? 'no' : 'yes';
			Settings::update(
				array(
					'preferred_gateway'          => $preferred,
					'hide_offline_when_online'   => $hide,
				)
			);
			$notice = 'saved';
		}

		IranianGateways::flush();

		wp_safe_redirect(
			add_query_arg( 'flavor_notice', $notice, self::page_url() )
		);
		exit;
	}

	/**
	 * Case-insensitive lookup of a registered gateway id.
	 *
	 * @param string $requested Requested id.
	 * @return string Real id, or ''.
	 */
	private function resolve_registered_id( string $requested ): string {
		foreach ( array_keys( IranianGateways::registered_gateways() ) as $id ) {
			if ( strtolower( (string) $id ) === strtolower( $requested ) ) {
				return (string) $id;
			}
		}
		return '';
	}

	/**
	 * Tell the owner that the gateway they picked in the wizard is not live
	 * yet, and link straight to the two screens that finish the job.
	 */
	public function pending_notice(): void {
		if ( ! self::can_manage() ) {
			return;
		}

		$pending = IranianGatewayManager::pending();
		if ( array() === $pending ) {
			return;
		}

		// The payments screen already shows this state in full.
		if ( isset( $_GET['page'] ) && self::PAGE === sanitize_key( wp_unslash( (string) $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$entry = IranianGateways::get( $pending['key'] );
		if ( null === $entry ) {
			return;
		}

		$status = IranianGateways::status();
		$state  = isset( $status[ $pending['key'] ] ) ? $status[ $pending['key'] ] : array();

		if ( ! empty( $state['enabled'] ) ) {
			return;
		}

		$message = ! empty( $state['active'] )
			? __( 'افزونه نصب و فعال است؛ فقط مرچنت کد/کلید API را در تنظیمات درگاه وارد کنید تا پرداخت آنلاین خودکار روشن شود.', 'flavor-core' )
			: __( 'افزونهٔ درگاه هنوز نصب یا فعال نشده است. با دکمهٔ زیر نصب را کامل کنید.', 'flavor-core' );

		echo '<div class="notice notice-warning">';
		echo '<p><strong>';
		/* translators: %s: gateway name. */
		echo esc_html( sprintf( __( 'پرداخت آنلاین «%s» هنوز آماده نیست.', 'flavor-core' ), (string) $entry['name'] ) );
		echo '</strong> ' . esc_html( $message ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( self::page_url() ) . '">';
		echo esc_html__( 'تکمیل اتصال درگاه', 'flavor-core' );
		echo '</a></p></div>';
	}
}
