<?php
/**
 * Minimal Elementor stand-ins for offline widget tests.
 *
 * Elementor is a third-party plugin we cannot ship or boot here, but a widget
 * is mostly plain PHP: it declares controls and renders markup. These stubs
 * provide just enough of Elementor's surface to instantiate a widget, feed it
 * settings and capture its output — which is where the bugs actually live
 * (unescaped values, broken URLs, empty states that render as broken grids).
 *
 * Global names are required: the widget classes extend `\Elementor\Widget_Base`
 * and reference `\Elementor\Controls_Manager` and `\Elementor\Repeater` by
 * fully-qualified name, exactly as real Elementor expects.
 *
 * @package Flavor
 */

namespace {
	// The older widgets (hero, about, gallery, testimonials, branch info) hand
	// their output to a template part rather than building markup inline. The
	// mock has no template loader, so stand one in: the contract under test is
	// that the widget calls it without fataling and returns a section shell.
	if ( ! function_exists( 'get_template_part' ) ) {
		/**
		 * Template-part stand-in.
		 *
		 * The signature mirrors WordPress core, which allows null for $name —
		 * the theme's widgets pass null explicitly.
		 *
		 * @param string      $slug Slug.
		 * @param string|null $name Name.
		 * @param array       $args Args.
		 */
		function get_template_part( string $slug, ?string $name = null, array $args = array() ): void {
			unset( $name, $args );
			echo '<div class="flavor-template-part" data-slug="' . htmlspecialchars( $slug, ENT_QUOTES ) . '"></div>';
		}
	}

	// The mock WordPress environment does not define the plural translator.
	if ( ! function_exists( '_n' ) ) {
		/**
		 * Pick a singular or plural form.
		 *
		 * @param string $single Single form.
		 * @param string $plural Plural form.
		 * @param int    $number Count.
		 * @param string $domain Text domain.
		 * @return string
		 */
		function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
			unset( $domain );
			return 1 === $number ? $single : $plural;
		}
	}
}

namespace Elementor {

	/**
	 * Control type constants.
	 */
	class Controls_Manager {
		const TEXT     = 'text';
		const TEXTAREA = 'textarea';
		const NUMBER   = 'number';
		const SELECT   = 'select';
		const SWITCHER = 'switcher';
		const MEDIA    = 'media';
		const URL      = 'url';
		const REPEATER = 'repeater';
		const WYSIWYG  = 'wysiwyg';
		const GALLERY  = 'gallery';
	}

	/**
	 * Repeater control.
	 */
	class Repeater {

		/**
		 * Declared sub-controls.
		 *
		 * @var array<string, array<string, mixed>>
		 */
		private array $controls = array();

		public function add_control( string $id, array $args = array() ): void {
			$this->controls[ $id ] = $args;
		}

		/**
		 * @return array<string, array<string, mixed>>
		 */
		public function get_controls(): array {
			return $this->controls;
		}
	}

	/**
	 * Widget base.
	 */
	abstract class Widget_Base {

		/**
		 * Settings the widget should pretend were saved.
		 *
		 * @var array<string, mixed>
		 */
		protected array $test_settings = array();

		/**
		 * Captured control declarations, for assertions.
		 *
		 * @var array<string, array<string, mixed>>
		 */
		public array $test_controls = array();

		/**
		 * @var array<string, array<string, mixed>>
		 */
		public array $test_sections = array();

		public function set_test_settings( array $settings ): void {
			$this->test_settings = $settings;
		}

		/**
		 * Stands in for Elementor's own accessor.
		 *
		 * @return array<string, mixed>
		 */
		public function get_settings_for_display(): array {
			return $this->test_settings;
		}

		protected function start_controls_section( string $id, array $args = array() ): void {
			$this->test_sections[ $id ] = $args;
		}

		protected function end_controls_section(): void {
		}

		protected function add_control( string $id, array $args = array() ): void {
			$this->test_controls[ $id ] = $args;
		}

		abstract public function get_name();

		public function get_title() {
			return '';
		}

		public function get_icon() {
			return '';
		}

		/**
		 * @return string[]
		 */
		public function get_keywords() {
			return array();
		}

		/**
		 * @return string[]
		 */
		public function get_categories() {
			return array( 'general' );
		}

		abstract protected function render();

		/**
		 * Test accessor: protected in real Elementor.
		 */
		public function test_register_controls(): void {
			$this->register_controls();
		}

		/**
		 * Test accessor: capture the rendered markup.
		 */
		public function test_render(): string {
			ob_start();
			$this->render();
			return (string) ob_get_clean();
		}
	}

	/**
	 * Elements manager, used for category registration.
	 */
	class Elements_Manager {

		/**
		 * @var array<string, array<string, mixed>>
		 */
		private array $categories = array(
			'general' => array( 'title' => 'General' ),
		);

		/**
		 * @return array<string, array<string, mixed>>
		 */
		public function get_categories(): array {
			return $this->categories;
		}

		public function add_category( string $name, array $args = array() ): void {
			$this->categories[ $name ] = $args;
		}
	}
}
