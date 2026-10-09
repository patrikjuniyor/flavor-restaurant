<?php
/**
 * Menu grid widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Menu_Widget
 */
class Menu_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_menu';
	}

	public function get_title() {
		return __( 'منوی رستوران', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-menu-card';
	}

	public function get_keywords() {
		return array( 'menu', 'منو', 'غذا', 'food', 'restaurant' );
	}

	protected function register_controls() {
		$text  = \Elementor\Controls_Manager::TEXT;
		$number = \Elementor\Controls_Manager::NUMBER;

		$this->start_controls_section(
			'content',
			array( 'label' => __( 'محتوا', 'flavor' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان بخش', 'flavor' ),
				'type'    => $text,
				'default' => __( 'منوی ما', 'flavor' ),
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'تعداد آیتم', 'flavor' ),
				'type'    => $number,
				'default' => 6,
				'min'     => 1,
				'max'     => 48,
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'مرتب‌سازی', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'menu_order',
				'options' => array(
					'menu_order' => __( 'ترتیب دستی', 'flavor' ),
					'popularity' => __( 'محبوب‌ترین', 'flavor' ),
					'date'       => __( 'جدیدترین', 'flavor' ),
					'price'      => __( 'ارزان‌ترین', 'flavor' ),
					'price-desc' => __( 'گران‌ترین', 'flavor' ),
					'title'      => __( 'الفبایی', 'flavor' ),
				),
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'        => __( 'نمایش قیمت', 'flavor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$title      = self::text( $s, 'title', __( 'منوی ما', 'flavor' ) );
		$count      = max( 1, min( 48, (int) ( $s['count'] ?? 6 ) ) );
		$orderby    = (string) ( $s['orderby'] ?? 'menu_order' );
		$show_price = 'yes' === ( $s['show_price'] ?? 'yes' );

		$products = array();
		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => $count,
					'orderby' => $orderby,
					'order'   => 'price' === $orderby ? 'ASC' : 'DESC',
				)
			);
		}

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';

		if ( empty( $products ) ) {
			// Without WooCommerce the widget is still honest about being empty
			// rather than rendering a broken grid.
			$inner .= '<p class="flavor-widget-note">' . esc_html__( 'محصولی برای نمایش پیدا نشد. ووکامرس را نصب و دستهٔ محصولات را پر کنید.', 'flavor' ) . '</p>';
			echo self::section( $title, $inner, 'flavor-section--menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			return;
		}

		$inner .= '<div class="flavor-menu__grid">';

		foreach ( $products as $product ) {
			$image_id  = $product->get_image_id();
			$image     = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';
			$permalink = $product->get_permalink();
			$name      = $product->get_name();

			$inner .= '<article class="flavor-card flavor-food-card">';

			if ( $image ) {
				$inner .= '<a class="flavor-food-card__media" href="' . esc_url( $permalink ) . '">'
					. '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" width="600" height="400" loading="lazy" />'
					. '</a>';
			}

			$inner .= '<div class="flavor-food-card__body">';
			$inner .= '<h3 class="flavor-food-card__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $name ) . '</a></h3>';

			$desc = wp_strip_all_tags( (string) $product->get_short_description() );
			if ( '' !== $desc ) {
				$inner .= '<p class="flavor-food-card__desc">' . esc_html( $desc ) . '</p>';
			}

			$inner .= '<div class="flavor-food-card__footer">';

			if ( $show_price ) {
				// Price always comes from WooCommerce server-side; the widget
				// never computes or mutates an amount.
				$inner .= '<strong class="flavor-food-card__price">' . wp_kses_post( $product->get_price_html() ) . '</strong>';
			}

			$inner .= '<a class="flavor-btn flavor-btn--outline flavor-btn--sm" href="' . esc_url( $permalink ) . '">'
				. esc_html__( 'جزئیات', 'flavor' )
				. '</a>';

			$inner .= '</div></div></article>';
		}

		$inner .= '</div>';

		echo self::section( $title, $inner, 'flavor-section--menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
