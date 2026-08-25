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
	}

	protected function render() {
		$s   = $this->get_settings_for_display();
		$urls = array();
		foreach ( $s['gallery'] ?? array() as $img ) {
			if ( ! empty( $img['url'] ) ) {
				$urls[] = $img['url'];
			}
		}
		get_template_part( 'template-parts/marketing/gallery', null, array( 'images' => $urls ) );
	}
}
