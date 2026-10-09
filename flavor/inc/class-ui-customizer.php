<?php
/** Validated presentation settings and state-preserving Customizer previews. @package Flavor */
namespace Flavor;
defined( 'ABSPATH' ) || exit;

class UI_Customizer {
	public static function init(): void {
		add_action( 'customize_register', array( self::class, 'register' ), 20 );
		add_action( 'customize_preview_init', array( self::class, 'preview_assets' ) );
		add_action( 'customize_controls_enqueue_scripts', array( self::class, 'control_assets' ) );
	}

	public static function register( $manager ): void {
		$manager->add_section( 'flavor_section_ui', array(
			'title'       => __( 'منو و رابط سفارش', 'flavor' ),
			'description' => __( 'ظاهر منو و ناوبری را پیش از انتشار ببینید. این تنظیمات قیمت، موجودی، روش دریافت، ظرفیت رزرو یا درگاه پرداخت را تغییر نمی‌دهند.', 'flavor' ),
			'panel'       => 'flavor_panel_design',
			'priority'    => 15,
		) );
		require_once FLAVOR_DIR . '/inc/class-ui-preview-control.php';
		$manager->add_control( new UI_Preview_Control( $manager, 'flavor_ui_preview', array(
			'section' => 'flavor_section_ui', 'settings' => array(), 'capability' => 'edit_theme_options', 'priority' => 1,
		) ) );
		foreach ( UI::schema() as $key => $definition ) {
			$id = 'flavor_ui_' . $key;
			$manager->add_setting( $id, array(
				'default'             => $definition['default'],
				'transport'           => 'postMessage',
				'sanitize_callback'   => array( self::class, 'sanitize_select' ),
				'validate_callback'   => array( self::class, 'validate_select' ),
			) );
			$manager->add_control( $id, array(
				'label'       => $definition['label'],
				'description' => $definition['description'],
				'type'        => 'select',
				'choices'     => $definition['choices'],
				'section'     => 'flavor_section_ui',
			) );
		}

		// Existing design controls now use their real choices, not arbitrary CSS.
		foreach ( self::design_choices() as $id => $choices ) {
			$setting = $manager->get_setting( $id );
			if ( ! $setting ) {
				$manager->add_setting( $id, array( 'default' => '' ) );
				$setting = $manager->get_setting( $id );
			}
			self::callback( $setting, 'sanitize_callback', array( self::class, 'sanitize_select' ), 2 );
			self::callback( $setting, 'validate_callback', array( self::class, 'validate_select' ), 3 );
			$control = $manager->get_control( $id );
			if ( $control ) { $control->choices = $choices; }
			if ( in_array( $id, array( 'flavor_font_heading', 'flavor_font_body', 'flavor_header_layout' ), true ) ) { $setting->transport = 'postMessage'; }
		}
		foreach ( array( 'heading' => __( 'فونت تیترها', 'flavor' ), 'body' => __( 'فونت متن و فرم‌ها', 'flavor' ) ) as $key => $label ) {
			$manager->add_control( 'flavor_font_' . $key, array(
				'label' => $label, 'type' => 'select', 'section' => 'flavor_section_layout',
				'choices' => self::design_choices()[ 'flavor_font_' . $key ],
				'description' => __( 'استعداد و وزیرمتن همراه قالب و به‌صورت محلی بارگذاری می‌شوند. پیش‌فرض، فونت خود پوسته را نگه می‌دارد.', 'flavor' ),
			) );
		}
		$header = $manager->get_control( 'flavor_header_layout' );
		if ( $header ) { $header->description = __( 'وسط‌چین در دسکتاپ اعمال می‌شود؛ در موبایل چینش خوانا و دکمهٔ منو حفظ می‌شود. هدر شیشه‌ای روی متن صفحه قرار نمی‌گیرد.', 'flavor' ); }
		$sticky = $manager->get_setting( 'flavor_header_sticky' );
		if ( $sticky ) {
			$sticky->default = true;
			$sticky->transport = 'postMessage';
			self::callback( $sticky, 'sanitize_callback', array( self::class, 'boolean' ), 2 );
			self::callback( $sticky, 'sanitize_js_callback', array( self::class, 'boolean' ), 2 );
		}
	}

	/** Keep every preset's existing geometry selectable, including imported values. */
	public static function design_choices(): array {
		$fonts = array( '' => __( 'پیش‌فرض پوسته', 'flavor' ), 'Vazirmatn' => __( 'وزیرمتن', 'flavor' ), 'Estedad' => __( 'استعداد', 'flavor' ) );
		if ( is_readable( FLAVOR_DIR . '/assets/fonts/iransans/IRANSans.woff2' ) || 'IRANSans' === get_theme_mod( 'flavor_font_body', '' ) || 'IRANSans' === get_theme_mod( 'flavor_font_heading', '' ) ) {
			$fonts['IRANSans'] = __( 'ایران‌سنس — فقط با فایل دارای مجوز؛ در غیر این صورت وزیرمتن', 'flavor' );
		}
		$radius = array( '' => __( 'پیش‌فرض پوسته', 'flavor' ), '0px' => __( 'بدون انحنا', 'flavor' ), '8px' => '8px', '16px' => '16px', '24px' => '24px' );
		$buttons = array( '' => __( 'پیش‌فرض پوسته', 'flavor' ), '6px' => '6px', '12px' => '12px', '9999px' => __( 'کپسولی', 'flavor' ) );
		foreach ( Design::skins() as $slug => $skin ) {
			$tokens = Design::tokens( $slug );
			if ( ! isset( $radius[ $tokens['radius'] ] ) ) { $radius[ $tokens['radius'] ] = $tokens['radius']; }
			if ( ! isset( $buttons[ $tokens['btn_radius'] ] ) ) { $buttons[ $tokens['btn_radius'] ] = $tokens['btn_radius']; }
		}
		return array(
			'flavor_skin' => array_map( static function ( $skin ) { return $skin['title'] . ' — ' . $skin['desc']; }, Design::skins() ),
			'flavor_radius' => $radius, 'flavor_btn_radius' => $buttons,
			'flavor_container_width' => array( '1140px' => '1140px', '1240px' => '1240px', '1360px' => '1360px', '1440px' => '1440px' ),
			'flavor_font_heading' => $fonts, 'flavor_font_body' => $fonts,
			'flavor_gutter_mobile' => array( '24px' => '24px', '32px' => '32px', '40px' => '40px' ),
			'flavor_gutter_tablet' => array( '' => __( 'مثل موبایل', 'flavor' ), '32px' => '32px', '40px' => '40px', '48px' => '48px' ),
			'flavor_gutter_desktop' => array( '32px' => '32px', '48px' => '48px', '64px' => '64px', '80px' => '80px' ),
			'flavor_body_size' => array( '14px' => '14px', '15px' => '15px', '16px' => '16px', '17px' => '17px', '18px' => '18px' ),
			'flavor_heading_size_mobile' => array( 'clamp(1.7rem, 7vw, 2.5rem)' => __( 'جمع‌وجور', 'flavor' ), 'clamp(1.9rem, 8vw, 3rem)' => __( 'استاندارد', 'flavor' ), 'clamp(2.1rem, 9vw, 3.4rem)' => __( 'درشت', 'flavor' ) ),
			'flavor_heading_size_tablet' => array( '' => __( 'مثل موبایل', 'flavor' ), 'clamp(2rem, 6vw, 3.6rem)' => __( 'جمع‌وجور', 'flavor' ), 'clamp(2.2rem, 7vw, 4rem)' => __( 'استاندارد', 'flavor' ), 'clamp(2.4rem, 8vw, 4.6rem)' => __( 'درشت', 'flavor' ) ),
			'flavor_heading_size_desktop' => array( 'clamp(2rem, 3vw, 3.6rem)' => __( 'جمع‌وجور', 'flavor' ), 'clamp(2.2rem, 4vw, 4.5rem)' => __( 'استاندارد', 'flavor' ), 'clamp(2.6rem, 5vw, 5.4rem)' => __( 'درشت', 'flavor' ) ),
			'flavor_section_space_mobile' => array( '40px' => '40px', '56px' => '56px', '72px' => '72px' ),
			'flavor_section_space_tablet' => array( '' => __( 'مثل موبایل', 'flavor' ), '64px' => '64px', '80px' => '80px', '96px' => '96px' ),
			'flavor_section_space_desktop' => array( '64px' => '64px', '88px' => '88px', '112px' => '112px', '136px' => '136px' ),
			'flavor_header_layout' => array( 'default' => __( 'پیش‌فرض پوسته', 'flavor' ), 'centered' => __( 'لوگو و منو وسط‌چین', 'flavor' ), 'minimal' => __( 'مینیمال و باریک', 'flavor' ), 'transparent' => __( 'شیشه‌ای خوانا', 'flavor' ) ),
		);
	}

	/** WP attaches callbacks in the constructor; changing a property alone is insufficient. */
	private static function callback( $setting, string $property, array $callback, int $args ): void {
		$hooks = array( 'sanitize_callback' => 'customize_sanitize_', 'validate_callback' => 'customize_validate_', 'sanitize_js_callback' => 'customize_sanitize_js_' );
		$hook = $hooks[ $property ] . $setting->id;
		if ( $setting->$property ) { remove_filter( $hook, $setting->$property, 10 ); }
		$setting->$property = $callback;
		add_filter( $hook, $callback, 10, $args );
	}

	public static function choices( string $id ): array {
		if ( 0 === strpos( $id, 'flavor_ui_' ) ) {
			$definition = UI::schema()[ substr( $id, strlen( 'flavor_ui_' ) ) ] ?? array();
			return $definition['choices'] ?? array();
		}
		return self::design_choices()[ $id ] ?? array();
	}
	public static function sanitize_select( $value, $setting ) {
		$choices = self::choices( $setting->id );
		return is_string( $value ) && array_key_exists( $value, $choices ) ? $value : $setting->default;
	}
	public static function validate_select( $validity, $value, $setting ) {
		if ( ! is_string( $value ) || ! array_key_exists( $value, self::choices( $setting->id ) ) ) {
			$validity->add( 'flavor_invalid_choice', __( 'یکی از گزینه‌های همین کنترل را انتخاب کنید.', 'flavor' ) );
		}
		return $validity;
	}
	public static function boolean( $value ): bool { return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true ); }

	public static function preview_assets(): void {
		$deps = array( 'customize-preview', 'flavor-main-js' );
		if ( UI::inner() ) { $deps[] = 'flavor-ui-js'; }
		wp_enqueue_script( 'flavor-customizer-preview', FLAVOR_URI . '/assets/js/customizer-preview.js', $deps, (string) filemtime( FLAVOR_DIR . '/assets/js/customizer-preview.js' ), true );
		$schema = array();
		foreach ( UI::schema() as $key => $definition ) { $schema[ $key ] = array_keys( $definition['choices'] ); }
		$defaults = Design::tokens( Design::current_skin() );
		wp_localize_script( 'flavor-customizer-preview', 'flavorPreviewData', array(
			'ui' => $schema,
			'fonts' => array_keys( self::design_choices()['flavor_font_body'] ),
			'heading' => $defaults['font_heading'], 'body' => $defaults['font_body'],
		) );
	}
	public static function control_assets(): void {
		wp_enqueue_style( 'flavor-customizer-modern', FLAVOR_URI . '/assets/css/customizer-modern.css', array( 'customize-controls' ), (string) filemtime( FLAVOR_DIR . '/assets/css/customizer-modern.css' ) );
		// Match the customizer accent with the active skin's primary color.
		$tokens = Design::tokens( Design::current_skin() );
		$accent = sanitize_hex_color( $tokens['primary'] ?? '' );
		if ( $accent ) {
			wp_add_inline_style( 'flavor-customizer-modern', ':root { --fv-skin-accent: ' . $accent . '; }' );
		}
		wp_enqueue_script( 'flavor-customizer-controls', FLAVOR_URI . '/assets/js/customizer-controls.js', array( 'customize-controls' ), (string) filemtime( FLAVOR_DIR . '/assets/js/customizer-controls.js' ), true );
		// Preview-a-demo without importing: if the user arrived from the demo
		// grid, stage the skin choice inside the changeset so they can inspect
		// it in the preview. Nothing is saved until they press Publish.
		$try_demo = isset( $_GET['flavor_try_demo'] ) ? sanitize_key( wp_unslash( $_GET['flavor_try_demo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $try_demo && array_key_exists( $try_demo, Design::skins() ) ) {
			wp_add_inline_script(
				'flavor-customizer-controls',
				'window.wp && wp.customize && wp.customize.bind("ready",function(){'
				. 'var s=wp.customize("flavor_skin");if(s&&s.get()!=="'.esc_js($try_demo).'"){s.set("'.esc_js($try_demo).'");}'
				. '});'
			);
		}
	}
}
