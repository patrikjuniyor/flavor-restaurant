<?php
/**
 * Gallery widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Gallery_Widget
 */
class Gallery_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_gallery';
	}

	public function get_title() {
		return __( 'گالری غذا', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'تصاویر', 'flavor' ) ) );
		$this->add_control( 'gallery', array( 'type' => \Elementor\Controls_Manager::GALLERY ) );
		$this->end_controls_section();
		$this->register_flavor_style_controls();
	}

	protected function render() {
		$s   = $this->get_settings_for_display();
		$urls = array();
		foreach ( $s['gallery'] ?? array() as $img ) {
			if ( ! empty( $img['url'] ) ) {
				$urls[] = $img['url'];
			}
		}

		if ( empty( $urls ) ) {
			// An untouched widget dragged onto a page must not become an invisible
			// hole. The shared marketing part still hides itself on classic pages —
			// a builder slot has to explain itself instead (R3-03).
			echo self::section( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
				__( 'گالری تصاویر', 'flavor' ),
				'<p class="flavor-widget-note">' . esc_html__( 'هنوز تصویری برای این گالری انتخاب نشده است؛ در تنظیماتِ ویجت چند تصویر از رسانه انتخاب کنید.', 'flavor' ) . '</p>',
				'flavor-section--gallery'
			);
			return;
		}

		get_template_part( 'template-parts/marketing/gallery', null, array( 'images' => $urls ) );
	}
}
