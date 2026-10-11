<?php
/**
 * Elementor widgets — registered only when Elementor is active.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Elementor
 */
class Elementor {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		// Elementor's category hook can run before widgets/register. Load the
		// widget base lazily in register_category() rather than invoking a class
		// that has not been required yet.
		add_action( 'elementor/elements/categories_registered', array( self::class, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( self::class, 'register' ) );

		require_once FLAVOR_DIR . '/elementor/class-kit-sync.php';
		\Flavor\Elementor\Kit_Sync::init();
	}

	/**
	 * Widget class files, in load order.
	 *
	 * Kept as data so a test can assert the list, the file names and the
	 * registered instances all agree — a widget that exists on disk but is
	 * not registered here is invisible and nothing else would notice.
	 *
	 * @return string[]
	 */
	public static function widget_files(): array {
		return array(
			'class-hero-widget.php',
			'class-about-widget.php',
			'class-gallery-widget.php',
			'class-testimonials-widget.php',
			'class-branch-info-widget.php',
			'class-menu-widget.php',
			'class-reservation-widget.php',
			'class-hours-widget.php',
			'class-order-cta-widget.php',
			'class-offers-widget.php',
			'class-chefs-widget.php',
			'class-cart-widget.php',
			'class-features-widget.php',
			'class-stats-widget.php',
			'class-process-widget.php',
			'class-faq-widget.php',
			'class-price-list-widget.php',
		);
	}

	/**
	 * Widget class names, matched to widget_files() by position.
	 *
	 * @return string[]
	 */
	public static function widget_classes(): array {
		return array(
			'Hero_Widget',
			'About_Widget',
			'Gallery_Widget',
			'Testimonials_Widget',
			'Branch_Info_Widget',
			'Menu_Widget',
			'Reservation_Widget',
			'Hours_Widget',
			'Order_CTA_Widget',
			'Offers_Widget',
			'Chefs_Widget',
			'Cart_Widget',
			'Features_Widget',
			'Stats_Widget',
			'Process_Widget',
			'Faq_Widget',
			'Price_List_Widget',
		);
	}

	/**
	 * Register Flavor's category before Elementor registers the widgets.
	 *
	 * @param \Elementor\Elements_Manager $elements Manager.
	 */
	public static function register_category( $elements ): void {
		if ( ! self::load_widget_base() ) {
			return;
		}

		\Flavor\Elementor\Widget_Base::register_category( $elements );
	}

	/**
	 * Load the shared widget base only when Elementor is available.
	 *
	 * @return bool
	 */
	private static function load_widget_base(): bool {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return false;
		}

		require_once FLAVOR_DIR . '/elementor/class-widget-base.php';

		return class_exists( '\Flavor\Elementor\Widget_Base' );
	}

	/**
	 * Elementor stores page layouts in post meta, which can leave post_content empty.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether the post contains saved Elementor elements.
	 */
	public static function has_builder_content( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
			if ( isset( \Elementor\Plugin::$instance->editor ) && method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				return true;
			}
			if ( isset( \Elementor\Plugin::$instance->preview ) && method_exists( \Elementor\Plugin::$instance->preview, 'is_preview_mode' ) && \Elementor\Plugin::$instance->preview->is_preview_mode( $post_id ) ) {
				return true;
			}
		}

		if ( 'builder' !== get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return false;
		}

		$data = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_array( $data ) ) {
			return ! empty( $data );
		}

		if ( ! is_string( $data ) || '' === trim( $data ) ) {
			return false;
		}

		$elements = json_decode( $data, true );
		return is_array( $elements ) && ! empty( $elements );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets Manager.
	 */
	public static function register( $widgets ): void {
		if ( ! self::load_widget_base() ) {
			return;
		}

		foreach ( self::widget_files() as $file ) {
			require_once FLAVOR_DIR . '/elementor/widgets/' . $file;
		}

		foreach ( self::widget_classes() as $class ) {
			$fqcn = '\\Flavor\\Elementor\\' . $class;

			// A missing class here would be a fatal on every page load, so
			// skip silently rather than take the site down with it.
			if ( ! class_exists( $fqcn ) ) {
				continue;
			}

			$widgets->register( new $fqcn() );
		}
	}
}
