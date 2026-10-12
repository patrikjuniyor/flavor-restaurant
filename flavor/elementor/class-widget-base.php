<?php
/**
 * Shared Elementor widget helpers.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Class Widget_Base
 */
abstract class Widget_Base extends \Elementor\Widget_Base {

	/**
	 * Slug of the branded widget category.
	 */
	public const CATEGORY = 'flavor';

	/**
	 * Widgets live in a "Flavor" category rather than Elementor's generic
	 * "General" bucket, so a merchant can find them without scrolling past
	 * every other plugin's widgets.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( self::CATEGORY );
	}

	/**
	 * Register the branded category once.
	 *
	 * @param \Elementor\Elements_Manager $elements Manager.
	 */
	public static function register_category( $elements ): void {
		$existing = $elements->get_categories();

		// Elementor throws if a category is added twice, and other code paths
		// (or a stale object cache) may already have registered it.
		if ( isset( $existing[ self::CATEGORY ] ) ) {
			return;
		}

		$elements->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'رستوران مستقیم — Flavor', 'flavor' ),
				'icon'  => 'eicon-info-circle',
			)
		);
	}

	/**
	 * Resolve a link setting into a safe URL.
	 *
	 * Elementor's URL control stores an array with `url`, `is_external` and
	 * `nofollow`. Treating it as a string produces "Array" in the href.
	 *
	 * @param mixed  $setting  Raw control value.
	 * @param string $fallback Used when nothing usable was entered.
	 * @return string
	 */
	protected static function url_from( $setting, string $fallback = '' ): string {
		$url = '';
		if ( is_array( $setting ) ) {
			$url = (string) ( $setting['url'] ?? '' );
		} elseif ( is_string( $setting ) ) {
			$url = $setting;
		}

		$url = trim( $url );
		if ( '' === $url ) {
			return $fallback;
		}

		return esc_url( $url );
	}

	/**
	 * Whether a link setting asked to open in a new tab.
	 *
	 * @param mixed $setting Raw control value.
	 * @return bool
	 */
	protected static function opens_new_tab( $setting ): bool {
		return is_array( $setting ) && ! empty( $setting['is_external'] );
	}

	/**
	 * Render an anchor's attributes, escaped and complete.
	 *
	 * @param mixed  $setting Raw control value.
	 * @param string $fallback Fallback URL.
	 * @param string $class    Optional class attribute value.
	 * @return string
	 */
	protected static function anchor_attrs( $setting, string $fallback = '', string $class = '' ): string {
		$href = self::url_from( $setting, $fallback );
		$out  = 'href="' . $href . '"';

		if ( '' !== $class ) {
			$out .= ' class="' . esc_attr( $class ) . '"';
		}
		if ( self::opens_new_tab( $setting ) ) {
			$out .= ' target="_blank" rel="noopener noreferrer"';
		}

		return $out;
	}

	/**
	 * Wrap rendered content in the theme's section shell.
	 *
	 * Reusing .flavor-section/.flavor-container means a widget inherits the
	 * theme's gutters, spacing tokens and responsive behaviour instead of
	 * shipping a second, slightly different layout.
	 *
	 * @param string $label    Accessible label for the region.
	 * @param string $inner    Rendered content.
	 * @param string $extra    Extra classes for the section.
	 * @return string
	 */
	protected static function section( string $label, string $inner, string $extra = '' ): string {
		$class = trim( 'flavor-section ' . $extra );

		return sprintf(
			'<section class="%1$s" aria-label="%2$s"><div class="flavor-container">%3$s</div></section>',
			esc_attr( $class ),
			esc_attr( $label ),
			$inner
		);
	}

	/**
	 * An image URL from a MEDIA control, or an empty string.
	 *
	 * @param mixed $setting Raw control value.
	 * @return string
	 */
	protected static function image_url( $setting ): string {
		if ( ! is_array( $setting ) || empty( $setting['url'] ) ) {
			return '';
		}
		return esc_url( (string) $setting['url'] );
	}

	/**
	 * Text from a control, with a fallback for an untouched widget.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @param string               $key      Control name.
	 * @param string               $fallback Fallback text.
	 * @return string
	 */
	protected static function text( array $settings, string $key, string $fallback = '' ): string {
		$value = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		$value = trim( $value );

		return '' === $value ? $fallback : $value;
	}

	/**
	 * Rows of a REPEATER control, as a clean list of arrays.
	 *
	 * Elementor hands back whatever was saved; anything that is not an
	 * array row is dropped rather than rendered.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @param string               $key      Repeater control name.
	 * @return array<int, array<string, mixed>>
	 */
	protected static function rows( array $settings, string $key ): array {
		$rows = $settings[ $key ] ?? array();

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values( array_filter( $rows, 'is_array' ) );
	}

	/**
	 * Premium-grade, token-aware styling shared by every Flavor widget.
	 *
	 * Elementor's Advanced tab styles the outer widget container. These
	 * controls deliberately reach the Flavor markup inside it, while leaving
	 * unset values alone so the active skin and dark-mode palette remain the
	 * defaults. All sizing controls are responsive and use Elementor's native
	 * CSS compiler; no inline style attributes or front-end JavaScript are
	 * needed.
	 */
	protected function register_flavor_style_controls(): void {
		$section_selector = '{{WRAPPER}} .flavor-section, {{WRAPPER}} .flavor-hero, {{WRAPPER}} .flavor-gallery';
		$heading_selector = '{{WRAPPER}} .flavor-section__title, {{WRAPPER}} .flavor-section-header__title, {{WRAPPER}} .flavor-about__title, {{WRAPPER}} .flavor-hero__title, {{WRAPPER}} .flavor-offer-banner__title, {{WRAPPER}} .flavor-res-banner__title';
		$grid_selector    = '{{WRAPPER}} .flavor-menu__grid, {{WRAPPER}} .flavor-features__grid, {{WRAPPER}} .flavor-chefs, {{WRAPPER}} .flavor-stats, {{WRAPPER}} .flavor-process, {{WRAPPER}} .flavor-order-cta__modes, {{WRAPPER}} .flavor-gallery__grid, {{WRAPPER}} .flavor-testimonials__grid';

		$this->start_controls_section(
			'flavor_visual_style',
			array(
				'label' => __( 'ظاهر و سبک Flavor', 'flavor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'flavor_style_brand_heading',
			array(
				'label' => __( 'رنگ‌های برند', 'flavor' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);

		$brand_colors = array(
			'flavor_style_primary_color' => array( __( 'رنگ اصلی', 'flavor' ), '--flavor-primary' ),
			'flavor_style_accent_color'  => array( __( 'رنگ تأکید', 'flavor' ), '--flavor-accent' ),
			'flavor_style_text_color'    => array( __( 'رنگ متن', 'flavor' ), '--flavor-ink' ),
			'flavor_style_muted_color'   => array( __( 'رنگ متن کم‌رنگ', 'flavor' ), '--flavor-muted' ),
			'flavor_style_surface_color' => array( __( 'رنگ سطح', 'flavor' ), '--flavor-surface' ),
			'flavor_style_line_color'    => array( __( 'رنگ خطوط', 'flavor' ), '--flavor-line' ),
		);

		foreach ( $brand_colors as $id => $settings ) {
			$this->add_control(
				$id,
				array(
					'label'     => $settings[0],
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}}' => $settings[1] . ': {{VALUE}};',
					),
				)
			);
		}

		$this->add_control(
			'flavor_style_section_heading',
			array(
				'label'     => __( 'بخش و چیدمان', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'flavor_style_section_background',
			array(
				'label'     => __( 'رنگ پس‌زمینهٔ بخش', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$section_selector => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'flavor_style_section_padding',
			array(
				'label'      => __( 'فاصلهٔ داخلی بخش', 'flavor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					$section_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'flavor_style_content_width',
			array(
				'label'      => __( 'حداکثر عرض محتوا', 'flavor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min'  => 320,
						'max'  => 1600,
						'step' => 10,
					),
					'%'  => array(
						'min'  => 25,
						'max'  => 100,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .flavor-container' => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'flavor_style_grid_columns',
			array(
				'label'     => __( 'تعداد ستون‌ها', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					''  => __( 'خودکار (پوسته)', 'flavor' ),
					'1' => '۱',
					'2' => '۲',
					'3' => '۳',
					'4' => '۴',
				),
				'default'   => '',
				'selectors' => array(
					$grid_selector => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				),
			)
		);

		$this->add_responsive_control(
			'flavor_style_alignment',
			array(
				'label'        => __( 'هم‌ترازی متن', 'flavor' ),
				'type'         => \Elementor\Controls_Manager::CHOOSE,
				'options'      => array(
					'start'  => array(
						'title' => __( 'آغاز', 'flavor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'وسط', 'flavor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'    => array(
						'title' => __( 'پایان', 'flavor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .flavor-section, {{WRAPPER}} .flavor-hero, {{WRAPPER}} .flavor-gallery' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'flavor_style_heading_color',
			array(
				'label'     => __( 'رنگ عنوان', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$heading_selector => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'flavor_style_heading_typography',
				'label'    => __( 'تایپوگرافی عنوان', 'flavor' ),
				'selector' => $heading_selector,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'flavor_style_body_typography',
				'label'    => __( 'تایپوگرافی متن', 'flavor' ),
				'selector' => '{{WRAPPER}} .flavor-section p, {{WRAPPER}} .flavor-hero__text, {{WRAPPER}} .flavor-about__text, {{WRAPPER}} .flavor-offer-banner__text, {{WRAPPER}} .flavor-res-banner__text, {{WRAPPER}} .flavor-feature__text, {{WRAPPER}} .flavor-process__text, {{WRAPPER}} .flavor-faq__answer, {{WRAPPER}} .flavor-order-cta__text',
			)
		);

		$this->add_control(
			'flavor_style_card_heading',
			array(
				'label'     => __( 'کارت‌ها و دکمه‌ها', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'flavor_style_card_background',
			array(
				'label'     => __( 'پس‌زمینهٔ کارت', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .flavor-card, {{WRAPPER}} .flavor-feature, {{WRAPPER}} .flavor-chef, {{WRAPPER}} .flavor-faq__item, {{WRAPPER}} .flavor-order-cta__mode, {{WRAPPER}} .flavor-hours-list, {{WRAPPER}} .flavor-price-list__item' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'flavor_style_card_border',
			array(
				'label'     => __( 'رنگ حاشیهٔ کارت', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .flavor-card, {{WRAPPER}} .flavor-feature, {{WRAPPER}} .flavor-chef, {{WRAPPER}} .flavor-faq__item, {{WRAPPER}} .flavor-order-cta__mode, {{WRAPPER}} .flavor-hours-list, {{WRAPPER}} .flavor-price-list__item' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'flavor_style_card_radius',
			array(
				'label'      => __( 'گردی گوشهٔ کارت', 'flavor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 48,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .flavor-card, {{WRAPPER}} .flavor-feature, {{WRAPPER}} .flavor-chef, {{WRAPPER}} .flavor-faq__item, {{WRAPPER}} .flavor-order-cta__mode, {{WRAPPER}} .flavor-hours-list, {{WRAPPER}} .flavor-price-list__item' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'flavor_style_button_background',
			array(
				'label'     => __( 'پس‌زمینهٔ دکمهٔ اصلی', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .flavor-btn--primary' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'flavor_style_button_text',
			array(
				'label'     => __( 'رنگ متن دکمهٔ اصلی', 'flavor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .flavor-btn--primary' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}
}
