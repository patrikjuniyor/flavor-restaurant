<?php
/**
 * Front-end assets. No Google Fonts. No CDN libraries.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Enqueue
 */
class Enqueue {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'front' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor' ) );
	}

	/**
	 * Front-end CSS/JS.
	 */
	public static function front(): void {
		wp_enqueue_style(
			'flavor-fonts',
			FLAVOR_URI . '/assets/css/fonts.css',
			array(),
			FLAVOR_VERSION
		);

		wp_enqueue_style(
			'flavor-main',
			FLAVOR_URI . '/assets/css/main.css',
			array( 'flavor-fonts' ),
			FLAVOR_VERSION
		);

		wp_enqueue_script(
			'flavor-menu',
			FLAVOR_URI . '/assets/js/menu.js',
			array(),
			FLAVOR_VERSION,
			true
		);

		$rest = rest_url( 'flavor/v1/' );
		wp_localize_script(
			'flavor-menu',
			'flavorData',
			array(
				'rest'      => esc_url_raw( $rest ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'hasCore'   => Theme_Setup::has_core(),
				'i18n'      => array(
					'add'     => __( 'افزودن', 'flavor' ),
					'loading' => __( 'در حال بارگذاری منو…', 'flavor' ),
					'empty'   => __( 'آیتمی پیدا نشد.', 'flavor' ),
					'offline' => __( 'افزونه Flavor Core فعال نیست. منو از ووکامرس خوانده نمی‌شود.', 'flavor' ),
				),
			)
		);
	}

	/**
	 * Editor styles.
	 */
	public static function editor(): void {
		wp_enqueue_style(
			'flavor-fonts',
			FLAVOR_URI . '/assets/css/fonts.css',
			array(),
			FLAVOR_VERSION
		);
	}
}
