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
		add_action( 'elementor/widgets/register', array( self::class, 'register' ) );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets Manager.
	 */
	public static function register( $widgets ): void {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
			return;
		}
		require_once FLAVOR_DIR . '/elementor/class-widget-base.php';
		require_once FLAVOR_DIR . '/elementor/widgets/class-hero-widget.php';
		require_once FLAVOR_DIR . '/elementor/widgets/class-about-widget.php';
		require_once FLAVOR_DIR . '/elementor/widgets/class-gallery-widget.php';
		require_once FLAVOR_DIR . '/elementor/widgets/class-testimonials-widget.php';
		require_once FLAVOR_DIR . '/elementor/widgets/class-branch-info-widget.php';
		$widgets->register( new \Flavor\Elementor\Hero_Widget() );
		$widgets->register( new \Flavor\Elementor\About_Widget() );
		$widgets->register( new \Flavor\Elementor\Gallery_Widget() );
		$widgets->register( new \Flavor\Elementor\Testimonials_Widget() );
		$widgets->register( new \Flavor\Elementor\Branch_Info_Widget() );
	}
}
