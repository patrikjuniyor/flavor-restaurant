<?php
/**
 * Theme Customizer — Enterprise Restaurant Design & Content Controls.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Customizer
 */
class Customizer {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'customize_register', array( self::class, 'register' ) );
		add_action( 'wp_head', array( self::class, 'head_css' ), 20 );
	}

	/**
	 * Register panels, sections, settings and controls.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer Manager.
	 */
	public static function register( $wp_customize ): void {
		// -------------------------------------------------------------
		// Panel 1: Design System & Presets
		// -------------------------------------------------------------
		$wp_customize->add_panel(
			'flavor_panel_design',
			array(
				'title'       => __( 'طراحی و استایل رستوران', 'flavor' ),
				'description' => __( 'پوسته‌های آماده، پالت رنگ، تایپوگرافی و ساختار ظاهری سایت', 'flavor' ),
				'priority'    => 20,
			)
		);

		// Section: Preset Selection
		$wp_customize->add_section(
			'flavor_section_preset',
			array(
				'title' => __( 'پوسته‌های آماده (Presets)', 'flavor' ),
				'panel' => 'flavor_panel_design',
			)
		);

		$preset_choices = array();
		foreach ( Design::skins() as $slug => $data ) {
			$preset_choices[ $slug ] = $data['title'] . ' — ' . $data['desc'];
		}

		$wp_customize->add_setting( 'flavor_skin', array( 'default' => 'modern-restaurant', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control(
			'flavor_skin',
			array(
				'label'       => __( 'انتخاب پوسته رستوران', 'flavor' ),
				'description' => __( 'با تغییر پوسته، رنگ‌بندی، فونت و استایل کلی رستوران هماهنگ می‌شود.', 'flavor' ),
				'section'     => 'flavor_section_preset',
				'type'        => 'select',
				'choices'     => $preset_choices,
			)
		);

		// Section: Colors
		$wp_customize->add_section(
			'flavor_section_colors',
			array(
				'title' => __( 'پالت رنگ اختصاصی', 'flavor' ),
				'panel' => 'flavor_panel_design',
			)
		);

		$colors = array(
			'primary'     => __( 'رنگ اصلی برند (Primary)', 'flavor' ),
			'secondary'   => __( 'رنگ ثانویه (Secondary)', 'flavor' ),
			'accent'      => __( 'رنگ تاکید و بج‌ها (Accent)', 'flavor' ),
			'bg'          => __( 'پس‌زمینه سایت (Background)', 'flavor' ),
			'surface'     => __( 'سطح کارت‌ها (Card Surface)', 'flavor' ),
			'surface_alt' => __( 'سطح متناوب (Alternate Surface)', 'flavor' ),
			'ink'         => __( 'رنگ متن اصلی (Text Color)', 'flavor' ),
			'muted'       => __( 'رنگ متن فرعی (Muted Text)', 'flavor' ),
			'line'        => __( 'رنگ خطوط و کادرها (Border Line)', 'flavor' ),
		);

		foreach ( $colors as $key => $label ) {
			$wp_customize->add_setting( 'flavor_' . $key, array( 'default' => '', 'sanitize_callback' => 'sanitize_hex_color' ) );
			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					'flavor_' . $key,
					array(
						'label'   => $label,
						'section' => 'flavor_section_colors',
					)
				)
			);
		}

		// Section: Typography & Layout
		$wp_customize->add_section(
			'flavor_section_layout',
			array(
				'title' => __( 'تایپوگرافی و گوشه‌ها', 'flavor' ),
				'panel' => 'flavor_panel_design',
			)
		);

		$wp_customize->add_setting( 'flavor_radius', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			'flavor_radius',
			array(
				'label'   => __( 'گردی گوشه کارت‌ها', 'flavor' ),
				'section' => 'flavor_section_layout',
				'type'    => 'select',
				'choices' => array(
					''        => __( 'پیش‌فرض پوسته', 'flavor' ),
					'0px'     => __( 'تیز و بدون انحنا (0px)', 'flavor' ),
					'8px'     => __( 'انحنای ملایم (8px)', 'flavor' ),
					'16px'    => __( 'انحنای استاندارد مدرن (16px)', 'flavor' ),
					'24px'    => __( 'کاملاً گرد و حبابی (24px)', 'flavor' ),
				),
			)
		);

		$wp_customize->add_setting( 'flavor_btn_radius', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			'flavor_btn_radius',
			array(
				'label'   => __( 'استایل دکمه‌ها', 'flavor' ),
				'section' => 'flavor_section_layout',
				'type'    => 'select',
				'choices' => array(
					''         => __( 'پیش‌فرض پوسته', 'flavor' ),
					'6px'      => __( 'دکمه مربعی ملایم (6px)', 'flavor' ),
					'12px'     => __( 'دکمه مدرن گرد (12px)', 'flavor' ),
					'9999px'   => __( 'دکمه کپسولی کامل (Pill 9999px)', 'flavor' ),
				),
			)
		);

		$wp_customize->add_setting( 'flavor_container_width', array( 'default' => '1240px', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			'flavor_container_width',
			array(
				'label'   => __( 'حداکثر عرض محتوا', 'flavor' ),
				'section' => 'flavor_section_layout',
				'type'    => 'select',
				'choices' => array(
					'1140px' => __( 'جمع‌وجور (1140px)', 'flavor' ),
					'1240px' => __( 'استاندارد (1240px)', 'flavor' ),
					'1360px' => __( 'عریض مدرن (1360px)', 'flavor' ),
					'1440px' => __( 'تمام صفحه لوکس (1440px)', 'flavor' ),
				),
			)
		);

		// -------------------------------------------------------------
		// Panel 2: Header & Navigation
		// -------------------------------------------------------------
		$wp_customize->add_section(
			'flavor_section_header',
			array(
				'title'    => __( 'هدر و ناوبری سایت', 'flavor' ),
				'priority' => 25,
			)
		);

		$wp_customize->add_setting( 'flavor_header_layout', array( 'default' => 'default', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control(
			'flavor_header_layout',
			array(
				'label'   => __( 'استایل و چینش هدر', 'flavor' ),
				'section' => 'flavor_section_header',
				'type'    => 'select',
				'choices' => array(
					'default'     => __( 'کلاسیک (لوگو راست / منو چپ)', 'flavor' ),
					'centered'    => __( 'لوگو وسط‌چین / منو دوطرفه', 'flavor' ),
					'minimal'     => __( 'مینیمال باریک', 'flavor' ),
					'transparent' => __( 'هدر شیشه‌ای شفاف روی تصویر هیرو', 'flavor' ),
				),
			)
		);

		$wp_customize->add_setting( 'flavor_header_sticky', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control(
			'flavor_header_sticky',
			array(
				'label'   => __( 'هدر چسبان هنگام اسکرول (Sticky Header)', 'flavor' ),
				'section' => 'flavor_section_header',
				'type'    => 'checkbox',
			)
		);

		$wp_customize->add_setting( 'flavor_header_topbar', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			'flavor_header_topbar',
			array(
				'label'       => __( 'نوار پیام بالای هدر (Announcement Bar)', 'flavor' ),
				'description' => __( 'مثال: ارسال رایگان سفارش‌های بالای ۳۰۰ هزار تومان در سراسر منطقه', 'flavor' ),
				'section'     => 'flavor_section_header',
				'type'        => 'text',
			)
		);

		// -------------------------------------------------------------
		// Panel 3: Hero Section
		// -------------------------------------------------------------
		$wp_customize->add_section(
			'flavor_section_hero',
			array(
				'title'    => __( 'بخش هیرو (بنر اصلی صفحه نخست)', 'flavor' ),
				'priority' => 30,
			)
		);

		$wp_customize->add_setting( 'flavor_hero_style', array( 'default' => 'fullscreen', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control(
			'flavor_hero_style',
			array(
				'label'   => __( 'استایل بخش هیرو', 'flavor' ),
				'section' => 'flavor_section_hero',
				'type'    => 'select',
				'choices' => array(
					'fullscreen' => __( 'تمام‌صفحه با تصویر پس‌زمینه و پوشش تاریک', 'flavor' ),
					'split'      => __( 'دوطرفه (متن در راست / تصویر شاخص در چپ)', 'flavor' ),
					'minimal'    => __( 'مینیمال وسط‌چین بدون شلوغی', 'flavor' ),
				),
			)
		);

		$wp_customize->add_setting( 'flavor_hero_badge', array( 'default' => __( 'طعم اصیل و ماندگار', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_badge', array( 'label' => __( 'بج بالای عنوان هیرو', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'text' ) );

		$wp_customize->add_setting( 'flavor_hero_title', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_title', array( 'label' => __( 'عنوان اصلی هیرو', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'text' ) );

		$wp_customize->add_setting( 'flavor_hero_text', array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control( 'flavor_hero_text', array( 'label' => __( 'توضیحات کوتاه هیرو', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'textarea' ) );

		$wp_customize->add_setting( 'flavor_hero_cta', array( 'default' => __( 'مشاهده منو و سفارش آنلاین', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_cta', array( 'label' => __( 'متن دکمه اول (سفارش)', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'text' ) );

		$wp_customize->add_setting( 'flavor_hero_cta2', array( 'default' => __( 'رزرو میز', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_cta2', array( 'label' => __( 'متن دکمه دوم (رزرو)', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'text' ) );

		// -------------------------------------------------------------
		// Panel 4: Reusable Homepage Sections
		// -------------------------------------------------------------
		$wp_customize->add_panel(
			'flavor_panel_sections',
			array(
				'title'       => __( 'بخش‌های صفحه نخست (Homepage Sections)', 'flavor' ),
				'description' => __( 'مدیریت و فعال‌سازی ۱۵ سکشن کاربردی صفحه اصلی', 'flavor' ),
				'priority'    => 35,
			)
		);

		// Section: Intro Highlights
		$wp_customize->add_section(
			'flavor_section_intro',
			array(
				'title' => __( '۱. معرفی و ویژگی‌های شاخص', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_intro_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_intro_enable', array( 'label' => __( 'نمایش بخش معرفی', 'flavor' ), 'section' => 'flavor_section_intro', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_intro_title', array( 'default' => __( 'چرا مهمان‌ها ما را انتخاب می‌کنند؟', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_intro_title', array( 'label' => __( 'عنوان بخش', 'flavor' ), 'section' => 'flavor_section_intro', 'type' => 'text' ) );

		// Section: Featured Dishes
		$wp_customize->add_section(
			'flavor_section_featured',
			array(
				'title' => __( '۲. پیشنهادهای ویژه سرآشپز', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_featured_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_featured_enable', array( 'label' => __( 'نمایش پیشنهادهای ویژه', 'flavor' ), 'section' => 'flavor_section_featured', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_featured_title', array( 'default' => __( 'محبوب‌ترین و لذیذترین‌های این هفته', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_featured_title', array( 'label' => __( 'عنوان بخش', 'flavor' ), 'section' => 'flavor_section_featured', 'type' => 'text' ) );

		// Section: Categories Showcase
		$wp_customize->add_section(
			'flavor_section_categories',
			array(
				'title' => __( '۳. دسته‌بندی‌های منو', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_cats_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_cats_enable', array( 'label' => __( 'نمایش کارت‌های دسته‌بندی', 'flavor' ), 'section' => 'flavor_section_categories', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_cats_title', array( 'default' => __( 'منوی غذا و نوشیدنی', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_cats_title', array( 'label' => __( 'عنوان دسته‌ها', 'flavor' ), 'section' => 'flavor_section_categories', 'type' => 'text' ) );

		// Section: Special Offers & Coupon Promo
		$wp_customize->add_section(
			'flavor_section_offers',
			array(
				'title' => __( '۴. تخفیف‌ها و پیشنهادهای طلایی', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_offers_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_offers_enable', array( 'label' => __( 'نمایش بنر تخفیف ویژه', 'flavor' ), 'section' => 'flavor_section_offers', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_offers_title', array( 'default' => __( '۱۵٪ تخفیف برای اولین سفارش آنلاین', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_offers_title', array( 'label' => __( 'عنوان آفر', 'flavor' ), 'section' => 'flavor_section_offers', 'type' => 'text' ) );
		$wp_customize->add_setting( 'flavor_offers_code', array( 'default' => 'FIRST15', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_offers_code', array( 'label' => __( 'کد تخفیف', 'flavor' ), 'section' => 'flavor_section_offers', 'type' => 'text' ) );

		// Section: Table Reservation CTA
		$wp_customize->add_section(
			'flavor_section_reservation',
			array(
				'title' => __( '۵. دعوت به رزرو آنلاین میز', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_res_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_res_enable', array( 'label' => __( 'نمایش بنر رزرو میز', 'flavor' ), 'section' => 'flavor_section_reservation', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_res_title', array( 'default' => __( 'لحظات ماندگار خود را پیشاپیش رزرو کنید', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_res_title', array( 'label' => __( 'عنوان بنر رزرو', 'flavor' ), 'section' => 'flavor_section_reservation', 'type' => 'text' ) );

		// Section: About & Story
		$wp_customize->add_section(
			'flavor_section_about',
			array(
				'title' => __( '۶. داستان و درباره ما', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_about_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_about_enable', array( 'label' => __( 'نمایش بخش درباره ما', 'flavor' ), 'section' => 'flavor_section_about', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_about', array( 'default' => '', 'sanitize_callback' => 'wp_kses_post' ) );
		$wp_customize->add_control( 'flavor_about', array( 'label' => __( 'متن داستان رستوران', 'flavor' ), 'section' => 'flavor_section_about', 'type' => 'textarea' ) );

		// Section: Gallery
		$wp_customize->add_section(
			'flavor_section_gallery',
			array(
				'title' => __( '۷. گالری تصاویر رستوران', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_gallery_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_gallery_enable', array( 'label' => __( 'نمایش گالری تصاویر', 'flavor' ), 'section' => 'flavor_section_gallery', 'type' => 'checkbox' ) );

		// Section: Testimonials
		$wp_customize->add_section(
			'flavor_section_testimonials',
			array(
				'title' => __( '۸. نظرات مهمان‌ها', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_testimonials_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_testimonials_enable', array( 'label' => __( 'نمایش بخش نظرات', 'flavor' ), 'section' => 'flavor_section_testimonials', 'type' => 'checkbox' ) );

		// Section: Working Hours & Location
		$wp_customize->add_section(
			'flavor_section_hours',
			array(
				'title' => __( '۹. ساعات کاری و موقعیت روی نقشه', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_hours_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_hours_enable', array( 'label' => __( 'نمایش ساعات کاری و آدرس', 'flavor' ), 'section' => 'flavor_section_hours', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_phone', array( 'default' => '02188001234', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_phone', array( 'label' => __( 'شماره تلفن تماس / سفارش', 'flavor' ), 'section' => 'flavor_section_hours', 'type' => 'text' ) );
		$wp_customize->add_setting( 'flavor_address', array( 'default' => 'تهران، خیابان ولیعصر، بالاتر از پارک‌وی', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_address', array( 'label' => __( 'نشانی شعبه اصلی', 'flavor' ), 'section' => 'flavor_section_hours', 'type' => 'text' ) );

		// -------------------------------------------------------------
		// Panel 5: Footer & Social Links
		// -------------------------------------------------------------
		$wp_customize->add_section(
			'flavor_section_footer',
			array(
				'title'    => __( 'پاورقی و شبکه‌های اجتماعی', 'flavor' ),
				'priority' => 40,
			)
		);

		$wp_customize->add_setting( 'flavor_social_instagram', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( 'flavor_social_instagram', array( 'label' => __( 'لینک اینستاگرام', 'flavor' ), 'section' => 'flavor_section_footer', 'type' => 'url' ) );

		$wp_customize->add_setting( 'flavor_social_telegram', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( 'flavor_social_telegram', array( 'label' => __( 'لینک تلگرام', 'flavor' ), 'section' => 'flavor_section_footer', 'type' => 'url' ) );

		$wp_customize->add_setting( 'flavor_social_whatsapp', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( 'flavor_social_whatsapp', array( 'label' => __( 'لینک واتس‌اپ', 'flavor' ), 'section' => 'flavor_section_footer', 'type' => 'url' ) );

		$wp_customize->add_setting( 'flavor_footer_copy', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_footer_copy', array( 'label' => __( 'متن کپی‌رایت اختصاصی', 'flavor' ), 'section' => 'flavor_section_footer', 'type' => 'text' ) );
	}

	/**
	 * Print dynamic CSS variables and layout rules into <head>.
	 */
	public static function head_css(): void {
		echo '<style id="flavor-tokens">' . Design::css_variables() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
