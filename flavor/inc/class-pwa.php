<?php
/**
 * Installable app manifest for the storefront.
 *
 * Served from PHP (not a static file) so the name, colours and icons follow
 * the live site settings and the active skin. Nothing is cached on disk and
 * no rewrite rules are required — `template_redirect` answers the request.
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
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( self::class, 'add_query_var' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_serve' ) );
		add_action( 'after_switch_theme', 'flush_rewrite_rules' );
	}

	/**
	 * Register the rewrite rule.
	 */
	public static function add_rewrite_rule(): void {
		add_rewrite_rule( '^flavor-app\.webmanifest$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register the query var so `get_query_var()` can read it back.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Public URL of the manifest.
	 */
	public static function manifest_url(): string {
		return home_url( '/flavor-app.webmanifest' );
	}

	/**
	 * Serve the manifest when requested.
	 */
	public static function maybe_serve(): void {
		$direct  = (bool) get_query_var( self::QUERY_VAR );
		$request = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		// Pretty permalinks use the rewrite rule; plain permalinks fall back
		// to ?flavor_manifest=1 so the feature never silently disappears.
		if ( ! $direct && ! str_contains( $request, 'flavor-app.webmanifest' ) ) {
			return;
		}

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
	 * Manifest payload built from live settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function manifest(): array {
		$uri  = FLAVOR_URI . '/assets/pwa';
		$name = (string) get_bloginfo( 'name' );
		$desc = (string) get_bloginfo( 'description' );

		$menu_page = get_page_by_path( 'menu' );
		$start_url = $menu_page ? get_permalink( $menu_page ) : home_url( '/' );

		$manifest = array(
			'name'             => $name,
			'short_name'       => mb_substr( $name, 0, 12 ),
			'description'      => $desc,
			'lang'             => get_bloginfo( 'language' ),
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'start_url'        => $start_url,
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
