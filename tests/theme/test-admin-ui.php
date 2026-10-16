<?php
/**
 * Admin UI asset gate.
 *
 * The theme owns five wp-admin screens (setup wizard, launch centre, demo
 * importer, changelog, settings transfer). Before this class each of them
 * carried its own inline `<style>` island, which meant every screen drifted
 * on its own and none of them could be restyled from one place. Admin_UI
 * loads a single stylesheet on exactly those screens and nothing else in
 * wp-admin, and the hero helper has to degrade to a gradient when the
 * photograph is missing rather than print a dead URL.
 *
 * The failure modes worth pinning are therefore: styling leaking into other
 * admin screens, the stylesheet missing from the package, a hero path that
 * does not resolve, and an unescaped custom property reaching the markup.
 *
 *   php tests/theme/test-admin-ui.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';
require_once FLAVOR_DIR . '/inc/class-admin-ui.php';

use Flavor\Admin_UI;

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

echo "--- 1. The class is wired into the theme ---\n";

$functions = (string) file_get_contents( FLAVOR_DIR . '/functions.php' );
check( false !== strpos( $functions, 'inc/class-admin-ui.php' ), 'functions.php requires the class file' );
check( false !== strpos( $functions, 'Admin_UI::init()' ), 'functions.php boots Admin_UI::init()' );

echo "\n--- 2. Only the theme-owned screens are styled ---\n";

check( Admin_UI::is_screen( 'appearance_page_flavor-setup' ), 'the setup wizard is one of ours' );
check( Admin_UI::is_screen( 'appearance_page_flavor-launch' ), 'the launch centre is one of ours' );
check( Admin_UI::is_screen( 'appearance_page_flavor-demos' ), 'the demo importer is one of ours' );
check( Admin_UI::is_screen( 'appearance_page_flavor-changelog' ), 'the changelog is one of ours' );
check( Admin_UI::is_screen( 'appearance_page_flavor-transfer' ), 'the settings transfer page is one of ours' );

check( ! Admin_UI::is_screen( '' ), 'an empty hook is refused' );
check( ! Admin_UI::is_screen( 'edit.php' ), 'a core list table is untouched' );
check( ! Admin_UI::is_screen( 'appearance_page_flavor-setup-extra' ), 'a longer slug does not match by prefix' );
check( ! Admin_UI::is_screen( 'appearance_page_other-plugin' ), "another plugin's appearance page is untouched" );

echo "\n--- 3. The stylesheet ships and is reachable ---\n";

$css_rel = FLAVOR_DIR . '/assets/css/admin-ui.css';
check( is_readable( $css_rel ), 'assets/css/admin-ui.css is present in the package' );

$css = (string) file_get_contents( $css_rel );
check( '' !== trim( $css ), 'the stylesheet is not empty' );
check( 0 === substr_count( $css, '<?php' ), 'the stylesheet carries no PHP' );
check(
        substr_count( $css, '{' ) === substr_count( $css, '}' ),
        'every rule block is closed (' . substr_count( $css, '{' ) . ' opened, ' . substr_count( $css, '}' ) . ' closed)'
);

echo "\n--- 4. Readability and motion floors survive ---\n";

preg_match_all( '/font-size:\s*(\d+(?:\.\d+)?)px/', $css, $font_sizes );
$too_small = array_values( array_filter( $font_sizes[1], static fn( $px ) => (float) $px < 12 ) );
check(
        array() === $too_small,
        'no rule drops below the 12px readability floor' . ( $too_small ? ' -- ' . implode( ', ', $too_small ) : '' )
);

check(
        false !== strpos( $css, 'prefers-reduced-motion' ),
        'the motion sheet honours prefers-reduced-motion'
);
check(
        false !== strpos( $css, 'transition: none' ) || false !== strpos( $css, 'animation: none' ),
        'the reduced-motion block actually disables the transitions'
);

check(
        false !== strpos( $css, '@keyframes flavorAdminFadeIn' ),
        'the wizard pane keyframes ship inside the admin sheet (main.css is never loaded in wp-admin)'
);
check(
        false !== strpos( $css, 'animation: flavorAdminFadeIn' ),
        'the active wizard pane uses those keyframes'
);
check(
        1 === preg_match( '/prefers-reduced-motion[^{]*\{.*?animation:\s*none/s', $css ),
        'the reduced-motion block also silences the pane animation'
);

echo "\n--- 5. Direction stays logical, so RTL does not leak ---\n";

check(
        0 === preg_match( '/text-align:\s*(left|right)\b/', $css ),
        'no physical text-align in the admin sheet'
);
check(
        0 === preg_match( '/(margin|padding)-(left|right)\s*:/', $css ),
        'no physical margin/padding side in the admin sheet'
);
check(
        0 === preg_match( '/^\s*(left|right)\s*:/m', $css ),
        'no bare physical offset in the admin sheet'
);

echo "\n--- 6. The hero helper degrades instead of printing a dead URL ---\n";

$hero = Admin_UI::hero( 'hero-kitchen' );
check( '' !== $hero, 'a bundled hero resolves to a URL' );
check( false !== strpos( $hero, '/assets/img/admin/hero-kitchen.jpg' ), 'the resolved URL points at the packaged photograph' );

check( '' === Admin_UI::hero( 'not-a-screen' ), 'an unknown slug resolves to nothing' );
check( '' === Admin_UI::hero( 'hero-does-not-exist' ), 'a missing file resolves to nothing' );

$style = Admin_UI::hero_style( 'hero-kitchen' );
check( false !== strpos( $style, 'background-image:' ), 'the helper returns a background-image declaration' );
check( false !== strpos( $style, 'url(' ), 'the declaration wraps the URL in url()' );
check( '' === Admin_UI::hero_style( 'not-a-screen' ), 'an unknown slug produces no style attribute at all' );

echo "\n--- 7. Every screen that paints a hero has a real image ---\n";

foreach ( array( 'hero-kitchen', 'hero-dining', 'hero-coffee' ) as $slug ) {
        $path = FLAVOR_DIR . '/assets/img/admin/' . $slug . '.jpg';
        check( is_readable( $path ), "assets/img/admin/{$slug}.jpg is packaged" );
}

echo "\n--- 8. No admin screen kept its inline style island ---\n";

$pages = array(
        'class-onboarding.php',
        'class-launch-center.php',
        'class-demo-importer.php',
        'class-changelog.php',
        'class-transfer.php',
);
foreach ( $pages as $page ) {
        $src = (string) file_get_contents( FLAVOR_DIR . '/inc/' . $page );
        check( false === strpos( $src, '<style' ), "{$page} no longer carries an inline <style> block" );
        check( false !== strpos( $src, 'flavor-admin-ui' ), "{$page} renders inside the shared admin wrapper" );
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
