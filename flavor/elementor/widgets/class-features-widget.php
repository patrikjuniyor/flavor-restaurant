<?php
/**
 * Features grid widget: "why us" cards with a title and a short text.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Features_Widget
 */
class Features_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_features';
	}

	public function get_title() {
		return __( 'چرا ما؟ (ویژگی‌ها)', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	public function get_keywords() {
		return array( 'features', 'ویژگی', 'چرا', 'مزایا', 'restaurant' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'flavor' ) ) );

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان بخش', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'چرا رستوران ما؟', 'flavor' ),
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'title', array( 'label' => __( 'عنوان', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );
		$repeater->add_control( 'text', array( 'label' => __( 'توضیح', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXTAREA ) );

		$this->add_control(
			'items',
			array(
				'label'   => __( 'ویژگی‌ها', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::REPEATER,
				'fields'  => $repeater->get_controls(),
				'default' => array(
					array(
						'title' => __( 'مواد تازه و روزانه', 'flavor' ),
						'text'  => __( 'سبزیجات و گوشت هر روز صبح تامین می‌شود.', 'flavor' ),
					),
					array(
						'title' => __( 'آشپزخانهٔ باز', 'flavor' ),
						'text'  => __( 'پخت غذا را از نزدیک ببینید.', 'flavor' ),
					),
					array(
						'title' => __( 'تحویل سریع', 'flavor' ),
						'text'  => __( 'سفارش شما در کوتاه‌ترین زمان آماده می‌شود.', 'flavor' ),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$title = self::text( $s, 'title', __( 'چرا رستوران ما؟', 'flavor' ) );
		$items = self::rows( $s, 'items' );

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';
		$inner .= '<div class="flavor-features__grid">';

		foreach ( $items as $item ) {
			$inner .= '<article class="flavor-card flavor-feature">';
			$inner .= '<h3 class="flavor-feature__title">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</h3>';
			$inner .= '<p class="flavor-feature__text">' . esc_html( (string) ( $item['text'] ?? '' ) ) . '</p>';
			$inner .= '</article>';
		}

		$inner .= '</div>';

		echo self::section( $title, $inner, 'flavor-section--features' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
