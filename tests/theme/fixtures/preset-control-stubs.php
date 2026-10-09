<?php
/**
 * Stand-ins for the Customizer classes and i18n helpers the preset picker
 * needs, so the control can be exercised without WordPress.
 *
 * @package Flavor
 */

namespace {
	if ( ! class_exists( '\WP_Customize_Control' ) ) {
		/**
		 * Minimal WP_Customize_Control.
		 */
		class WP_Customize_Control {
			/**
			 * Control id.
			 *
			 * @var string
			 */
			public $id = '';

			/**
			 * Label.
			 *
			 * @var string
			 */
			public $label = '';

			/**
			 * Description.
			 *
			 * @var string
			 */
			public $description = '';

			/**
			 * Manager.
			 *
			 * @var mixed
			 */
			public $manager = null;

			/**
			 * Constructor.
			 *
			 * @param mixed  $manager Manager.
			 * @param string $id      Control id.
			 * @param array  $args    Args.
			 */
			public function __construct( $manager, string $id, array $args = array() ) {
				$this->manager = $manager;
				$this->id      = $id;
				foreach ( $args as $key => $value ) {
					if ( property_exists( $this, $key ) ) {
						$this->{$key} = $value;
					}
				}
			}

			/**
			 * The setting's current value.
			 *
			 * @return mixed
			 */
			public function value() {
				return get_theme_mod( $this->id, '' );
			}

			/**
			 * Render; subclasses override.
			 */
			public function render_content(): void {}
		}
	}

	if ( ! function_exists( '_n' ) ) {
		/**
		 * Plural forms, without a locale catalogue.
		 *
		 * @param string $single Single form.
		 * @param string $plural Plural form.
		 * @param int    $number Count.
		 * @return string
		 */
		function _n( string $single, string $plural, int $number ): string {
			return 1 === $number ? $single : $plural;
		}
	}

	if ( ! function_exists( 'esc_textarea' ) ) {
		/**
		 * Escape for a textarea.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		function esc_textarea( string $text ): string {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'wp_json_encode' ) ) {
		/**
		 * JSON encode.
		 *
		 * @param mixed $data Data.
		 * @return string|false
		 */
		function wp_json_encode( $data ) {
			return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- no flags needed here.
		}
	}
}
