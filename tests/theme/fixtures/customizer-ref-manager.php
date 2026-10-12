<?php
/**
 * Recording manager for the offline Customizer reference generator.
 *
 * dev-tools/docs/export-customizer-reference.php asks a live WordPress what
 * the theme registered and renders docs/fa/08-jadval-kontrol-ha.md from the
 * answer. This fixture plays the same role without WordPress: it mimics just
 * enough of WP_Customize_Manager (core defaults included, so a control that
 * omits `priority` sorts where core would sort it) for the four registrar
 * classes to run against, and it records the result the way the manager's
 * maps would look when the Customizer pane renders.
 *
 * Loaded by tests/theme/test-customizer-reference.php.
 *
 * @package Flavor
 */

// Registrars touch the hook registry to re-point setting callbacks; the
// registry itself is irrelevant to what this fixture records.
if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * No-op filter registration.
	 *
	 * @return bool
	 */
	function add_filter() {
		return true;
	}
}
if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * No-op filter removal.
	 *
	 * @return bool
	 */
	function remove_filter() {
		return true;
	}
}

if ( ! class_exists( 'WP_Customize_Control' ) ) {
	/**
	 * Control base with core's property defaults, args copied like core does.
	 */
	#[AllowDynamicProperties]
	class WP_Customize_Control {
		/**
		 * Id.
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
		 * Section id.
		 *
		 * @var string
		 */
		public $section = '';

		/**
		 * Type.
		 *
		 * @var string
		 */
		public $type = 'text';

		/**
		 * Priority — the default core hands to a control that omits one.
		 *
		 * Verified against the live export of the reference table: controls
		 * with no explicit priority land after the builder's drag handles
		 * (priority 5) and before the first explicit pick (20).
		 *
		 * @var int
		 */
		public $priority = 10;

		/**
		 * Capability — core default.
		 *
		 * @var string
		 */
		public $capability = 'edit_theme_options';

		/**
		 * Bound settings.
		 *
		 * @var array
		 */
		public $settings = array();

		/**
		 * Choices.
		 *
		 * @var array
		 */
		public $choices = array();

		/**
		 * Manager back-reference.
		 *
		 * @var mixed
		 */
		public $manager;

		/**
		 * Constructor.
		 *
		 * @param mixed  $manager Manager.
		 * @param string $id      Control id.
		 * @param array  $args    Args.
		 */
		public function __construct( $manager = null, $id = '', $args = array() ) {
			$this->manager = $manager;
			$this->id      = (string) $id;
			foreach ( (array) $args as $key => $value ) {
				$this->$key = $value;
			}
			if ( empty( $this->settings ) ) {
				$this->settings = array( $this->id );
			}
		}
	}
}

if ( ! class_exists( 'WP_Customize_Section' ) ) {
	/**
	 * Section object, core defaults.
	 */
	#[AllowDynamicProperties]
	class WP_Customize_Section {
		/** @var string */
		public $id = '';
		/** @var string */
		public $title = '';
		/** @var string */
		public $description = '';
		/** @var string */
		public $panel = '';
		/** @var string */
		public $capability = 'edit_theme_options';
		/** @var int */
		public $priority = 160;

		/**
		 * Constructor.
		 *
		 * @param mixed  $manager Manager.
		 * @param string $id      Section id.
		 * @param array  $args    Args.
		 */
		public function __construct( $manager = null, $id = '', $args = array() ) {
			$this->id = (string) $id;
			foreach ( (array) $args as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Customize_Panel' ) ) {
	/**
	 * Panel object, core defaults.
	 */
	#[AllowDynamicProperties]
	class WP_Customize_Panel {
		/** @var string */
		public $id = '';
		/** @var string */
		public $title = '';
		/** @var string */
		public $description = '';
		/** @var int */
		public $priority = 160;

		/**
		 * Constructor.
		 *
		 * @param mixed  $manager Manager.
		 * @param string $id      Panel id.
		 * @param array  $args    Args.
		 */
		public function __construct( $manager = null, $id = '', $args = array() ) {
			$this->id = (string) $id;
			foreach ( (array) $args as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Customize_Setting' ) ) {
	/**
	 * Setting object; args become properties, like core.
	 */
	#[AllowDynamicProperties]
	class WP_Customize_Setting {
		/** @var string */
		public $id = '';
		/** @var string */
		public $default = '';
		/** @var string */
		public $transport = 'refresh';
		/** @var callable|null */
		public $sanitize_callback;
		/** @var callable|null */
		public $validate_callback;
		/** @var callable|null */
		public $sanitize_js_callback;
		/** @var mixed */
		public $manager;

		/**
		 * Constructor.
		 *
		 * @param mixed  $manager Manager.
		 * @param string $id      Setting id.
		 * @param array  $args    Args.
		 */
		public function __construct( $manager = null, $id = '', $args = array() ) {
			$this->manager = $manager;
			$this->id      = (string) $id;
			foreach ( (array) $args as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

// Control subclasses the theme instantiates directly.
foreach ( array( 'WP_Customize_Image_Control', 'WP_Customize_Color_Control', 'WP_Customize_Upload_Control' ) as $ref_control_class ) {
	if ( ! class_exists( $ref_control_class ) ) {
		eval( 'class ' . $ref_control_class . ' extends \WP_Customize_Control {}' );
	}
}

if ( ! class_exists( 'Flavor_Ref_Manager' ) ) {
	/**
	 * The recording manager.
	 */
	class Flavor_Ref_Manager {
		/**
		 * Settings by id.
		 *
		 * @var array<string, \WP_Customize_Setting>
		 */
		public $settings = array();

		/**
		 * Controls by id.
		 *
		 * @var array<string, \WP_Customize_Control>
		 */
		public $controls = array();

		/**
		 * Sections by id.
		 *
		 * @var array<string, \WP_Customize_Section>
		 */
		public $sections = array();

		/**
		 * Panels by id.
		 *
		 * @var array<string, \WP_Customize_Panel>
		 */
		public $panels = array();

		/**
		 * Control classes registered by type.
		 *
		 * @var array<string, string>
		 */
		public $control_types = array();

		/**
		 * Selective refresh stand-in.
		 *
		 * @var object
		 */
		public $selective_refresh;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->selective_refresh = new class() {
				/**
				 * Partials.
				 *
				 * @var array
				 */
				public $partials = array();

				/**
				 * Record a partial.
				 *
				 * @param string $id   Partial id.
				 * @param array  $args Args.
				 */
				public function add_partial( $id, $args = array() ) {
					$this->partials[ (string) $id ] = (array) $args;
				}
			};
		}

		/**
		 * Add a setting; core returns the object.
		 *
		 * @param string|object $id   Id or prebuilt setting.
		 * @param array         $args Args.
		 * @return \WP_Customize_Setting
		 */
		public function add_setting( $id, $args = array() ) {
			if ( is_object( $id ) ) {
				$this->settings[ (string) $id->id ] = $id;
				return $id;
			}
			$setting                            = new WP_Customize_Setting( $this, (string) $id, (array) $args );
			$this->settings[ (string) $id ]     = $setting;
			return $setting;
		}

		/**
		 * Get a setting.
		 *
		 * @param string $id Id.
		 * @return \WP_Customize_Setting|null
		 */
		public function get_setting( $id ) {
			return isset( $this->settings[ (string) $id ] ) ? $this->settings[ (string) $id ] : null;
		}

		/**
		 * Register a control.
		 *
		 * Mirrors core: a built object is stored as-is; args get wrapped in the
		 * class named by register_control_type( $args['type'] ) when there is
		 * one, otherwise in WP_Customize_Control. Re-registering an id replaces
		 * the control but keeps its insertion position, exactly as the real
		 * manager's keyed array does — the reference's row order depends on
		 * that for priority ties.
		 *
		 * @param string|object $id   Control object or id.
		 * @param array         $args Args.
		 * @return \WP_Customize_Control
		 */
		public function add_control( $id, $args = array() ) {
			if ( is_object( $id ) ) {
				$control = $id;
				if ( empty( $control->settings ) ) {
					$control->settings = array( (string) $control->id );
				}
			} else {
				$args          = (array) $args;
				$args['id']    = (string) $id;
				$control_class = isset( $args['type'], $this->control_types[ $args['type'] ] )
					? $this->control_types[ $args['type'] ]
					: 'WP_Customize_Control';
				$control       = new $control_class( $this, (string) $id, $args );
			}
			$this->controls[ (string) $control->id ] = $control;
			return $control;
		}

		/**
		 * Get a control.
		 *
		 * @param string $id Id.
		 * @return \WP_Customize_Control|null
		 */
		public function get_control( $id ) {
			return isset( $this->controls[ (string) $id ] ) ? $this->controls[ (string) $id ] : null;
		}

		/**
		 * Register a section.
		 *
		 * @param string $id   Section id.
		 * @param array  $args Args.
		 * @return \WP_Customize_Section
		 */
		public function add_section( $id, $args = array() ) {
			$section                      = new WP_Customize_Section( $this, (string) $id, (array) $args );
			$this->sections[ (string) $id ] = $section;
			return $section;
		}

		/**
		 * Get a section.
		 *
		 * @param string $id Id.
		 * @return \WP_Customize_Section|null
		 */
		public function get_section( $id ) {
			return isset( $this->sections[ (string) $id ] ) ? $this->sections[ (string) $id ] : null;
		}

		/**
		 * Register a panel.
		 *
		 * @param string $id   Panel id.
		 * @param array  $args Args.
		 * @return \WP_Customize_Panel
		 */
		public function add_panel( $id, $args = array() ) {
			$panel                        = new WP_Customize_Panel( $this, (string) $id, (array) $args );
			$this->panels[ (string) $id ]   = $panel;
			return $panel;
		}

		/**
		 * Get a panel.
		 *
		 * @param string $id Id.
		 * @return \WP_Customize_Panel|null
		 */
		public function get_panel( $id ) {
			return isset( $this->panels[ (string) $id ] ) ? $this->panels[ (string) $id ] : null;
		}

		/**
		 * Registered controls, like the manager accessor.
		 *
		 * @return array<string, \WP_Customize_Control>
		 */
		public function controls() {
			return $this->controls;
		}

		/**
		 * Registered sections.
		 *
		 * @return array<string, \WP_Customize_Section>
		 */
		public function sections() {
			return $this->sections;
		}

		/**
		 * Registered panels.
		 *
		 * @return array<string, \WP_Customize_Panel>
		 */
		public function panels() {
			return $this->panels;
		}

		/**
		 * Core records custom control classes by name.
		 *
		 * @param string $control_class Class name.
		 */
		public function register_control_type( $control_class ) {
			$this->control_types[ (string) $control_class ] = (string) $control_class;
		}
	}
}
