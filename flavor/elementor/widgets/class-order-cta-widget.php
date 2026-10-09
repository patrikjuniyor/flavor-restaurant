<?php
/**
 * Order call-to-action widget (dine-in / takeaway / delivery).
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Order_CTA_Widget
 */
class Order_CTA_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_order_cta';
	}

	public function get_title() {
		return __( 'دعوت به سفارش', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-cart-medium';
	}

	public function get_keywords() {
		return array( 'order', 'سفارش', 'ارسال', 'بیرون‌بر', 'delivery', 'cta' );
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
				'default' => __( 'چطور سفارش می‌دهید؟', 'flavor' ),
			)
		);

		$this->add_control(
			'text',
			array(
				'label'   => __( 'توضیح', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'روش دریافت را انتخاب کنید؛ بقیهٔ مسیر را ما انجام می‌دهیم.', 'flavor' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'modes',
			array( 'label' => __( 'روش‌های سفارش', 'flavor' ) )
		);

		// Mirrors the three order modes the plugin actually implements, so the
		// widget cannot advertise a flow the checkout does not support.
		foreach ( self::modes() as $key => $label ) {
			$this->add_control(
				'show_' . $key,
				array(
					'label'        => $label,
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * The three order modes, keyed to match Flavor Core.
	 *
	 * @return array<string, string>
	 */
	public static function modes(): array {
		return array(
			'dine_in'  => __( 'سفارش سالن (QR)', 'flavor' ),
			'takeaway' => __( 'بیرون‌بر', 'flavor' ),
			'delivery' => __( 'ارسال با پیک', 'flavor' ),
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$title = self::text( $s, 'title', __( 'چطور سفارش می‌دهید؟', 'flavor' ) );
		$text  = self::text( $s, 'text' );

		$page     = get_page_by_path( 'menu' );
		$fallback = $page ? get_permalink( $page ) : home_url( '/menu/' );

		$active = array();
		foreach ( array_keys( self::modes() ) as $key ) {
			if ( 'yes' === ( $s[ 'show_' . $key ] ?? 'yes' ) ) {
				$active[] = $key;
			}
		}

		if ( empty( $active ) ) {
			$active = array_keys( self::modes() );
		}

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';

		if ( '' !== $text ) {
			$inner .= '<p class="flavor-order-cta__text">' . esc_html( $text ) . '</p>';
		}

		$inner .= '<div class="flavor-order-cta__modes">';

		foreach ( $active as $key ) {
			$label = self::modes()[ $key ];
			$href  = esc_url( add_query_arg( 'flavor_mode', $key, $fallback ) );

			$inner .= '<a class="flavor-order-cta__mode" href="' . $href . '">';
			$inner .= '<span class="flavor-order-cta__mode-label">' . esc_html( $label ) . '</span>';
			$inner .= '</a>';
		}

		$inner .= '</div>';

		echo self::section( $title, $inner, 'flavor-section--order-cta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
