<?php
/**
 * Suggested cache exclusions and HTTP caching headers for LiteSpeed / WP Rocket / Super Cache / CDNs.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class CacheHints
 */
class CacheHints {

	/**
	 * Default public max-age in seconds for stale-while-revalidate pages.
	 */
	public const DEFAULT_PUBLIC_MAX_AGE = 300;

	/**
	 * Default stale-while-revalidate window in seconds.
	 */
	public const DEFAULT_STALE_WHILE_REVALIDATE = 600;

	/**
	 * Static asset max-age in seconds (1 year).
	 */
	public const STATIC_IMMUTABLE_MAX_AGE = 31536000;

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'rocket_cache_reject_uri', array( $this, 'rocket' ) );
		add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ), 20 );
		add_action( 'send_headers', array( $this, 'send_cache_headers' ) );
	}

	/**
	 * Paths that must never be full-page cached.
	 *
	 * @return string[]
	 */
	public static function uris(): array {
		return array(
			'/kitchen-dashboard',
			'/kitchen-receipt',
			'/branch/.*/table/',
			'/checkout',
			'/cart',
			'/my-account',
			'/wc-api/',
			'/wp-json/flavor/',
		);
	}

	/**
	 * WP Rocket reject list.
	 *
	 * @param string[] $uris URIs.
	 * @return string[]
	 */
	public function rocket( array $uris ): array {
		return array_merge( $uris, self::uris() );
	}

	/**
	 * Determine if current request context is a dynamic customer page (cart, checkout, account).
	 *
	 * @param string $uri Optional request URI to evaluate if conditional functions aren't ready.
	 * @return bool
	 */
	public static function is_customer_page( string $uri = '' ): bool {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		if ( '' === $uri && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		if ( '' !== $uri ) {
			$clean_path = '/' . trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
			foreach ( array( '/cart', '/checkout', '/my-account', '/kitchen-dashboard', '/kitchen-receipt' ) as $no_store_path ) {
				if ( $clean_path === $no_store_path || 0 === strpos( $clean_path, $no_store_path . '/' ) ) {
					return true;
				}
			}
			if ( preg_match( '#^/branch/[^/]+/table/#i', $clean_path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if a static asset URI represents a versioned or demo asset that can be safely immutable.
	 *
	 * @param string $uri Request URI or asset path.
	 * @return bool
	 */
	public static function is_immutable_asset( string $uri ): bool {
		$parsed = wp_parse_url( $uri );
		$path   = $parsed['path'] ?? $uri;
		$query  = $parsed['query'] ?? '';

		// Demo images packaging: demos/ or flavor-demo-pack
		if ( preg_match( '#/(demos|flavor-demo-pack)/#i', $path ) ) {
			$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'svg', 'gif', 'avif' ), true ) ) {
				return true;
			}
		}

		// Static CSS/JS with version query string or versioned bundle in path
		if ( preg_match( '#\.(css|js)$#i', $path ) ) {
			if ( '' !== $query && ( false !== strpos( $query, 'ver=' ) || false !== strpos( $query, 'v=' ) ) ) {
				return true;
			}
			if ( preg_match( '#[.-]([0-9]+\.[0-9]+(\.[0-9]+)?|[a-f0-9]{8,})\.(css|js)$#i', $path ) ) {
				return true;
			}
		}

		// Web fonts
		if ( preg_match( '#\.(woff2?|ttf|otf|eot)$#i', $path ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Compute the appropriate Cache-Control policy value for a given context.
	 *
	 * @param array<string, mixed> $context Context parameters (uri, is_admin, is_customer, is_static, user_logged_in).
	 * @return string Cache-Control header value.
	 */
	public static function compute_cache_control( array $context = array() ): string {
		$is_admin       = (bool) ( $context['is_admin'] ?? ( function_exists( 'is_admin' ) ? is_admin() : false ) );
		$is_logged_in   = (bool) ( $context['is_logged_in'] ?? ( function_exists( 'is_user_logged_in' ) ? is_user_logged_in() : false ) );
		$uri            = (string) ( $context['uri'] ?? ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) );
		$is_customer    = (bool) ( $context['is_customer'] ?? self::is_customer_page( $uri ) );
		$is_immutable   = (bool) ( $context['is_immutable'] ?? self::is_immutable_asset( $uri ) );

		// Static immutable bundles
		if ( $is_immutable ) {
			$max_age = (int) apply_filters( 'flavor_cache_immutable_max_age', self::STATIC_IMMUTABLE_MAX_AGE );
			return "public, max-age={$max_age}, immutable";
		}

		// Admin pages: private, no-cache
		if ( $is_admin ) {
			return 'private, no-cache';
		}

		// Customer dynamic pages or logged-in user requests: strictly no-store
		if ( $is_customer || $is_logged_in ) {
			return 'no-store, no-cache, must-revalidate, max-age=0';
		}

		// Public storefront/content pages: stale-while-revalidate
		$max_age = (int) apply_filters( 'flavor_cache_public_max_age', self::DEFAULT_PUBLIC_MAX_AGE );
		$swr     = (int) apply_filters( 'flavor_cache_stale_while_revalidate', self::DEFAULT_STALE_WHILE_REVALIDATE );

		return "public, max-age={$max_age}, stale-while-revalidate={$swr}";
	}

	/**
	 * Filter wp_headers for front-end requests.
	 *
	 * @param array<string, string> $headers Headers map.
	 * @return array<string, string>
	 */
	public function filter_wp_headers( array $headers ): array {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return $headers;
		}

		// If headers already explicitly set a Cache-Control, respect it unless it's a customer page that must be no-store
		$is_customer = self::is_customer_page();
		if ( $is_customer ) {
			$headers['Cache-Control'] = self::compute_cache_control( array( 'is_customer' => true ) );
			$headers['Pragma']        = 'no-cache';
			$headers['Expires']       = 'Wed, 11 Jan 1984 05:00:00 GMT';
			return $headers;
		}

		if ( ! isset( $headers['Cache-Control'] ) ) {
			$headers['Cache-Control'] = self::compute_cache_control(
				array(
					'is_customer'  => false,
					'is_admin'     => false,
					'is_logged_in' => function_exists( 'is_user_logged_in' ) ? is_user_logged_in() : false,
				)
			);
		}

		return $headers;
	}

	/**
	 * Action hook for send_headers.
	 */
	public function send_cache_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( self::is_immutable_asset( $uri ) ) {
			$policy = self::compute_cache_control( array( 'is_immutable' => true ) );
			header( "Cache-Control: {$policy}" );
		}
	}

	/**
	 * One-time setup hint.
	 */
	public function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 'yes' === get_option( 'flavor_core_cache_notice_dismissed' ) ) {
			return;
		}
		if ( ! isset( $_GET['page'] ) || 0 !== strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'flavor' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'اگر افزونه کش دارید، این مسیرها را از کش تمام‌صفحه خارج کنید: /kitchen-dashboard/، /kitchen-receipt/، /wp-json/flavor/، کوکی flavor_ctx', 'flavor-core' );
		echo '</p></div>';
	}
}
