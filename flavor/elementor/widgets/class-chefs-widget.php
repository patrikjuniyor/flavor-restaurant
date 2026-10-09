<?php
/**
 * Chefs / team members widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Chefs_Widget
 */
class Chefs_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_chefs';
	}

	public function get_title() {
		return __( 'سرآشپزها و تیم', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_keywords() {
		return array( 'chef', 'سرآشپز', 'تیم', 'team', 'staff' );
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
				'default' => __( 'تیم ما', 'flavor' ),
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'name',
			array(
				'label'   => __( 'نام', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$repeater->add_control(
			'role',
			array(
				'label'   => __( 'نقش', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'سرآشپز', 'flavor' ),
			)
		);

		$repeater->add_control(
			'bio',
			array(
				'label'   => __( 'درباره', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => '',
			)
		);

		// Alt text is a control, not an afterthought: an image-only card with
		// no alternative text is invisible to a screen reader.
		$repeater->add_control(
			'image',
			array(
				'label' => __( 'تصویر', 'flavor' ),
				'type'  => \Elementor\Controls_Manager::MEDIA,
			)
		);

		$repeater->add_control(
			'image_alt',
			array(
				'label'       => __( 'متن جایگزین تصویر', 'flavor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'برای دسترس‌پذیری لازم است. اگر خالی بماند، از نام استفاده می‌شود.', 'flavor' ),
			)
		);

		$this->add_control(
			'members',
			array(
				'label'       => __( 'اعضا', 'flavor' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'name' => __( 'سرآشپز شما', 'flavor' ),
						'role' => __( 'سرآشپز ارشد', 'flavor' ),
					),
				),
				'title_field' => '{{{ name }}}',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$members = (array) ( $s['members'] ?? array() );

		$title = self::text( $s, 'title', __( 'تیم ما', 'flavor' ) );

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';

		if ( empty( $members ) ) {
			$inner .= '<p class="flavor-widget-note">' . esc_html__( 'هنوز عضوی اضافه نشده است.', 'flavor' ) . '</p>';
			echo self::section( $title, $inner, 'flavor-section--chefs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$inner .= '<div class="flavor-chefs">';

		foreach ( $members as $member ) {
			$member = (array) $member;
			$name   = self::text( $member, 'name' );
			$role   = self::text( $member, 'role' );
			$bio    = self::text( $member, 'bio' );
			$image  = self::image_url( $member['image'] ?? array() );

			if ( '' === $name && '' === $role && '' === $bio && '' === $image ) {
				continue;
			}

			// Fall back to the name so a screen reader is never left with the
			// file name of an uploaded portrait.
			$alt = self::text( $member, 'image_alt' );
			if ( '' === $alt ) {
				$alt = '' !== $name ? $name : $role;
			}

			$inner .= '<article class="flavor-chef">';

			if ( $image ) {
				$inner .= '<div class="flavor-chef__media">'
					. '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" />'
					. '</div>';
			}

			$inner .= '<div class="flavor-chef__body">';

			if ( '' !== $name ) {
				$inner .= '<h3 class="flavor-chef__name">' . esc_html( $name ) . '</h3>';
			}
			if ( '' !== $role ) {
				$inner .= '<p class="flavor-chef__role">' . esc_html( $role ) . '</p>';
			}
			if ( '' !== $bio ) {
				$inner .= '<p class="flavor-chef__bio">' . esc_html( $bio ) . '</p>';
			}

			$inner .= '</div></article>';
		}

		$inner .= '</div>';

		echo self::section( $title, $inner, 'flavor-section--chefs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
