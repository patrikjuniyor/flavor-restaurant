<?php
/**
 * Chrome builder contract tests.
 *
 * The header and footer builders are only worth shipping if the controls write
 * through to real markup and real CSS. This suite runs the same code the theme
 * runs — sanitizers, renderers, the CSS variable block and the Customizer
 * registration — against the Flavor Core mock environment, and fails when a
 * control stops being wired to output.
 *
 *   php tests/theme/test-chrome-builder.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-dark-mode.php';
require_once FLAVOR_DIR . '/inc/class-wishlist.php';
require_once FLAVOR_DIR . '/inc/class-bespoke-demos.php';
require_once FLAVOR_DIR . '/inc/template-tags.php';
use Flavor\Chrome_Builder;
use Flavor\Chrome_Order_Control;

require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';

/**
 * Stand-in for the WP_Error object a Customizer validator receives.
 *
 * The shared mock environment declares WP_Error with required constructor
 * arguments while core hands validators an empty one, so the harness uses this.
 */
final class Mock_Validity {
	/** @var array<string, string[]> */
	public $errors = array();

	/**
	 * @param string $code    Error code.
	 * @param string $message Message.
	 */
	public function add( $code, $message = '' ): void {
		$this->errors[ (string) $code ][] = (string) $message;
	}

	/**
	 * @return bool Whether anything was reported.
	 */
	public function has_errors(): bool {
		return $this->errors !== array();
	}

	/**
	 * @return self
	 */
	public static function make(): self {
		return new self();
	}
}

/**
 * Setting stand-in: core exposes these properties to a registrar.
 */
final class Mock_Setting {
	/** @var string */
	public $id;
	/** @var mixed */
	public $default;
	/** @var string */
	public $transport = 'refresh';
	/** @var callable|null */
	public $sanitize_callback;
	/** @var callable|null */
	public $validate_callback;

	/**
	 * @param string              $id   Setting id.
	 * @param array<string,mixed> $args Args.
	 */
	public function __construct( string $id, array $args = array() ) {
		$this->id = $id;
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
	}

	/**
	 * Run the stored sanitizer, like core does before saving.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public function sanitize( $value ) {
		if ( is_callable( $this->sanitize_callback ) ) {
			/*
			 * One argument, exactly like core: WP_Customize_Setting registers an
			 * add_setting sanitizer with accepted_args = 1, so a callback that
			 * demands the setting object fatalises the save.
			 */
			return call_user_func( $this->sanitize_callback, $value );
		}
		return $value;
	}

	/**
	 * Run the stored validator.
	 *
	 * @param mixed $value Raw value.
	 * @return \WP_Error
	 */
	public function validate( $value ) {
		$validity = Mock_Validity::make();
		$hooks    = $GLOBALS['_flavor_test_filters'][ 'customize_validate_' . $this->id ] ?? array();

		foreach ( $hooks as $hook ) {
			list( $callback, $priority, $accepted ) = $hook;

			// Core passes exactly $accepted arguments, no more.
			$args     = array( $validity, $value, $this );
			$validity = $callback( ...array_slice( $args, 0, max( 1, $accepted ) ) );
		}

		if ( is_callable( $this->validate_callback ) ) {
			$validity = call_user_func( $this->validate_callback, $validity, $value );
		}

		return $validity;
	}

	/**
	 * How many arguments the stored sanitizer demands.
	 *
	 * @return int Required parameter count, zero when there is no callback.
	 */
	public function sanitizer_arity(): int {
		if ( ! is_callable( $this->sanitize_callback ) ) {
			return 0;
		}

		$callback = $this->sanitize_callback;
		$reflect  = is_array( $callback ) ? new \ReflectionMethod( $callback[0], $callback[1] ) : new \ReflectionFunction( $callback );

		return $reflect->getNumberOfRequiredParameters();
	}
}

/**
 * Customizer manager stand-in that records everything a registrar adds.
 */
final class Mock_Manager {
	/** @var array<string, Mock_Setting> */
	public $settings = array();
	/** @var array<string, object> */
	public $controls = array();
	/** @var array<string, object> */
	public $sections = array();
	/** @var array<string, object> */
	public $panels = array();
	/** @var array<string, array<string, mixed>> */
	public $partials = array();
	/** @var object */
	public $selective_refresh;

	/**
	 * Track partials like core does.
	 */
	public function __construct() {
		$this->selective_refresh = new class() {
			/** @var array<string, array<string, mixed>> */
			public $partials = array();

			/**
			 * @param string              $id   Partial id.
			 * @param array<string,mixed> $args Args.
			 */
			public function add_partial( $id, $args = array() ): void {
				$this->partials[ is_object( $id ) ? $id->id : $id ] = (array) $args;
			}
		};
	}

	/**
	 * @param string              $id   Panel id.
	 * @param array<string, mixed> $args Args.
	 */
	public function add_panel( $id, array $args = array() ): void {
		$this->panels[ (string) $id ] = (object) $args;
	}

	/**
	 * @param string              $id   Section id.
	 * @param array<string, mixed> $args Args.
	 */
	public function add_section( $id, array $args = array() ): void {
		$this->sections[ (string) $id ] = (object) $args;
	}

	/**
	 * @param string $id Section id.
	 * @return object|null
	 */
	public function get_section( string $id ) {
		return $this->sections[ $id ] ?? null;
	}

	/**
	 * @param string              $id   Setting id.
	 * @param array<string, mixed> $args Args.
	 * @return Mock_Setting
	 */
	public function add_setting( $id, array $args = array() ) {
		$setting = new Mock_Setting( (string) $id, $args );
		if ( isset( $this->settings[ $id ] ) ) {
			throw new \RuntimeException( 'Duplicate setting registered: ' . $id );
		}
		$this->settings[ (string) $id ] = $setting;

		return $setting;
	}

	/**
	 * @param string $id Setting id.
	 * @return Mock_Setting|null
	 */
	public function get_setting( string $id ) {
		return $this->settings[ $id ] ?? null;
	}

	/**
	 * @param mixed                $control Control or id.
	 * @param string               $id      Setting id.
	 * @param array<string, mixed> $args    Args.
	 * @return object
	 */
	public function add_control( $control, $id = '', array $args = array() ) {
		if ( is_object( $control ) ) {
			// Core binds an unbuilt `settings` list to the control id.
			if ( ! isset( $control->settings ) ) {
				$control->settings = array( (string) $control->id );
			}

			$this->controls[ (string) $control->id ] = $control;
			return $control;
		}

		/*
		 * Core accepts add_control( $id, $args ), add_control( $args ) and a built
		 * control object. The builder uses the first two, so the stand-in has to
		 * tell them apart or every control lands under the same empty key.
		 */
		if ( is_string( $control ) ) {
			// add_control( $setting_id, $args ) — the form the theme uses everywhere.
			$args = is_array( $id ) ? $id : array();
			$id   = $control;
		} elseif ( is_array( $control ) ) {
			$args = $control;
			$id   = isset( $args['id'] )
				? $args['id']
				: ( is_array( $args['settings'] ?? null ) ? ( $args['settings'][0] ?? '' ) : ( $args['settings'] ?? '' ) );
		}

		$object       = (object) $args;
		$object->id   = (string) $id;
		$object->type = isset( $object->type ) ? (string) $object->type : 'text';

		if ( ! isset( $object->settings ) ) {
			$object->settings = array( $object->id );
		} elseif ( ! is_array( $object->settings ) ) {
			$object->settings = array( (string) $object->settings );
		}

		if ( isset( $this->controls[ $object->id ] ) ) {
			throw new \RuntimeException( 'Duplicate control registered: ' . $object->id );
		}

		$this->controls[ $object->id ] = $object;

		return $object;
	}

	/**
	 * @param string $id Control id.
	 * @return object|null
	 */
	public function get_control( string $id ) {
		return $this->controls[ $id ] ?? null;
	}

	/**
	 * @param string              $id   Partial id.
	 * @param array<string, mixed> $args Args.
	 */
	public function add_partial( $id, array $args = array() ): void {
		$this->partials[ (string) ( is_object( $id ) ? $id->id : $id ) ] = $args;
		foreach ( (array) ( $args['settings'] ?? array() ) as $setting_id ) {
			if ( isset( $this->settings[ $setting_id ] ) ) {
				$this->settings[ $setting_id ]->transport = 'refresh';
			}
		}
	}
}

/* The builder classes extend WordPress bases, so they load after the stubs. */
require_once FLAVOR_DIR . '/inc/class-chrome-control.php';
// The recorder has to exist before the registrar runs, so its own add_filter()
// calls are captured instead of reaching the (inert) global stand-in.
require_once __DIR__ . '/fixtures/chrome-hook-recorder.php';
require_once FLAVOR_DIR . '/inc/class-chrome-builder.php';

/* ------------------------------------------------------------------ assertions */

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
function check( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

/**
 * Replace the seeded theme mods.
 *
 * @param array<string, mixed> $mods Mods.
 */
function seed( array $mods = array() ): void {
	$GLOBALS['_mock_theme_mods'] = $mods;
}

/**
 * Capture a renderer.
 *
 * @param callable $callback Renderer.
 * @return string
 */
function capture( callable $callback ): string {
	ob_start();
	$callback();
	return (string) ob_get_clean();
}

seed();

echo "--- 1. Registries and sanitizers ---\n";

$header = Chrome_Builder::header_components();
$footer = Chrome_Builder::footer_blocks();

check( count( $header ) >= 8, 'The header offers at least eight elements (' . count( $header ) . ')' );
check( count( $footer ) >= 7, 'The footer offers at least seven blocks (' . count( $footer ) . ')' );
check(
	count( array_unique( array_keys( $header ) ) ) === count( $header ) && count( array_unique( array_keys( $footer ) ) ) === count( $footer ),
	'Component keys are unique'
);

$complete = true;
foreach ( array( $header, $footer ) as $registry ) {
	foreach ( $registry as $key => $config ) {
		if ( '' === trim( (string) $config['label'] ) || ! is_bool( $config['default'] ) ) {
			$complete = false;
		}
	}
}
check( $complete, 'Every element has a label and a boolean default' );

check(
	Chrome_Builder::normalize_order( 'cart,phone,unknown,phone', $header ) === array( 'cart', 'phone', 'scheme', 'wishlist', 'order', 'mobile_nav', 'search', 'account' ),
	'Unknown keys are dropped, duplicates merged and missing ones appended'
);
check(
	Chrome_Builder::normalize_order( array( 'menu', 'hours' ), $footer ) === array( 'menu', 'hours', 'brand', 'contact', 'social', 'newsletter', 'widgets' ),
	'Array input and partial coverage normalise the same way'
);
check(
	Chrome_Builder::normalize_order( str_repeat( 'phone,', 200 ), $header ) === array( 'phone', 'scheme', 'wishlist', 'order', 'mobile_nav', 'search', 'cart', 'account' ),
	'A hostile order string cannot grow the list'
);
check( Chrome_Builder::normalize_order( null, $header ) === array_keys( $header ), 'A missing value falls back to the registry order' );

check(
	Chrome_Builder::sanitize_checkbox( true ) === 'yes' && Chrome_Builder::sanitize_checkbox( 'off' ) === 'no' && Chrome_Builder::sanitize_checkbox( 0 ) === 'no',
	'Checkbox storage is always yes/no'
);

echo "\n--- 2. Untouched sites render exactly what they rendered before ---\n";

check(
	Chrome_Builder::header_elements() === array( 'phone', 'scheme', 'wishlist', 'order', 'mobile_nav' ),
	'Default header elements are the five the template hard-coded'
);
check(
	Chrome_Builder::footer_elements() === array( 'brand', 'menu', 'hours', 'contact' ),
	'Default footer blocks are the four columns the template hard-coded'
);
check( '' === Chrome_Builder::css_vars(), 'Nothing is printed into the head while no chrome setting is changed' );
check( Chrome_Builder::social_in_brand(), 'Social links stay inside the brand column by default' );
check( 0 === Chrome_Builder::footer_columns( 'desktop' ), 'The footer grid stays automatic by default' );

$actions = capture( static function () { Chrome_Builder::render_header_actions( 'classic' ); } );
check( strpos( $actions, 'flavor-header__phone' ) !== false, 'Default markup still carries the phone action' );
check( strpos( $actions, 'flavor-header__order-btn' ) !== false, 'Default markup still carries the order button' );
check( strpos( $actions, 'id="flavor-drawer-toggle"' ) !== false, 'Default markup still carries the drawer toggle' );
check( strpos( $actions, 'flavor-scheme-toggle' ) !== false, 'Night mode toggle keeps its handler' );
check( strpos( $actions, 'data-wishlist-open' ) !== false, 'Wishlist button keeps its handler' );
check( strpos( $actions, 'flavor-header__search' ) === false, 'Search stays off until it is switched on' );
check( strpos( $actions, 'flavor-header__cart' ) === false, 'Cart stays off until it is switched on' );
check( strpos( $actions, 'flavor-header__account' ) === false, 'Account stays off until it is switched on' );
check( strpos( $actions, 'href="tel:02188001234"' ) !== false, 'The phone number is dialable, not decorative' );

$brand = capture( static function () { Chrome_Builder::render_brand( 'classic' ); } );
check( strpos( $brand, 'flavor-brand__name' ) !== false && strpos( $brand, 'flavor-brand__tagline' ) === false, 'Tagline is off until enabled' );

seed( array( 'flavor_header_show_tagline' => 'yes' ) );
$brand = capture( static function () { Chrome_Builder::render_brand( 'classic' ); } );
check( strpos( $brand, 'flavor-brand__tagline' ) !== false, 'Enabling the tagline control changes the markup' );
seed();

echo "\n--- 3. Order and visibility really drive the markup ---\n";

seed( array( Chrome_Builder::HEADER_ORDER => 'search,phone,scheme,wishlist,order,mobile_nav,cart,account', 'flavor_header_show_search' => 'yes', 'flavor_header_show_cart' => 'yes' ) );
$actions = capture( static function () { Chrome_Builder::render_header_actions( 'classic' ); } );
check(
	strpos( $actions, 'flavor-header__search' ) < strpos( $actions, 'flavor-header__phone' ),
	'The stored order decides which element comes first'
);
// WooCommerce is not part of this harness: the cart element has to opt out of
// the row cleanly instead of fatalising on a WC() call.
check(
	strpos( $actions, 'flavor-header__cart' ) === false,
	'A cart element with no shop behind it is skipped, not fatal'
);
check(
	0 === Chrome_Builder::cart_count() && ! Chrome_Builder::has_cart(),
	'The helpers report an unavailable cart instead of guessing'
);

seed( array( 'flavor_header_show_cart' => 'yes', 'flavor_header_show_search' => 'yes', 'flavor_header_show_account' => 'yes' ) );
$actions = capture( static function () { Chrome_Builder::render_header_actions( 'classic' ); } );
check( strpos( $actions, 'role="search"' ) !== false, 'Search renders a real GET form' );
check( strpos( $actions, 'name="s"' ) !== false, 'The search field uses the WordPress query var' );
check( strpos( $actions, 'flavor-header__account' ) !== false, 'The account element links to a login URL' );

seed( array( 'flavor_header_show_phone' => 'no', 'flavor_header_show_wishlist' => 'no' ) );
$actions = capture( static function () { Chrome_Builder::render_header_actions( 'classic' ); } );
check( strpos( $actions, 'flavor-header__phone' ) === false, 'Switching an element off removes it from the row' );
check( strpos( $actions, 'data-wishlist-open' ) === false, 'No orphan wishlist button survives a hidden setting' );

seed( array( 'flavor_header_show_search' => 'yes' ) );
$drawer = capture( static function () { Chrome_Builder::render_mobile_drawer( 'classic' ); } );
check( strpos( $drawer, 'id="flavor-drawer-search"' ) !== false, 'Phones keep search in the drawer where the header hides it' );

seed( array( 'flavor_header_show_mobile_nav' => 'no' ) );
$drawer = capture( static function () { Chrome_Builder::render_mobile_drawer( 'classic' ); } );
$gates = Chrome_Builder::body_class( array( 'flavor-theme' ) );
check( '' === $drawer, 'Hiding the drawer removes the dialog entirely' );
check( in_array( 'flavor-chrome-no-mobile-nav', $gates, true ), '…and opens the inline menu on phones instead' );

seed( array( Chrome_Builder::HEADER_ORDER => 'nonsense,phone' ) );
$order = Chrome_Builder::header_elements();
check( in_array( 'phone', $order, true ) && ! in_array( 'nonsense', $order, true ), 'A corrupted order value still renders the valid elements' );

echo "\n--- 4. Footer blocks ---\n";

seed( array(
	'flavor_footer_show_social'  => 'yes',
	'flavor_social_instagram'    => 'https://instagram.com/flavor',
	'flavor_social_telegram'     => 'https://t.me/flavor',
	Chrome_Builder::FOOTER_BLOCKS => 'brand,menu,hours,contact,social',
) );
$footer_html = capture( static function () { Chrome_Builder::render_footer( 'classic' ); } );
check( substr_count( $footer_html, 'flavor-social-links' ) === 1, 'A separate social column does not duplicate the links' );
check(
	strpos( $footer_html, 'flavor-footer__col--brand' ) < strpos( $footer_html, 'flavor-social-links' ),
	'The social column keeps its place in the stored order'
);

seed( array( 'flavor_social_whatsapp' => 'https://wa.me/982188001234' ) );
$footer_html = capture( static function () { Chrome_Builder::render_footer( 'classic' ); } );
check(
	substr_count( $footer_html, 'flavor-social-links' ) === 1 && substr_count( $footer_html, 'wa.me' ) === 1,
	'Switching the social column off would have shown them once either way'
);
check( strpos( $footer_html, 'id="flavor-footer"' ) !== false, 'The footer exposes the id the Customizer partial refreshes' );

seed( array( Chrome_Builder::FOOTER_BLOCKS => 'contact,menu,brand,hours' ) );
$footer_html = capture( static function () { Chrome_Builder::render_footer( 'classic' ); } );
check(
	strpos( $footer_html, 'اطلاعات تماس' ) < strpos( $footer_html, 'دسترسی سریع' ),
	'The stored column order is the rendered order'
);

seed( array(
	'flavor_footer_columns'      => 2,
	Chrome_Builder::FOOTER_BLOCKS => 'brand,menu,hours,contact',
) );
$footer_html = capture( static function () { Chrome_Builder::render_footer( 'classic' ); } );
check(
	substr_count( $footer_html, '<div class="flavor-footer__col' ) === 4,
	'A narrow column count wraps the blocks instead of deleting them'
);
check( 2 === Chrome_Builder::footer_columns( 'desktop' ), 'The column choice itself is still readable' );
check(
	strpos( Chrome_Builder::css_vars(), '--flavor-footer-cols:repeat(2, minmax(0, 1fr))' ) !== false,
	'…and reaches the generated CSS'
);

seed( array( 'flavor_footer_show_copy' => 'no', 'flavor_footer_show_powered' => 'no' ) );
$footer_html = capture( static function () { Chrome_Builder::render_footer( 'classic' ); } );
check( strpos( $footer_html, 'flavor-footer__bottom' ) === false, 'Both bottom-bar switches off removes the whole row' );

seed( array( 'flavor_footer_show_newsletter' => 'yes' ) );
$footer_html = capture( static function () { Chrome_Builder::render_footer( 'classic' ); } );
check( strpos( $footer_html, 'data-flavor-subscribe' ) !== false, 'The newsletter column reuses the real subscribe form' );
check( strpos( $footer_html, 'type="email"' ) !== false && strpos( $footer_html, 'maxlength="120"' ) !== false, '…with a bounded email field' );
check( strpos( $footer_html, 'aria-live="polite"' ) !== false, '…and a polite status region' );

seed( array( 'flavor_footer_show_widgets' => 'yes', Chrome_Builder::FOOTER_BLOCKS => 'widgets,brand,menu,hours,contact' ) );
$GLOBALS['_mock_sidebars']['footer-1'] = '<section class="flavor-widget">WIDGET</section>';
$footer_html = capture( static function () { Chrome_Builder::render_footer( 'classic' ); } );
check( strpos( $footer_html, 'WIDGET' ) !== false, 'A column set to widgets prints that column’s widget area' );
unset( $GLOBALS['_mock_sidebars']['footer-1'] );

seed( array( 'flavor_footer_show_map' => 'yes' ) );
$footer_html = capture( static function () { Chrome_Builder::render_footer( 'classic' ); } );
check( strpos( $footer_html, 'flavor-footer__map' ) !== false, 'The branches link is a real link when enabled' );

echo "\n--- 5. Tokens become CSS custom properties ---\n";

seed( array( 'flavor_header_min_height' => '96px', 'flavor_footer_columns' => 3 ) );
$css = Chrome_Builder::css_vars();
check( strpos( $css, '--flavor-header-height:96px;' ) !== false, 'A desktop header height reaches the CSS' );
check( strpos( $css, '--flavor-footer-cols:repeat(3, minmax(0, 1fr));' ) !== false, 'A column count becomes a grid template' );

seed( array( 'flavor_header_min_height' => '999px' ) );
check( '' === Chrome_Builder::css_vars(), 'A value outside the option list is ignored entirely' );

seed( array( 'flavor_header_min_height' => '72px; } body { display: none' ) );
check( '' === Chrome_Builder::css_vars(), 'Injected CSS cannot be stored through a token' );

seed( array( 'flavor_header_border' => 'shadow' ) );
$css = Chrome_Builder::css_vars();
check( strpos( $css, '--flavor-header-border-size:0px;' ) !== false, 'The border choice expands into two properties' );
check( strpos( $css, '--flavor-header-shadow:' ) !== false, '…including the shadow' );

seed( array( 'flavor_button_size' => 'compact' ) );
$gates = Chrome_Builder::body_class( array() );
check( in_array( 'flavor-chrome-button-size', $gates, true ), 'A gated setting adds its body class' );
check( strpos( Chrome_Builder::css_vars(), '--flavor-btn-pad-y:8px;' ) !== false, '…and its variables' );

seed( array( 'flavor_header_bg' => '#123456' ) );
$css = Chrome_Builder::css_vars();
check( strpos( $css, '--flavor-header-bg:#123456;' ) !== false, 'A header colour override is emitted' );
check( in_array( 'flavor-chrome-header-bg', Chrome_Builder::body_class( array() ), true ), '…and gated behind a class' );

seed( array( 'flavor_header_bg' => 'not-a-colour' ) );
check( '' === Chrome_Builder::css_vars(), 'Garbage in a colour slot changes nothing' );

seed( array( 'flavor_header_sticky_mobile' => 'no' ) );
check(
	in_array( 'flavor-chrome-sticky-mobile-off', Chrome_Builder::body_class( array() ), true ),
	'Sticky-on-mobile off is expressed as a class, not inline styles'
);

echo "\n--- 6. Chrome CSS, gates and variables stay in sync ---\n";

$chrome_css = (string) file_get_contents( FLAVOR_DIR . '/assets/css/chrome.css' );

$missing_rules = array();
foreach ( array_keys( Chrome_Builder::token_settings() ) as $id ) {
	$gate = Chrome_Builder::gate_for( $id );
	if ( false === strpos( $chrome_css, '.' . $gate ) ) {
		$missing_rules[] = $gate;
	}
}
check( ! $missing_rules, 'Every gated setting has a rule in chrome.css' . ( $missing_rules ? ' (missing: ' . implode( ', ', $missing_rules ) . ')' : '' ) );

$missing_vars = array();
$emitted = array();
foreach ( Chrome_Builder::token_settings() as $id => $config ) {
	$emitted[] = $config['var'];
	foreach ( (array) ( $config['extra']['compact'] ?? array() ) as $name => $unused ) {
		$emitted[] = $name;
	}
	foreach ( (array) ( $config['extra']['shadow'] ?? array() ) as $name => $unused ) {
		$emitted[] = $name;
	}
}
foreach ( Chrome_Builder::color_settings() as $config ) {
	$emitted[] = $config['var'];
}
$emitted = array_values( array_unique( $emitted ) );
foreach ( $emitted as $name ) {
	if ( false === strpos( $chrome_css, $name ) ) {
		$missing_vars[] = $name;
	}
}
check( ! $missing_vars, 'Every variable PHP can write is read by the stylesheet' . ( $missing_vars ? ' (unused: ' . implode( ', ', $missing_vars ) . ')' : '' ) );

preg_match_all( "/'flavor-chrome-[a-z0-9-]+'/", $chrome_css, $css_gates );
$unknown_gates = array();
foreach ( array_unique( (array) ( $css_gates[0] ?? array() ) ) as $gate ) {
	$needle = substr( $gate, 1, -1 );
	$found = false;
	foreach ( array_keys( Chrome_Builder::token_settings() ) as $id ) {
		if ( Chrome_Builder::gate_for( $id ) === $needle ) {
			$found = true;
			break;
		}
	}
	foreach ( array_keys( Chrome_Builder::color_settings() ) as $id ) {
		if ( Chrome_Builder::gate_for( $id ) === $needle ) {
			$found = true;
			break;
		}
	}
	foreach ( Chrome_Builder::state_gates() as $map ) {
		if ( in_array( $needle, $map, true ) ) {
			$found = true;
		}
	}
	if ( ! $found ) {
		$unknown_gates[] = $needle;
	}
}
check( ! $unknown_gates, 'No stylesheet rule waits for a class PHP never prints' . ( $unknown_gates ? ' (' . implode( ', ', $unknown_gates ) . ')' : '' ) );

$templates = '';
foreach ( array( '/header.php', '/footer.php', '/template-parts/demos/header.php', '/template-parts/demos/footer.php' ) as $file ) {
	$templates .= (string) file_get_contents( FLAVOR_DIR . $file );
}
check(
	strpos( $templates, 'Chrome_Builder::render_header_actions' ) !== false && strpos( $templates, 'Chrome_Builder::render_footer' ) !== false,
	'The shipped templates delegate to the builder instead of duplicating markup'
);
check(
	strpos( $templates, 'flavor-header__phone' ) === false || strpos( $templates, 'render_header_actions' ) !== false,
	'Header markup lives in one place'
);

echo "\n--- 7. Customizer registration ---\n";

seed();
$manager = new Mock_Manager();
Chrome_Builder::register( $manager );

$missing_settings = array();
$wrong_sanitizer = array();
foreach ( Chrome_Builder::settings_map() as $id => $callback ) {
	if ( ! isset( $manager->settings[ $id ] ) ) {
		$missing_settings[] = $id;
		continue;
	}
	if ( strpos( $manager->settings[ $id ]->id, 'flavor_' ) !== 0 ) {
		$wrong_sanitizer[] = $id;
	}
}
check( ! $missing_settings, 'Every chrome setting the class promises is registered' . ( $missing_settings ? ' (' . implode( ', ', $missing_settings ) . ')' : '' ) );

$too_wide = array();
$orphans  = array();

foreach ( $manager->settings as $setting_id => $setting ) {
	if ( $setting->sanitizer_arity() > 1 ) {
		$too_wide[] = $setting_id;
	}
}

foreach ( $manager->controls as $control_id => $control ) {
	$found = false;

	foreach ( (array) ( $control->settings ?? array() ) as $bound ) {
		if ( isset( $manager->settings[ $bound ] ) ) {
			$found = true;
			break;
		}
	}

	if ( ! $found ) {
		$orphans[] = $control_id;
	}
}

check(
	array() === $too_wide,
	'Every sanitizer can be called the way core calls it'
	. ( $too_wide ? ' — needs a bound callback: ' . implode( ', ', $too_wide ) : '' )
);
check(
	array() === $orphans,
	'Every chrome control is bound to a registered setting'
	. ( $orphans ? ' — dangling: ' . implode( ', ', $orphans ) : '' )
);
check( ! $wrong_sanitizer, 'No setting is registered under an unrelated id' );

$live = 0;
$refresh = 0;
foreach ( $manager->settings as $setting ) {
	if ( 'postMessage' === $setting->transport ) {
		++$live;
	} else {
		++$refresh;
	}
}
check( $live > 15, 'Most chrome settings preview without a reload (' . $live . ' postMessage)' );
check( $refresh > 0, 'Structural settings use refresh plus selective partials (' . $refresh . ')' );

$schema = Chrome_Builder::preview_schema();
$untracked = array();
foreach ( $manager->settings as $id => $setting ) {
	$is_partial = false;
	foreach ( $manager->partials as $partial ) {
		if ( in_array( $id, (array) ( $partial['settings'] ?? array() ), true ) ) {
			$is_partial = true;
			break;
		}
	}
	if ( 'postMessage' === $setting->transport && ! isset( $schema['tokens'][ $id ] ) && ! isset( $schema['colors'][ $id ] ) && ! isset( $schema['switches'][ $id ] ) ) {
		$untracked[] = $id;
	}
}
check( ! $untracked, 'Every postMessage setting is in the preview schema' . ( $untracked ? ' (' . implode( ', ', $untracked ) . ')' : '' ) );

$partial_missing = array();
foreach ( $manager->partials as $id => $partial ) {
	if ( empty( $partial['selector'] ) || empty( $partial['render_callback'] ) ) {
		$partial_missing[] = $id;
		continue;
	}
	if ( ! is_callable( $partial['render_callback'] ) ) {
		$partial_missing[] = $id;
	}
}
check( ! $partial_missing, 'Each partial has a selector and a callable renderer' );

check(
	isset( $manager->controls[ Chrome_Builder::HEADER_ORDER ], $manager->controls[ Chrome_Builder::FOOTER_BLOCKS ] )
	&& $manager->controls[ Chrome_Builder::HEADER_ORDER ] instanceof Chrome_Order_Control,
	'Both regions get a real builder control'
);

$control = $manager->controls[ Chrome_Builder::HEADER_ORDER ];
$control->to_json();
$control_json = json_decode( wp_json_encode( $control->json ), true );
check(
	is_array( $control_json ) && isset( $control_json['flavorDefaultOrder'], $control_json['flavorDefaults'] ),
	'The control hands the script its order and defaults'
);
check(
	is_array( $control_json ) && count( $control_json['flavorItems'] ?? array() ) === count( $header ),
	'The control lists one row per registered element'
);
check(
	is_array( $control_json ) && count( (array) ( $control_json['settings'] ?? array() ) ) === count( $header ) + 1,
	'Visibility settings are attached to the control, so they publish with it'
);

$html = capture( static function () use ( $control ) { $control->render_content(); } );
check(
	substr_count( $html, 'data-flavor-key=' ) === count( $header ) && substr_count( $html, 'data-flavor-setting=' ) === count( $header ),
	'The rendered list has one row and one switch per element'
);
check( strpos( $html, 'data-flavor-reset' ) !== false, 'The list ships a reset button' );
check( strpos( $html, 'aria-label=' ) !== false, 'Every move button is labelled' );

$orphan = array();
foreach ( $manager->settings as $id => $setting ) {
	if ( 0 === strpos( $id, Chrome_Builder::HEADER_SHOW_PREFIX ) ) {
		$key = substr( $id, strlen( Chrome_Builder::HEADER_SHOW_PREFIX ) );
		if ( false === strpos( $html, 'flavor_header_show_' . $key ) && null === $manager->get_control( $id ) ) {
			$orphan[] = $id;
		}
	}
}
check( ! $orphan, 'No header element is registered without a switch in the UI' . ( $orphan ? ' (' . implode( ', ', $orphan ) . ')' : '' ) );

/*
 * The validators cannot ride on add_setting(): core registers that callback for
 * one argument. They are bound to customize_validate_<id> instead — assert the
 * wiring, not just the maths.
 */
$validator_hooks = array(
	Chrome_Builder::HEADER_ORDER,
	Chrome_Builder::FOOTER_BLOCKS,
	'flavor_header_align',
	'flavor_footer_columns',
);

$loose = array();

foreach ( $validator_hooks as $id ) {
	$hooks = $GLOBALS['_flavor_test_filters'][ 'customize_validate_' . $id ] ?? array();

	if ( 1 !== count( $hooks ) || 3 !== $hooks[0][2] ) {
		$loose[] = $id;
	}
}

check(
	array() === $loose,
	'Every validator is bound to its own setting with the full argument list'
	. ( $loose ? ' (' . implode( ', ', $loose ) . ')' : '' )
);

echo "\n--- 8. Stored values are repaired, not trusted ---\n";

$setting = $manager->settings[ Chrome_Builder::HEADER_ORDER ];
check(
	$setting->sanitize( '<script>alert(1)</script>,phone,phone' ) === 'phone,scheme,wishlist,order,mobile_nav,search,cart,account',
	'Markup in an order value cannot survive sanitization'
);
check( $setting->validate( 'phone,bogus' )->has_errors(), 'A value that needs repairing is reported to the owner' );
check( ! $setting->validate( implode( ',', array_keys( $header ) ) )->has_errors(), 'The canonical order validates cleanly' );

$nav_size = $manager->settings['flavor_header_nav_size'];
check( '16px' === $nav_size->sanitize( '16px' ), 'A listed size is kept' );
check( '' === $nav_size->sanitize( '40vw' ), 'An unlisted size falls back to the control default' );
check( $nav_size->validate( '40vw' )->has_errors(), '…and the owner is told' );

$columns = $manager->settings['flavor_footer_columns'];
check( 3 === $columns->sanitize( '3' ), 'Column counts are stored as integers' );
check( 0 === $columns->sanitize( '9' ), 'An out-of-range column count returns to auto' );

$colour = $manager->settings['flavor_header_bg'];
check( '#0a0a0a' === $colour->sanitize( '#0a0a0a' ), 'A valid colour is stored' );
check( '' === $colour->sanitize( 'red' ), 'A named colour is rejected' );

echo "\n--- 9. Assets load only where they are needed ---\n";

$enqueue_source = (string) file_get_contents( FLAVOR_DIR . '/inc/class-chrome-builder.php' );
check(
	strpos( $enqueue_source, "if ( is_admin() ) {\n\t\t\treturn;" ) !== false,
	'The chrome stylesheet is never queued in the admin'
);
check(
	strpos( $enqueue_source, "add_action( 'customize_controls_enqueue_scripts'" ) !== false && strpos( $enqueue_source, "add_action( 'customize_preview_init'" ) !== false,
	'Builder scripts are bound to the Customizer only'
);
if ( preg_match( '/public static function assets\(\): void \{(.*?)\n\t\}/s', $enqueue_source, $matches ) ) {
	$public_body = $matches[1];
} else {
	$public_body = 'unreadable';
}

check(
	! ( strpos( $public_body, 'unreadable' ) === 0 )
		&& strpos( $public_body, 'flavor-customizer-chrome' ) === false
		&& strpos( $public_body, 'customize-controls' ) === false,
	'No Customizer-only asset is queued on a public page view'
);

foreach ( array( 'controls_assets', 'preview_assets' ) as $method ) {
	preg_match( '/public static function ' . $method . '\(\): void \{(.*?)\n\t\}/s', $enqueue_source, $matches );
	$body = isset( $matches[1] ) ? $matches[1] : '';

	check(
		preg_match( '/\(string\) filemtime\(/', $body ) === 1,
		strpos( $body, 'wp_enqueue_style' ) !== false || strpos( $body, 'wp_enqueue_script' ) !== false
			? 'Customizer ' . str_replace( '_', ' ', $method ) . ' is versioned by filemtime'
			: 'Customizer ' . str_replace( '_', ' ', $method ) . ' queues nothing'
	);
}

$controls_js = (string) file_get_contents( FLAVOR_DIR . '/assets/js/customizer-chrome.js' );
$preview_js = (string) file_get_contents( FLAVOR_DIR . '/assets/js/customizer-chrome-preview.js' );
check( strpos( $controls_js, 'sortable' ) !== false && strpos( $controls_js, 'confirm' ) !== false, 'Drag-and-drop and the reset confirmation exist in the controls script' );
check( strpos( $controls_js, 'aria-live' ) !== false, 'The controls script announces moves to assistive tech' );
check( strpos( $preview_js, 'classList' ) !== false && strpos( $preview_js, 'setProperty' ) !== false, 'The preview script writes the same two things PHP writes' );
check( strpos( $preview_js, 'removeProperty' ) !== false, '…and clears them when the owner reverts to the default' );
foreach ( array( 'controls' => $controls_js, 'preview' => $preview_js ) as $where => $script ) {
	check( (bool) preg_match( "/'use strict'/", $script ), 'The ' . $where . ' script is strict mode' );
}

echo "\n--- 10. Migration and snapshot ---\n";

seed( array( 'flavor_header_sticky' => 'yes' ) );
$GLOBALS['_mock_options'][ Chrome_Builder::VERSION_OPTION ] = 0;
Chrome_Builder::migrate();
check(
	Chrome_Builder::order_to_string( get_theme_mod( Chrome_Builder::HEADER_ORDER, '' ), $header ) === (string) get_theme_mod( Chrome_Builder::HEADER_ORDER ),
	'Migration stores a complete, valid header order'
);
check(
	'yes' === get_theme_mod( 'flavor_header_sticky' ),
	'A legacy truthy sticky value is normalised, not lost'
);
check(
	1 === (int) ( $GLOBALS['_mock_options'][ Chrome_Builder::VERSION_OPTION ] ?? 0 ),
	'The schema version is recorded so migration runs once'
);

seed( array( 'flavor_header_min_height' => '96px', 'flavor_footer_columns' => 3 ) );
$snapshot = Chrome_Builder::snapshot();
check( isset( $snapshot['flavor_header_min_height'] ), 'The snapshot carries the configured chrome' );
check( ! isset( $snapshot['flavor_phone'] ), 'The snapshot ignores settings it does not own' );

seed( array() );
Chrome_Builder::restore( $snapshot );
check(
	'96px' === get_theme_mod( 'flavor_header_min_height' ) && 3 === (int) get_theme_mod( 'flavor_footer_columns' ),
	'Restoring a snapshot brings the chrome back'
);

$sidebars = capture( static function () { Chrome_Builder::sidebars(); } );
check(
	isset( $GLOBALS['_mock_registered_sidebars']['footer-2'], $GLOBALS['_mock_registered_sidebars']['footer-3'] ) && '' === $sidebars,
	'One widget area per extra footer column is registered'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
