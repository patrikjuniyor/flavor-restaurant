<?php
/**
 * Testimonials widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Testimonials_Widget
 */
class Testimonials_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_testimonials';
	}

	public function get_title() {
		return __( 'نظرات مهمان', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-testimonial';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'نظرها', 'flavor' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'name', array( 'label' => __( 'نام', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );
		$repeater->add_control( 'text', array( 'label' => __( 'متن', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXTAREA ) );
		$this->add_control(
			'items',
			array(
				'type'    => \Elementor\Controls_Manager::REPEATER,
				'fields'  => $repeater->get_controls(),
				'default' => array(),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		get_template_part( 'template-parts/marketing/testimonials', null, array( 'items' => $s['items'] ?? array() ) );
	}
}
