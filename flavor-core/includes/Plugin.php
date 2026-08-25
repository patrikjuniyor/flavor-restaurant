<?php
/**
 * Main plugin singleton.
 *
 * @package FlavorCore
 */

namespace FlavorCore;

use FlavorCore\API\RestController;
use FlavorCore\Branch\BranchSeeder;
use FlavorCore\Database\Schema;
use FlavorCore\Delivery\ZoneAdmin;
use FlavorCore\Loyalty\DiscountManager;
use FlavorCore\Loyalty\LoyaltyAdmin;
use FlavorCore\Loyalty\PointsManager;
use FlavorCore\Menu\AvailabilityAdmin;
use FlavorCore\Menu\AvailabilityManager;
use FlavorCore\Menu\ScheduleAdmin;
use FlavorCore\Order\KitchenTicketSync;
use FlavorCore\Order\OrderModes;
use FlavorCore\Order\PhoneOrderAdmin;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Reservation\ReminderCron;
use FlavorCore\Reservation\ReservationAdmin;
use FlavorCore\SMS\SmsManager;
use FlavorCore\Support\Settings;
use FlavorCore\Table\TableAdmin;
use FlavorCore\WooCommerce\Currency;
use FlavorCore\WooCommerce\GatewayRegistrar;
use FlavorCore\WooCommerce\ProductModifiers;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether boot() already ran.
	 */
	private bool $booted = false;

	/**
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hidden constructor.
	 */
	private function __construct() {}

	/**
	 * Boot modules after plugins_loaded.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'flavor-core', false, dirname( FLAVOR_CORE_BASENAME ) . '/languages' );

		if ( ! $this->woocommerce_is_active() ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		Schema::maybe_upgrade();

		if ( (string) get_option( 'flavor_core_plugin_version', '' ) !== FLAVOR_CORE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'flavor_core_plugin_version', FLAVOR_CORE_VERSION, false );
		}

		( new Settings() )->hooks();
		( new BranchPostType() )->hooks();
		( new BranchSeeder() )->hooks();
		( new TableAdmin() )->hooks();
		( new ZoneAdmin() )->hooks();
		( new ReservationAdmin() )->hooks();
		( new ReminderCron() )->hooks();
		( new ScheduleAdmin() )->hooks();
		( new AvailabilityAdmin() )->hooks();
		( new PhoneOrderAdmin() )->hooks();
		( new LoyaltyAdmin() )->hooks();
		( new PointsManager() )->hooks();
		( new DiscountManager() )->hooks();
		( new Currency() )->hooks();
		( new ProductModifiers() )->hooks();
		( new GatewayRegistrar() )->hooks();
		( new OrderModes() )->hooks();
		( new KitchenTicketSync() )->hooks();
		( new SmsManager() )->hooks();
		( new AvailabilityManager() )->hooks();
		( new RestController() )->hooks();
		( new Admin\AdminMenus() )->hooks();
		( new Support\Rewrites() )->hooks();
		( new Support\Privacy() )->hooks();
		( new Support\CacheHints() )->hooks();

		/**
		 * Fires after Flavor Core has booted.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'flavor_core_booted', $this );
	}

	/**
	 * WooCommerce presence check.
	 */
	public function woocommerce_is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Admin notice when WooCommerce is missing.
	 */
	public function missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'افزونه Flavor Core برای اجرا به ووکامرس ۸.۵ یا بالاتر نیاز دارد.', 'flavor-core' );
		echo '</p></div>';
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent wakeup.
	 */
	public function __wakeup() {
		throw new \RuntimeException( 'Cannot unserialize singleton' );
	}
}
