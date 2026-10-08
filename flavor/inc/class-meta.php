<?php
/**
 * Mobile-browser chrome: theme color, app icons and install metadata.
 *
 * A restaurant site is opened from a QR code on a table, so the browser bar
 * takes the brand colour instead of stock white, and the site can be pinned
 * to the home screen with a branded icon.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Meta
 */
class Meta {

	/**
	 * Inline style handle used for the custom-property override.
	 */
	const HANDLE = 'flavor-brand-color';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'head' ), 2 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'brand_color_override' ), 20 );
	}

	/**
	 * An explicit `flavor_theme_color` Customizer value wins over the skin
	 * palette; it is appended as a tiny inline style instead of a new request.
	 */
	public static function brand_color_override(): void {
		$override = (string) get_theme_mod( 'flavor_theme_color', '' );
		if ( ! preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $override ) ) {
			return;
		}

		wp_register_style( self::HANDLE, false, array(), FLAVOR_VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, ':root{--flavor-primary:' . esc_attr( $override ) . '}' );
	}

	/**
	 * Brand colour for browser chrome, resolved from the active skin
	 * (and any Customizer override) so every one of the twelve demos
	 * paints its own bar.
	 */
	public static function theme_color(): string {
		$tokens = Design::resolved();
		$color  = (string) ( $tokens['primary'] ?? '' );

		/**
		 * @param string $color Hex colour.
		 */
		$color = (string) apply_filters( 'flavor_theme_color', $color );

		return (bool) preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $color ) ? $color : '#a74e2c';
	}

	/**
	 * Background colour used behind the address bar / splash while the
	 * installable app boots (the skin surface keeps the transition seamless).
	 */
	public static function background_color(): string {
		$tokens = Design::resolved();
		$color  = (string) ( $tokens['bg'] ?? '' );

		return (bool) preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $color ) ? $color : '#f4efe7';
	}

	/**
	 * Print the meta tags, icons and manifest link.
	 */
	public static function head(): void {
		$uri   = FLAVOR_URI . '/assets/pwa';
		$color = self::theme_color();

		echo '<meta name="theme-color" content="' . esc_attr( $color ) . '" />' . "\n";
		echo '<meta name="color-scheme" content="light dark" />' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes" />' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />' . "\n";
		printf(
			'<meta name="apple-mobile-web-app-title" content="%s" />' . "\n",
			esc_attr( get_bloginfo( 'name' ) )
		);

		// Core prints its own icon tags when a Site Icon is set; stay out of its way.
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" href="%s" sizes="32x32" />' . "\n", esc_url( $uri . '/favicon-32.png' ) );
			printf( '<link rel="icon" href="%s" sizes="48x48" />' . "\n", esc_url( $uri . '/favicon-48.png' ) );
		}
		printf( '<link rel="apple-touch-icon" href="%s" />' . "\n", esc_url( $uri . '/apple-touch-icon.png' ) );
		printf( '<link rel="manifest" href="%s" />' . "\n", esc_url( PWA::manifest_url() ) );
	}
}
