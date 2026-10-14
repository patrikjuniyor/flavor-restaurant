<?php
/**
 * Test Asset Loading & Performance Optimization (پرامپت ۷۴ / Prompt 74).
 *
 * Requirements:
 * 1. No unused CSS/JS enqueued on public pages (conditional enqueues).
 * 2. All public storefront scripts have strategy=defer (including pwa-register, main, digits, motion, etc.).
 * 3. Hero images at top of page have fetchpriority=high and loading=eager, others have loading=lazy.
 * 4. Fonts are preloaded selectively (Vazirmatn always, Estedad when skin uses it) and have font-display: swap.
 *
 * php tests/theme/test-asset-loading.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-bespoke-demos.php';
require_once FLAVOR_DIR . '/inc/template-tags.php';
require_once FLAVOR_DIR . '/inc/class-enqueue.php';
require_once FLAVOR_DIR . '/inc/class-pwa.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-ui-pages.php';
require_once FLAVOR_DIR . '/inc/class-customer-ui.php';
require_once FLAVOR_DIR . '/inc/class-premium-assets.php';

use Flavor\Enqueue;
use Flavor\Design;
use Flavor\PWA;

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

echo "=== 1. Script Defer Strategy Verification ===\n";

// Check all public scripts in flavor/inc/*.php
$inc_files = glob( FLAVOR_DIR . '/inc/*.php' );
$script_definitions = array();

foreach ( $inc_files as $file ) {
	$code = file_get_contents( $file );
	preg_match_all( '/wp_enqueue_script\s*\(\s*([^\;]+)\);/s', $code, $matches );
	foreach ( $matches[1] as $call ) {
		// Extract script handle
		if ( preg_match( '/[\'"]([^\'"]+)[\'"]/', $call, $handle_match ) ) {
			$handle = $handle_match[1];
			// Skip admin or customizer scripts
			if ( false !== stripos( $handle, 'admin' ) || false !== stripos( $handle, 'customizer' ) ) {
				continue;
			}
			$has_defer = false !== strpos( $call, "'strategy'" ) && false !== strpos( $call, "'defer'" );
			$script_definitions[ $handle ] = array(
				'file'      => basename( $file ),
				'has_defer' => $has_defer,
			);
		}
	}
}

check( count( $script_definitions ) >= 10, 'found ' . count( $script_definitions ) . ' public front-end scripts' );

$missing_defer = array();
foreach ( $script_definitions as $handle => $info ) {
	if ( ! $info['has_defer'] ) {
		$missing_defer[] = "{$handle} (in {$info['file']})";
	}
}

check( empty( $missing_defer ), 'all public storefront scripts have strategy=defer: ' . ( empty( $missing_defer ) ? 'OK' : implode( ', ', $missing_defer ) ) );

echo "\n=== 2. Hero Images fetchpriority=high and eager loading ===\n";

// Check hero templates
$hero_templates = array(
	'flavor/template-parts/marketing/hero.php',
	'flavor/template-parts/demos/catering.php',
	'flavor/template-parts/demos/cloud-kitchen.php',
	'flavor/template-parts/demos/dark-luxe.php',
	'flavor/template-parts/demos/juice-bar.php',
	'flavor/template-parts/demos/minimal-clean.php',
);

$all_heroes_high = true;
foreach ( $hero_templates as $tmpl ) {
	$content = file_get_contents( dirname( __DIR__, 2 ) . '/' . $tmpl );
	if ( false === strpos( $content, 'fetchpriority' ) || false === strpos( $content, 'high' ) ) {
		$all_heroes_high = false;
		echo "  [FAIL] {$tmpl} missing fetchpriority=high\n";
	}
}
check( $all_heroes_high, 'all hero template parts specify fetchpriority=high' );

// Test keep_hero_eager filter logic
check( false === Enqueue::keep_hero_eager( true, '<img class="flavor-hero__bg" src="hero.jpg" />', 'the_content' ), 'keep_hero_eager returns false for hero images (prevents loading=lazy)' );
check( true === Enqueue::keep_hero_eager( true, '<img class="flavor-card__img" src="dish.jpg" />', 'the_content' ), 'keep_hero_eager retains default lazy loading for non-hero images' );

// Test flavor_responsive_image attributes passthrough
$resp_html = flavor_responsive_image(
	'https://example.com/demo/hero.jpg',
	'flavor-hero',
	array(
		'class'         => 'flavor-hero__bg',
		'alt'           => 'Hero Image',
		'fetchpriority' => 'high',
		'loading'       => 'eager',
	)
);
check( false !== strpos( $resp_html, 'fetchpriority="high"' ), 'flavor_responsive_image passes fetchpriority="high"' );
check( false !== strpos( $resp_html, 'loading="eager"' ), 'flavor_responsive_image passes loading="eager"' );

echo "\n=== 3. Selective Font Preloading & font-display: swap ===\n";

// Test preload output for default skin
ob_start();
Enqueue::preload();
$preload_html = ob_get_clean();

check( false !== strpos( $preload_html, 'Vazirmatn-Regular.woff2' ), 'Vazirmatn font is preloaded' );
check( false !== strpos( $preload_html, 'as="font"' ), 'font preload specifies as="font"' );
check( false !== strpos( $preload_html, 'crossorigin' ), 'font preload includes crossorigin attribute' );

// Test font-display: swap in fonts.css
$fonts_css = file_get_contents( FLAVOR_DIR . '/assets/css/fonts.css' );
preg_match_all( '/@font-face\s*\{([^}]+)\}/s', $fonts_css, $font_blocks );
check( count( $font_blocks[1] ) >= 4, 'found ' . count( $font_blocks[1] ) . ' font-face definitions' );

$missing_swap = 0;
foreach ( $font_blocks[1] as $block ) {
	if ( false === strpos( $block, 'font-display: swap;' ) ) {
		++$missing_swap;
	}
}
check( 0 === $missing_swap, 'all @font-face rules define font-display: swap' );

echo "\n=== 4. Conditional Enqueueing (No unused assets on standard pages) ===\n";

// Inspect front() conditionals
$enqueue_code = file_get_contents( FLAVOR_DIR . '/inc/class-enqueue.php' );
check( false !== strpos( $enqueue_code, 'template-menu.php' ), 'menu.js and search assets conditionally gated on template-menu.php' );
check( false !== strpos( $enqueue_code, 'template-reservation.php' ), 'reservation.js conditionally gated on template-reservation.php' );
check( false !== strpos( $enqueue_code, 'template-contact.php' ), 'contact.js conditionally gated on template-contact.php' );
check( false !== strpos( $enqueue_code, "'catering' === \$skin && is_front_page()" ), 'catering.js strictly gated on catering demo homepage' );

echo "\n──────────────────────────────────────────────────\n";
echo "Result: {$passed} checks passed, {$failed} failed.\n";

if ( $failed > 0 ) {
	exit( 1 );
}
