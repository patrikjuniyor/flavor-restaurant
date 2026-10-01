<?php
/**
 * Bespoke, RTL-first demo landing pages. Only completed packs opt in.
 * Shared UI reads live WooCommerce products; it never simulates checkout.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

class Bespoke_Demos {
	/** Ready packs; legacy homepages remain untouched. */
	public const SLUGS = array( 'juice-bar' );

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_action( 'customize_register', array( self::class, 'customizer' ) );
	}

	public static function active(): bool {
		return in_array( Design::current_skin(), self::SLUGS, true );
	}

	/** @return array<string, mixed> */
	public static function demo(): array {
		static $packs = null;
		if ( null === $packs ) {
			require_once FLAVOR_DIR . '/inc/demo-catalog.php';
			$packs = flavor_demo_catalog();
		}
		$skin = Design::current_skin();
		return (array) apply_filters( 'flavor_bespoke_demo', $packs[ $skin ] ?? array(), $skin );
	}

	/** Copy can be overridden in the Customizer without editing templates. */
	public static function value( string $key, string $fallback = '' ): string {
		$demo    = self::demo();
		$default = $demo['landing'][ $key ] ?? $fallback;
		return (string) get_theme_mod( 'flavor_landing_' . $key, is_scalar( $default ) ? $default : $fallback );
	}

	public static function enabled( string $key ): bool {
		return 'no' !== get_theme_mod( 'flavor_landing_' . $key . '_enable', 'yes' );
	}

	/** Local, bundled media only; traversal and missing-image paths are rejected. */
	public static function asset( string $file ): string {
		$file = sanitize_file_name( basename( $file ) );
		$skin = Design::current_skin();
		if ( ! is_readable( FLAVOR_DIR . '/demos/' . $skin . '/' . $file ) ) {
			$file = 'hero.jpg';
		}
		return FLAVOR_URI . '/demos/' . $skin . '/' . $file;
	}

	public static function page_url( string $slug ): string {
		$page = get_page_by_path( $slug );
		return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
	}

	public static function action_url( string $action = 'menu' ): string {
		if ( 'phone' === $action ) {
			return 'tel:' . preg_replace( '/\D+/', '', (string) get_theme_mod( 'flavor_phone', self::demo()['phone'] ?? '' ) );
		}
		if ( 0 === strpos( $action, '#' ) ) {
			return home_url( '/' ) . $action;
		}
		return self::page_url( in_array( $action, array( 'menu', 'reservation', 'branches', 'contact' ), true ) ? $action : 'menu' );
	}

	public static function digits( string $value ): string {
		return strtr( $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/** Safe inline emphasis, not arbitrary markup from settings. */
	public static function hero_title(): string {
		$demo   = self::demo();
		$title  = (string) get_theme_mod( 'flavor_hero_title', $demo['hero_title'] ?? get_bloginfo( 'name' ) );
		$accent = self::value( 'hero_accent' );
		$pos    = '' !== $accent ? strpos( $title, $accent ) : false;
		if ( false === $pos ) {
			return esc_html( $title );
		}
		return esc_html( substr( $title, 0, $pos ) ) . '<em>' . esc_html( $accent ) . '</em>' . esc_html( substr( $title, $pos + strlen( $accent ) ) );
	}

	/** Decorative icons have no duplicate accessible names. */
	public static function icon( string $name, int $size = 22 ): void {
		$paths = array(
			'arrow'   => '<path d="M19 12H5m6-6-6 6 6 6"/>',
			'leaf'    => '<path d="M20 4C9 2 3 7 5 14c2 6 12 7 15-10Z"/><path d="M4 21 15 10M9 16v-5m0 5h5"/>',
			'bag'     => '<path d="M5 7h14l1 14H4L5 7Z"/><path d="M9 8V5a3 3 0 0 1 6 0v3"/>',
			'phone'   => '<path d="m7 3 3 5-2 2a15 15 0 0 0 6 6l2-2 5 3v3c-9 2-20-9-18-18l4 1Z"/>',
			'pin'     => '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
			'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
			'check'   => '<path d="m5 12 4 4L19 6"/>',
			'plus'    => '<path d="M12 5v14M5 12h14"/>',
			'glass'   => '<path d="M6 5h12l-2 16H8L6 5ZM13 5l3-3"/><path d="M7 10h10"/>',
			'spark'   => '<path d="m12 2 3 7 7 3-7 3-3 7-3-7-7-3 7-3 3-7Z"/>',
			'truck'   => '<path d="M2 5h12v12H2V5Zm12 4h4l4 5v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
			'calendar'=> '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 11h18"/>',
			'home'    => '<path d="m3 10 9-7 9 7v11h-6v-7H9v7H3V10Z"/>',
			'menu'    => '<path d="M4 6h16M4 12h16M4 18h16"/>',
		);
		$size = max( 12, min( 64, $size ) );
		echo '<svg aria-hidden="true" focusable="false" width="' . esc_attr( (string) $size ) . '" height="' . esc_attr( (string) $size ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">';
		echo $paths[ $name ] ?? $paths['spark']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal trusted SVG.
		echo '</svg>';
	}

	/** @param string[] $classes @return string[] */
	public static function body_class( array $classes ): array {
		if ( self::active() ) {
			$classes[] = 'flavor-bespoke';
		}
		return $classes;
	}

	/** @param \WP_Customize_Manager $wp_customize Customizer. */
	public static function customizer( $wp_customize ): void {
		if ( ! self::active() ) {
			return;
		}
		$wp_customize->add_section( 'flavor_bespoke', array(
			'title'       => __( 'صفحه اختصاصی دمو', 'flavor' ),
			'description' => __( 'تیتر، تصویر اصلی، رنگ و فونت از بخش‌های معمول قالب قابل تغییرند. این بخش محتوای صفحه اختصاصی را کنترل می‌کند.', 'flavor' ),
			'priority'    => 38,
		) );
		$fields = array(
			'eyebrow'       => __( 'برچسب بالای تیتر', 'flavor' ),
			'hero_accent'   => __( 'عبارت برجسته در تیتر اصلی', 'flavor' ),
			'primary_label'=> __( 'متن دکمه اصلی', 'flavor' ),
			'menu_title'    => __( 'تیتر منوی منتخب', 'flavor' ),
			'story_title'   => __( 'تیتر داستان برند', 'flavor' ),
			'feature_title' => __( 'تیتر پیشنهاد اختصاصی', 'flavor' ),
			'feature_text'  => __( 'توضیح پیشنهاد اختصاصی', 'flavor' ),
			'visit_title'   => __( 'تیتر بخش تماس', 'flavor' ),
			'brand_caption'=> __( 'زیرعنوان لوگوی متنی', 'flavor' ),
		);
		foreach ( $fields as $key => $label ) {
			$id = 'flavor_landing_' . $key;
			$wp_customize->add_setting( $id, array( 'default' => self::value( $key ), 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp_customize->add_control( $id, array( 'label' => $label, 'section' => 'flavor_bespoke', 'type' => 'feature_text' === $key ? 'textarea' : 'text' ) );
		}
		foreach ( array( 'story' => 'داستان برند', 'process' => 'مراحل و ویژگی‌ها', 'feature' => 'پیشنهاد اختصاصی', 'faq' => 'پرسش‌های متداول' ) as $key => $label ) {
			$id = 'flavor_landing_' . $key . '_enable';
			$wp_customize->add_setting( $id, array( 'default' => 'yes', 'sanitize_callback' => static function ( $v ) { return 'no' === $v ? 'no' : 'yes'; } ) );
			$wp_customize->add_control( $id, array( 'label' => $label, 'section' => 'flavor_bespoke', 'type' => 'radio', 'choices' => array( 'yes' => __( 'نمایش', 'flavor' ), 'no' => __( 'پنهان', 'flavor' ) ) ) );
		}
		$wp_customize->add_setting( 'flavor_landing_story_image', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( new \WP_Customize_Image_Control( $wp_customize, 'flavor_landing_story_image', array( 'label' => __( 'تصویر داستان برند', 'flavor' ), 'section' => 'flavor_bespoke' ) ) );
	}
}
