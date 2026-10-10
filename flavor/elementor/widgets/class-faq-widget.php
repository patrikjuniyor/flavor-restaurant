<?php
/**
 * FAQ widget: questions and answers as native disclosure elements.
 *
 * <details> needs no JavaScript, works with keyboards and screen readers,
 * and keeps the answers in the HTML for search engines.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Faq_Widget
 */
class Faq_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_faq';
	}

	public function get_title() {
		return __( 'پرسش‌های متداول', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-help-o';
	}

	public function get_keywords() {
		return array( 'faq', 'پرسش', 'سوال', 'متداول', 'question', 'answer' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'flavor' ) ) );

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان بخش', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'پرسش‌های متداول', 'flavor' ),
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'question', array( 'label' => __( 'پرسش', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );
		$repeater->add_control( 'answer', array( 'label' => __( 'پاسخ', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXTAREA ) );

		$this->add_control(
			'items',
			array(
				'label'   => __( 'پرسش و پاسخ', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::REPEATER,
				'fields'  => $repeater->get_controls(),
				'default' => array(
					array(
						'question' => __( 'آیا رزرو میز لازم است؟', 'flavor' ),
						'answer'   => __( 'رزرو برای گروه‌های بیشتر از چهار نفر توصیه می‌شود.', 'flavor' ),
					),
					array(
						'question' => __( 'آیا غذای گیاهی دارید؟', 'flavor' ),
						'answer'   => __( 'بله، با علامت مخصوص در منو مشخص شده است.', 'flavor' ),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$title = self::text( $s, 'title', __( 'پرسش‌های متداول', 'flavor' ) );
		$items = self::rows( $s, 'items' );

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';
		$inner .= '<div class="flavor-faq">';

		foreach ( $items as $item ) {
			$question = trim( (string) ( $item['question'] ?? '' ) );

			if ( '' === $question ) {
				continue;
			}

			$inner .= '<details class="flavor-faq__item">';
			$inner .= '<summary class="flavor-faq__question">' . esc_html( $question ) . '</summary>';
			$inner .= '<div class="flavor-faq__answer">' . nl2br( esc_html( (string) ( $item['answer'] ?? '' ) ), false ) . '</div>';
			$inner .= '</details>';
		}

		$inner .= '</div>';

		echo self::section( $title, $inner, 'flavor-section--faq' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
