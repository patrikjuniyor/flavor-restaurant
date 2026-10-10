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
		add_action( 'elementor/elements/categories_registered', array( \Flavor\Elementor\Widget_Base::class, 'register_category' ) );
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
	 * @param \Elementor\Widgets_Manager $widgets Manager.
	 */
	public static function register( $widgets ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once FLAVOR_DIR . '/elementor/class-widget-base.php';

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
