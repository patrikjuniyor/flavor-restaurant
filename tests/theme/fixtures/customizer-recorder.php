<?php
/**
 * A recording stand-in for WP_Customize_Manager.
 *
 * Parsing class-customizer.php for setting ids would only catch the literal
 * ones: the colour, typography and gallery settings are built inside loops.
 * Running the real register() against this recorder collects every setting
 * the Customizer actually declares, including those.
 *
 * @package Flavor
 */

namespace {
	if ( ! class_exists( '\WP_Customize_Setting' ) ) {
		/**
		 * A setting that records its transport.
		 */
		class WP_Customize_Setting {
			/**
			 * Setting id.
			 *
			 * @var string
			 */
			public $id = '';

			/**
			 * Transport.
			 *
			 * @var string
			 */
			public $transport = 'refresh';

			/**
			 * Args.
			 *
			 * @var array
			 */
			public $args = array();

			/**
			 * Constructor.
			 *
			 * @param string $id   Id.
			 * @param array  $args Args.
			 */
			public function __construct( string $id, array $args = array() ) {
				$this->id        = $id;
				$this->args      = $args;
				$this->transport = (string) ( $args['transport'] ?? 'refresh' );
			}
		}
	}

	if ( ! class_exists( '\WP_Customize_Selective_Refresh' ) ) {
		/**
		 * Records partials instead of rendering them.
		 */
		class WP_Customize_Selective_Refresh {
			/**
			 * Registered partials.
			 *
			 * @var array
			 */
			public $partials = array();

			/**
			 * Add a partial.
			 *
			 * @param string $id    Partial id.
			 * @param array  $args  Args.
			 */
			public function add_partial( string $id, array $args = array() ): void {
				$this->partials[ $id ] = $args;
			}
		}
	}

	if ( ! class_exists( '\WP_Customize_Manager' ) ) {
		/**
		 * Recording Customizer manager.
		 */
		class WP_Customize_Manager {
			/**
			 * Settings.
			 *
			 * @var array<string, \WP_Customize_Setting>
			 */
			public $settings = array();

			/**
			 * Controls, by id.
			 *
			 * @var array
			 */
			public $controls = array();

			/**
			 * Sections.
			 *
			 * @var array
			 */
			public $sections = array();

			/**
			 * Panels.
			 *
			 * @var array
			 */
			public $panels = array();

			/**
			 * Selective refresh.
			 *
			 * @var \WP_Customize_Selective_Refresh
			 */
			public $selective_refresh;

			/**
			 * Constructor.
			 */
			public function __construct() {
				$this->selective_refresh = new \WP_Customize_Selective_Refresh();
			}

			/**
			 * Register a setting.
			 *
			 * @param string|object $id   Id or setting object.
			 * @param array         $args Args.
			 * @return \WP_Customize_Setting|null
			 */
			public function add_setting( $id, array $args = array() ) {
				if ( is_object( $id ) ) {
					$this->settings[ $id->id ] = $id;
					return $id;
				}
				$this->settings[ (string) $id ] = new \WP_Customize_Setting( (string) $id, $args );
				return $this->settings[ (string) $id ];
			}

			/**
			 * Get a setting.
			 *
			 * @param string $id Id.
			 * @return \WP_Customize_Setting|null
			 */
			public function get_setting( string $id ) {
				return $this->settings[ $id ] ?? null;
			}

			/**
			 * Register a control.
			 *
			 * @param string|object $id   Id or control object.
			 * @param array         $args Args.
			 */
			public function add_control( $id, array $args = array() ): void {
				$key = is_object( $id ) ? $id->id : (string) $id;
				$this->controls[ $key ] = is_object( $id ) ? $id : $args;
			}

			/**
			 * Register a section.
			 *
			 * @param string $id   Id.
			 * @param array  $args Args.
			 */
			public function add_section( string $id, array $args = array() ): void {
				$this->sections[ $id ] = $args;
			}

			/**
			 * Register a panel.
			 *
			 * @param string $id   Id.
			 * @param array  $args Args.
			 */
			public function add_panel( string $id, array $args = array() ): void {
				$this->panels[ $id ] = $args;
			}

			/**
			 * Register a control type.
			 *
			 * @param string $type Class name.
			 */
			public function register_control_type( string $type ): void {}
		}
	}

	// Core control subclasses the theme instantiates directly.
	foreach ( array( 'WP_Customize_Image_Control', 'WP_Customize_Color_Control', 'WP_Customize_Upload_Control' ) as $control_class ) {
		if ( ! class_exists( '\\' . $control_class ) ) {
			eval( 'class ' . $control_class . ' extends \\WP_Customize_Control { public function __construct( $manager, $id, array $args = array() ) { parent::__construct( $manager, $id, $args ); } }' );
		}
	}
}
