<?php
/**
 * Hero widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Hero_Widget
 */
class Hero_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_hero';
	}

	public function get_title() {
		return __( 'هیرو رستوران', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-banner';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'flavor' ) ) );
		$this->add_control( 'title', array( 'label' => __( 'عنوان', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_control( 'text', array( 'label' => __( 'متن', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => '' ) );
		$this->add_control( 'cta', array( 'label' => __( 'دکمه', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __( 'مشاهده منو', 'flavor' ) ) );
		$this->add_control( 'image', array( 'label' => __( 'تصویر', 'flavor' ), 'type' => \Elementor\Controls_Manager::MEDIA ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		get_template_part(
			'template-parts/marketing/hero',
			null,
			array(
				'title' => $s['title'] ?? '',
				'text'  => $s['text'] ?? '',
				'cta'   => $s['cta'] ?? '',
				'image' => $s['image']['url'] ?? '',
			)
		);
	}
}
