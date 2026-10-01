<?php
/**
 * Front-end assets enqueueing.
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

		$skin = Design::current_skin();
		if ( Bespoke_Demos::active() || in_array( $skin, array( 'persian-traditional', 'luxury-dining', 'fast-food', 'cafe-bistro', 'pizza-italian', 'bakery-pastry' ), true ) ) {
			$display_font = FLAVOR_URI . '/assets/fonts/estedad/Estedad-Variable.woff2';
			echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $display_font ) . '" crossorigin />' . "\n";
		}
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

		wp_enqueue_style(
			'flavor-marketing',
			FLAVOR_URI . '/assets/css/marketing.css',
			array( 'flavor-main' ),
			FLAVOR_VERSION
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'flavor-rtl',
				FLAVOR_URI . '/assets/css/rtl.css',
				array( 'flavor-main' ),
				FLAVOR_VERSION
			);
		}

		// Each commercial demo can ship its own art direction without adding
		// unused CSS to other sites. The filename must match the sanitized skin.
		$skin       = Design::current_skin();
		$skin_file  = '/assets/css/skins/' . sanitize_file_name( $skin ) . '.css';
		$skin_path  = FLAVOR_DIR . $skin_file;
		$skin_deps  = is_rtl() ? array( 'flavor-marketing', 'flavor-rtl' ) : array( 'flavor-marketing' );
		if ( Bespoke_Demos::active() ) {
			wp_enqueue_style(
				'flavor-bespoke',
				FLAVOR_URI . '/assets/css/bespoke-demos.css',
				$skin_deps,
				(string) filemtime( FLAVOR_DIR . '/assets/css/bespoke-demos.css' )
			);
			$skin_deps[] = 'flavor-bespoke';
		}
		if ( is_readable( $skin_path ) ) {
			wp_enqueue_style(
				'flavor-skin-' . sanitize_html_class( $skin ),
				FLAVOR_URI . $skin_file,
				$skin_deps,
				(string) filemtime( $skin_path )
			);
		}

		$menu_page = get_page_by_path( 'menu' );
		$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );

		$rest    = rest_url( 'flavor/v1/' );
		$payload = array(
			'rest'    => esc_url_raw( $rest ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'menuUrl' => esc_url_raw( $menu_url ),
			'hasCore' => Theme_Setup::has_core(),
			'i18n'    => array(
				'add'     => __( 'افزودن', 'flavor' ),
				'loading' => __( 'در حال بارگذاری منو…', 'flavor' ),
				'empty'   => __( 'آیتمی پیدا نشد.', 'flavor' ),
				'offline' => __( 'افزونه Flavor Core فعال نیست. منو از ووکامرس خوانده نمی‌شود.', 'flavor' ),
			),
		);

		if ( class_exists( '\\FlavorCore\\WooCommerce\\Currency' ) ) {
			$payload['currency'] = array(
				'storage' => \FlavorCore\WooCommerce\Currency::storage_unit(),
				'display' => \FlavorCore\WooCommerce\Currency::display_unit(),
				'label'   => \FlavorCore\WooCommerce\Currency::display_label(),
			);
		}

		$payload['ajax']     = esc_url_raw( admin_url( 'admin-ajax.php' ) );
		$payload['branchId'] = self::current_branch_id();
		if ( Bespoke_Demos::active() && $payload['branchId'] ) {
			$payload['defaultMode'] = Bespoke_Demos::demo()['landing']['default_mode'] ?? 'takeaway';
			$modes = get_post_meta( $payload['branchId'], '_flavor_order_modes', true );
			$payload['orderModes'] = is_array( $modes ) ? array_values( $modes ) : array( 'dine_in', 'takeaway', 'delivery' );
		}

		// Enqueue global theme script
		wp_enqueue_script(
			'flavor-main-js',
			FLAVOR_URI . '/assets/js/main.js',
			array(),
			FLAVOR_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script( 'flavor-main-js', 'flavorData', $payload );

		if ( Bespoke_Demos::active() ) {
			wp_enqueue_script(
				'flavor-bespoke-js',
				FLAVOR_URI . '/assets/js/bespoke-demos.js',
				array( 'flavor-main-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/bespoke-demos.js' ),
				array( 'in_footer' => true, 'strategy' => 'defer' )
			);
		}

		// The catering brief stays local to the homepage; no unused planner code
		// is loaded on other demos or on commerce/reservation pages.
		if ( 'catering' === $skin && is_front_page() ) {
			wp_enqueue_script(
				'flavor-catering-js',
				FLAVOR_URI . '/assets/js/catering.js',
				array( 'flavor-bespoke-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/catering.js' ),
				array( 'in_footer' => true, 'strategy' => 'defer' )
			);
		}

		$is_menu = is_page_template( 'page-templates/template-menu.php' );
		if ( $is_menu ) {
			wp_enqueue_script(
				'flavor-menu',
				FLAVOR_URI . '/assets/js/menu.js',
				array( 'flavor-main-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/menu.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			wp_enqueue_style(
				'flavor-search',
				FLAVOR_URI . '/assets/css/search.css',
				array( 'flavor-main' ),
				FLAVOR_VERSION
			);

			wp_enqueue_script(
				'flavor-search',
				FLAVOR_URI . '/assets/js/search.js',
				array( 'flavor-main-js' ),
				FLAVOR_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			$search_payload         = $payload;
			$search_payload['i18n'] = array_merge(
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
				array( 'flavor-main-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/reservation.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * Branch id from the Flavor Core order context, when available.
	 */
	public static function current_branch_id(): int {
		if ( class_exists( \FlavorCore\Order\OrderModes::class ) ) {
			$ctx = \FlavorCore\Order\OrderModes::get();
			if ( is_array( $ctx ) && ! empty( $ctx['branch_id'] ) && 'flavor_branch' === get_post_type( (int) $ctx['branch_id'] ) ) {
				return (int) $ctx['branch_id'];
			}
		}
		return class_exists( \FlavorCore\PostTypes\BranchPostType::class ) ? \FlavorCore\PostTypes\BranchPostType::default_id() : 0;
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
