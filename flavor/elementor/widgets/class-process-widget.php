<?php
/**
 * Process widget: numbered steps, for example how to order or reserve.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Process_Widget
 */
class Process_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_process';
	}

	public function get_title() {
		return __( 'مراحل سفارش یا رزرو', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-steps';
	}

	public function get_keywords() {
		return array( 'steps', 'process', 'مراحل', 'سفارش', 'رزرو', 'restaurant' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'flavor' ) ) );

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان بخش', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'چطور سفارش دهید؟', 'flavor' ),
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'title', array( 'label' => __( 'عنوان مرحله', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );
		$repeater->add_control( 'text', array( 'label' => __( 'توضیح', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXTAREA ) );

		$this->add_control(
			'steps',
			array(
				'label'   => __( 'مراحل', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::REPEATER,
				'fields'  => $repeater->get_controls(),
				'default' => array(
					array(
						'title' => __( 'انتخاب غذا', 'flavor' ),
						'text'  => __( 'از منو غذای مورد نظرتان را انتخاب کنید.', 'flavor' ),
					),
					array(
						'title' => __( 'ثبت سفارش', 'flavor' ),
						'text'  => __( 'سفارش را با پرداخت آنلاین یا در محل ثبت کنید.', 'flavor' ),
					),
					array(
						'title' => __( 'لذت ببرید', 'flavor' ),
						'text'  => __( 'غذای شما آماده و تحویل داده می‌شود.', 'flavor' ),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$title = self::text( $s, 'title', __( 'چطور سفارش دهید؟', 'flavor' ) );
		$steps = self::rows( $s, 'steps' );

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';
		$inner .= '<ol class="flavor-process">';

		foreach ( $steps as $step ) {
			$inner .= '<li class="flavor-process__step">';
			$inner .= '<h3 class="flavor-process__title">' . esc_html( (string) ( $step['title'] ?? '' ) ) . '</h3>';
			$inner .= '<p class="flavor-process__text">' . esc_html( (string) ( $step['text'] ?? '' ) ) . '</p>';
			$inner .= '</li>';
		}

		$inner .= '</ol>';

		echo self::section( $title, $inner, 'flavor-section--process' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
