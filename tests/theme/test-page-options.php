<?php
/**
 * Per-page options contract tests.
 *
 * These values are written by a metabox and land directly in a <style> tag,
 * so the sanitizer is the security boundary: whatever it lets through reaches
 * the page as CSS. The tests below push a stylesheet-escape payload through
 * every field and assert it comes out empty.
 *
 *   php tests/theme/test-page-options.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';
require_once __DIR__ . '/fixtures/page-options-stubs.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-page-options.php';

use Flavor\Page_Options;

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

echo "=== Flavor Per-Page Options Contract Tests ===\n\n";

$options = Page_Options::options();

echo "--- 1. Option definitions are coherent ---\n";

check( count( $options ) >= 4, 'a useful set of page options is declared (' . count( $options ) . ')' );

foreach ( $options as $key => $option ) {
	check( '' !== (string) ( $option['label'] ?? '' ), "{$key} has a label" );
	check(
		in_array( $option['type'], array( 'checkbox', 'select', 'color' ), true ),
		"{$key} declares a known control type ({$option['type']})"
	);
	if ( 'select' === $option['type'] ) {
		check( count( $option['choices'] ) >= 2, "{$key} offers a real choice" );
		check( array_key_exists( '', $option['choices'] ), "{$key} offers a 'use the theme default' option" );
	}
}

echo "\n--- 2. Sanitizer accepts the legitimate values ---\n";

check( '1' === Page_Options::sanitize( 'hide_title', '1' ), 'hide_title accepts "1"' );
check( '' === Page_Options::sanitize( 'hide_title', 'yes' ), 'hide_title rejects anything but "1"' );
check( 'full' === Page_Options::sanitize( 'page_layout', 'full' ), 'page_layout accepts "full"' );
check( 'narrow' === Page_Options::sanitize( 'page_layout', 'narrow' ), 'page_layout accepts "narrow"' );
check( '' === Page_Options::sanitize( 'page_layout', '' ), 'page_layout accepts empty (theme default)' );
check( '#f4efe7' === Page_Options::sanitize( 'page_bg', '#f4efe7' ), 'page_bg accepts a 6-digit hex' );
check( '#fff' === Page_Options::sanitize( 'page_bg', '#fff' ), 'page_bg accepts a 3-digit hex' );
check( '' === Page_Options::sanitize( 'unknown_key', 'anything' ), 'an unknown option key is rejected' );

echo "\n--- 3. Sanitizer blocks CSS breakout ---\n";

// Every one of these would otherwise be injected into a <style> block.
$payloads = array(
	'red;background:url(javascript:alert(1))',
	'#fff;}body{display:none}',
	'#fff</style><script>alert(1)</script>',
	'url(https://evil.example/track)',
	'expression(alert(1))',
	'#GGG',
	'rgb(255,0,0)',
	'var(--flavor-primary)',
	array( '#ffffff' ),
);

foreach ( $payloads as $payload ) {
	$label = is_array( $payload ) ? '(array)' : ( '' === $payload ? '(empty)' : $payload );
	check(
		'' === Page_Options::sanitize( 'page_bg', $payload ),
		'page_bg rejects ' . substr( (string) $label, 0, 42 )
	);
}

foreach ( array( 'full;color:red', 'narrow;}', 'FULL' ) as $payload ) {
	check( '' === Page_Options::sanitize( 'page_layout', $payload ), "page_layout rejects \"{$payload}\"" );
}

echo "\n--- 4. Body classes reflect the saved options ---\n";

$post_id = 4242;

// Start from a clean slate.
foreach ( array_keys( $options ) as $key ) {
	delete_post_meta( $post_id, Page_Options::PREFIX . $key );
}

$classes = Page_Options::body_classes( array( 'flavor-theme' ), $post_id );
check( ! in_array( 'flavor-hide-title', $classes, true ), 'an untouched page gets no hide-title class' );
check( ! in_array( 'flavor-layout-full', $classes, true ), 'an untouched page gets no layout class' );

update_post_meta( $post_id, Page_Options::PREFIX . 'hide_title', '1' );
update_post_meta( $post_id, Page_Options::PREFIX . 'page_layout', 'full' );
update_post_meta( $post_id, Page_Options::PREFIX . 'transparent_header', '1' );

$classes = Page_Options::body_classes( array( 'flavor-theme' ), $post_id );
check( in_array( 'flavor-hide-title', $classes, true ), 'hide_title adds its body class' );
check( in_array( 'flavor-layout-full', $classes, true ), 'page_layout=full adds its body class' );
check( in_array( 'flavor-header-overlay', $classes, true ), 'transparent_header adds its body class' );
check( in_array( 'flavor-theme', $classes, true ), 'existing body classes are preserved' );

// A stored value that the sanitizer would reject must not reach the class list.
update_post_meta( $post_id, Page_Options::PREFIX . 'page_layout', 'full;color:red' );
$classes = Page_Options::body_classes( array(), $post_id );
check( ! in_array( 'flavor-layout-full;color:red', $classes, true ), 'a hostile stored layout never becomes a class' );

echo "\n--- 5. Inline CSS is only emitted when needed ---\n";

delete_post_meta( $post_id, Page_Options::PREFIX . 'hide_title' );
delete_post_meta( $post_id, Page_Options::PREFIX . 'page_bg' );
check( '' === Page_Options::inline_css( $post_id ), 'a page with no options produces no CSS' );

update_post_meta( $post_id, Page_Options::PREFIX . 'page_bg', '#123456' );
$css = Page_Options::inline_css( $post_id );
check( false !== strpos( $css, '--flavor-page-bg:#123456' ), 'page_bg is published as a custom property' );
check( false === strpos( $css, 'url(' ), 'no url() ever reaches the emitted CSS' );
check( false === strpos( $css, '<' ), 'no markup ever reaches the emitted CSS' );

update_post_meta( $post_id, Page_Options::PREFIX . 'hide_title', '1' );
$css = Page_Options::inline_css( $post_id );
check( false !== strpos( $css, '--flavor-page-title-display:none' ), 'hide_title is published as a custom property' );
check( substr_count( $css, 'body.flavor-page-scoped' ) === 1, 'the rule is scoped to one selector' );

echo "\n--- 6. The stylesheet consumes the variables the PHP sets ---\n";

$main = (string) file_get_contents( FLAVOR_DIR . '/assets/css/main.css' );
check( false !== strpos( $main, 'var(--flavor-page-bg' ), 'main.css reads --flavor-page-bg' );
check( false !== strpos( $main, 'var(--flavor-page-title-display' ), 'main.css reads --flavor-page-title-display' );
check( false !== strpos( $main, '.flavor-layout-narrow' ), 'main.css implements the narrow layout' );
check( false !== strpos( $main, '.flavor-layout-full' ), 'main.css implements the full-width layout' );
check( false !== strpos( $main, '.flavor-header-overlay' ), 'main.css implements the transparent header' );

// Every variable the PHP can emit must have a consumer, or the option is a no-op.
preg_match_all( '/--flavor-[a-z-]+/', Page_Options::inline_css( $post_id ), $emitted );
foreach ( array_unique( $emitted[0] ) as $variable ) {
	check( false !== strpos( $main, 'var(' . $variable ), "{$variable} is consumed by the stylesheet" );
}

echo "\n--- 7. The metabox registers on the right screens ---\n";

$GLOBALS['_mock_meta_boxes'] = array();
Page_Options::add_metabox();
$boxes = $GLOBALS['_mock_meta_boxes'] ?? array();

check( isset( $boxes['flavor_page_options'] ), 'the metabox is registered' );
check(
	array( 'page', 'post', 'product' ) === ( $boxes['flavor_page_options']['screen'] ?? null ),
	'the metabox appears on pages, posts and products'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
