<?php
/**
 * Table reservation call-to-action widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Reservation_Widget
 */
class Reservation_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_reservation';
	}

	public function get_title() {
		return __( 'دعوت به رزرو میز', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-calendar';
	}

	public function get_keywords() {
		return array( 'reservation', 'رزرو', 'میز', 'booking', 'table' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content',
			array( 'label' => __( 'محتوا', 'flavor' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'عنوان', 'flavor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'میز خود را رزرو کنید', 'flavor' ),
				'placeholder' => __( 'میز خود را رزرو کنید', 'flavor' ),
			)
		);

		$this->add_control(
			'text',
			array(
				'label'   => __( 'توضیح', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'رزرو آنلاین در چند ثانیه، بدون تماس تلفنی.', 'flavor' ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'   => __( 'متن دکمه', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'رزرو میز', 'flavor' ),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'       => __( 'نشانی دکمه', 'flavor' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => home_url( '/reservation/' ),
				'description' => __( 'اگر خالی بماند، به صفحهٔ پیش‌فرض رزرو می‌رود.', 'flavor' ),
			)
		);

		$this->add_control(
			'image',
			array(
				'label' => __( 'تصویر پس‌زمینه', 'flavor' ),
				'type'  => \Elementor\Controls_Manager::MEDIA,
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$title = self::text( $s, 'title', __( 'میز خود را رزرو کنید', 'flavor' ) );
		$text  = self::text( $s, 'text' );
		$label = self::text( $s, 'button_text', __( 'رزرو میز', 'flavor' ) );

		// Fall back to the theme's reservation page when no URL was entered.
		$page = get_page_by_path( 'reservation' );
		$fallback = $page ? get_permalink( $page ) : home_url( '/reservation/' );

		$image = self::image_url( $s['image'] ?? array() );

		$inner = '<div class="flavor-res-banner"' . ( $image ? ' style="background-image:url(' . esc_url( $image ) . ')"' : '' ) . '>';
		$inner .= '<div class="flavor-res-banner__content">';
		$inner .= '<h2 class="flavor-res-banner__title">' . esc_html( $title ) . '</h2>';

		if ( '' !== $text ) {
			$inner .= '<p class="flavor-res-banner__text">' . esc_html( $text ) . '</p>';
		}

		$inner .= '<a ' . self::anchor_attrs( $s['link'] ?? array(), $fallback, 'flavor-btn flavor-btn--primary flavor-btn--lg' ) . '>'
			. esc_html( $label )
			. '</a>';

		$inner .= '</div></div>';

		echo self::section( $title, $inner, 'flavor-section--reservation' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
