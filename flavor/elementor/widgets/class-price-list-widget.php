<?php
/**
 * Price list widget: a printed-menu style list of items with prices.
 *
 * Use it for a fixed list that is not a WooCommerce product, such as a
 * set-menu or a drinks card. Prices are text the merchant types, shown as
 * written; the widget never does arithmetic on them.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Price_List_Widget
 */
class Price_List_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_price_list';
	}

	public function get_title() {
		return __( 'فهرست قیمت (چاپی)', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-price-list';
	}

	public function get_keywords() {
		return array( 'price', 'list', 'قیمت', 'فهرست', 'منو', 'menu' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'flavor' ) ) );

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'نوشیدنی‌ها', 'flavor' ),
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'name', array( 'label' => __( 'نام', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );
		$repeater->add_control( 'price', array( 'label' => __( 'قیمت (متن آزاد)', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );
		$repeater->add_control( 'note', array( 'label' => __( 'توضیح کوتاه', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );

		$this->add_control(
			'items',
			array(
				'label'   => __( 'آیتم‌ها', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::REPEATER,
				'fields'  => $repeater->get_controls(),
				'default' => array(
					array(
						'name'  => __( 'چای ماسالا', 'flavor' ),
						'price' => '۱۵۰٬۰۰۰',
						'note'  => __( 'گرم، با دارچین', 'flavor' ),
					),
					array(
						'name'  => __( 'دوغ محلی', 'flavor' ),
						'price' => '۸۰٬۰۰۰',
						'note'  => '',
					),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$title = self::text( $s, 'title', __( 'فهرست قیمت', 'flavor' ) );
		$items = self::rows( $s, 'items' );

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';
		$inner .= '<ul class="flavor-price-list">';

		foreach ( $items as $item ) {
			$name = trim( (string) ( $item['name'] ?? '' ) );

			if ( '' === $name ) {
				continue;
			}

			$note  = trim( (string) ( $item['note'] ?? '' ) );
			$price = trim( (string) ( $item['price'] ?? '' ) );

			$inner .= '<li class="flavor-price-list__item">';
			$inner .= '<span class="flavor-price-list__name">' . esc_html( $name );

			if ( '' !== $note ) {
				$inner .= '<small class="flavor-price-list__note">' . esc_html( $note ) . '</small>';
			}

			$inner .= '</span>';
			$inner .= '<span class="flavor-price-list__price">' . esc_html( $price ) . '</span>';
			$inner .= '</li>';
		}

		$inner .= '</ul>';

		echo self::section( $title, $inner, 'flavor-section--price-list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
