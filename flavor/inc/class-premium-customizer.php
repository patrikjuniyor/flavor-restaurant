<?php
/**
 * Customizer surface for the premium layer.
 *
 * Every added feature has an off switch: a restaurant owner who does not want a
 * popup, a dock or a mega menu should not have to edit code to remove it.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Premium_Customizer
 */
class Premium_Customizer {

	/**
	 * Panel id.
	 */
	const PANEL = 'flavor_panel_premium';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'customize_register', array( self::class, 'register' ), 20 );
	}

	/**
	 * Build the panel.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	public static function register( $wp_customize ): void {
		if ( ! method_exists( $wp_customize, 'add_panel' ) ) {
			return;
		}

		$wp_customize->add_panel(
			self::PANEL,
			array(
				'title'       => __( 'ویژگی‌های پیشرفته Flavor', 'flavor' ),
				'description' => __( 'حالت شب، مگامنو، ابزارهای فروش، شمارش معکوس، عضویت خبرنامه و رضایت کوکی.', 'flavor' ),
				'priority'    => 8,
			)
		);

		self::appearance( $wp_customize );
		self::navigation( $wp_customize );
		self::shopping( $wp_customize );
		self::engagement( $wp_customize );
		self::dock( $wp_customize );
		self::locations( $wp_customize );
	}

	/**
	 * Night scheme + wishlist.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	private static function appearance( $wp_customize ): void {
		$wp_customize->add_section(
			'flavor_section_scheme',
			array(
				'title' => __( 'حالت شب و علاقه‌مندی‌ها', 'flavor' ),
				'panel' => self::PANEL,
			)
		);

		self::checkbox( $wp_customize, 'flavor_color_scheme_enable', 'flavor_section_scheme', __( 'فعال‌بودن حالت شب/روز', 'flavor' ), 'yes' );
		self::select(
			$wp_customize,
			'flavor_color_scheme',
			'flavor_section_scheme',
			__( 'حالت پیش‌فرض سایت', 'flavor' ),
			'auto',
			array(
				'auto'  => __( 'خودکار (پیروی از تنظیم دستگاه بازدیدکننده)', 'flavor' ),
				'light' => __( 'روشن', 'flavor' ),
				'dark'  => __( 'شب', 'flavor' ),
			)
		);
		self::checkbox( $wp_customize, 'flavor_color_scheme_toggle', 'flavor_section_scheme', __( 'نمایش دکمهٔ تغییر حالت برای بازدیدکننده', 'flavor' ), 'yes' );
		self::checkbox( $wp_customize, 'flavor_wishlist_enable', 'flavor_section_scheme', __( 'فعال‌بودن علاقه‌مندی‌ها (بدون افزونه)', 'flavor' ), 'yes' );
	}

	/**
	 * Mega menu.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	private static function navigation( $wp_customize ): void {
		$wp_customize->add_section(
			'flavor_section_mega',
			array(
				'title'       => __( 'مگامنو', 'flavor' ),
				'panel'       => self::PANEL,
				'description' => __( 'شاخه‌هایی که فرزند زیادی دارند به پنل چندستونی تبدیل می‌شوند. با گذاشتن کلاس «mega-menu» روی یک آیتم، آن را دستی هم می‌توان مگامنو کرد.', 'flavor' ),
			)
		);

		self::checkbox( $wp_customize, 'flavor_mega_enable', 'flavor_section_mega', __( 'فعال‌بودن مگامنو در منوی اصلی', 'flavor' ), 'yes' );
		self::select(
			$wp_customize,
			'flavor_mega_columns',
			'flavor_section_mega',
			__( 'تعداد ستون‌ها', 'flavor' ),
			3,
			array( 2 => '۲', 3 => '۳', 4 => '۴' )
		);
		self::number( $wp_customize, 'flavor_mega_threshold', 'flavor_section_mega', __( 'حداقل تعداد زیرمنو برای مگامنو شدن', 'flavor' ), 5, 2, 20 );
	}

	/**
	 * Cart, quick view, free delivery, badges.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	private static function shopping( $wp_customize ): void {
		$wp_customize->add_section(
			'flavor_section_shopping',
			array(
				'title' => __( 'ابزارهای فروش', 'flavor' ),
				'panel' => self::PANEL,
			)
		);

		self::checkbox( $wp_customize, 'flavor_sticky_buy_enable', 'flavor_section_shopping', __( 'نوار چسبان «افزودن به سبد» در صفحهٔ محصول', 'flavor' ), 'yes' );
		self::checkbox( $wp_customize, 'flavor_quick_view_enable', 'flavor_section_shopping', __( 'نمای سریع روی کارت‌های فروشگاه', 'flavor' ), 'yes' );

		self::number( $wp_customize, 'flavor_free_delivery_threshold', 'flavor_section_shopping', __( 'سقف ارسال رایگان (به تومان، ۰ = خاموش)', 'flavor' ), 0, 0, 100000000 );
		self::checkbox( $wp_customize, 'flavor_free_delivery_detect', 'flavor_section_shopping', __( 'اگر خالی بود، از قانون ارسال رایگان ووکامرس خوانده شود', 'flavor' ), 'yes' );

		self::checkbox( $wp_customize, 'flavor_trust_badges_enable', 'flavor_section_shopping', __( 'نمایش نشان‌های اعتماد', 'flavor' ), 'yes' );
		self::checkbox( $wp_customize, 'flavor_trust_badges_single', 'flavor_section_shopping', __( 'نمایش نشان‌ها زیر دکمهٔ خرید محصول', 'flavor' ), 'yes' );
		self::textarea(
			$wp_customize,
			'flavor_trust_badges',
			'flavor_section_shopping',
			__( 'هر خط یک نشان: آیکون|متن', 'flavor' ),
			implode(
				"\n",
				array(
					'shield|پرداخت امن از طریق درگاه بانکی',
					'truck|ارسال با بستهٔ حرارتی و کنترل کیفیت',
					'leaf|مواد اولیهٔ تازه، تهیهٔ روز',
					'clock|پشتیبانی و پیگیری سفارش',
				)
			),
			'flavor_sanitize_badges',
			__( 'آیکون‌های مجاز: shield, truck, leaf, clock, star, tag, check, lock, info, heart', 'flavor' )
		);
	}

	/**
	 * Countdown, newsletter, cookies.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	private static function engagement( $wp_customize ): void {
		$wp_customize->add_section(
			'flavor_section_engagement',
			array(
				'title' => __( 'شمارش معکوس، خبرنامه و کوکی', 'flavor' ),
				'panel' => self::PANEL,
			)
		);

		self::checkbox( $wp_customize, 'flavor_offer_countdown_enable', 'flavor_section_engagement', __( 'فعال‌بودن شمارش معکوس پیشنهاد', 'flavor' ), 'yes' );
		self::text(
			$wp_customize,
			'flavor_offer_deadline',
			'flavor_section_engagement',
			__( 'پایان پیشنهاد ویژه (۱۴۰۵-۰۸-۳۰ ۲۳:۵۹ یا 2026-11-21 23:59)', 'flavor' ),
			'',
			'flavor_sanitize_deadline',
			__( 'خالی بگذارید تا شمارش معکوس روی محصولات زمان‌دار، از تاریخ پایان حراج خود ووکامرس خوانده شود.', 'flavor' )
		);

		self::checkbox( $wp_customize, 'flavor_newsletter_enable', 'flavor_section_engagement', __( 'فعال‌بودن فرم عضویت خبرنامه', 'flavor' ), 'yes' );
		self::select(
			$wp_customize,
			'flavor_newsletter_mode',
			'flavor_section_engagement',
			__( 'زمان نمایش فرم عضویت', 'flavor' ),
			'exit',
			array(
				'exit'  => __( 'هنگام قصد خروج (دسکتاپ) — در موبایل با تأخیر', 'flavor' ),
				'delay' => __( 'بعد از چند ثانیه', 'flavor' ),
			)
		);
		self::number( $wp_customize, 'flavor_newsletter_delay', 'flavor_section_engagement', __( 'تأخیر یا زمان موبایل (ثانیه)', 'flavor' ), 30, 5, 300 );
		self::text( $wp_customize, 'flavor_newsletter_title', 'flavor_section_engagement', __( 'عنوان فرم عضویت', 'flavor' ), __( 'اول از همه، تازه‌های منو را بدانید', 'flavor' ) );
		self::textarea( $wp_customize, 'flavor_newsletter_text', 'flavor_section_engagement', __( 'متن فرم عضویت', 'flavor' ), __( 'منوی فصل، پیشنهادهای ویژه و تخفیف‌های مناسبتی را یک هفته زودتر دریافت کنید. هر وقت خواستید لغو کنید.', 'flavor' ) );
		self::text( $wp_customize, 'flavor_newsletter_code', 'flavor_section_engagement', __( 'کد تخفیف تشکر (اختیاری)', 'flavor' ), '' );
		self::checkbox( $wp_customize, 'flavor_newsletter_notify', 'flavor_section_engagement', __( 'ارسال ایمیل اطلاع به مدیر برای هر عضو جدید', 'flavor' ), 'no' );

		self::checkbox( $wp_customize, 'flavor_cookie_notice_enable', 'flavor_section_engagement', __( 'نمایش نوار رضایت کوکی', 'flavor' ), 'yes' );
		self::textarea( $wp_customize, 'flavor_cookie_text', 'flavor_section_engagement', __( 'متن نوار کوکی', 'flavor' ), '' );
	}

	/**
	 * Floating dock.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	private static function dock( $wp_customize ): void {
		$wp_customize->add_section(
			'flavor_section_dock',
			array(
				'title' => __( 'داک شناور', 'flavor' ),
				'panel' => self::PANEL,
			)
		);

		self::checkbox( $wp_customize, 'flavor_dock_enable', 'flavor_section_dock', __( 'نمایش دکمه‌های شناور', 'flavor' ), 'yes' );
		self::checkbox( $wp_customize, 'flavor_dock_top', 'flavor_section_dock', __( 'دکمهٔ بازگشت به بالا با حلقهٔ پیشرفت', 'flavor' ), 'yes' );
		self::checkbox( $wp_customize, 'flavor_dock_call', 'flavor_section_dock', __( 'دکمهٔ تماس تلفنی', 'flavor' ), 'yes' );
		self::checkbox( $wp_customize, 'flavor_dock_whatsapp', 'flavor_section_dock', __( 'دکمهٔ واتس‌اپ (از شمارهٔ تماس ساخته می‌شود)', 'flavor' ), 'yes' );
	}

	/**
	 * Branch map.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	private static function locations( $wp_customize ): void {
		$wp_customize->add_section(
			'flavor_section_map',
			array(
				'title'       => __( 'نقشهٔ شعبه‌ها', 'flavor' ),
				'panel'       => self::PANEL,
				'description' => __( 'نقشه بدون کلید API و بدون درخواست به سرور گوگل نمایش داده می‌شود و فقط وقتی دیده می‌شود که در قاب باشد.', 'flavor' ),
			)
		);

		self::checkbox( $wp_customize, 'flavor_branch_map_enable', 'flavor_section_map', __( 'نمایش نقشهٔ جاسازی‌شده در صفحهٔ شعبه', 'flavor' ), 'yes' );
	}

	/**
	 * Add a checkbox setting + control.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 * @param string                $id           Setting id.
	 * @param string                $section      Section id.
	 * @param string                $label        Label.
	 * @param string                $default      Default value.
	 */
	public static function checkbox( $wp_customize, string $id, string $section, string $label, string $default = 'yes' ): void {
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $label,
				'section' => $section,
				'type'    => 'checkbox',
			)
		);
	}

	/**
	 * Text setting.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 * @param string                $id           Setting id.
	 * @param string                $section      Section id.
	 * @param string                $label        Label.
	 * @param string                $default      Default.
	 * @param string                $sanitize     Sanitize callback.
	 * @param string                $description  Description.
	 */
	public static function text( $wp_customize, string $id, string $section, string $label, string $default = '', string $sanitize = 'sanitize_text_field', string $description = '' ): void {
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => $sanitize ) );
		$wp_customize->add_control(
			$id,
			array(
				'label'       => $label,
				'section'     => $section,
				'type'        => 'text',
				'description' => $description,
			)
		);
	}

	/**
	 * Number setting.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 * @param string                $id           Setting id.
	 * @param string                $section      Section id.
	 * @param string                $label        Label.
	 * @param int                   $default      Default.
	 * @param int                   $min          Minimum.
	 * @param int                   $max          Maximum.
	 */
	public static function number( $wp_customize, string $id, string $section, string $label, int $default, int $min, int $max ): void {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $default,
				'sanitize_callback' => static function ( $value ) use ( $min, $max, $default ) {
					$value = absint( $value );
					return ( $value < $min || $value > $max ) ? $default : $value;
				},
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $label,
				'section' => $section,
				'type'    => 'number',
				'input_attrs' => array(
					'min' => $min,
					'max' => $max,
				),
			)
		);
	}

	/**
	 * Select setting.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 * @param string                $id           Setting id.
	 * @param string                $section      Section id.
	 * @param string                $label        Label.
	 * @param mixed                 $default      Default.
	 * @param array<mixed>          $choices      Choices.
	 */
	public static function select( $wp_customize, string $id, string $section, string $label, $default, array $choices ): void {
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $label,
				'section' => $section,
				'type'    => 'select',
				'choices' => $choices,
			)
		);
	}

	/**
	 * Textarea setting.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 * @param string                $id           Setting id.
	 * @param string                $section      Section id.
	 * @param string                $label        Label.
	 * @param string                $default      Default.
	 * @param string                $sanitize     Sanitize callback.
	 * @param string                $description  Description.
	 */
	public static function textarea( $wp_customize, string $id, string $section, string $label, string $default = '', string $sanitize = 'sanitize_textarea_field', string $description = '' ): void {
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => $sanitize ) );
		$wp_customize->add_control(
			$id,
			array(
				'label'       => $label,
				'section'     => $section,
				'type'        => 'textarea',
				'description' => $description,
			)
		);
	}

	/**
	 * Keep only well-formed `icon|label` lines, at most six of them.
	 *
	 * @param mixed $value Raw textarea.
	 * @return string
	 */
	public static function sanitize_badges( $value ): string {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		$out   = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );

			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$label = sanitize_text_field( $parts[1] ?? $parts[0] );

			if ( '' === $label ) {
				continue;
			}

			$icon = sanitize_key( $parts[0] );
			$icon = in_array( $icon, array( 'shield', 'truck', 'leaf', 'clock', 'star', 'tag', 'check', 'lock', 'info', 'heart' ), true ) ? $icon : 'check';

			$out[] = $icon . '|' . $label;

			if ( count( $out ) >= 6 ) {
				break;
			}
		}

		return implode( "\n", $out );
	}

	/**
	 * Normalise a campaign deadline, or drop it when it is not a date.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_deadline( $value ): string {
		$value = trim( sanitize_text_field( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		$stamp = Engagement::parse_deadline( $value );

		if ( $stamp <= 0 ) {
			return '';
		}

		try {
			$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
			$date     = new \DateTime( '@' . $stamp );
			$date->setTimezone( $timezone );
		} catch ( \Exception $error ) {
			unset( $error );
			return '';
		}

		return $date->format( 'Y-m-d H:i' );
	}
}
