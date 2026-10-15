<?php
/**
 * Smoke test the Elementor integration on a real WordPress install.
 *
 * Offline widget stand-ins are useful for escaping and output contracts, but
 * they cannot prove that Elementor accepts a control declaration or renders a
 * saved document. This test runs against the pinned, real Elementor plugin in
 * the `real-wordpress` CI job.
 *
 *   php tests/integration/real-elementor-smoke.php --wp=/path/to/wordpress
 *
 * @package Flavor
 */

declare( strict_types=1 );

$options = getopt( '', array( 'wp:' ) );
$wp_root = rtrim( (string) ( $options['wp'] ?? '' ), '/' );

if ( '' === $wp_root || ! is_file( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/integration/real-elementor-smoke.php --wp=/path/to/wordpress\n" );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$passed = 0;
$failed = 0;
$runtime_errors = array();
$smoke_created_posts = array();

/**
 * Record a test assertion.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
function elementor_smoke_check( bool $condition, string $message ): void {
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
 * Create a deterministic seven-character Elementor node id.
 *
 * @param string $seed Unique seed.
 * @return string
 */
function elementor_smoke_id( string $seed ): string {
	return substr( md5( $seed ), 0, 7 );
}

/**
 * Wrap a widget in the legacy section/column structure used by Elementor page templates.
 *
 * @param string               $type     Widget name.
 * @param array<string, mixed> $settings Widget settings.
 * @param string               $seed     Unique seed.
 * @return array<string, mixed>
 */
function elementor_smoke_section( string $type, array $settings, string $seed ): array {
	return array(
		'id'       => elementor_smoke_id( $seed . ':section' ),
		'elType'   => 'section',
		'settings' => array(),
		'elements' => array(
			array(
				'id'       => elementor_smoke_id( $seed . ':column' ),
				'elType'   => 'column',
				'settings' => array(
					'_column_size' => 100,
					'_inline_size' => null,
				),
				'elements' => array(
					array(
						'id'         => elementor_smoke_id( $seed . ':widget' ),
						'elType'     => 'widget',
						'widgetType' => $type,
						'settings'   => $settings,
						'elements'   => array(),
					),
				),
				'isInner'  => false,
			)
		),
		'isInner'  => false,
	);
}

/**
 * Save an Elementor page through Elementor's document API.
 *
 * @param string                       $title    Page title.
 * @param array<int, array<string,mixed>> $elements Document elements.
 * @return int|WP_Error
 */
function elementor_smoke_create_page( string $title, array $elements ) {
	$post_id = wp_insert_post(
		array(
			'post_title'  => $title,
			'post_type'   => 'page',
			'post_status' => 'publish',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	global $smoke_created_posts;
	$smoke_created_posts[] = (int) $post_id;

	$document = \Elementor\Plugin::instance()->documents->get( (int) $post_id );
	if ( ! $document ) {
		return new WP_Error( 'elementor_document_missing', 'Elementor did not create a page document.' );
	}

	if ( ! $document->save( array( 'elements' => $elements, 'settings' => array() ) ) ) {
		return new WP_Error( 'elementor_document_save_failed', 'Elementor refused to save the smoke-test document.' );
	}

	$document->set_is_built_with_elementor( true );
	return (int) $post_id;
}

set_error_handler(
	static function ( int $severity, string $message, string $file = '', int $line = 0 ): bool {
		global $runtime_errors;
		$fatal_levels = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
		if ( 0 !== ( $severity & $fatal_levels ) ) {
			$runtime_errors[] = trim( $message ) . ' in ' . basename( $file ) . ':' . $line;
			return false;
		}
		// R3-03: a widget that only warns while rendering an empty state still
		// ships a fatal on a customer site with WP_DEBUG off. Capture any
		// diagnostic raised inside the theme's own render path; plugin and
		// core noise stays outside our contract.
		$in_theme = preg_match(
			'#/themes/flavor/(elementor/widgets|template-parts|inc)/#',
			str_replace( '\\', '/', $file )
		);
		$levels   = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED;
		if ( $in_theme && 0 !== ( $severity & $levels ) ) {
			$runtime_errors[] = 'PHP diagnostic: ' . trim( $message ) . ' in ' . basename( $file ) . ':' . $line;
		}
		return false;
	}
);

echo "=== Flavor Real Elementor Smoke Test ===\n\n";

$active = is_plugin_active( 'elementor/elementor.php' );
elementor_smoke_check( $active, 'Elementor plugin is active' );
elementor_smoke_check( defined( 'ELEMENTOR_VERSION' ), 'Elementor exposes its version constant' );

if ( ! $active || ! defined( 'ELEMENTOR_VERSION' ) || ! class_exists( '\\Elementor\\Plugin' ) ) {
	echo "\nResults: {$passed} Passed, {$failed} Failed\n";
	exit( 1 );
}

elementor_smoke_check( '4.3.4' === ELEMENTOR_VERSION, 'the integration suite is pinned to Elementor 4.3.4 (' . ELEMENTOR_VERSION . ')' );
elementor_smoke_check( 'flavor' === get_option( 'stylesheet' ), 'Flavor is the active theme' );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( ! empty( $admins ) ? (int) $admins[0] : 1 );

$elementor = \Elementor\Plugin::instance();
$manager   = $elementor->widgets_manager;
$types     = $manager->get_widget_types();

if ( class_exists( '\\Flavor\\Elementor' ) ) {
	$expected_classes = \Flavor\Elementor::widget_classes();
	elementor_smoke_check( count( $expected_classes ) >= 17, 'Flavor declares all widgets dynamically (' . count( $expected_classes ) . ' registered, >= 17)' );
} else {
	$expected_classes = array();
	elementor_smoke_check( false, 'Flavor Elementor integration class is loaded' );
}

echo "\n--- Actual Elementor registration and controls ---\n";

$expected_names = array();
$widget_instances = array();
$required_controls = array(
	'flavor_style_primary_color',
	'flavor_style_accent_color',
	'flavor_style_text_color',
	'flavor_style_muted_color',
	'flavor_style_surface_color',
	'flavor_style_line_color',
	'flavor_style_section_background',
	'flavor_style_section_padding',
	'flavor_style_content_width',
	'flavor_style_grid_columns',
	'flavor_style_alignment',
	'flavor_style_heading_color',
	'flavor_style_heading_typography_typography',
	'flavor_style_body_typography_typography',
	'flavor_style_card_background',
	'flavor_style_card_border',
	'flavor_style_card_radius',
	'flavor_style_button_background',
	'flavor_style_button_text',
);

foreach ( $expected_classes as $class ) {
	$fqcn = '\\Flavor\\Elementor\\' . $class;
	if ( ! class_exists( $fqcn ) ) {
		elementor_smoke_check( false, "{$class} class is loaded by the active theme" );
		continue;
	}

	$probe = new $fqcn();
	$name  = (string) $probe->get_name();
	$expected_names[] = $name;
	$widget = $manager->get_widget_types( $name );

	elementor_smoke_check( is_object( $widget ) && is_a( $widget, $fqcn ), "{$name} is registered in Elementor's actual Widgets_Manager" );
	if ( ! is_object( $widget ) ) {
		continue;
	}

	$widget_instances[ $name ] = $widget;
	$stack    = $widget->get_stack( false );
	$controls = array_merge( $stack['controls'] ?? array(), $stack['style_controls'] ?? array() );
	$missing  = array_values( array_diff( $required_controls, array_keys( $controls ) ) );
	elementor_smoke_check(
		empty( $missing ),
		"{$name} registers all Flavor Style controls" . ( $missing ? ' — missing ' . implode( ', ', $missing ) : '' )
	);

	$style_section = $stack['controls']['flavor_visual_style'] ?? array();
	$style_control = $stack['style_controls']['flavor_style_primary_color'] ?? array();
	elementor_smoke_check(
		'section' === ( $style_section['type'] ?? '' ) && 'flavor_visual_style' === ( $style_control['section'] ?? '' ),
		"{$name} groups its compiled design controls in the Flavor Style section"
	);
}

elementor_smoke_check(
	count( $expected_names ) === count( array_unique( $expected_names ) ),
	'each Flavor widget has a unique Elementor name'
);

$categories = $elementor->elements_manager->get_categories();
elementor_smoke_check( isset( $categories['flavor'] ), 'the branded Flavor widget category is registered' );

/**
 * Render one Elementor document and turn runtime exceptions into assertions.
 *
 * @param int $post_id Page id.
 * @return string
 */
function elementor_smoke_render_page( int $post_id ): string {
	try {
		return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $post_id, true );
	} catch ( Throwable $error ) {
		return '__THROWN__:' . $error->getMessage();
	}
}

/**
 * Create one small, published WooCommerce product for the data-backed menu widget.
 *
 * @return int
 */
function elementor_smoke_create_product(): int {
	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'Flavor Elementor CI Dish' );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_regular_price( '12.50' );
	$product->set_short_description( 'A test product rendered through WooCommerce.' );
	$product_id = (int) $product->save();
	if ( $product_id > 0 ) {
		global $smoke_created_posts;
		$smoke_created_posts[] = $product_id;
	}

	return $product_id;
}

echo "\n--- Real Elementor document and template rendering ---\n";

$product_id = elementor_smoke_create_product();
elementor_smoke_check( $product_id > 0, 'a published WooCommerce product is available to the menu widget' );

$template_files = glob( get_template_directory() . '/elementor/templates/*.json' ) ?: array();
elementor_smoke_check( count( $template_files ) >= 3, 'the three shipped page templates are present' );

foreach ( $template_files as $template_file ) {
	$template = json_decode( (string) file_get_contents( $template_file ), true );
	$slug     = basename( $template_file, '.json' );
	if ( ! is_array( $template ) || empty( $template['content'] ) || ! is_array( $template['content'] ) ) {
		elementor_smoke_check( false, "{$slug} is valid Elementor template JSON" );
		continue;
	}

	$post_id = elementor_smoke_create_page( 'Flavor Elementor CI — ' . $slug, $template['content'] );
	if ( is_wp_error( $post_id ) ) {
		elementor_smoke_check( false, "{$slug} imports into a real Elementor document — " . $post_id->get_error_message() );
		continue;
	}

	elementor_smoke_check( \Flavor\Elementor::has_builder_content( $post_id ), "{$slug} is recognized as Elementor-built page content" );
	$html = elementor_smoke_render_page( $post_id );
	$threw = 0 === strpos( $html, '__THROWN__:' );
	elementor_smoke_check( ! $threw, "{$slug} renders through Elementor Frontend" . ( $threw ? ' — ' . substr( $html, 11 ) : '' ) );
	elementor_smoke_check( strlen( $html ) > 500, "{$slug} produces page markup (" . strlen( $html ) . ' bytes)' );
	elementor_smoke_check( false === strpos( $html, 'Elementor widget error' ), "{$slug} has no Elementor widget error placeholder" );

	if ( 'flavor-menu' === $slug && $product_id > 0 ) {
		elementor_smoke_check( false !== strpos( $html, 'Flavor Elementor CI Dish' ), 'the menu template displays a real WooCommerce product' );
	}
}

echo "\n--- Every widget executes inside a real Elementor page ---\n";

$widget_settings = array(
	'flavor_hero' => array( 'title' => 'Flavor CI Hero', 'text' => 'A real Elementor render.' ),
	'flavor_about' => array( 'title' => 'Flavor CI Story', 'text' => '<p>About content from Elementor.</p>' ),
	'flavor_gallery' => array( 'gallery' => array() ),
	'flavor_testimonials' => array( 'items' => array( array( 'name' => 'CI Guest', 'text' => 'A verified review.' ) ) ),
	'flavor_menu' => array( 'title' => 'Flavor CI Menu', 'count' => 4, 'orderby' => 'date', 'show_price' => 'yes' ),
	'flavor_features' => array(
		'title' => 'Flavor CI Features',
		'items' => array( array( 'title' => 'Fresh', 'text' => 'Prepared every day.' ) ),
		'flavor_style_primary_color' => '#2d6a4f',
		'flavor_style_grid_columns' => '2',
		'flavor_style_section_padding' => array( 'top' => '32', 'right' => '24', 'bottom' => '32', 'left' => '24', 'unit' => 'px', 'isLinked' => false ),
	),
	'flavor_branch_info' => array( 'title' => 'Flavor CI Branch' ),
	'flavor_reservation' => array( 'title' => 'Reserve a table', 'button_text' => 'Reserve' ),
	'flavor_hours' => array( 'title' => 'Hours' ),
	'flavor_order_cta' => array( 'title' => 'Order now' ),
	'flavor_offers' => array( 'title' => 'Special offer', 'code' => 'CI15' ),
	'flavor_chefs' => array( 'title' => 'Our chefs' ),
	'flavor_cart' => array( 'title' => 'Cart' ),
	'flavor_stats' => array(),
	'flavor_process' => array( 'title' => 'How it works' ),
	'flavor_faq' => array( 'title' => 'FAQ' ),
	'flavor_price_list' => array( 'title' => 'Drinks' ),
);

$all_widgets = array();
foreach ( $expected_names as $name ) {
	$all_widgets[] = elementor_smoke_section(
		$name,
		$widget_settings[ $name ] ?? array(),
		'all-widgets:' . $name
	);
}

$all_page_id = elementor_smoke_create_page( 'Flavor Elementor CI — all widgets', $all_widgets );
if ( is_wp_error( $all_page_id ) ) {
	elementor_smoke_check( false, 'all-widget regression page saves — ' . $all_page_id->get_error_message() );
} else {
	$all_html = elementor_smoke_render_page( $all_page_id );
	$threw    = 0 === strpos( $all_html, '__THROWN__:' );
	elementor_smoke_check( ! $threw, 'all dynamically registered widgets render together without a PHP exception (' . count( $expected_names ) . ' widgets)' . ( $threw ? ' — ' . substr( $all_html, 11 ) : '' ) );
	elementor_smoke_check( strlen( $all_html ) > 1500, 'the all-widget page produces substantial markup (' . strlen( $all_html ) . ' bytes)' );
elementor_smoke_check( false !== strpos( $all_html, 'flavor-widget-note' ), 'the all-widget page shows the empty gallery note, not a hole' );
	elementor_smoke_check( false !== strpos( $all_html, 'Flavor CI Features' ), 'the all-widget page renders the custom feature content' );
	elementor_smoke_check( false !== strpos( $all_html, 'Flavor Elementor CI Dish' ), 'the all-widget page renders the WooCommerce-backed menu product' );
	$compiled_css = strtolower( preg_replace( '/\s+/', '', $all_html ) );
	elementor_smoke_check( false !== strpos( $compiled_css, '--flavor-primary:#2d6a4f;' ), 'the Style tab brand-color control compiles to page CSS' );
	elementor_smoke_check( false !== strpos( $compiled_css, 'grid-template-columns:repeat(2,minmax(0,1fr))' ), 'the responsive grid control compiles to page CSS' );
	elementor_smoke_check( false !== strpos( $compiled_css, 'padding:32px24px32px24px' ), 'the responsive section-spacing control compiles to page CSS' );
	elementor_smoke_check( false === strpos( $all_html, 'Elementor widget error' ), 'the all-widget page has no Elementor error placeholder' );
}

echo "\n--- Data-backed widgets: filled and empty states (R3-03) ---";

$demo_uri   = get_template_directory_uri() . '/demos/modern-restaurant/category-main.jpg';
$filled_html = elementor_smoke_render_page(
	(int) elementor_smoke_create_page(
		'Flavor Elementor CI — data states filled',
		array(
			elementor_smoke_section( 'flavor_gallery', array( 'gallery' => array( array( 'url' => $demo_uri ) ) ), 'data:gallery-filled' ),
			elementor_smoke_section(
				'flavor_offers',
				array(
					'title' => 'Flavor CI Offer',
					'text'  => 'Filled banner copy rendered by real Elementor.',
					'code'  => 'CI15',
				),
				'data:offers-filled'
			),
		)
	)
);
elementor_smoke_check( false === strpos( $filled_html, '__THROWN__:' ), 'filled gallery/offers page renders' . ( 0 === strpos( $filled_html, '__THROWN__:' ) ? ' — ' . substr( $filled_html, 11 ) : '' ) );
elementor_smoke_check( false !== strpos( $filled_html, 'flavor-gallery__item' ), 'a filled gallery renders real tiles, not a note' );
elementor_smoke_check( false !== strpos( $filled_html, 'Flavor CI Offer' ) && false !== strpos( $filled_html, 'flavor-offer-banner' ), 'filled offers renders the banner with its content' );
elementor_smoke_check( false === strpos( $filled_html, 'flavor-widget-note' ), 'filled states contain no empty-state notes' );

if ( $product_id > 0 ) {
	wp_delete_post( $product_id, true );
}
$empty_html = elementor_smoke_render_page(
	(int) elementor_smoke_create_page(
		'Flavor Elementor CI — data states empty',
		array(
			elementor_smoke_section( 'flavor_menu', array( 'title' => 'Flavor CI Empty Menu' ), 'data:menu-empty' ),
			elementor_smoke_section( 'flavor_gallery', array( 'gallery' => array() ), 'data:gallery-empty' ),
			elementor_smoke_section( 'flavor_offers', array( 'title' => '', 'text' => '', 'code' => '' ), 'data:offers-empty' ),
		)
	)
);
elementor_smoke_check( false === strpos( $empty_html, '__THROWN__:' ), 'empty-state page renders without an exception' );
$note_count = substr_count( $empty_html, 'flavor-widget-note' );
elementor_smoke_check( 3 === $note_count, 'menu, gallery and offers each answer an empty state with a Persian note (' . $note_count . '/3)' );
elementor_smoke_check( false === strpos( $empty_html, 'Flavor Elementor CI Dish' ), 'the deleted product is gone from the menu widget' );
elementor_smoke_check(
	false === strpos( $empty_html, 'flavor-gallery__grid' ) && false === strpos( $empty_html, 'flavor-offer-banner' ) && false === strpos( $empty_html, 'flavor-menu__grid' ),
	'empty states paint no grids and no banner shells'
);

// Keep repeatable local runs isolated and never leave test pages/products behind.
foreach ( array_reverse( $smoke_created_posts ) as $smoke_post_id ) {
	wp_delete_post( $smoke_post_id, true );
}

elementor_smoke_check( empty( $runtime_errors ), 'no fatal-level PHP runtime errors' . ( $runtime_errors ? ' — ' . $runtime_errors[0] : '' ) );

echo "\n=======================================================\n";
echo 'Elementor ' . ELEMENTOR_VERSION . "\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
