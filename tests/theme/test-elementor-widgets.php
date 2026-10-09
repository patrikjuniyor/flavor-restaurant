<?php
/**
 * Elementor widget contract tests.
 *
 * A widget that exists on disk but is never registered is invisible, and a
 * widget whose render() leaks an unescaped value is a stored-XSS vector on
 * every page that uses it. Neither failure is caught by `php -l`.
 *
 * These tests boot the widgets against minimal Elementor stand-ins and assert
 * the contracts that matter: registration, branding, well-formed output and
 * escaping.
 *
 *   php tests/theme/test-elementor-widgets.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/elementor-stubs.php';

require_once FLAVOR_DIR . '/inc/class-elementor.php';
require_once FLAVOR_DIR . '/elementor/class-widget-base.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-hero-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-about-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-gallery-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-testimonials-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-branch-info-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-menu-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-reservation-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-hours-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-order-cta-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-offers-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-chefs-widget.php';
require_once FLAVOR_DIR . '/elementor/widgets/class-cart-widget.php';

use Flavor\Elementor\Widget_Base;

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
 * Check that tags open and close in the right order.
 *
 * DOMDocument cannot do this job: its HTML parser uses the HTML 4 vocabulary
 * and reports "Tag section invalid" for every perfectly good HTML5 section,
 * which would fail every widget in the suite.
 *
 * @param string $html Markup.
 * @return string|null Error description, or null when it is balanced.
 */
$markup_error = static function ( string $html ): ?string {
	if ( '' === trim( $html ) ) {
		return 'empty output';
	}

	// Elements that never have a closing tag.
	$void = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr' );

	$stack = array();

	if ( preg_match_all( '/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)([^>]*?)(\/?)>/s', $html, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $tag ) {
			$is_closing  = '/' === $tag[1];
			$name        = strtolower( $tag[2] );
			$is_self_closed = '/' === $tag[4];

			if ( in_array( $name, $void, true ) || $is_self_closed ) {
				continue;
			}

			if ( $is_closing ) {
				$open = array_pop( $stack );
				if ( $open !== $name ) {
					return sprintf( 'closing </%s> does not match open <%s>', $name, $open ?: 'nothing' );
				}
				continue;
			}

			$stack[] = $name;
		}
	}

	if ( ! empty( $stack ) ) {
		return 'unclosed tags: ' . implode( ', ', $stack );
	}

	return null;
};

echo "=== Flavor Elementor Widget Contract Tests ===\n\n";

$files   = \Flavor\Elementor::widget_files();
$classes = \Flavor\Elementor::widget_classes();

echo "--- 1. Registration lists agree with the files on disk ---\n";

check( count( $files ) === count( $classes ), 'widget_files() and widget_classes() have the same length (' . count( $files ) . ')' );
check( count( $files ) >= 12, 'at least twelve widgets are registered (' . count( $files ) . ')' );

foreach ( $files as $file ) {
	check( is_file( FLAVOR_DIR . '/elementor/widgets/' . $file ), "{$file} exists" );
}

foreach ( $classes as $class ) {
	check( class_exists( '\\Flavor\\Elementor\\' . $class ), "class {$class} is loadable" );
}

// The bug this catches: a widget file added to disk but never added to the
// registration list, which is invisible until someone wonders why it is missing.
$on_disk = glob( FLAVOR_DIR . '/elementor/widgets/class-*-widget.php' );
check(
	count( $on_disk ) === count( $files ),
	'every widget file on disk is registered (' . count( $on_disk ) . ' on disk, ' . count( $files ) . ' registered)'
);

echo "\n--- 2. Every widget identifies itself ---\n";

$widgets = array();
foreach ( $classes as $class ) {
	$fqcn = '\\Flavor\\Elementor\\' . $class;
	/** @var Widget_Base $widget */
	$widget    = new $fqcn();
	$widgets[] = $widget;

	$name = (string) $widget->get_name();
	check( '' !== $name, "{$class}::get_name() is set ({$name})" );
	check( str_starts_with( $name, 'flavor_' ), "{$name} is namespaced with flavor_" );
	check( '' !== (string) $widget->get_title(), "{$class} has a title" );
	check( '' !== (string) $widget->get_icon(), "{$class} has an icon" );
}

echo "\n--- 3. Widgets live in the branded category ---\n";

foreach ( $widgets as $widget ) {
	check(
		array( 'flavor' ) === $widget->get_categories(),
		(string) $widget->get_name() . ' is filed under the "flavor" category'
	);
}

$manager = new \Elementor\Elements_Manager();
Widget_Base::register_category( $manager );
check( isset( $manager->get_categories()['flavor'] ), 'register_category() adds the "flavor" category' );

// Elementor throws if a category is added twice; re-running the hook must be safe.
$threw = false;
try {
	Widget_Base::register_category( $manager );
} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
	$threw = true;
}
check( ! $threw, 'registering the category twice does not throw' );

echo "\n--- 4. Every widget renders well-formed markup ---\n";

foreach ( $widgets as $widget ) {
	$widget->test_register_controls();
	$widget->set_test_settings( array() );
	$html  = $widget->test_render();
	$error = $markup_error( $html );
	check(
		null === $error,
		(string) $widget->get_name() . ' renders with empty settings' . ( $error ? " — {$error}" : '' )
	);
}

echo "\n--- 5. Hostile settings are escaped, not executed ---\n";

$payload = '"><script>alert(1)</script>';

$cases = array(
	'Menu_Widget'           => array( 'title' => $payload ),
	'Reservation_Widget'    => array( 'title' => $payload, 'text' => $payload, 'button_text' => $payload ),
	'Hours_Widget'          => array( 'title' => $payload, 'rows' => array( array( 'day' => $payload, 'hours' => $payload ) ) ),
	'Order_CTA_Widget'      => array( 'title' => $payload, 'text' => $payload ),
	'Offers_Widget'         => array( 'title' => $payload, 'code' => $payload, 'button_text' => $payload ),
	'Chefs_Widget'          => array( 'title' => $payload, 'members' => array( array( 'name' => $payload, 'role' => $payload, 'bio' => $payload ) ) ),
	'Cart_Widget'           => array( 'title' => $payload, 'empty_text' => $payload ),
	'Hero_Widget'           => array( 'title' => $payload, 'text' => $payload, 'cta' => $payload ),
	'Testimonials_Widget'   => array( 'name' => $payload ),
);

foreach ( $cases as $class => $settings ) {
	$fqcn   = '\\Flavor\\Elementor\\' . $class;
	$widget = new $fqcn();
	$widget->test_register_controls();
	$widget->set_test_settings( $settings );
	$html = $widget->test_render();

	check(
		false === strpos( $html, '<script>alert(1)</script>' ),
		"{$class} does not emit a live <script> tag"
	);
	check(
		false === strpos( $html, '"onerror' ) && false === strpos( $html, '" on' ),
		"{$class} does not break out of an attribute"
	);
}

echo "\n--- 6. Widgets with no WooCommerce degrade honestly ---\n";

// WooCommerce is absent in this environment on purpose: the widgets must render
// a readable message rather than a broken grid or a fatal.
$menu = new \Flavor\Elementor\Menu_Widget();
$menu->set_test_settings( array( 'title' => 'منو' ) );
$out = $menu->test_render();
check( false !== strpos( $out, 'flavor-section' ), 'Menu widget still renders its section shell' );
check( null === $markup_error( $out ), 'Menu widget output is well formed without WooCommerce' );

$cart = new \Flavor\Elementor\Cart_Widget();
$cart->set_test_settings( array( 'title' => 'سبد' ) );
$out = $cart->test_render();
check( false !== strpos( $out, 'flavor-section' ), 'Cart widget still renders its section shell' );
check( null === $markup_error( $out ), 'Cart widget output is well formed without WooCommerce' );

echo "\n--- 7. The stylesheet the widgets depend on exists ---\n";

check( is_file( FLAVOR_DIR . '/assets/css/elementor-widgets.css' ), 'assets/css/elementor-widgets.css exists' );
check(
	(bool) preg_match( '/--flavor-primary/', (string) file_get_contents( FLAVOR_DIR . '/assets/css/elementor-widgets.css' ) ),
	'widget stylesheet is built from the skin tokens, not hard-coded colours'
);
check(
	(bool) preg_match( '/prefers-reduced-motion/', (string) file_get_contents( FLAVOR_DIR . '/assets/css/elementor-widgets.css' ) ),
	'widget stylesheet respects prefers-reduced-motion'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
