<?php
/**
 * Front-end assets. Conditional, no Google Fonts, no CDN libraries.
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
		add_action( 'wp_head', array( self::class, 'preload' ), 1 );
		add_filter( 'wp_lazy_loading_enabled', array( self::class, 'keep_hero_eager' ), 10, 3 );
	}

	/**
	 * Preload the primary Persian font.
	 */
	public static function preload(): void {
		$href = FLAVOR_URI . '/assets/fonts/vazirmatn/Vazirmatn-Regular.woff2';
		echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $href ) . '" crossorigin />' . "\n";
	}

	/**
	 * Front-end CSS/JS — only what the view needs.
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

		$needs_marketing = is_front_page() || is_home() || is_page_template( 'page-templates/template-branches.php' );
		if ( $needs_marketing ) {
			wp_enqueue_style(
				'flavor-marketing',
				FLAVOR_URI . '/assets/css/marketing.css',
				array( 'flavor-main' ),
				FLAVOR_VERSION
			);
		}

		$rest    = rest_url( 'flavor/v1/' );
		$payload = array(
			'rest'    => esc_url_raw( $rest ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'hasCore' => Theme_Setup::has_core(),
			'i18n'    => array(
				'add'     => __( 'افزودن', 'flavor' ),
				'loading' => __( 'در حال بارگذاری منو…', 'flavor' ),
				'empty'   => __( 'آیتمی پیدا نشد.', 'flavor' ),
				'offline' => __( 'افزونه Flavor Core فعال نیست. منو از ووکامرس خوانده نمی‌شود.', 'flavor' ),
			),
		);

		$payload['ajax']     = esc_url_raw( admin_url( 'admin-ajax.php' ) );
		$payload['branchId'] = self::current_branch_id();

		$is_menu = is_page_template( 'page-templates/template-menu.php' );
		if ( $is_menu ) {
			wp_enqueue_script(
				'flavor-menu',
				FLAVOR_URI . '/assets/js/menu.js',
				array(),
				FLAVOR_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			wp_localize_script( 'flavor-menu', 'flavorData', $payload );

			wp_enqueue_style(
				'flavor-search',
				FLAVOR_URI . '/assets/css/search.css',
				array( 'flavor-main' ),
				FLAVOR_VERSION
			);

			wp_enqueue_script(
				'flavor-search',
				FLAVOR_URI . '/assets/js/search.js',
				array(),
				FLAVOR_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			$search_payload          = $payload;
			$search_payload['i18n']  = array_merge(
				$payload['i18n'],
				array(
					'recent'       => __( 'جست‌وجوهای اخیر', 'flavor' ),
					'popular'      => __( 'پرجست‌وجوها', 'flavor' ),
					'noResults'    => __( 'چیزی پیدا نشد.', 'flavor' ),
					'didYouMean'   => __( 'منظورتان این بود؟', 'flavor' ),
					'resultsFound' => __( 'نتیجه برای', 'flavor' ),
					'unavailable'  => __( 'ناموجود', 'flavor' ),
					'minutes'      => __( 'دقیقه', 'flavor' ),
					'kcal'         => __( 'کالری', 'flavor' ),
					'error'        => __( 'جست‌وجو ناموفق بود. دوباره تلاش کنید.', 'flavor' ),
				)
			);

			wp_localize_script( 'flavor-search', 'flavorSearchData', $search_payload );
		}

		if ( is_page_template( 'page-templates/template-reservation.php' ) ) {
			wp_enqueue_script(
				'flavor-reservation',
				FLAVOR_URI . '/assets/js/reservation.js',
				array(),
				FLAVOR_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			wp_localize_script( 'flavor-reservation', 'flavorData', $payload );
		}
	}

	/**
	 * Branch id from the Flavor Core order context, when available.
	 */
	public static function current_branch_id(): int {
		if ( class_exists( '\\FlavorCore\\Order\\OrderModes' ) ) {
			$ctx = \FlavorCore\Order\OrderModes::get();
			if ( is_array( $ctx ) && ! empty( $ctx['branch_id'] ) ) {
				return (int) $ctx['branch_id'];
			}
		}
		return 0;
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

	/**
	 * Hero images stay eager for LCP.
	 *
	 * @param bool   $default Default.
	 * @param string $tag     Tag.
	 * @param string $context Context.
	 */
	public static function keep_hero_eager( bool $default, string $tag, string $context ): bool {
		unset( $tag, $context );
		return $default;
	}
}
