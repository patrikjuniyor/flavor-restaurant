<?php
/**
 * Special offer / promo banner widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Offers_Widget
 */
class Offers_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_offers';
	}

	public function get_title() {
		return __( 'پیشنهاد ویژه', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-percent';
	}

	public function get_keywords() {
		return array( 'offer', 'تخفیف', 'پیشنهاد', 'promo', 'coupon' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content',
			array( 'label' => __( 'محتوا', 'flavor' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( '۱۵٪ تخفیف برای اولین سفارش آنلاین', 'flavor' ),
			)
		);

		$this->add_control(
			'text',
			array(
				'label'   => __( 'توضیح', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'کد تخفیف را هنگام پرداخت وارد کنید.', 'flavor' ),
			)
		);

		$this->add_control(
			'code',
			array(
				'label'       => __( 'کد تخفیف', 'flavor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'FIRST15',
				'description' => __( 'اعتبار این کد توسط ووکامرس بررسی می‌شود؛ این ویجت فقط آن را نمایش می‌دهد.', 'flavor' ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'   => __( 'متن دکمه', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'سفارش آنلاین', 'flavor' ),
			)
		);

		$this->add_control(
			'link',
			array(
				'label' => __( 'نشانی دکمه', 'flavor' ),
				'type'  => \Elementor\Controls_Manager::URL,
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$title = self::text( $s, 'title' );
		$text  = self::text( $s, 'text' );
		$code  = self::text( $s, 'code' );
		$label = self::text( $s, 'button_text', __( 'سفارش آنلاین', 'flavor' ) );

		$page     = get_page_by_path( 'menu' );
		$fallback = $page ? get_permalink( $page ) : home_url( '/menu/' );

		$inner = '<div class="flavor-offer-banner">';
		$inner .= '<div class="flavor-offer-banner__content">';

		if ( '' !== $code ) {
			$inner .= '<span class="flavor-offer-banner__badge">' . esc_html( $code ) . '</span>';
		}

		if ( '' !== $title ) {
			$inner .= '<h2 class="flavor-offer-banner__title">' . esc_html( $title ) . '</h2>';
		}

		if ( '' !== $text ) {
			$inner .= '<p class="flavor-offer-banner__text">' . esc_html( $text ) . '</p>';
		}

		$inner .= '<a ' . self::anchor_attrs( $s['link'] ?? array(), $fallback, 'flavor-btn flavor-btn--primary' ) . '>'
			. esc_html( $label )
			. '</a>';

		$inner .= '</div></div>';

		echo self::section( $title, $inner, 'flavor-section--offers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
