<?php
/**
 * Global-namespace WordPress stubs for the chrome builder tests.
 *
 * WordPress function and class names are global, so the stand-ins cannot live
 * inside the test's namespace. They are guarded by function_exists/class_exists
 * to stay compatible with the Flavor Core mock environment.
 *
 * @package Flavor
 */
/* ------------------------------------------------------------------ WP stubs */

if ( ! function_exists( 'set_theme_mod' ) ) {
	/**
	 * Seed the mock theme-mod store the same way WordPress would.
	 *
	 * @param string $key   Setting.
	 * @param mixed  $value Value.
	 */
	function set_theme_mod( string $key, $value ): void {
		$GLOBALS['_mock_theme_mods'][ $key ] = $value;
	}
}

if ( ! function_exists( 'get_theme_mods' ) ) {
	/**
	 * All seeded theme mods.
	 *
	 * @return array<string, mixed>
	 */
	function get_theme_mods(): array {
		return (array) ( $GLOBALS['_mock_theme_mods'] ?? array() );
	}
}

if ( ! function_exists( 'has_custom_logo' ) ) {
	/**
	 * The fixture site has no logo upload.
	 */
	function has_custom_logo(): bool {
		return ! empty( $GLOBALS['_mock_theme_mods']['custom_logo'] );
	}
}

if ( ! function_exists( 'the_custom_logo' ) ) {
	/**
	 * Print the logo the way core would, minimally.
	 */
	function the_custom_logo(): void {
		echo '<span class="custom-logo-link">LOGO</span>';
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	/**
	 * Trim helper.
	 *
	 * @param string $text  Text.
	 * @param int    $words Word count.
	 */
	function wp_trim_words( string $text, int $words = 55 ): string {
		$parts = preg_split( '/\s+/u', trim( $text ) ) ?: array();

		return implode( ' ', array_slice( $parts, 0, $words ) );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escaping stub.
	 *
	 * @param string $url URL.
	 */
	function esc_url( string $url ): string {
		return $url;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escaping stub.
	 *
	 * @param string $text Text.
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'get_search_query' ) ) {
	/**
	 * No query on the fixture request.
	 */
	function get_search_query(): string {
		return '';
	}
}

if ( ! function_exists( 'wp_login_url' ) ) {
	/**
	 * Login URL stub.
	 */
	function wp_login_url(): string {
		return 'https://example.test/wp-login.php';
	}
}

if ( ! function_exists( 'has_nav_menu' ) ) {
	/**
	 * The fixture site has no footer menu assigned.
	 *
	 * @param string $location Location.
	 */
	function has_nav_menu( string $location ): bool {
		return false;
	}
}

if ( ! function_exists( 'is_active_sidebar' ) ) {
	/**
	 * No widget is placed in the fixture.
	 *
	 * @param string $id Sidebar id.
	 */
	function is_active_sidebar( string $id ): bool {
		return ! empty( $GLOBALS['_mock_sidebars'][ $id ] );
	}
}

if ( ! function_exists( 'dynamic_sidebar' ) ) {
	/**
	 * Render a seeded widget area.
	 *
	 * @param string $id Sidebar id.
	 */
	function dynamic_sidebar( string $id ): void {
		echo (string) ( $GLOBALS['_mock_sidebars'][ $id ] ?? '' );
	}
}

if ( ! function_exists( 'register_sidebar' ) ) {
	/**
	 * Collect registered widget areas instead of rendering admin UI.
	 *
	 * @param array<string, mixed> $args Sidebar args.
	 */
	function register_sidebar( array $args ): void {
		$GLOBALS['_mock_registered_sidebars'][ (string) $args['id'] ] = $args;
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	/**
	 * Dependency probe.
	 *
	 * @param string $handle Handle.
	 * @param string $type   Type.
	 */
	function wp_style_is( string $handle, string $type = 'enqueued' ): bool {
		return false;
	}
}

if ( ! function_exists( 'checked' ) ) {
	/**
	 * Attribute helper.
	 *
	 * @param mixed $checked Current.
	 * @param mixed $current Compared.
	 */
	function checked( $checked, $current = true, string $display = '' ): string {
		$out = ( $checked == $current ) ? ' checked="checked"' : '';
		if ( 'echo' !== $display ) {
			echo $out;
		}
		return $out;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error for validate callbacks.
	 */
	class WP_Error {
		/** @var array<string, string> */
		public $errors = array();

		/**
		 * Record a message.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 */
		public function add( $code, $message = '' ): void {
			$this->errors[ (string) $code ] = (string) $message;
		}

		/**
		 * Whether anything was reported.
		 */
		public function has_errors(): bool {
			return (bool) $this->errors;
		}
	}
}

if ( ! class_exists( 'WP_Customize_Control' ) ) {
	/**
	 * Customizer control base, enough for the builder control to render.
	 */
	class WP_Customize_Control {
		public $id;
		public $label = '';
		public $description = '';
		public $section = '';
		public $type = 'text';
		public $settings = array();
		public $json = array();
		public $manager;

		/**
		 * Store args as properties, like core does.
		 *
		 * @param mixed                $manager Manager.
		 * @param string               $id      Control id.
		 * @param array<string, mixed> $args    Args.
		 */
		public function __construct( $manager = null, $id = '', array $args = array() ) {
			$this->manager = $manager;
			$this->id      = (string) $id;
			foreach ( $args as $key => $value ) {
				$this->$key = $value;
			}

			// Core binds a control to its own setting when none was listed.
			if ( empty( $this->settings ) ) {
				$this->settings = array( $this->id );
			}
		}

		/**
		 * Serialise.
		 */
		public function to_json(): void {
			$this->json['id']      = $this->id;
			$this->json['settings'] = $this->settings;
		}

		/**
		 * No-op enqueue.
		 */
		public function enqueue(): void {}

		/**
		 * No-op.
		 */
		public function render_content(): void {}
	}

	/**
	 * Colour control stand-in.
	 */
	class WP_Customize_Color_Control extends WP_Customize_Control {}

	/**
	 * Image control stand-in.
	 */
	class WP_Customize_Image_Control extends WP_Customize_Control {}
}


if ( ! function_exists( 'wp_nav_menu' ) ) {
	/**
	 * Menu renderer stand-in.
	 *
	 * @param array<string, mixed> $args Args.
	 */
	function wp_nav_menu( array $args = array() ): void {
		echo '<ul class="' . esc_attr( (string) ( $args['menu_class'] ?? '' ) ) . '"><li><a href="#">MENU</a></li></ul>';
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Pass-through.
	 *
	 * @param string $data Data.
	 */
	function wp_kses_post( string $data ): string {
		return $data;
	}
}

if ( ! function_exists( 'is_front_page' ) ) {
	/**
	 * Always the front page for these tests.
	 */
	function is_front_page(): bool {
		return true;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * No singular view.
	 */
	function is_singular( $post_types = '' ): bool {
		return false;
	}
}

if ( ! function_exists( 'is_archive' ) ) {
	/**
	 * No archive view.
	 */
	function is_archive(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_search' ) ) {
	/**
	 * No search view.
	 */
	function is_search(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_cart' ) ) {
	/**
	 * No cart view.
	 */
	function is_cart(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	/**
	 * No checkout view.
	 */
	function is_checkout(): bool {
		return false;
	}
}
