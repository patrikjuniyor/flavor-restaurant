<?php
/**
 * Installable app manifest + offline shell for the storefront.
 *
 * Served from PHP (not static files) so the name, colours, icons and the
 * service worker's menu URL follow the live site settings and the active
 * skin. Nothing is cached on disk and no rewrite rules are required beyond
 * the two registered below — `template_redirect` answers both requests.
 *
 * Prompt 63: the service worker is the minimal offline shell — it precaches
 * the menu page on install, refreshes that cache on every successful menu
 * navigation and answers any failed navigation from it, so a phone with no
 * signal still opens the menu. Root scope comes from the
 * `Service-Worker-Allowed: /` header; registration ships in
 * assets/js/pwa-register.js and only runs in secure contexts.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class PWA
 */
class PWA {

	/**
	 * Query var that marks a manifest request.
	 */
	const QUERY_VAR = 'flavor_manifest';

	/**
	 * Query var that marks a service-worker request.
	 */
	const SW_QUERY_VAR = 'flavor_sw';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( self::class, 'add_query_var' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_serve' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_register' ) );
		add_action( 'after_switch_theme', 'flush_rewrite_rules' );
	}

	/**
	 * Register the rewrite rules.
	 */
	public static function add_rewrite_rule(): void {
		add_rewrite_rule( '^flavor-app\.webmanifest$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_rewrite_rule( '^flavor-sw\.js$', 'index.php?' . self::SW_QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register the query vars so `get_query_var()` can read them back.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::SW_QUERY_VAR;
		return $vars;
	}

	/**
	 * Public URL of the manifest.
	 */
	public static function manifest_url(): string {
		return home_url( '/flavor-app.webmanifest' );
	}

	/**
	 * Public URL of the service worker (root scope via header).
	 */
	public static function sw_url(): string {
		return home_url( '/flavor-sw.js' );
	}

	/**
	 * Serve the manifest or the service worker when requested.
	 */
	public static function maybe_serve(): void {
		$request = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		$manifest = (bool) get_query_var( self::QUERY_VAR ) || str_contains( $request, 'flavor-app.webmanifest' );
		$sw       = (bool) get_query_var( self::SW_QUERY_VAR ) || str_contains( $request, 'flavor-sw.js' );

		// Pretty permalinks use the rewrite rules; plain permalinks fall back
		// to the query vars so the feature never silently disappears.
		if ( $manifest ) {
			self::serve_manifest();
		}
		if ( $sw ) {
			self::serve_sw();
		}
	}

	/**
	 * Emit the manifest JSON.
	 */
	private static function serve_manifest(): void {
		$body = wp_json_encode(
			self::manifest(),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);

		status_header( 200 );
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON body.
		exit;
	}

	/**
	 * Emit the service worker with the live menu URL and cache version.
	 */
	private static function serve_sw(): void {
		$source = FLAVOR_DIR . '/assets/pwa/sw-source.js';
		$js     = is_readable( $source ) ? (string) file_get_contents( $source ) : '';
		if ( '' === trim( $js ) ) {
			status_header( 500 );
			exit;
		}
		$js = str_replace(
			array( '__CACHE_VERSION__', '__MENU_URL__' ),
			array( (string) filemtime( $source ), self::menu_url() ),
			$js
		);

		status_header( 200 );
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS body, no user input.
		exit;
	}

	/**
	 * Start URL: the menu page when present, else the front page.
	 */
	public static function menu_url(): string {
		$menu_page = get_page_by_path( 'menu' );
		return $menu_page ? (string) get_permalink( $menu_page ) : home_url( '/' );
	}

	/**
	 * Register script: progressive enhancement, secure contexts only.
	 */
	public static function enqueue_register(): void {
		$parts = (array) wp_parse_url( home_url() );
		$scheme = (string) ( $parts['scheme'] ?? 'https' );
		$host   = (string) ( $parts['host'] ?? '' );
		$secure = 'https' === $scheme || in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
		if ( ! $secure ) {
			return;
		}
		$file = FLAVOR_DIR . '/assets/js/pwa-register.js';
		wp_enqueue_script( 'flavor-pwa-register', FLAVOR_URI . '/assets/js/pwa-register.js', array(), (string) ( is_readable( $file ) ? filemtime( $file ) : '1' ), true );
		wp_localize_script(
			'flavor-pwa-register',
			'flavorPwa',
			array(
				'sw'    => self::sw_url(),
				'scope' => home_url( '/' ),
			)
		);
	}

	/**
	 * Manifest payload built from live settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function manifest(): array {
		$uri  = FLAVOR_URI . '/assets/pwa';
		$name = (string) get_bloginfo( 'name' );
		$desc = (string) get_bloginfo( 'description' );

		$manifest = array(
			'name'             => $name,
			'short_name'       => flavor_substr( $name, 0, 12 ),
			'description'      => $desc,
			'lang'             => get_bloginfo( 'language' ),
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'id'               => home_url( '/' ),
			'start_url'        => self::menu_url(),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'orientation'      => 'portrait-primary',
			'theme_color'      => Meta::theme_color(),
			'background_color' => Meta::background_color(),
			'icons'            => array(
				array(
					'src'     => $uri . '/icon-192.png',
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				array(
					'src'     => $uri . '/icon-512.png',
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				array(
					'src'     => $uri . '/icon-maskable-512.png',
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'maskable',
				),
			),
		);

		/**
		 * @param array<string, mixed> $manifest Manifest payload.
		 */
		return (array) apply_filters( 'flavor_pwa_manifest', $manifest );
	}
}
