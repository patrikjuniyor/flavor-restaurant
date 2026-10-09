<?php
/**
 * Cart summary widget.
 *
 * Displays what WooCommerce already knows. It never computes a total,
 * applies a coupon or mutates the cart — the checkout stays server-side.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cart_Widget
 */
class Cart_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_cart';
	}

	public function get_title() {
		return __( 'سبد خرید', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-cart';
	}

	public function get_keywords() {
		return array( 'cart', 'سبد', 'خرید', 'basket', 'checkout' );
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
				'default' => __( 'سبد شما', 'flavor' ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'   => __( 'متن دکمه', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'تکمیل سفارش', 'flavor' ),
			)
		);

		$this->add_control(
			'empty_text',
			array(
				'label'   => __( 'متنِ سبد خالی', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'سبد شما خالی است.', 'flavor' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$title      = self::text( $s, 'title', __( 'سبد شما', 'flavor' ) );
		$label      = self::text( $s, 'button_text', __( 'تکمیل سفارش', 'flavor' ) );
		$empty_text = self::text( $s, 'empty_text', __( 'سبد شما خالی است.', 'flavor' ) );

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';

		$has_woo = function_exists( 'WC' ) && WC() && WC()->cart;

		if ( ! $has_woo ) {
			$inner .= '<p class="flavor-widget-note">' . esc_html__( 'برای نمایش سبد، ووکامرس باید فعال باشد.', 'flavor' ) . '</p>';
			echo self::section( $title, $inner, 'flavor-section--cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$count = WC()->cart->get_cart_contents_count();

		if ( $count < 1 ) {
			$inner .= '<p class="flavor-cart-summary__empty">' . esc_html( $empty_text ) . '</p>';
			$inner .= '<a class="flavor-btn flavor-btn--outline" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">'
				. esc_html__( 'مشاهدهٔ منو', 'flavor' )
				. '</a>';

			echo self::section( $title, $inner, 'flavor-section--cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		// Totals come straight from WooCommerce's own calculation.
		$inner .= '<p class="flavor-cart-summary__count">'
			. esc_html( sprintf( /* translators: %d is the number of items in the cart. */
				_n( '%d آیتم در سبد', '%d آیتم در سبد', $count, 'flavor' ),
				$count
			) )
			. '</p>';

		$inner .= '<p class="flavor-cart-summary__total">'
			. wp_kses_post( WC()->cart->get_cart_total() )
			. '</p>';

		$inner .= '<a class="flavor-btn flavor-btn--primary flavor-btn--lg" href="' . esc_url( wc_get_checkout_url() ) . '">'
			. esc_html( $label )
			. '</a>';

		echo self::section( $title, $inner, 'flavor-section--cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
