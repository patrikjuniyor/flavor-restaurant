<?php
/**
 * Install and activate WooCommerce when Flavor Core is activated.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Flavor Core's required WooCommerce dependency.
 */
final class DependencyInstaller {

	/**
	 * WooCommerce's main plugin file.
	 */
	private const PLUGIN_FILE = 'woocommerce/woocommerce.php';

	/**
	 * WooCommerce's WordPress.org plugin slug.
	 */
	private const PLUGIN_SLUG = 'woocommerce';

	/**
	 * Ensure WooCommerce is installed and active.
	 *
	 * Called only after an administrator has explicitly activated Flavor Core.
	 *
	 * @param bool $network_wide Whether Flavor Core is being network-activated.
	 * @return true|\WP_Error
	 */
	public static function ensure_active( bool $network_wide = false ) {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		self::load_plugin_api();

		if ( \is_plugin_active( self::PLUGIN_FILE ) || ( \is_multisite() && \is_plugin_active_for_network( self::PLUGIN_FILE ) ) ) {
			return true;
		}

		if ( ! self::has_capability( 'activate_plugins' ) ) {
			return new \WP_Error(
				'flavor_woocommerce_activation_forbidden',
				__( 'این حساب اجازهٔ فعال‌سازی افزونه‌ها را ندارد. لطفاً WooCommerce را دستی فعال کنید.', 'flavor-core' )
			);
		}

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE ) ) {
			if ( ! self::has_capability( 'install_plugins' ) ) {
				return new \WP_Error(
					'flavor_woocommerce_install_forbidden',
					__( 'این حساب اجازهٔ نصب افزونه‌ها را ندارد. لطفاً WooCommerce را از بخش «افزونه‌ها ← افزودن» نصب و فعال کنید.', 'flavor-core' )
				);
			}

			$install_result = self::install_from_wordpress_org();
			if ( \is_wp_error( $install_result ) ) {
				return $install_result;
			}
		}

		$activation_result = \activate_plugin( self::PLUGIN_FILE, '', $network_wide );
		if ( \is_wp_error( $activation_result ) ) {
			return $activation_result;
		}

		return true;
	}

	/**
	 * Download WooCommerce from the official WordPress.org plugin directory.
	 *
	 * @return true|\WP_Error
	 */
	private static function install_from_wordpress_org() {
		self::load_upgrader_api();

		$plugin_info = \plugins_api(
			'plugin_information',
			array(
				'slug' => self::PLUGIN_SLUG,
			)
		);

		if ( \is_wp_error( $plugin_info ) ) {
			return $plugin_info;
		}

		if ( ! is_object( $plugin_info ) || empty( $plugin_info->download_link ) ) {
			return new \WP_Error(
				'flavor_woocommerce_download_unavailable',
				__( 'پیوند دریافت WooCommerce از مخزن رسمی وردپرس در دسترس نیست.', 'flavor-core' )
			);
		}

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $plugin_info->download_link );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		if ( true !== $result || ! file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE ) ) {
			return new \WP_Error(
				'flavor_woocommerce_install_failed',
				__( 'نصب خودکار WooCommerce کامل نشد. ممکن است سرور اجازهٔ نوشتن فایل‌ها یا اتصال به WordPress.org را ندهد.', 'flavor-core' )
			);
		}

		\wp_clean_plugins_cache( true );

		return true;
	}

	/**
	 * Load the plugin activation functions when called from an activation hook.
	 */
	private static function load_plugin_api(): void {
		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Load WordPress.org plugin lookup and upgrader APIs.
	 */
	private static function load_upgrader_api(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}

	/**
	 * Check a capability, allowing trusted WP-CLI activation as well.
	 *
	 * @param string $capability Capability to check.
	 */
	private static function has_capability( string $capability ): bool {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		return \current_user_can( $capability );
	}
}
