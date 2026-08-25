<?php
/**
 * Plugin Name:       Flavor Core
 * Plugin URI:        https://github.com/flavor-restaurant/flavor-restaurant
 * Description:       منطق کسب‌وکار رستوران مستقیم: شعبه، میز و QR، سفارش سالن/بیرون‌بر/ارسال، داشبورد آشپزخانه، رزرو شمسی، OTP و تومان.
 * Version:           0.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.5
 * WC tested up to:   9.9
 * Author:            Flavor
 * Author URI:        https://github.com/flavor-restaurant/flavor-restaurant
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flavor-core
 * Domain Path:       /languages
 *
 * @package FlavorCore
 */

defined( 'ABSPATH' ) || exit;

define( 'FLAVOR_CORE_VERSION', '0.3.0' );
define( 'FLAVOR_CORE_DB_VERSION', '1.0.0' );
define( 'FLAVOR_CORE_FILE', __FILE__ );
define( 'FLAVOR_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'FLAVOR_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'FLAVOR_CORE_BASENAME', plugin_basename( __FILE__ ) );
define( 'FLAVOR_CORE_REST_NAMESPACE', 'flavor/v1' );

require_once FLAVOR_CORE_PATH . 'includes/Autoloader.php';

FlavorCore\Autoloader::register();

register_activation_hook( __FILE__, array( 'FlavorCore\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FlavorCore\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		FlavorCore\Plugin::instance()->boot();
	},
	11
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FLAVOR_CORE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FLAVOR_CORE_FILE, true );
		}
	}
);
