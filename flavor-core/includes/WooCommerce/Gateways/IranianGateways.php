<?php
/**
 * Iranian payment gateway catalog and live status.
 *
 * Iranian restaurants do not use Stripe/PayPal. They install a WooCommerce
 * gateway plugin from the local market — ZarinPal, Zibal, IDPay, PayPing,
 * bank gateways — and each one registers itself with a different id, a
 * different settings page and a different plugin folder. This class keeps
 * that knowledge in one place so the admin UI, the setup wizard, the Launch
 * Center and the dashboard all describe the same reality.
 *
 * It never processes a payment. Money still moves through the gateway plugin
 * and WooCommerce's own checkout; this is the map, the status and the wiring.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce\Gateways;

use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class IranianGateways
 */
final class IranianGateways {

	/**
	 * Settings keys that are chrome, not credentials. When every stored key is
	 * one of these the gateway has never been configured.
	 *
	 * @var string[]
	 */
	private const CHROME_KEYS = array(
		'enabled',
		'title',
		'description',
		'instructions',
		'order_button_text',
		'method_title',
		'method_description',
		'icon',
		'sandbox',
		'test_mode',
		'testmode',
		'test',
		'is_test',
	);

	/**
	 * Catalog cache.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $catalog_cache = null;

	/**
	 * Installed plugin headers cache.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $plugins_cache = null;

	/**
	 * Registered WooCommerce gateways cache.
	 *
	 * @var array<string, object>|null
	 */
	private static ?array $registered_cache = null;

	/**
	 * Clear every cache. Called after an install/activate/enable action so the
	 * page a merchant lands on shows the new state rather than the old one.
	 */
	public static function flush(): void {
		self::$catalog_cache    = null;
		self::$plugins_cache    = null;
		self::$registered_cache = null;
	}

	/**
	 * Supported Iranian gateways, in the order a restaurant owner is most
	 * likely to want them.
	 *
	 * Fields:
	 *  - name/latin   Display names.
	 *  - description  One sentence, Persian.
	 *  - type         psp | bank | bnpl | aggregator (see type_labels()).
	 *  - website      Official site, '' when unknown.
	 *  - install_slug WordPress.org slug, '' when not in the directory.
	 *  - folders      Known plugin folder names, used for detection.
	 *  - gateway_ids  Known WooCommerce gateway ids, used for matching.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function catalog(): array {
		if ( null !== self::$catalog_cache ) {
			return self::$catalog_cache;
		}

		$catalog = array(
			'zarinpal'     => array(
				'name'         => 'زرین‌پال',
				'latin'        => 'ZarinPal',
				'description'  => 'پرتکرارترین درگاه واسط ایران با پنل ساده، تسویه سریع و حالت تست.',
				'type'         => 'psp',
				'website'      => 'https://www.zarinpal.com',
				'install_slug' => 'zarinpal-woocommerce-payment-gateway',
				'folders'      => array( 'zarinpal-woocommerce-payment-gateway', 'zarinpal-woocommerce', 'zarinpal-gateway' ),
				'gateway_ids'  => array( 'WC_ZPal', 'zarinpal', 'WC_ZarinPal' ),
			),
			'zibal'        => array(
				'name'         => 'زیبال',
				'latin'        => 'Zibal',
				'description'  => 'درگاه واسط با API ساده، تسویه روزانه و پشتیبانی از پرداخت مستقیم.',
				'type'         => 'psp',
				'website'      => 'https://zibal.ir',
				'install_slug' => 'zibal-payment-gateway-for-woocommerce',
				'folders'      => array( 'zibal-payment-gateway-for-woocommerce', 'zibal-woocommerce', 'woocommerce-zibal' ),
				'gateway_ids'  => array( 'WC_Gateway_Zibal', 'zibal', 'wc_zibal' ),
			),
			'idpay'        => array(
				'name'         => 'آی‌دی‌پی',
				'latin'        => 'IDPay',
				'description'  => 'درگاه واسط با وب‌سرویس ساده و امکان توضیح دلخواه روی صفحه پرداخت.',
				'type'         => 'psp',
				'website'      => 'https://idpay.ir',
				'install_slug' => '',
				'folders'      => array( 'idpay-woocommerce', 'woo-idpay-gateway', 'idpay-woocommerce-plugin', 'idpay-gateway' ),
				'gateway_ids'  => array( 'idpay', 'WC_IDPay', 'wc_idpay' ),
			),
			'payping'      => array(
				'name'         => 'پی‌پینگ',
				'latin'        => 'PayPing',
				'description'  => 'درگاه واسط با تسویه چندبار در روز و پنل گزارش‌گیری کامل.',
				'type'         => 'psp',
				'website'      => 'https://payping.ir',
				'install_slug' => 'woo-payping-gateway',
				'folders'      => array( 'woo-payping-gateway', 'payping-gateway', 'payping' ),
				'gateway_ids'  => array( 'WC_payping', 'payping', 'wc_payping' ),
			),
			'payir'        => array(
				'name'         => 'پی‌آی‌آر',
				'latin'        => 'Pay.ir',
				'description'  => 'درگاه واسط ارزان با اتصال ساده؛ افزونهٔ رسمی داخل «ووکامرس فارسی» است.',
				'type'         => 'psp',
				'website'      => 'https://pay.ir',
				'install_slug' => '',
				'folders'      => array( 'pay-ir', 'payir', 'payir-woocommerce', 'pay-ir-woocommerce' ),
				'gateway_ids'  => array( 'payir', 'WC_PayIR', 'wc_payir', 'pay_ir' ),
			),
			'nextpay'      => array(
				'name'         => 'نکست‌پی',
				'latin'        => 'NextPay',
				'description'  => 'درگاه واسط با تمرکز روی سرعت و ابزارهای گزارش فروش.',
				'type'         => 'psp',
				'website'      => 'https://nextpay.org',
				'install_slug' => '',
				'folders'      => array( 'nextpay', 'nextpay-woocommerce', 'nextpay-gateway' ),
				'gateway_ids'  => array( 'nextpay', 'WC_NextPay', 'wc_nextpay' ),
			),
			'digipay'      => array(
				'name'         => 'دیجی‌پی',
				'latin'        => 'DigiPay',
				'description'  => 'خرید اعتباری و تقسیم مبلغ؛ مناسب سفارش‌های بزرگ‌تر.',
				'type'         => 'bnpl',
				'website'      => 'https://www.mydigipay.com',
				'install_slug' => 'digipay-payment-gateway',
				'folders'      => array( 'digipay-payment-gateway', 'digipay' ),
				'gateway_ids'  => array( 'digipay_fullpayment', 'digipay_installment', 'digipay_qr', 'digipay' ),
			),
			'snappay'      => array(
				'name'         => 'اسنپ‌پی',
				'latin'        => 'Snapp Pay',
				'description'  => 'خرید اعتباری اسنپ برای مشتریانی که فعلاً پرداخت نقدی ندارند.',
				'type'         => 'bnpl',
				'website'      => 'https://snapppay.ir',
				'install_slug' => '',
				'folders'      => array( 'snapp-pay', 'snapppay', 'snappay' ),
				'gateway_ids'  => array( 'snapppay', 'snapp_pay', 'WC_SnappPay' ),
			),
			'mellat'       => array(
				'name'         => 'بانک ملت',
				'latin'        => 'Bank Mellat',
				'description'  => 'درگاه مستقیم بانک ملت (به‌پرداخت) با تسویه فوری به حساب.',
				'type'         => 'bank',
				'website'      => 'https://www.bankmellat.ir',
				'install_slug' => 'mellat-woocommerce',
				'folders'      => array( 'mellat-woocommerce', 'bank-mellat' ),
				'gateway_ids'  => array( 'bankmellat', 'WC_Mellat', 'mellat', 'wc_mellat' ),
			),
			'parsian'      => array(
				'name'         => 'بانک پارسیان',
				'latin'        => 'Bank Parsian',
				'description'  => 'درگاه مستقیم پارسیان؛ مناسب رستوران‌هایی که حساب پارسیان دارند.',
				'type'         => 'bank',
				'website'      => 'https://www.parsian-bank.ir',
				'install_slug' => 'parsian-woocommerce',
				'folders'      => array( 'parsian-woocommerce', 'bank-parsian' ),
				'gateway_ids'  => array( 'bankparsian', 'WC_Parsian', 'parsian', 'wc_parsian' ),
			),
			'sadad'        => array(
				'name'         => 'سداد (بانک ملی)',
				'latin'        => 'Sadad',
				'description'  => 'درگاه مستقیم بانک ملی برای پذیرندگان دارای ترمینال سداد.',
				'type'         => 'bank',
				'website'      => 'https://www.sadad.co.ir',
				'install_slug' => 'sadad-payment',
				'folders'      => array( 'sadad-payment', 'sadad-woocommerce' ),
				'gateway_ids'  => array( 'sadad', 'WC_Sadad', 'wc_sadad' ),
			),
			'saman'        => array(
				'name'         => 'بانک سامان',
				'latin'        => 'Saman (SEP)',
				'description'  => 'درگاه مستقیم سامان/سپ برای پذیرندگان دارای درگاه اختصاصی.',
				'type'         => 'bank',
				'website'      => 'https://www.sep.ir',
				'install_slug' => '',
				'folders'      => array( 'saman-woocommerce', 'sep-woocommerce', 'saman-gateway' ),
				'gateway_ids'  => array( 'saman', 'WC_Saman', 'wc_saman', 'sep' ),
			),
			'asanpardakht' => array(
				'name'         => 'آسان پرداخت',
				'latin'        => 'Asan Pardakht',
				'description'  => 'درگاه پرداخت آسان‌پرداخت با پوشش کارت‌خوان و درگاه اینترنتی.',
				'type'         => 'psp',
				'website'      => 'https://asanpardakht.ir',
				'install_slug' => '',
				'folders'      => array( 'asan-pardakht', 'asanpardakht', 'asan-pardakht-woocommerce' ),
				'gateway_ids'  => array( 'asan', 'asanpardakht', 'WC_AsanPardakht', 'wc_asan' ),
			),
			'gateland'     => array(
				'name'         => 'گیت‌لند',
				'latin'        => 'Gateland',
				'description'  => 'درگاه هوشمند چندکاناله که چند بانک و واسط را پشت یک اتصال می‌آورد.',
				'type'         => 'aggregator',
				'website'      => '',
				'install_slug' => 'gateland',
				'folders'      => array( 'gateland' ),
				'gateway_ids'  => array( 'gateland' ),
			),
			'parsigate'    => array(
				'name'         => 'پارسی‌گیت',
				'latin'        => 'ParsiGate',
				'description'  => 'بستهٔ چنددرگاهی: زرین‌پال، زیبال، پی‌پینگ، دیجی‌پی، اسنپ‌پی و بانک‌ها.',
				'type'         => 'aggregator',
				'website'      => '',
				'install_slug' => 'parsigate',
				'folders'      => array( 'parsigate' ),
				// ParsiGate registers one gateway per bank/PSP driver (its own
				// ids), so matching those ids here would mark this card live
				// whenever any unrelated bank gateway is enabled. The card
				// therefore only tracks the package itself and links to its
				// settings for the per-driver setup.
				'gateway_ids'  => array( 'parsigate' ),
			),
		);

		/**
		 * Filter the Iranian gateway catalog.
		 *
		 * A site that uses a gateway this list does not know can add an entry
		 * with the same shape rather than waiting for a release.
		 *
		 * @param array<string, array<string, mixed>> $catalog Catalog keyed by slug.
		 */
		$catalog = apply_filters( 'flavor_core_iranian_gateways', $catalog );

		self::$catalog_cache = is_array( $catalog ) ? $catalog : array();

		return self::$catalog_cache;
	}

	/**
	 * Catalog keys.
	 *
	 * @return string[]
	 */
	public static function keys(): array {
		return array_keys( self::catalog() );
	}

	/**
	 * One catalog entry.
	 *
	 * @param string $key Catalog key.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $key ): ?array {
		$catalog = self::catalog();
		return isset( $catalog[ $key ] ) ? $catalog[ $key ] : null;
	}

	/**
	 * Persian labels for the catalog "type" field.
	 *
	 * @return array<string, string>
	 */
	public static function type_labels(): array {
		return array(
			'psp'        => __( 'درگاه واسط', 'flavor-core' ),
			'bank'       => __( 'درگاه بانکی', 'flavor-core' ),
			'bnpl'       => __( 'خرید اعتباری', 'flavor-core' ),
			'aggregator' => __( 'چند درگاهی', 'flavor-core' ),
		);
	}

	/**
	 * All installed plugins, keyed by plugin basename.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function installed_plugins(): array {
		if ( null !== self::$plugins_cache ) {
			return self::$plugins_cache;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			$admin_include = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $admin_include ) ) {
				require_once $admin_include;
			}
		}

		self::$plugins_cache = function_exists( 'get_plugins' ) ? (array) get_plugins() : array();

		return self::$plugins_cache;
	}

	/**
	 * Find the installed plugin file that belongs to a catalog entry.
	 *
	 * Matching by folder rather than by main file name survives the plugin
	 * author renaming their bootstrap file, which happens.
	 *
	 * @param array<string, mixed>                $entry   Catalog entry.
	 * @param array<string, array<string, mixed>> $plugins Installed plugins.
	 * @return string Plugin basename, or '' when not installed.
	 */
	private static function locate_plugin( array $entry, array $plugins ): string {
		$folders = isset( $entry['folders'] ) && is_array( $entry['folders'] ) ? $entry['folders'] : array();
		if ( array() === $folders ) {
			return '';
		}

		foreach ( array_keys( $plugins ) as $file ) {
			$file  = (string) $file;
			$parts = explode( '/', $file );
			$dir   = count( $parts ) > 1 ? strtolower( $parts[0] ) : strtolower( basename( $file, '.php' ) );

			foreach ( $folders as $folder ) {
				if ( strtolower( (string) $folder ) === $dir ) {
					return $file;
				}
			}
		}

		return '';
	}

	/**
	 * Is a plugin active?
	 *
	 * @param string $file Plugin basename.
	 * @return bool
	 */
	public static function plugin_is_active( string $file ): bool {
		if ( '' === $file ) {
			return false;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			$admin_include = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $admin_include ) ) {
				require_once $admin_include;
			}
		}
		if ( function_exists( 'is_plugin_active' ) ) {
			return (bool) is_plugin_active( $file );
		}

		$active = (array) get_option( 'active_plugins', array() );
		if ( in_array( $file, $active, true ) ) {
			return true;
		}

		// Multisite network-activated plugins.
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network[ $file ] );
		}

		return false;
	}

	/**
	 * Every gateway WooCommerce has registered, enabled or not, keyed by id.
	 *
	 * @return array<string, object>
	 */
	public static function registered_gateways(): array {
		if ( null !== self::$registered_cache ) {
			return self::$registered_cache;
		}

		$registered = array();

		if ( function_exists( 'WC' ) ) {
			$wc = WC();
			if ( is_object( $wc ) && method_exists( $wc, 'payment_gateways' ) ) {
				$factory = $wc->payment_gateways();
				$all     = array();

				if ( is_object( $factory ) && method_exists( $factory, 'payment_gateways' ) ) {
					$all = $factory->payment_gateways();
				} elseif ( is_object( $factory ) && method_exists( $factory, 'get_available_payment_gateways' ) ) {
					// Test doubles and older WooCommerce builds only expose the
					// available list; that is still enough to match ids.
					$all = $factory->get_available_payment_gateways();
				}

				if ( is_array( $all ) ) {
					foreach ( $all as $id => $gateway ) {
						if ( is_object( $gateway ) ) {
							$registered[ (string) $id ] = $gateway;
						}
					}
				}
			}
		}

		self::$registered_cache = $registered;

		return self::$registered_cache;
	}

	/**
	 * Registered gateway ids, lower-cased for case-insensitive matching.
	 *
	 * @return array<string, string> Lower-case id => real id.
	 */
	private static function registered_lookup(): array {
		$lookup = array();
		foreach ( array_keys( self::registered_gateways() ) as $id ) {
			$lookup[ strtolower( (string) $id ) ] = (string) $id;
		}
		return $lookup;
	}

	/**
	 * Real registered ids that belong to a catalog entry.
	 *
	 * @param string $key Catalog key.
	 * @return string[]
	 */
	public static function enabled_ids( string $key ): array {
		$entry = self::get( $key );
		if ( null === $entry ) {
			return array();
		}

		$lookup = self::registered_lookup();
		$ids    = array();

		foreach ( (array) $entry['gateway_ids'] as $candidate ) {
			$candidate = strtolower( (string) $candidate );
			if ( isset( $lookup[ $candidate ] ) ) {
				$ids[] = $lookup[ $candidate ];
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Stored settings of one WooCommerce gateway.
	 *
	 * @param string $gateway_id Registered gateway id.
	 * @return array<string, mixed>
	 */
	public static function gateway_settings( string $gateway_id ): array {
		if ( '' === $gateway_id ) {
			return array();
		}
		$settings = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Whether a gateway's settings contain anything a merchant typed in.
	 *
	 * Returns null when neither a yes nor a no can be justified: the gateway
	 * is not registered, or its plugin stores credentials somewhere this
	 * heuristic cannot see. Callers treat null as "unknown", not "missing".
	 *
	 * @param string $gateway_id Registered gateway id.
	 * @return bool|null
	 */
	public static function credentials_present( string $gateway_id ): ?bool {
		if ( '' === $gateway_id ) {
			return null;
		}

		$settings = self::gateway_settings( $gateway_id );
		if ( array() === $settings ) {
			return null;
		}

		foreach ( $settings as $key => $value ) {
			if ( in_array( (string) $key, self::CHROME_KEYS, true ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = array_filter(
					$value,
					static function ( $item ): bool {
						return is_scalar( $item ) && '' !== trim( (string) $item );
					}
				);
			}
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return true;
			}
			if ( is_array( $value ) && array() !== $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a gateway is running in sandbox/test mode.
	 *
	 * @param string $gateway_id Registered gateway id.
	 * @return bool
	 */
	public static function is_test_mode( string $gateway_id ): bool {
		$settings = self::gateway_settings( $gateway_id );
		foreach ( array( 'sandbox', 'test_mode', 'testmode', 'test', 'is_test' ) as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! is_scalar( $settings[ $key ] ) ) {
				continue;
			}
			$value = strtolower( trim( (string) $settings[ $key ] ) );
			if ( in_array( $value, array( 'yes', 'on', '1', 'true', 'sandbox' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Settings screen URL of one registered gateway.
	 *
	 * @param string $gateway_id Registered gateway id.
	 * @return string
	 */
	public static function settings_url( string $gateway_id ): string {
		if ( '' === $gateway_id ) {
			return admin_url( 'admin.php?page=wc-settings&tab=checkout' );
		}
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode( $gateway_id ) );
	}

	/**
	 * Plugin install URL (one click, WordPress.org).
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	public static function install_url( string $slug ): string {
		if ( '' === $slug || ! function_exists( 'wp_nonce_url' ) || ! current_user_can( 'install_plugins' ) ) {
			return '';
		}
		return wp_nonce_url(
			admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $slug ) ),
			'install-plugin_' . $slug
		);
	}

	/**
	 * Plugin activation URL.
	 *
	 * @param string $file Plugin basename.
	 * @return string
	 */
	public static function activate_url( string $file ): string {
		if ( '' === $file || ! function_exists( 'wp_nonce_url' ) || ! current_user_can( 'activate_plugins' ) ) {
			return '';
		}
		return wp_nonce_url(
			admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $file ) ),
			'activate-plugin_' . $file
		);
	}

	/**
	 * WordPress.org search URL for plugins missing from the directory.
	 *
	 * @param string $term Search term.
	 * @return string
	 */
	public static function search_url( string $term ): string {
		return admin_url( 'plugin-install.php?tab=search&type=term&s=' . rawurlencode( $term ) );
	}

	/**
	 * The merchant's preferred online gateway (catalog key), or ''.
	 *
	 * @return string
	 */
	public static function preferred_key(): string {
		if ( ! class_exists( Settings::class ) ) {
			return '';
		}
		$key = sanitize_key( (string) Settings::get( 'preferred_gateway', '' ) );
		return null === self::get( $key ) ? '' : $key;
	}

	/**
	 * Is the store currency one the Iranian gateways accept?
	 *
	 * @return bool
	 */
	public static function currency_supported(): bool {
		$currency = strtoupper( (string) get_option( 'woocommerce_currency', '' ) );
		return in_array( $currency, array( 'IRR', 'IRT', 'IRHR', 'IRHT' ), true );
	}

	/**
	 * Live status of every catalog entry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function status(): array {
		$catalog    = self::catalog();
		$plugins    = self::installed_plugins();
		$registered = self::registered_gateways();
		$lookup     = self::registered_lookup();
		$types      = self::type_labels();
		$preferred  = self::preferred_key();
		$statuses   = array();

		foreach ( $catalog as $key => $entry ) {
			$plugin_file = self::locate_plugin( $entry, $plugins );
			$installed   = '' !== $plugin_file;
			$active      = $installed && self::plugin_is_active( $plugin_file );

			$matched_ids = array();
			$enabled     = array();
			foreach ( (array) $entry['gateway_ids'] as $candidate ) {
				$needle = strtolower( (string) $candidate );
				if ( isset( $lookup[ $needle ] ) ) {
					$real_id       = $lookup[ $needle ];
					$matched_ids[] = $real_id;
					$gateway       = isset( $registered[ $real_id ] ) ? $registered[ $real_id ] : null;
					if ( is_object( $gateway ) && isset( $gateway->enabled ) && 'yes' === (string) $gateway->enabled ) {
						$enabled[] = $real_id;
					}
				}
			}
			$matched_ids = array_values( array_unique( $matched_ids ) );
			$enabled     = array_values( array_unique( $enabled ) );

			$primary     = array() !== $enabled ? $enabled[0] : ( array() !== $matched_ids ? $matched_ids[0] : '' );
			$configured  = '' !== $primary ? self::credentials_present( $primary ) : null;
			$test_mode   = '' !== $primary ? self::is_test_mode( $primary ) : false;
			$is_enabled  = array() !== $enabled;
			$is_ready    = $is_enabled && false !== $configured;

			$statuses[ $key ] = array_merge(
				$entry,
				array(
					'key'            => (string) $key,
					'type_label'     => isset( $types[ $entry['type'] ] ) ? $types[ $entry['type'] ] : $entry['type'],
					'installed'      => $installed,
					'plugin_file'    => $plugin_file,
					'active'         => $active,
					'matched_ids'    => $matched_ids,
					'enabled'        => $is_enabled,
					'enabled_ids'    => $enabled,
					'gateway_id'     => $primary,
					'configured'     => $configured,
					'test_mode'      => $test_mode,
					'ready'          => $is_ready,
					'preferred'      => ( '' !== $preferred && $preferred === $key ),
					'install_url'    => ! $installed ? self::install_url( (string) $entry['install_slug'] ) : '',
					'activate_url'   => $installed && ! $active ? self::activate_url( $plugin_file ) : '',
					'settings_url'   => '' !== $primary ? self::settings_url( $primary ) : '',
					'search_url'     => self::search_url( (string) $entry['latin'] ),
				)
			);
		}

		return $statuses;
	}

	/**
	 * Numbers and names the dashboard / wizard can show without re-reading
	 * the catalog.
	 *
	 * @return array<string, mixed>
	 */
	public static function summary(): array {
		$statuses = self::status();
		$summary  = array(
			'total'      => count( $statuses ),
			'installed'  => 0,
			'active'     => 0,
			'enabled'    => 0,
			'ready'      => 0,
			'ready_names'   => array(),
			'enabled_names' => array(),
			'preferred'  => self::preferred_key(),
			'preferred_name' => '',
			'currency'   => (string) get_option( 'woocommerce_currency', '' ),
			'currency_ok' => self::currency_supported(),
		);

		foreach ( $statuses as $status ) {
			if ( $status['installed'] ) {
				++$summary['installed'];
			}
			if ( $status['active'] ) {
				++$summary['active'];
			}
			if ( $status['enabled'] ) {
				++$summary['enabled'];
				$summary['enabled_names'][] = $status['name'];
			}
			if ( $status['ready'] ) {
				++$summary['ready'];
				$summary['ready_names'][] = $status['name'];
			}
			if ( $status['preferred'] ) {
				$summary['preferred_name'] = $status['name'];
			}
		}

		return $summary;
	}
}
