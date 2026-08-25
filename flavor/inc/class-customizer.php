<?php
/**
 * Theme Customizer — branding and skin.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Customizer
 */
class Customizer {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'customize_register', array( self::class, 'register' ) );
		add_action( 'wp_head', array( self::class, 'head_css' ), 20 );
	}

	/**
	 * Register sections.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	public static function register( $wp_customize ): void {
		$wp_customize->add_section(
			'flavor_brand',
			array(
				'title'    => __( 'رستوران مستقیم — ظاهر', 'flavor' ),
				'priority' => 30,
			)
		);

		$wp_customize->add_setting( 'flavor_skin', array( 'default' => 'modern-cafe', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control(
			'flavor_skin',
			array(
				'label'   => __( 'پوست دمو', 'flavor' ),
				'section' => 'flavor_brand',
				'type'    => 'select',
				'choices' => Design::skins(),
			)
		);

		$wp_customize->add_setting( 'flavor_header_layout', array( 'default' => 'default', 'sanitize_callback' => 'sanitize_key' ) );
		$wp_customize->add_control(
			'flavor_header_layout',
			array(
				'label'   => __( 'چینش هدر', 'flavor' ),
				'section' => 'flavor_brand',
				'type'    => 'select',
				'choices' => array(
					'default'  => __( 'لوگو راست / منو چپ', 'flavor' ),
					'centered' => __( 'وسط‌چین', 'flavor' ),
					'minimal'  => __( 'مینیمال', 'flavor' ),
				),
			)
		);

		foreach ( array(
			'accent'  => __( 'رنگ تاکید', 'flavor' ),
			'bg'      => __( 'پس‌زمینه', 'flavor' ),
			'surface' => __( 'سطح کارت', 'flavor' ),
			'ink'     => __( 'متن', 'flavor' ),
		) as $key => $label ) {
			$wp_customize->add_setting( 'flavor_' . $key, array( 'default' => '', 'sanitize_callback' => 'sanitize_hex_color' ) );
			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					'flavor_' . $key,
					array(
						'label'   => $label,
						'section' => 'flavor_brand',
					)
				)
			);
		}

		$wp_customize->add_setting( 'flavor_hero_title', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_title', array( 'label' => __( 'عنوان هیرو', 'flavor' ), 'section' => 'flavor_brand', 'type' => 'text' ) );

		$wp_customize->add_setting( 'flavor_hero_text', array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control( 'flavor_hero_text', array( 'label' => __( 'متن هیرو', 'flavor' ), 'section' => 'flavor_brand', 'type' => 'textarea' ) );

		$wp_customize->add_setting( 'flavor_hero_cta', array( 'default' => __( 'مشاهده منو', 'flavor' ), 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'flavor_hero_cta', array( 'label' => __( 'دکمه هیرو', 'flavor' ), 'section' => 'flavor_brand', 'type' => 'text' ) );

		$wp_customize->add_setting( 'flavor_about', array( 'default' => '', 'sanitize_callback' => 'wp_kses_post' ) );
		$wp_customize->add_control( 'flavor_about', array( 'label' => __( 'متن درباره ما', 'flavor' ), 'section' => 'flavor_brand', 'type' => 'textarea' ) );
	}

	/**
	 * Print tokens + layout class hook.
	 */
	public static function head_css(): void {
		echo '<style id="flavor-tokens">' . Design::css_variables() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
