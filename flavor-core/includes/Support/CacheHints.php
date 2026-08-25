<?php
/**
 * Suggested cache exclusions for LiteSpeed / WP Rocket / Super Cache.
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
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'rocket_cache_reject_uri', array( $this, 'rocket' ) );
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
	 * One-time setup hint.
	 */
	public function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 'yes' === get_option( 'flavor_core_cache_notice_dismissed' ) ) {
			return;
		}
		if ( ! isset( $_GET['page'] ) || 0 !== strpos( (string) wp_unslash( $_GET['page'] ), 'flavor' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'اگر افزونه کش دارید، این مسیرها را از کش تمام‌صفحه خارج کنید: /kitchen-dashboard/ ، /kitchen-receipt/ ، /wp-json/flavor/ ، کوکی flavor_ctx', 'flavor-core' );
		echo '</p></div>';
	}
}
