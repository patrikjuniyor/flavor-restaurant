<?php
/**
 * Branch info widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Branch_Info_Widget
 */
class Branch_Info_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_branch_info';
	}

	public function get_title() {
		return __( 'اطلاعات شعبه', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-map-pin';
	}

	public function get_keywords() {
		return array( 'branch', 'شعبه', 'آدرس', 'location', 'تماس' );
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
				'default'     => '',
				'description' => __( 'اگر خالی بماند، نام شعبه به‌عنوان عنوان نمایش داده می‌شود.', 'flavor' ),
			)
		);

		$this->add_control(
			'show_address',
			array(
				'label'        => __( 'نمایش نشانی', 'flavor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_phone',
			array(
				'label'        => __( 'نمایش تلفن', 'flavor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_city',
			array(
				'label'        => __( 'نمایش شهر', 'flavor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_link',
			array(
				'label'        => __( 'نمایش لینک صفحهٔ شعبه', 'flavor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$title        = self::text( $s, 'title' );
		$show_address = 'yes' === ( $s['show_address'] ?? 'yes' );
		$show_phone   = 'yes' === ( $s['show_phone'] ?? 'yes' );
		$show_city    = 'yes' === ( $s['show_city'] ?? 'yes' );
		$show_link    = 'yes' === ( $s['show_link'] ?? '' );

		$branch = null;
		$has_core = class_exists( '\FlavorCore\PostTypes\BranchPostType' );

		if ( $has_core ) {
			$id     = \FlavorCore\PostTypes\BranchPostType::default_id();
			$branch = $id ? \FlavorCore\PostTypes\BranchPostType::to_array( $id ) : null;
		}

		// Without the plugin, or with no branch configured yet, say so rather
		// than rendering an empty section and leaving the merchant guessing.
		if ( ! $branch ) {
			$message = $has_core
				? __( 'هیچ شعبه‌ای تعریف نشده است. ابتدا یک شعبه در افزونهٔ Flavor Core بسازید.', 'flavor' )
				: __( 'افزونهٔ Flavor Core برای نمایش اطلاعات شعبه لازم است.', 'flavor' );

			echo self::section(
				(string) __( 'اطلاعات شعبه', 'flavor' ),
				'<p class="flavor-widget-note">' . esc_html( $message ) . '</p>',
				'flavor-section--branch'
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		if ( '' === $title ) {
			$title = (string) ( $branch['name'] ?? '' );
		}

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';
		$inner .= '<dl class="flavor-branch-info">';

		if ( $show_address && '' !== (string) ( $branch['address'] ?? '' ) ) {
			$inner .= '<div class="flavor-branch-info__row">';
			$inner .= '<dt>' . esc_html__( 'نشانی', 'flavor' ) . '</dt>';
			$inner .= '<dd>' . esc_html( (string) $branch['address'] ) . '</dd>';
			$inner .= '</div>';
		}

		if ( $show_city && '' !== (string) ( $branch['city'] ?? '' ) ) {
			$inner .= '<div class="flavor-branch-info__row">';
			$inner .= '<dt>' . esc_html__( 'شهر', 'flavor' ) . '</dt>';
			$inner .= '<dd>' . esc_html( (string) $branch['city'] ) . '</dd>';
			$inner .= '</div>';
		}

		if ( $show_phone && '' !== (string) ( $branch['phone'] ?? '' ) ) {
			$phone = (string) $branch['phone'];
			$inner .= '<div class="flavor-branch-info__row">';
			$inner .= '<dt>' . esc_html__( 'تلفن', 'flavor' ) . '</dt>';
			// A phone number is written left-to-right even in RTL text, so the
			// digits keep their order while the label stays right-aligned.
			$inner .= '<dd><a dir="ltr" href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a></dd>';
			$inner .= '</div>';
		}

		$inner .= '</dl>';

		if ( $show_link && ! empty( $branch['permalink'] ) ) {
			$inner .= '<a class="flavor-btn flavor-btn--outline" href="' . esc_url( (string) $branch['permalink'] ) . '">'
				. esc_html__( 'مشاهدهٔ شعبه', 'flavor' )
				. '</a>';
		}

		echo self::section( $title, $inner, 'flavor-section--branch' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
