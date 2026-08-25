<?php
/**
 * Theme supports, menus, image sizes.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Theme_Setup
 */
class Theme_Setup {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'after_setup_theme', array( self::class, 'supports' ) );
		add_action( 'after_setup_theme', array( self::class, 'menus' ) );
		add_action( 'widgets_init', array( self::class, 'sidebars' ) );
		add_action( 'admin_notices', array( self::class, 'plugin_notice' ) );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * add_theme_support calls.
	 */
	public static function supports(): void {
		load_theme_textdomain( 'flavor', FLAVOR_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
		);
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 240,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );

		add_image_size( 'flavor-card', 600, 400, true );
		add_image_size( 'flavor-hero', 1600, 900, true );

		add_editor_style( 'assets/css/main.css' );
	}

	/**
	 * Nav menus.
	 */
	public static function menus(): void {
		register_nav_menus(
			array(
				'primary' => __( 'منوی اصلی', 'flavor' ),
				'footer'  => __( 'منوی پاورقی', 'flavor' ),
			)
		);
	}

	/**
	 * Sidebars.
	 */
	public static function sidebars(): void {
		register_sidebar(
			array(
				'name'          => __( 'پاورقی', 'flavor' ),
				'id'            => 'footer-1',
				'before_widget' => '<section class="flavor-widget">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="flavor-widget__title">',
				'after_title'   => '</h2>',
			)
		);
	}

	/**
	 * Tell admins the companion plugin is missing.
	 */
	public static function plugin_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( defined( 'FLAVOR_CORE_VERSION' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'قالب Flavor بدون افزونه Flavor Core مثل یک قالب رستورانی معمولی کار می‌کند. برای سفارش‌گیری، QR و آشپزخانه افزونه را فعال کنید.', 'flavor' );
		echo '</p></div>';
	}

	/**
	 * Body classes.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		$classes[] = 'flavor-theme';
		$classes[] = defined( 'FLAVOR_CORE_VERSION' ) ? 'flavor-has-core' : 'flavor-no-core';
		return $classes;
	}

	/**
	 * Whether the companion plugin is active.
	 */
	public static function has_core(): bool {
		return defined( 'FLAVOR_CORE_VERSION' );
	}
}
