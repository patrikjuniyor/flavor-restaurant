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
	 * How many images the homepage gallery grid lays out.
	 */
	public const GALLERY_SLOTS = 6;

	/**
	 * Register an image-upload setting plus its control in one step.
	 *
	 * Every section image used to be hard-coded to the active skin's demo
	 * art, so a restaurant could never show its own photos. These settings
	 * store a URL, matching what the demo importer already writes.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer manager.
	 * @param string                $id           Setting id.
	 * @param string                $section      Section id.
	 * @param string                $label        Control label.
	 * @param string                $description  Optional help text.
	 */
	private static function image_control( $wp_customize, string $id, string $section, string $label, string $description = '' ): void {
		$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control(
			new \WP_Customize_Image_Control(
				$wp_customize,
				$id,
				array(
					'label'       => $label,
					'description' => $description,
					'section'     => $section,
				)
			)
		);
	}

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'customize_register', array( self::class, 'register' ) );
		add_action( 'wp_head', array( self::class, 'head_css' ), 20 );
		add_action( 'customize_controls_enqueue_scripts', array( self::class, 'preset_assets' ) );
		add_action( 'customize_controls_enqueue_scripts', array( self::class, 'contrast_assets' ) );
		add_action( 'customize_controls_enqueue_scripts', array( self::class, 'repeater_assets' ) );
		add_action( 'customize_preview_init', array( self::class, 'live_preview_assets' ) );
	}

	/**
	 * Assets for the visual preset picker.
	 *
	 * Customizer-only: the stylesheet is never enqueued on the front end.
	 */
	/**
	 * Assets for the live readability report.
	 *
	 * Customizer-only.
	 */
	/**
	 * Assets for live token preview.
	 *
	 * Preview-frame only: this runs inside the site being previewed, not in
	 * the Customizer pane.
	 */
	public static function live_preview_assets(): void {
		wp_enqueue_script(
			'flavor-customizer-live-preview',
			FLAVOR_URI . '/assets/js/customizer-live-preview.js',
			array( 'customize-preview' ),
			FLAVOR_VERSION,
			true
		);

		$tokens = self::typography_tokens();

		wp_add_inline_script(
			'flavor-customizer-live-preview',
			'window.flavorLivePreview = ' . wp_json_encode(
				array(
					'tokens'   => Live_Preview::tokens(),
					'defaults' => array(
						'bodySize'  => '15px',
						'typeScale' => $tokens['flavor_type_scale']['default'],
						// Colours the server also emits as a "r, g, b" twin.
						'rgbVars' => array( 'flavor_primary', 'flavor_secondary', 'flavor_accent', 'flavor_surface', 'flavor_ink' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Assets for the repeating sections.
	 *
	 * Customizer-only. Depends on the media frame for image fields, and on
	 * jQuery only to satisfy the media API; the list logic itself is plain
	 * DOM, because the Customizer is not always running a jQuery build the
	 * theme can rely on.
	 */
	public static function repeater_assets(): void {
		wp_enqueue_script(
			'flavor-customizer-repeater',
			FLAVOR_URI . '/assets/js/customizer-repeater.js',
			array( 'customize-controls', 'jquery', 'media-editor' ),
			FLAVOR_VERSION,
			true
		);
	}

	/**
	 * Assets for the live readability report.
	 *
	 * Customizer-only.
	 */
	public static function contrast_assets(): void {
		wp_enqueue_script(
			'flavor-customizer-contrast',
			FLAVOR_URI . '/assets/js/customizer-contrast.js',
			array( 'customize-controls' ),
			FLAVOR_VERSION,
			true
		);

		$pairs = array();
		foreach ( Contrast::pairs() as $key => $pair ) {
			$pairs[] = array(
				'key' => $key,
				'fg'  => $pair['fg'],
				'bg'  => $pair['bg'],
				'min' => $pair['min'],
			);
		}

		// The preset values ship too, so a token the merchant left empty is
		// judged against what the front end would actually resolve it to.
		wp_add_inline_script(
			'flavor-customizer-contrast',
			'window.flavorContrastData = ' . wp_json_encode(
				array(
					'pairs'    => $pairs,
					'settings' => array_keys( Design::colour_token_labels() ),
					'current'  => Design::tokens( Design::current_skin() ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Assets for the visual preset picker.
	 *
	 * Customizer-only: the stylesheet is never enqueued on the front end.
	 */
	public static function preset_assets(): void {
		wp_enqueue_style(
			'flavor-customizer-presets',
			FLAVOR_URI . '/assets/css/customizer-presets.css',
			array(),
			FLAVOR_VERSION
		);

		wp_enqueue_script(
			'flavor-customizer-presets',
			FLAVOR_URI . '/assets/js/customizer-presets.js',
			array( 'customize-controls' ),
			FLAVOR_VERSION,
			true
		);

		// The button clears these, all through the changeset. The list is
		// shipped from PHP so it cannot fall out of step with the tokens.
		wp_add_inline_script(
			'flavor-customizer-presets',
			'window.flavorPresetData = ' . wp_json_encode(
				array(
					'colourSettings' => array_map(
						static fn( string $key ): string => 'flavor_' . $key,
						array_keys( Design::colour_token_labels() )
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Register panels, sections, settings and controls.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer Manager.
	 */
	public static function register( $wp_customize ): void {
		// Customizer control classes extend WP_Customize_Control, which core
		// only loads when the Customizer itself boots — inside
		// WP_Customize_Manager::__construct(), before this hook fires.
		// Declaring these from functions.php fatals on every other request,
		// including the front end, because the parent class is not there.
		//
		// Found by running the theme against real WordPress 7.1.3: the
		// offline test fixtures define a WP_Customize_Control stub, so the
		// unit tests passed while the site did not load at all.
		if ( class_exists( '\WP_Customize_Control' ) ) {
			require_once FLAVOR_DIR . '/inc/class-preset-control.php';
			require_once FLAVOR_DIR . '/inc/class-contrast-control.php';
			require_once FLAVOR_DIR . '/inc/class-repeater-control.php';
		}

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

		$wp_customize->add_setting( 'flavor_skin', array( 'default' => 'modern-restaurant', 'sanitize_callback' => 'sanitize_key', 'transport' => 'postMessage' ) );

		// A visual picker replaces the old radio list. Twelve prose
		// descriptions in a row gave the merchant no way to compare palettes;
		// a thumbnail of each skin does.
		if ( class_exists( '\Flavor\Preset_Control' ) ) {
			$wp_customize->register_control_type( '\Flavor\Preset_Control' );
			$wp_customize->add_control(
				new \Flavor\Preset_Control(
					$wp_customize,
					'flavor_skin',
					array(
						'label'       => __( 'انتخاب پوسته رستوران', 'flavor' ),
						'description' => __( 'با تغییر پوسته، رنگ‌بندی، فونت و استایل کلی رستوران هماهنگ می‌شود.', 'flavor' ),
						'section'     => 'flavor_section_preset',
					)
				)
			);
		} else {
			// Radio, not select: a native <select> opens a browser overlay
			// that the Customizer's CSS cannot reach.
			$wp_customize->add_control(
				'flavor_skin',
				array(
					'label'       => __( 'انتخاب پوسته رستوران', 'flavor' ),
					'description' => __( 'با تغییر پوسته، رنگ‌بندی، فونت و استایل کلی رستوران هماهنگ می‌شود.', 'flavor' ),
					'section'     => 'flavor_section_preset',
					'type'        => 'radio',
					'choices'     => $preset_choices,
				)
			);
		}

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
			$wp_customize->add_setting( 'flavor_' . $key, array( 'default' => '', 'sanitize_callback' => 'sanitize_hex_color', 'transport' => 'postMessage' ) );
			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					'flavor_' . $key,
					array(
						'label'   => $label,
						'section' => 'flavor_section_colors',
						'description' => __( 'رنگ را خالی بگذارید تا مقدارِ پوستهٔ انتخابی به کار برود؛ پرکردنش آن را برای این رنگ لغو می‌کند.', 'flavor' ),
					)
				)
			);
		}

		// A readability report sits with the colour pickers, because that is
		// where the decision is made. UI::variables() already corrects an
		// unreadable pair; this says so out loud instead of doing it silently.
		if ( class_exists( '\Flavor\Contrast_Control' ) ) {
			$wp_customize->register_control_type( '\Flavor\Contrast_Control' );
			$wp_customize->add_setting(
				'flavor_contrast_report',
				array(
					// Display-only: it renders a report inside the pane and
					// produces no front-end markup, so there is nothing for
					// a live preview to update.
					'default'           => '',
					'sanitize_callback' => '__return_empty_string',
				)
			);
			$wp_customize->add_control(
				new \Flavor\Contrast_Control(
					$wp_customize,
					'flavor_contrast_report',
					array(
						'label'       => __( 'خوانایی رنگ‌ها', 'flavor' ),
						'description' => __( 'نسبت کنتراست هر ترکیب با استاندارد WCAG AA سنجیده می‌شود. این فقط گزارش است و مانع ذخیره نمی‌شود.', 'flavor' ),
						'section'     => 'flavor_section_colors',
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

		$wp_customize->add_setting( 'flavor_radius', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'postMessage' ) );
		$wp_customize->add_control(
			'flavor_radius',
			array(
				'label'   => __( 'گردی گوشه کارت‌ها', 'flavor' ),
				'description' => __( 'شعاعِ گوشهٔ کارت‌ها به پیکسل. عددِ بزرگ‌تر حسِ نرم‌تر و دوستانه‌تر می‌دهد.', 'flavor' ),
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

		$wp_customize->add_setting( 'flavor_btn_radius', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'postMessage' ) );
		$wp_customize->add_control(
			'flavor_btn_radius',
			array(
				'label'   => __( 'استایل دکمه‌ها', 'flavor' ),
				'description' => __( 'شکلِ دکمه‌ها، از گوشه‌دار تا کاملاً گرد.', 'flavor' ),
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

		$wp_customize->add_setting( 'flavor_container_width', array( 'default' => '1240px', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'postMessage' ) );
		$wp_customize->add_control(
			'flavor_container_width',
			array(
				'label'   => __( 'حداکثر عرض محتوا', 'flavor' ),
				'description' => __( 'حداکثرِ عرضِ محتوا به پیکسل. روی صفحه‌های عریض، محتوا از این مقدار فراتر نمی‌رود.', 'flavor' ),
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

		// Responsive controls are explicit desktop/mobile tokens rather than
		// arbitrary CSS fields. They are reflected in the live preview and in
		// the safe settings transfer file.
		$responsive = array(
			'flavor_gutter_mobile'          => array( __( 'حاشیهٔ افقی موبایل', 'flavor' ), '32px', array( '24px' => '24px', '32px' => '32px', '40px' => '40px' ) ),
			'flavor_gutter_tablet'          => array( __( 'حاشیهٔ افقی تبلت', 'flavor' ), '', array( '' => __( 'مثل موبایل', 'flavor' ), '32px' => '32px', '40px' => '40px', '48px' => '48px' ) ),
			'flavor_gutter_desktop'         => array( __( 'حاشیهٔ افقی دسکتاپ', 'flavor' ), '48px', array( '32px' => '32px', '48px' => '48px', '64px' => '64px', '80px' => '80px' ) ),
			'flavor_body_size'              => array( __( 'اندازهٔ متن پایه', 'flavor' ), '15px', array( '14px' => '14px', '15px' => '15px', '16px' => '16px', '17px' => '17px', '18px' => '18px' ) ),
			'flavor_heading_size_mobile'    => array( __( 'مقیاس تیتر موبایل', 'flavor' ), 'clamp(1.9rem, 8vw, 3rem)', array( 'clamp(1.7rem, 7vw, 2.5rem)' => __( 'جمع‌وجور', 'flavor' ), 'clamp(1.9rem, 8vw, 3rem)' => __( 'استاندارد', 'flavor' ), 'clamp(2.1rem, 9vw, 3.4rem)' => __( 'درشت', 'flavor' ) ) ),
			'flavor_heading_size_tablet'    => array( __( 'مقیاس تیتر تبلت', 'flavor' ), '', array( '' => __( 'مثل موبایل', 'flavor' ), 'clamp(2rem, 6vw, 3.6rem)' => __( 'جمع‌وجور', 'flavor' ), 'clamp(2.2rem, 7vw, 4rem)' => __( 'استاندارد', 'flavor' ), 'clamp(2.4rem, 8vw, 4.6rem)' => __( 'درشت', 'flavor' ) ) ),
			'flavor_heading_size_desktop'   => array( __( 'مقیاس تیتر دسکتاپ', 'flavor' ), 'clamp(2.2rem, 4vw, 4.5rem)', array( 'clamp(2rem, 3vw, 3.6rem)' => __( 'جمع‌وجور', 'flavor' ), 'clamp(2.2rem, 4vw, 4.5rem)' => __( 'استاندارد', 'flavor' ), 'clamp(2.6rem, 5vw, 5.4rem)' => __( 'درشت', 'flavor' ) ) ),
			'flavor_section_space_mobile'   => array( __( 'فاصلهٔ سکشن موبایل', 'flavor' ), '56px', array( '40px' => '40px', '56px' => '56px', '72px' => '72px' ) ),
			'flavor_section_space_tablet'   => array( __( 'فاصلهٔ سکشن تبلت', 'flavor' ), '', array( '' => __( 'مثل موبایل', 'flavor' ), '64px' => '64px', '80px' => '80px', '96px' => '96px' ) ),
			'flavor_section_space_desktop'  => array( __( 'فاصلهٔ سکشن دسکتاپ', 'flavor' ), '88px', array( '64px' => '64px', '88px' => '88px', '112px' => '112px', '136px' => '136px' ) ),
		);
		foreach ( $responsive as $id => $config ) {
			$wp_customize->add_setting( $id, array( 'default' => $config[1], 'sanitize_callback' => array( self::class, 'sanitize_responsive' ), 'transport' => 'postMessage' ) );
			$wp_customize->add_control(
				$id,
				array(
					'label'   => $config[0],
					'section' => 'flavor_section_layout',
					'type'    => 'select',
					'choices' => $config[2],
					// N-02's acceptance criterion, stated where it is set: the
					// three breakpoints are independent, so a mobile value can
					// never silently change the desktop one.
					'description' => __( 'این مقدار فقط برای همین اندازهٔ صفحه اعمال می‌شود؛ تغییرِ موبایل دسکتاپ را عوض نمی‌کند.', 'flavor' ),
				)
			);
		}

		// -------------------------------------------------------------
		// Type scale: the responsive block above sets the base sizes; this
		// block sets the hierarchy around them. Every value is a fixed choice
		// rather than a free-text field, so a merchant cannot type a value
		// that collapses the layout.
		// -------------------------------------------------------------
		$wp_customize->add_section(
			'flavor_section_typography',
			array(
				'title'       => __( 'مقیاس تایپوگرافی', 'flavor' ),
				'panel'       => 'flavor_panel_design',
				'description' => __( 'اندازهٔ پایه و مقیاس تیتر اصلی در بخش «تایپوگرافی و گوشه‌ها» تنظیم می‌شوند؛ اینجا سلسله‌مراتب títuloها و وزن و فاصلهٔ خطوط را تعیین می‌کنید.', 'flavor' ),
			)
		);

		$typography = array(
			'flavor_type_scale'              => array(
				__( 'نسبت مقیاس تیترها', 'flavor' ),
				'1.25',
				array(
					'1.125' => __( 'جمع‌وجور — ۱.۱۲۵ (نهم بزرگ)', 'flavor' ),
					'1.15'  => __( 'ملایم — ۱.۱۵', 'flavor' ),
					'1.2'   => __( 'متعادل — ۱.۲ (سوم کوچک)', 'flavor' ),
					'1.25'  => __( 'استاندارد — ۱.۲۵ (پیش‌فرض)', 'flavor' ),
					'1.333' => __( 'برجسته — ۱.۳۳۳ (چهارم درست)', 'flavor' ),
					'1.414' => __( 'دراماتیک — ۱.۴۱۴', 'flavor' ),
					'1.5'   => __( 'بسیار درشت — ۱.۵ (پنجم درست)', 'flavor' ),
				),
				__( 'فقط títuloهای h2 تا h6 را تغییر می‌دهد. título اصلی (h1) از مقیاس ریسپانسیو پیروی می‌کند تا روی موبایل نشکند.', 'flavor' ),
			),
			'flavor_heading_weight'          => array(
				__( 'وزن تیترها', 'flavor' ),
				'700',
				array(
					'500' => __( 'نیمه‌ضخیم (500)', 'flavor' ),
					'600' => __( 'نیمه‌درشت (600)', 'flavor' ),
					'700' => __( 'درشت (700) — پیش‌فرض', 'flavor' ),
					'800' => __( 'خیلی درشت (800)', 'flavor' ),
					'900' => __( 'سیاه (900)', 'flavor' ),
				),
				'',
			),
			'flavor_heading_line_height'     => array(
				__( 'ارتفاع خط تیترها', 'flavor' ),
				'1.25',
				array(
					'1.1'  => __( 'فشرده (1.1)', 'flavor' ),
					'1.15' => __( 'نیمه‌فشرده (1.15)', 'flavor' ),
					'1.2'  => __( 'متوسط (1.2)', 'flavor' ),
					'1.25' => __( 'استاندارد (1.25) — پیش‌فرض', 'flavor' ),
					'1.3'  => __( 'باز (1.3)', 'flavor' ),
					'1.4'  => __( 'خیلی باز (1.4)', 'flavor' ),
				),
				'',
			),
			'flavor_heading_letter_spacing'  => array(
				__( 'فاصلهٔ حروف تیترها', 'flavor' ),
				'0em',
				array(
					'-0.02em' => __( 'فشرده‌تر (0.02em-)', 'flavor' ),
					'-0.01em' => __( 'کمی فشرده (0.01em-)', 'flavor' ),
					'0em'     => __( 'پیش‌فرض (0) — پیشنهادی برای فارسی', 'flavor' ),
					'0.01em'  => __( 'کمی باز (0.01em)', 'flavor' ),
					'0.02em'  => __( 'باز (0.02em)', 'flavor' ),
					'0.04em'  => __( 'خیلی باز (0.04em)', 'flavor' ),
				),
				__( 'هشدار: در خط فارسی حروف به هم می‌چسبند. هر مقدارِ غیرصفر پیوستگیِ کلمات را می‌شکند و متن را نازیبا می‌کند؛ فقط برای تیترهای لاتین استفاده کنید.', 'flavor' ),
			),
			'flavor_body_weight'             => array(
				__( 'وزن متن بدنه', 'flavor' ),
				'400',
				array(
					'300' => __( 'نازک (300)', 'flavor' ),
					'400' => __( 'معمولی (400) — پیش‌فرض', 'flavor' ),
					'500' => __( 'متوسط (500)', 'flavor' ),
					'600' => __( 'نیمه‌درشت (600)', 'flavor' ),
					'700' => __( 'درشت (700)', 'flavor' ),
				),
				'',
			),
			'flavor_body_line_height'        => array(
				__( 'ارتفاع خط متن بدنه', 'flavor' ),
				'1.7',
				array(
					'1.5' => __( 'فشرده (1.5)', 'flavor' ),
					'1.6' => __( 'نیمه‌فشرده (1.6)', 'flavor' ),
					'1.7' => __( 'استاندارد (1.7) — پیش‌فرض', 'flavor' ),
					'1.8' => __( 'خوانا (1.8)', 'flavor' ),
					'1.9' => __( 'خیلی خوانا (1.9)', 'flavor' ),
					'2'   => __( 'حداکثر خوانایی (2)', 'flavor' ),
				),
				__( 'برای متن فارسی، مقادیر بازتر خوانایی را به‌طور محسوسی بهتر می‌کنند.', 'flavor' ),
			),
		);

		foreach ( $typography as $id => $config ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $config[1],
					'sanitize_callback' => array( self::class, 'sanitize_typography' ),
					'transport'         => 'postMessage',
				)
			);

			$wp_customize->add_control(
				$id,
				array(
					'label'       => $config[0],
					'description' => $config[3],
					'section'     => 'flavor_section_typography',
					'type'        => 'select',
					'choices'     => $config[2],
				)
			);
		}

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

		$wp_customize->add_setting( 'flavor_header_layout', array( 'default' => 'default', 'sanitize_callback' => 'sanitize_key', 'transport' => 'postMessage' ) );
		$wp_customize->add_control(
			'flavor_header_layout',
			array(
				'label'   => __( 'استایل و چینش هدر', 'flavor' ),
				'description' => __( 'چینشِ هدر: لوگو در میان، لوگو در راست با منو در چپ، یا منو در زیرِ لوگو.', 'flavor' ),
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

		$wp_customize->add_setting(
			'flavor_logo_height',
			array(
				'default'           => 52,
				'sanitize_callback' => array( self::class, 'sanitize_logo_height' ),
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			'flavor_logo_height',
			array(
				'label'       => __( 'ارتفاع لوگو در هدر (پیکسل)', 'flavor' ),
				'description' => __( 'لوگو با حفظ نسبت ابعاد تا این ارتفاع کوچک می‌شود و در موبایل خودکار کوچک‌تر می‌شود. فایل لوگو با هر ابعادی آپلود شود، هدر به هم نمی‌ریزد.', 'flavor' ),
				'section'     => 'flavor_section_header',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => 24,
					'max'  => 160,
					'step' => 2,
				),
			)
		);

		$wp_customize->add_setting( 'flavor_header_sticky', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key', 'transport' => 'postMessage' ) );
		$wp_customize->add_control(
			'flavor_header_sticky',
			array(
				'label'   => __( 'هدر چسبان هنگام اسکرول (Sticky Header)', 'flavor' ),
				'description' => __( 'با فعال‌بودن، هدر هنگامِ اسکرول بالای صفحه می‌ماند. روی موبایل برای صرفه‌جویی در فضا غیرفعال می‌شود.', 'flavor' ),
				'section' => 'flavor_section_header',
				'type'    => 'checkbox',
			)
		);

		$wp_customize->add_setting( 'flavor_header_topbar', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'postMessage' ) );
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
				'description' => __( 'قالبِ بخشِ اصلی: تمام‌صفحه، دوستونه با تصویر در کنار، یا مینیمال بدونِ تصویرِ بزرگ.', 'flavor' ),
				'section' => 'flavor_section_hero',
				'type'    => 'select',
				'choices' => array(
					'fullscreen' => __( 'تمام‌صفحه با تصویر پس‌زمینه و پوشش تاریک', 'flavor' ),
					'split'      => __( 'دوطرفه (متن در راست / تصویر شاخص در چپ)', 'flavor' ),
					'minimal'    => __( 'مینیمال وسط‌چین بدون شلوغی', 'flavor' ),
				),
			)
		);

		// The templates have always read this theme mod, but only the demo
		// importer could write it — there was no way to upload a hero by hand.
		self::image_control(
			$wp_customize,
			'flavor_hero_image',
			'flavor_section_hero',
			__( 'تصویر بخش هیرو', 'flavor' ),
			__( 'پیشنهاد: دست‌کم ۱۶۰۰×۹۰۰ پیکسل و افقی. تصویر با نسبت ثابت و ریسپانسیو برش داده می‌شود و نسخهٔ سبک‌تر آن برای موبایل فرستاده می‌شود.', 'flavor' )
		);

		$wp_customize->add_setting( 'flavor_hero_badge', array( 'default' => __( 'طعم اصیل و ماندگار', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_badge', array( 'label' => __( 'بج بالای عنوان هیرو', 'flavor' ), 'description' => __( 'متن کوتاهِ بالای عنوان؛ مثلاً «تازه‌ترین منو». بیش از دو سه واژه نشود چون در موبایل دورِ عنوان می‌پیچد.', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'text' ) );

		$wp_customize->add_setting( 'flavor_hero_title', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_title', array( 'label' => __( 'عنوان اصلی هیرو', 'flavor' ), 'description' => __( 'عنوانی که بازدیدکننده در نگاهِ اول می‌بیند. کوتاه‌تر از این، نرخِ تبدیل بهتر است.', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'text' ) );

		$wp_customize->add_setting( 'flavor_hero_text', array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control( 'flavor_hero_text', array( 'label' => __( 'توضیحات کوتاه هیرو', 'flavor' ), 'description' => __( 'یک یا دو جمله زیرِ عنوان. اگر طولانی شود، در موبایل دکمه‌ها را به پایینِ صفحه می‌راند.', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'textarea' ) );

		$wp_customize->add_setting( 'flavor_hero_cta', array( 'default' => __( 'مشاهده منو و سفارش آنلاین', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_cta', array( 'label' => __( 'متن دکمه اول (سفارش)', 'flavor' ), 'description' => __( 'متنِ دکمهٔ اصلی. مقصدِ این دکمه را هسته تعیین می‌کند؛ بدونِ آن، به صفحهٔ منو می‌رود.', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'text' ) );

		$wp_customize->add_setting( 'flavor_hero_cta2', array( 'default' => __( 'رزرو میز', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_cta2', array( 'label' => __( 'متن دکمه دوم (رزرو)', 'flavor' ), 'description' => __( 'متنِ دکمهٔ دوم. برای پنهان‌کردن، خالی بگذارید.', 'flavor' ), 'section' => 'flavor_section_hero', 'type' => 'text' ) );

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
		$wp_customize->add_control( 'flavor_intro_enable', array( 'label' => __( 'نمایش بخش معرفی', 'flavor' ), 'description' => __( 'نمایشِ بخشِ معرفی در صفحهٔ اصلی. غیرفعال‌کردن محتوا را پاک نمی‌کند.', 'flavor' ), 'section' => 'flavor_section_intro', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_intro_title', array( 'default' => __( 'چرا مهمان‌ها ما را انتخاب می‌کنند؟', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_intro_title', array( 'label' => __( 'عنوان بخش', 'flavor' ), 'description' => __( 'عنوانِ بخشِ معرفی. این عنوان در تیترِ بخش و در نشانه‌گذاریِ ساختاریافتهٔ صفحه به کار می‌رود.', 'flavor' ), 'section' => 'flavor_section_intro', 'type' => 'text' ) );

		// Section: Featured Dishes
		$wp_customize->add_section(
			'flavor_section_featured',
			array(
				'title' => __( '۲. پیشنهادهای ویژه سرآشپز', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_featured_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_featured_enable', array( 'label' => __( 'نمایش پیشنهادهای ویژه', 'flavor' ), 'description' => __( 'نمایشِ پیشنهادهای ویژه. آیتم‌ها از محصولاتِ برگزیدهٔ ووکامرس خوانده می‌شوند.', 'flavor' ), 'section' => 'flavor_section_featured', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_featured_title', array( 'default' => __( 'محبوب‌ترین و لذیذترین‌های این هفته', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_featured_title', array( 'label' => __( 'عنوان بخش', 'flavor' ), 'description' => __( 'عنوانِ بخشِ پیشنهادهای ویژه.', 'flavor' ), 'section' => 'flavor_section_featured', 'type' => 'text' ) );

		// Section: Categories Showcase
		$wp_customize->add_section(
			'flavor_section_categories',
			array(
				'title' => __( '۳. دسته‌بندی‌های منو', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_cats_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_cats_enable', array( 'label' => __( 'نمایش کارت‌های دسته‌بندی', 'flavor' ), 'description' => __( 'نمایشِ کارت‌های دسته‌بندیِ منو.', 'flavor' ), 'section' => 'flavor_section_categories', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_cats_title', array( 'default' => __( 'منوی غذا و نوشیدنی', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_cats_title', array( 'label' => __( 'عنوان دسته‌ها', 'flavor' ), 'description' => __( 'عنوانِ بخشِ دسته‌بندی‌ها.', 'flavor' ), 'section' => 'flavor_section_categories', 'type' => 'text' ) );

		// Section: Special Offers & Coupon Promo
		$wp_customize->add_section(
			'flavor_section_offers',
			array(
				'title' => __( '۴. تخفیف‌ها و پیشنهادهای طلایی', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_offers_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_offers_enable', array( 'label' => __( 'نمایش بنر تخفیف ویژه', 'flavor' ), 'description' => __( 'نمایشِ بنرِ تخفیف. آفر در صفحهٔ اصلی دیده می‌شود؛ تعریفِ خودِ تخفیف در ووکامرس انجام می‌شود.', 'flavor' ), 'section' => 'flavor_section_offers', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_offers_title', array( 'default' => __( '۱۵٪ تخفیف برای اولین سفارش آنلاین', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_offers_title', array( 'label' => __( 'عنوان آفر', 'flavor' ), 'description' => __( 'عنوانِ روی بنرِ تخفیف. کدِ تخفیف را در فیلدِ بعدی وارد کنید تا مشتری بتواند کپی‌اش کند.', 'flavor' ), 'section' => 'flavor_section_offers', 'type' => 'text' ) );
		$wp_customize->add_setting( 'flavor_offers_code', array( 'default' => 'FIRST15', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_offers_code', array( 'label' => __( 'کد تخفیف', 'flavor' ), 'description' => __( 'کدی که مشتری در سبد وارد می‌کند. خودِ تخفیف باید در ووکامرس تعریف شود؛ اینجا فقط نمایش است.', 'flavor' ), 'section' => 'flavor_section_offers', 'type' => 'text' ) );

		// Section: Table Reservation CTA
		$wp_customize->add_section(
			'flavor_section_reservation',
			array(
				'title' => __( '۵. دعوت به رزرو آنلاین میز', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_res_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_res_enable', array( 'label' => __( 'نمایش بنر رزرو میز', 'flavor' ), 'description' => __( 'نمایشِ بنرِ دعوت به رزرو. مقصدِ دکمه صفحهٔ رزرو است؛ اگر هسته فعال نباشد، بنر نمایش داده نمی‌شود.', 'flavor' ), 'section' => 'flavor_section_reservation', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_res_title', array( 'default' => __( 'لحظات ماندگار خود را پیشاپیش رزرو کنید', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_res_title', array( 'label' => __( 'عنوان بنر رزرو', 'flavor' ), 'description' => __( 'عنوانِ روی بنرِ رزرو. کوتاه بنویسید؛ متنِ بلند در موبایل دکمه را به خطِ بعد می‌راند.', 'flavor' ), 'section' => 'flavor_section_reservation', 'type' => 'text' ) );
		self::image_control(
			$wp_customize,
			'flavor_res_image',
			'flavor_section_reservation',
			__( 'تصویر بنر رزرو', 'flavor' ),
			__( 'نسبت ۴:۳، دست‌کم ۸۰۰×۶۰۰ پیکسل. اگر خالی بماند تصویر دموی پوستهٔ فعال نمایش داده می‌شود.', 'flavor' )
		);

		// Section: About & Story
		$wp_customize->add_section(
			'flavor_section_about',
			array(
				'title' => __( '۶. داستان و درباره ما', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_about_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_about_enable', array( 'label' => __( 'نمایش بخش درباره ما', 'flavor' ), 'description' => __( 'نمایشِ بخشِ «دربارهٔ ما».', 'flavor' ), 'section' => 'flavor_section_about', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_about', array( 'default' => '', 'sanitize_callback' => 'wp_kses_post' ) );
		$wp_customize->add_control( 'flavor_about', array( 'label' => __( 'متن داستان رستوران', 'flavor' ), 'description' => __( 'متنِ داستانِ رستوران. این متن در نشانه‌گذاریِ ساختاریافتهٔ صفحه هم به کار می‌رود.', 'flavor' ), 'section' => 'flavor_section_about', 'type' => 'textarea' ) );
		self::image_control(
			$wp_customize,
			'flavor_about_image',
			'flavor_section_about',
			__( 'تصویر بخش درباره ما', 'flavor' ),
			__( 'نسبت ۴:۳، دست‌کم ۸۰۰×۶۰۰ پیکسل. اگر خالی بماند تصویر دموی پوستهٔ فعال نمایش داده می‌شود.', 'flavor' )
		);
		// Read by the «دربارهٔ مجموعه» page template and by the bespoke story
		// section. It used to live in the demo-only panel, so the seven legacy
		// skins had no way to set the photo on their About page at all.
		self::image_control(
			$wp_customize,
			'flavor_landing_story_image',
			'flavor_section_about',
			__( 'تصویر صفحهٔ «دربارهٔ مجموعه»', 'flavor' ),
			__( 'تصویر عمودی (نسبت ۴:۵) مناسب‌تر است. در دموهای اختصاصی برای بخش داستان برند هم استفاده می‌شود.', 'flavor' )
		);

		// Section: Gallery
		$wp_customize->add_section(
			'flavor_section_gallery',
			array(
				'title' => __( '۷. گالری تصاویر رستوران', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_gallery_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_gallery_enable', array( 'label' => __( 'نمایش گالری تصاویر', 'flavor' ), 'description' => __( 'نمایشِ گالری. اگر تصویری در بستهٔ دمو نباشد، این بخش خودبه‌خود پنهان می‌شود.', 'flavor' ), 'section' => 'flavor_section_gallery', 'type' => 'checkbox' ) );
		// Six discrete slots rather than a repeater: the Customizer has no
		// native repeating control, and six is what the grid lays out.
		for ( $slot = 1; $slot <= self::GALLERY_SLOTS; $slot++ ) {
			self::image_control(
				$wp_customize,
				'flavor_gallery_image_' . $slot,
				'flavor_section_gallery',
					/* translators: %d: gallery slot number. */
					sprintf( __( 'تصویر گالری %d', 'flavor' ), $slot ),
					1 === $slot ? __( 'نسبت ۴:۳. هر جای خالی با تصویر دموی قالب پر می‌شود.', 'flavor' ) : ''
				);
		}

		// A repeater on top of the fixed slots: add, reorder, duplicate and
		// delete, with alt text per image. The old slots stay in place so an
		// existing site keeps its photos; Repeater::migrate() reads them
		// whenever the new list is empty.
		if ( class_exists( '\Flavor\Repeater_Control' ) ) {
			$wp_customize->register_control_type( '\Flavor\Repeater_Control' );
			$wp_customize->add_setting(
				'flavor_gallery_items',
				array(
					'default'           => array(),
					'sanitize_callback' => static fn( $value ) => Repeater::sanitize( $value, 'gallery' ),
					'transport'         => 'refresh',
				)
			);
			$wp_customize->add_control(
				new \Flavor\Repeater_Control(
					$wp_customize,
					'flavor_gallery_items',
					array(
						'label'       => __( 'تصویرهای گالری', 'flavor' ),
						'description' => __( 'تعداد دلخواه تصویر بیفزایید و با فلش‌ها جابه‌جا کنید. متن جایگزین برای هر تصویر را پر کنید — این متن را صفحه‌خوان‌ها می‌خوانند.', 'flavor' ),
						'section'     => 'flavor_section_gallery',
						'schema'      => 'gallery',
					)
				)
			);
		}

		// Section: Testimonials
		$wp_customize->add_section(
			'flavor_section_testimonials',
			array(
				'title' => __( '۸. نظرات مهمان‌ها', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_testimonials_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_testimonials_enable', array( 'label' => __( 'نمایش بخش نظرات', 'flavor' ), 'description' => __( 'نمایشِ بخشِ نظراتِ مشتریان. آیتم‌ها را در بخشِ مربوط ویرایش کنید.', 'flavor' ), 'section' => 'flavor_section_testimonials', 'type' => 'checkbox' ) );

		// The three reviews were hard-coded in the template. Now they are
		// editable, reorderable and unlimited (up to the cap).
		if ( class_exists( '\Flavor\Repeater_Control' ) ) {
			$wp_customize->add_setting(
				'flavor_testimonials_items',
				array(
					'default'           => array(),
					'sanitize_callback' => static fn( $value ) => Repeater::sanitize( $value, 'testimonials' ),
					'transport'         => 'refresh',
				)
			);
			$wp_customize->add_control(
				new \Flavor\Repeater_Control(
					$wp_customize,
					'flavor_testimonials_items',
					array(
						'label'       => __( 'نظر مهمان‌ها', 'flavor' ),
						'description' => __( 'هر تعداد نظر که دارید بیفزایید. اگر خالی بماند، نمونه‌های پیش‌فرض نمایش داده می‌شود.', 'flavor' ),
						'section'     => 'flavor_section_testimonials',
						'schema'      => 'testimonials',
					)
				)
			);
		}

		// Section: Working Hours & Location
		$wp_customize->add_section(
			'flavor_section_hours',
			array(
				'title' => __( '۹. ساعات کاری و موقعیت روی نقشه', 'flavor' ),
				'panel' => 'flavor_panel_sections',
			)
		);
		$wp_customize->add_setting( 'flavor_hours_enable', array( 'default' => 'yes', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control( 'flavor_hours_enable', array( 'label' => __( 'نمایش ساعات کاری و آدرس', 'flavor' ), 'description' => __( 'نمایشِ ساعاتِ کاری و نشانی. ساعاتِ هر شعبه در هسته تنظیم می‌شود.', 'flavor' ), 'section' => 'flavor_section_hours', 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'flavor_phone', array( 'default' => '02188001234', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_phone', array( 'label' => __( 'شماره تلفن تماس / سفارش', 'flavor' ), 'description' => __( 'شماره‌ای که در هدر و دکمه‌های تماس استفاده می‌شود؛ برای دکمهٔ تماس به‌صورت خودکار به پیوندِ tel تبدیل می‌شود.', 'flavor' ), 'section' => 'flavor_section_hours', 'type' => 'text' ) );
		$wp_customize->add_setting( 'flavor_address', array( 'default' => 'تهران، خیابان ولیعصر، بالاتر از پارک‌وی', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_address', array( 'label' => __( 'نشانی شعبه اصلی', 'flavor' ), 'description' => __( 'نشانیِ شعبهٔ اصلی؛ در پانوشت و در نشانه‌گذاریِ ساختاریافتهٔ محلِ کسب نمایش داده می‌شود.', 'flavor' ), 'section' => 'flavor_section_hours', 'type' => 'text' ) );

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
		$wp_customize->add_control( 'flavor_social_instagram', array( 'label' => __( 'لینک اینستاگرام', 'flavor' ), 'description' => __( 'نشانی کامل با https://. خالی بگذارید تا این آیکون در پانوشت نمایش داده نشود.', 'flavor' ), 'section' => 'flavor_section_footer', 'type' => 'url' ) );

		$wp_customize->add_setting( 'flavor_social_telegram', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( 'flavor_social_telegram', array( 'label' => __( 'لینک تلگرام', 'flavor' ), 'description' => __( 'نشانی کامل با https://. خالی بگذارید تا این آیکون در پانوشت نمایش داده نشود.', 'flavor' ), 'section' => 'flavor_section_footer', 'type' => 'url' ) );

		$wp_customize->add_setting( 'flavor_social_whatsapp', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( 'flavor_social_whatsapp', array( 'label' => __( 'لینک واتس‌اپ', 'flavor' ), 'description' => __( 'نشانی کاملِ واتس‌اپ. خالی بگذارید تا این آیکون در پانوشت نمایش داده نشود.', 'flavor' ), 'section' => 'flavor_section_footer', 'type' => 'url' ) );

		$wp_customize->add_setting( 'flavor_footer_copy', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_footer_copy', array( 'label' => __( 'متن کپی‌رایت اختصاصی', 'flavor' ), 'description' => __( 'متنِ کپی‌رایتِ پانوشت. خالی بگذارید تا متنِ پیش‌فرضِ قالب استفاده شود.', 'flavor' ), 'section' => 'flavor_section_footer', 'type' => 'text' ) );

		// Live preview routing. Last, deliberately: it flips the transport of
		// settings that must already have been registered above.
		Live_Preview::register( $wp_customize );
	}

	/**
	 * Print dynamic CSS variables and layout rules into <head>.
	 */
	public static function head_css(): void {
		echo '<style id="flavor-tokens">' . Design::css_variables() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Sanitize responsive values against the finite token set.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string
	 */
	public static function sanitize_responsive( $value ): string {
		$allowed = array(
			'24px', '32px', '40px', '48px', '56px', '64px', '72px', '80px', '88px', '112px', '136px', '14px', '15px', '16px', '17px', '18px',
			'clamp(1.7rem, 7vw, 2.5rem)', 'clamp(1.9rem, 8vw, 3rem)', 'clamp(2.1rem, 9vw, 3.4rem)',
			'clamp(2rem, 3vw, 3.6rem)', 'clamp(2.2rem, 4vw, 4.5rem)', 'clamp(2.6rem, 5vw, 5.4rem)',
		);
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return in_array( $value, $allowed, true ) ? $value : '15px';
	}

	/**
	 * Sanitize a typography token.
	 *
	 * Deliberately separate from sanitize_responsive(): that one falls back to
	 * '15px' for anything outside its hard-coded list, which would turn a
	 * heading weight of '700' into '--flavor-heading-weight: 15px'. A shared
	 * sanitizer needs a shared fallback, and these two groups have none.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string
	 */
	public static function sanitize_typography( $value ): string {
		$allowed = array(
			// Type scale ratios.
			'1.125', '1.15', '1.2', '1.25', '1.333', '1.414', '1.5',
			// Weights.
			'300', '400', '500', '600', '700', '800', '900',
			// Line heights.
			'1.1', '1.15', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9', '2',
			// Letter spacing.
			'-0.02em', '-0.01em', '0em', '0.01em', '0.02em', '0.04em',
		);

		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Clamp the logo height so a stray value cannot break the header again.
	 *
	 * @param mixed $value Raw setting value.
	 * @return int
	 */
	public static function sanitize_logo_height( $value ): int {
		$value = absint( $value );

		if ( $value < 24 || $value > 160 ) {
			return 52;
		}

		return $value;
	}
}
