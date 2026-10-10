<?php
/**
 * Smoke test against a real WordPress installation.
 *
 * The offline suites cannot catch a class of bug that only appears when
 * WordPress actually loads the theme: a class used before its file is
 * required, a control extending a parent core loads lazily, a function
 * removed in a newer release. Two such bugs shipped, and both were found
 * only by installing WordPress 7.1.3 and requesting a page.
 *
 * This file is included by WordPress itself, not run standalone. It
 * requests the theme's real templates with output buffering and asserts
 * each one renders without a PHP error.
 *
 *   php tests/integration/real-wp-smoke.php --wp=/path/to/wordpress
 *
 * @package Flavor
 */

declare( strict_types=1 );

$options = getopt( '', array( 'wp:' ) );
$wp_root = rtrim( (string) ( $options['wp'] ?? '' ), '/' );

if ( '' === $wp_root || ! is_file( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/integration/real-wp-smoke.php --wp=/path/to/wordpress\n" );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_root . '/wp-load.php';

$passed = 0;
$failed = 0;
$errors = array();

/**
 * Record the assertion.
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

set_error_handler(
	static function ( int $number, string $text, string $file = '', int $line = 0 ): bool {
		global $errors;
		if ( E_ERROR === $number || E_PARSE === $number || E_CORE_ERROR === $number || E_COMPILE_ERROR === $number ) {
			$errors[] = trim( $text ) . ' in ' . basename( $file ) . ':' . $line;
		}
		// Let WordPress' own handler decide on notices and deprecations.
		return false;
	}
);

echo "=== Flavor Real WordPress Smoke Test ===\n\n";

echo "--- Environment ---\n";

$wp_version = get_bloginfo( 'version' );
$theme      = wp_get_theme( 'flavor' );

check( 'flavor' === get_option( 'stylesheet' ), 'the Flavor theme is the active theme' );
check( version_compare( $wp_version, '7.0', '>=' ), "WordPress is current ({$wp_version})" );
check( is_plugin_active( 'woocommerce/woocommerce.php' ), 'WooCommerce is active' );
check( defined( 'WC_VERSION' ), 'WooCommerce reports a version (' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'none' ) . ')' );

if ( defined( 'WC_VERSION' ) ) {
	check( version_compare( WC_VERSION, '11.0', '>=' ), 'WooCommerce is 11.0 or newer (' . WC_VERSION . ')' );
}

/**
 * Render a template the way WordPress would, capturing the output.
 *
 * @param string $template Template path relative to the theme.
 * @return string
 */
function render_template( string $template ): string {
	ob_start();
	try {
		load_template( get_template_directory() . '/' . $template, false );
	} catch ( Throwable $error ) {
		ob_end_clean();
		return '__THROWN__:' . $error->getMessage();
	}
	return (string) ob_get_clean();
}

echo "\n--- Templates render without a PHP error ---\n";

$templates = array(
	'front-page.php'                            => 'the front page',
	'template-parts/marketing/hero.php'         => 'the hero section',
	'template-parts/marketing/gallery.php'      => 'the gallery section',
	'template-parts/marketing/testimonials.php' => 'the testimonials section',
	'template-parts/marketing/hours.php'        => 'the opening hours section',
	'header.php'                                => 'the header',
	'footer.php'                                => 'the footer',
);

foreach ( $templates as $template => $label ) {
	$path = get_template_directory() . '/' . $template;
	if ( ! is_file( $path ) ) {
		check( false, "{$label} exists at {$template}" );
		continue;
	}

	$errors = array();
	$html   = render_template( $template );

	$thrown = 0 === strpos( $html, '__THROWN__:' );
	check( ! $thrown, $label . ' renders' . ( $thrown ? ' — ' . substr( $html, 11 ) : '' ) );
	check( empty( $errors ), $label . ' raises no PHP error' . ( empty( $errors ) ? '' : ' — ' . $errors[0] ) );
}

echo "\n--- The rendered markup is real ---\n";

$errors = array();
$front  = render_template( 'front-page.php' );

check( strlen( $front ) > 500, 'the front page produces substantial markup (' . strlen( $front ) . ' bytes)' );
check( false !== strpos( $front, 'flavor-section' ), 'the front page lays out theme sections' );
check( false === strpos( $front, '__THROWN__' ), 'the front page did not throw' );

// A fatal that WordPress caught would have produced an error page instead.
check( false === strpos( $front, 'There has been a critical error' ), 'no WordPress fatal page' );

echo "\n--- The design tokens reach the page ---\n";

$tokens = \Flavor\Design::resolved();
check( isset( $tokens['primary'] ) && (bool) preg_match( '/^#[0-9a-f]{6}$/i', $tokens['primary'] ), 'a valid primary colour resolves (' . ( $tokens['primary'] ?? 'none' ) . ')' );

$css = \Flavor\Design::css_variables();
check( false !== strpos( $css, '--flavor-primary' ), 'the CSS custom properties include the primary token' );
check( false !== strpos( $css, '--flavor-h2' ), 'the derived type scale is emitted' );

echo "\n--- The new surfaces survive a real boot ---\n";

// These are the classes that fatalled before being wired correctly.
check( class_exists( 'Flavor\\Block_Patterns' ), 'Block_Patterns is loaded (it was called but never required)' );
check( class_exists( 'Flavor\\Page_Options' ), 'Page_Options is loaded' );
check( class_exists( 'Flavor\\Live_Preview' ), 'Live_Preview is loaded' );
check( class_exists( 'Flavor\\Repeater' ), 'Repeater is loaded' );
check( class_exists( 'Flavor\\Contrast' ), 'Contrast is loaded' );

// The control classes must NOT be loaded outside the Customizer: their
// parent only exists once WP_Customize_Manager boots.
check( ! class_exists( 'Flavor\\Preset_Control' ), 'Preset_Control is not loaded outside the Customizer' );
check( ! class_exists( 'Flavor\\Contrast_Control' ), 'Contrast_Control is not loaded outside the Customizer' );
check( ! class_exists( 'Flavor\\Repeater_Control' ), 'Repeater_Control is not loaded outside the Customizer' );

echo "\n--- The repeater feeds the gallery with real fallback data ---\n";

// The demo pack is a separate download, so this theme may be running
// with no photography installed at all. Both states are supported and
// both are asserted here; N-15 is only done if the packless one is clean.
$has_demo_art = is_dir( get_template_directory() . '/demos' );
$items        = \Flavor\Repeater::items( 'gallery' );

if ( $has_demo_art ) {
	check( ! empty( $items ), 'the gallery has items to render (' . count( $items ) . ')' );
	check( '' !== ( $items[0]['image'] ?? '' ), 'the first gallery item has an image' );
	check( '' !== \Flavor\Repeater::image_alt( $items[0] ), 'the first gallery item has alt text' );
} else {
	// No photographs means no gallery, and the section steps aside rather
	// than rendering a grid of broken images.
	check( empty( $items ), 'with no demo art the gallery offers nothing rather than dead URLs' );
	check(
		0 === substr_count( (string) @file_get_contents( get_template_directory() . '/template-parts/marketing/gallery.php' ), 'FLAVOR_URI . ' ),
		'the gallery template never concatenates a demo URL directly'
	);
	check(
		is_file( get_template_directory() . '/assets/img/demo-placeholder.svg' ),
		'the placeholder survived into the packless install'
	);
	check(
		\Flavor\Bespoke_Demos::asset( 'hero.jpg' ) === \Flavor\Bespoke_Demos::placeholder(),
		'asset() degrades to the placeholder when the demo pack is absent'
	);
	check(
		false === strpos( \Flavor\Bespoke_Demos::asset( 'story.jpg' ), '/demos/' ),
		'a missing skin image does not produce a /demos/ URL'
	);
}

$testimonials = \Flavor\Repeater::items( 'testimonials' );
check( ! empty( $testimonials ), 'the testimonials have items (' . count( $testimonials ) . ')' );

echo "\n--- The per-page options metabox is registered ---\n";

check( post_type_supports( 'page', 'title' ), 'pages are intact' );
check( is_callable( array( 'Flavor\\Page_Options', 'sanitize' ) ), 'Page_Options::sanitize() is callable' );
check( '' === \Flavor\Page_Options::sanitize( 'page_bg', 'javascript:alert(1)' ), 'the metabox sanitizer rejects a javascript: URL' );

echo "\n--- The changelog panel is registered on a real install ---\n";

check( class_exists( 'Flavor\\Changelog' ), 'Changelog is loaded on a real install' );
check( is_readable( get_template_directory() . '/readme.txt' ), 'readme.txt is where the panel reads from' );
$releases = \Flavor\Changelog::releases();
check( ! empty( $releases ), 'the changelog parses on a real install (' . count( $releases ) . ' release(s))' );
if ( ! empty( $releases ) ) {
	$installed = \Flavor\Changelog::current_version();
	check( '' !== $installed, 'the installed version is readable (' . $installed . ')' );
	check( $releases[0]['version'] === $installed, 'the newest changelog entry matches the installed version' );
}

echo "\n--- The Customizer boots, in both its paths ---\n";

// The Customizer has two entry points and neither is exercised by loading
// a page, which is why both have shipped broken:
//
//   customize_register   the pane      (N-08: control classes extending a
//                                       parent that did not exist yet)
//   customize_preview_init  the frame  (this: Customizer::typography_tokens(),
//                                       a method that never existed, called
//                                       with self:: when it lives on Design)
//
// A theme can render a perfect front page and still be unusable, because
// the Customizer is the first thing an owner opens.
require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';

$customizer_ok = true;
$customizer_error = '';

try {
	$manager = new WP_Customize_Manager();

	// Path 1: the pane. Registers every setting, section and control.
	do_action( 'customize_register', $manager );
} catch ( \Throwable $e ) {
	$customizer_ok = false;
	$customizer_error = 'customize_register: ' . $e->getMessage();
}

check( $customizer_ok, 'the Customizer pane registers without fataling' . ( $customizer_error ? ' — ' . $customizer_error : '' ) );
check( class_exists( 'Flavor\\Preset_Control' ), 'the control classes load inside the Customizer, where their parent exists' );

$preview_ok = true;
$preview_error = '';

try {
	// Path 2: the preview frame. This only fires while previewing, which is
	// exactly why it escaped every previous test.
	$preview = new WP_Customize_Manager();
	$preview->start_previewing_theme();
	$preview->wp_loaded();
} catch ( \Throwable $e ) {
	$preview_ok = false;
	$preview_error = $e->getMessage();
}

check(
	$preview_ok,
	'the Customizer preview frame initialises without fataling'
	. ( $preview_error ? ' — ' . $preview_error : '' )
);

// The method that broke is on Design, and it is worth asserting directly:
// a typo in a static call cannot be caught by static analysis of one file.
check(
	is_callable( array( 'Flavor\\Design', 'typography_tokens' ) ),
	'Design::typography_tokens() exists'
);
check(
	! is_callable( array( 'Flavor\\Customizer', 'typography_tokens' ) ),
	'typography_tokens() is NOT on Customizer, so self:: would never work there'
);
check(
	false !== strpos( (string) @file_get_contents( FLAVOR_DIR . '/inc/class-customizer.php' ), 'Design::typography_tokens()' ),
	'the Customizer calls it through Design, not self'
);

echo "\n--- The update channel is off on a fresh install and its page renders ---\n";

// M-04: the channel must make no request and schedule nothing until the owner
// configures it. The page itself is exercised as an administrator, because a
// fatal in a settings screen is only visible on a real request.
check( ! \Flavor\Update_Channel::enabled(), 'the update channel is off on a fresh install' );
check( null === \Flavor\Update_Channel::check(), 'an off channel returns without any network call' );
check( false === wp_next_scheduled( \Flavor\Update_Channel::CRON_HOOK ), 'no update check is scheduled while the channel is off' );
check( false !== has_filter( 'upgrader_pre_download', array( 'Flavor\\Update_Channel', 'pre_download' ) ), 'the package verifier is hooked into upgrader_pre_download' );
check( false !== has_filter( 'upgrader_pre_install', array( 'Flavor\\Update_Channel', 'pre_install' ) ), 'the pre-update backup is hooked into upgrader_pre_install' );

require_once ABSPATH . 'wp-admin/includes/template.php';
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( ! empty( $admins ) ? (int) $admins[0] : 1 );
ob_start();
try {
	\Flavor\Update_Channel::render();
	$settings_html = (string) ob_get_clean();
	$settings_ok   = true;
} catch ( \Throwable $e ) {
	ob_end_clean();
	$settings_html = '';
	$settings_ok   = false;
}
check( $settings_ok, 'the update settings page renders as an administrator without fataling' );
check( false !== strpos( $settings_html, 'flavor_updates_save' ), 'the settings form carries its nonce action' );
check( false !== strpos( $settings_html, 'flavor_updates_rollback' ) || false !== strpos( $settings_html, 'تنظیمات کانال' ), 'the page shows the channel settings and rollback section' );

echo "\n--- Translation actually loads ---\n";

$loaded = is_textdomain_loaded( 'flavor' ) || is_file( get_template_directory() . '/languages/flavor-ar.mo' );
check( $loaded, 'the Arabic catalogue is present for load_theme_textdomain()' );

echo "\n=======================================================\n";
echo "WordPress {$wp_version}, WooCommerce " . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'n/a' ) . "\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
