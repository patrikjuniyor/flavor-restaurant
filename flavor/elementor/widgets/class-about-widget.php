<?php
/**
 * About widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class About_Widget
 */
class About_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_about';
	}

	public function get_title() {
		return __( 'درباره رستوران', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-info-circle';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'flavor' ) ) );
		$this->add_control( 'title', array( 'label' => __( 'عنوان', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __( 'داستان ما', 'flavor' ) ) );
		$this->add_control( 'text', array( 'label' => __( 'متن', 'flavor' ), 'type' => \Elementor\Controls_Manager::WYSIWYG ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		get_template_part(
			'template-parts/marketing/about',
			null,
			array(
				'title' => $s['title'] ?? '',
				'text'  => $s['text'] ?? '',
			)
		);
	}
}
