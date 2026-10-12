<?php
/**
 * One stylesheet, one script and one payload for the premium layer.
 *
 * Loading everything from a single place keeps the request count flat: the
 * whole addition costs one CSS file and one deferred JS file, which is what a
 * performance-conscious paid theme does instead of one file per shortcode.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Premium_Assets
 */
class Premium_Assets {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'front' ), 40 );
	}

	/**
	 * Enqueue the assets and hand the script its configuration.
	 */
	public static function front(): void {
		$dark_css = '/assets/css/dark-mode.css';
		$css      = '/assets/css/premium.css';
		$js       = '/assets/js/premium.js';

		wp_enqueue_style(
			'flavor-dark-mode',
			FLAVOR_URI . $dark_css,
			array( 'flavor-main' ),
			(string) filemtime( FLAVOR_DIR . $dark_css )
		);

		wp_enqueue_style(
			'flavor-premium',
			FLAVOR_URI . $css,
			array( 'flavor-dark-mode' ),
			(string) filemtime( FLAVOR_DIR . $css )
		);

		wp_enqueue_script(
			'flavor-premium',
			FLAVOR_URI . $js,
			array( 'flavor-main-js' ),
			(string) filemtime( FLAVOR_DIR . $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script( 'flavor-premium', 'flavorPremium', self::payload() );
	}

	/**
	 * Multiplication factor from storage units to display units (Rial → Toman
	 * is 0.1), so the progress bar can talk about the number the visitor sees.
	 */
	public static function display_factor(): float {
		if ( ! class_exists( '\FlavorCore\WooCommerce\Currency' ) ) {
			return 1.0;
		}

		$storage = \FlavorCore\WooCommerce\Currency::storage_unit();
		$display = \FlavorCore\WooCommerce\Currency::display_unit();

		if ( $storage === $display ) {
			return 1.0;
		}

		$converted = \FlavorCore\WooCommerce\Currency::convert( 1000, $storage, $display );

		return $converted > 0 ? $converted / 1000 : 1.0;
	}

	/**
	 * Every label the premium script renders.
	 *
	 * Same rule as Enqueue::menu_strings(): the script holds no copy of its own,
	 * so a Persian literal in premium.js is a bug the parity test fails on. Keys
	 * are the JavaScript names, and tests/theme/test-js-i18n.php checks the
	 * placeholders against the call that fills them.
	 *
	 * @return array<string,string>
	 */
	public static function strings(): array {
		return array(
			'loading'            => __( 'در حال دریافت اطلاعات…', 'flavor' ),
			'error'              => __( 'دریافت اطلاعات ناموفق بود. صفحهٔ محصول را باز کنید.', 'flavor' ),
			'open'               => __( 'صفحهٔ کامل محصول', 'flavor' ),
			'qty'                => __( 'تعداد', 'flavor' ),
			'ended'              => __( 'این پیشنهاد به پایان رسید.', 'flavor' ),
			'saved'              => __( 'ثبت شد. ممنون!', 'flavor' ),
			'failed'             => __( 'ثبت نشد؛ بعداً تلاش کنید.', 'flavor' ),
			'close'              => __( 'بستن', 'flavor' ),
			'email_invalid'      => __( 'ایمیل معتبر وارد کنید.', 'flavor' ),
			'scheme_night'       => __( 'حالت شب فعال شد.', 'flavor' ),
			'scheme_day'         => __( 'حالت روز فعال شد.', 'flavor' ),
			'choose_combo'       => __( 'برای این غذا ابتدا ترکیبات را انتخاب کنید.', 'flavor' ),
			/* translators: %s values. */
			'wish_aria'          => __( 'افزودن %s به علاقه‌مندی‌ها', 'flavor' ),
			/* translators: %s values. */
			'free_ship_remaining' => __( '%s دیگر تا ارسال رایگان', 'flavor' ),
			'free_ship_done'     => __( 'ارسال این سفارش رایگان است.', 'flavor' ),
			'unit_day'           => __( 'روز', 'flavor' ),
			'unit_hour'          => __( 'ساعت', 'flavor' ),
			'unit_minute'        => __( 'دقیقه', 'flavor' ),
			'unit_second'        => __( 'ثانیه', 'flavor' ),
			'consent_all'        => __( 'انتخاب شما ثبت شد.', 'flavor' ),
			'consent_required'   => __( 'فقط کوکی ضروری فعال است.', 'flavor' ),
			/* translators: %s values. */
			'count_all'          => __( '%s غذا', 'flavor' ),
			/* translators: %1$s, %2$s values. */
			'count_of'           => __( '%1$s از %2$s غذا', 'flavor' ),
			'shown_suffix'       => __( 'نمایش داده می‌شود.', 'flavor' ),
			'copied'             => __( 'کپی شد', 'flavor' ),
			'copy_failed'        => __( 'کپی نشد', 'flavor' ),
			'address_copied'     => __( 'نشانی کپی شد.', 'flavor' ),
			'address_copy_hint'  => __( 'کپی نشد؛ دستی انتخاب کنید.', 'flavor' ),
		);
	}

	/**
	 * Script configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function payload(): array {
		return array(
			'ajax'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'nonce'     => wp_create_nonce( 'flavor_subscribe' ),
			'home'      => esc_url_raw( home_url( '/' ) ),
			'inCart'    => ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ),
			'factor'    => self::display_factor(),
			'freeShip'  => Shopping_Extras::free_delivery_state(),
			'wishlist'  => array( 'enabled' => Wishlist::enabled() ),
			'mega'      => array( 'enabled' => Nav_Mega::enabled() ),
			'quickView' => array(
				'enabled' => 'no' !== get_theme_mod( 'flavor_quick_view_enable', 'yes' ),
				'store'   => esc_url_raw( rest_url( 'wc/store/v1/products/' ) ),
			),
			'consent'   => array( 'key' => 'flavorConsent' ),
			'popup'     => array( 'key' => 'flavorPopupSeen' ),
			'i18n'      => self::strings(),
		);
	}
}
