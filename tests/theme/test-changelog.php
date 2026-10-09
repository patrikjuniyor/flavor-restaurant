<?php
/**
 * Changelog panel tests (the server-free half of N-10).
 *
 * The panel reads readme.txt instead of keeping its own copy of the
 * history, so the interesting failure is not "does it render" but "does it
 * stay honest": a changelog that silently shows nothing is worse than no
 * changelog, because it looks like there were no changes.
 *
 *   php tests/theme/test-changelog.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';
require_once FLAVOR_DIR . '/inc/class-changelog.php';

use Flavor\Changelog;

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

echo "=== Flavor Changelog Panel Tests ===\n\n";

echo "--- 1. readme.txt is where the panel expects it ---\n";

check( is_readable( Changelog::readme_path() ), 'readme.txt ships with the theme and is readable' );

echo "\n--- 2. The changelog section is parsed ---\n";

$releases = Changelog::releases();

check( ! empty( $releases ), 'at least one release was parsed (' . count( $releases ) . ')' );
check( count( $releases ) <= 12, 'the list is capped so a long history cannot flood the page' );

foreach ( $releases as $index => $release ) {
	check( '' !== trim( $release['version'] ), "release {$index} has a version" );
	check( ! empty( $release['entries'] ), "release {$index} ({$release['version']}) has at least one entry" );
	check(
		1 === preg_match( '/^\d+\.\d+/', $release['version'] ),
		"release {$index} version looks like a version number ({$release['version']})"
	);
}

if ( count( $releases ) > 1 ) {
	// Newest first, so the version an owner just installed is at the top.
	check( $releases[0]['version'] !== $releases[1]['version'], 'releases are distinct entries' );
}

echo "\n--- 3. The parse stops at the next section ---\n";

// Anything after the changelog belongs to another section; leaking it in
// would make the panel claim changes that are not changes.
$file = (string) file_get_contents( Changelog::readme_path() );
check( false !== strpos( $file, '== Changelog ==' ), 'readme.txt has a Changelog section' );

$versions = array_column( $releases, 'version' );
foreach ( $versions as $version ) {
	check(
		false === strpos( $version, '==' ),
		"no section heading leaked into a version number ({$version})"
	);
}

// Every parsed version must actually appear as a heading in readme.txt.
foreach ( $versions as $version ) {
	check(
		false !== strpos( $file, '= ' . $version . ' =' ),
		"version {$version} is a real heading in readme.txt"
	);
}

echo "\n--- 4. The capability guard actually guards ---\n";

// An anonymous visitor must not reach the page. wp_die throws in this
// harness, so a rejection is observable rather than fatal.
wp_set_current_user( 0 );
$rejected = false;
try {
	Changelog::render();
} catch ( \Throwable $e ) {
	$rejected = true;
}
check( $rejected, 'a visitor without manage_options is refused' );

// A shop manager is not an administrator, so they are refused too.
$GLOBALS['_mock_users'][7] = new \WP_User( array( 'ID' => 7, 'roles' => array( 'shop_manager' ) ) );
wp_set_current_user( 7 );
$rejected = false;
try {
	Changelog::render();
} catch ( \Throwable $e ) {
	$rejected = true;
}
check( $rejected, 'a shop manager is refused' );

echo "\n--- 5. Rendering is safe and does not fatal ---\n";

// Now as an administrator, the page must render without leaking a fatal.
// Anything missing here would be a latent fatal on a real install.
$GLOBALS['_mock_users'][1] = new \WP_User( array( 'ID' => 1, 'roles' => array( 'administrator' ) ) );
wp_set_current_user( 1 );

ob_start();
$error = null;
try {
	Changelog::render();
} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$error = $e->getMessage();
}
$html = (string) ob_get_clean();

check( null === $error, 'render() does not throw for an administrator' . ( $error ? ' — ' . $error : '' ) );
check( false !== strpos( $html, 'flavor-changelog' ), 'the page renders its wrapper' );

if ( ! empty( $releases ) ) {
	$top = $releases[0]['version'];
	check( false !== strpos( $html, $top ), "the newest version ({$top}) is shown" );
}

echo "\n--- 6. Output is escaped, not trusted ---\n";

// A changelog line is authored by us, but a future edit should not be able
// to turn a bullet into markup. Every entry must appear escaped.
foreach ( $releases as $release ) {
	foreach ( $release['entries'] as $entry ) {
		$escaped = htmlspecialchars( $entry, ENT_QUOTES );
		check(
			false !== strpos( $html, $escaped ),
			'the entry for ' . $release['version'] . ' appears escaped in the output'
		);
		break; // One per release is enough to prove the escaping path.
	}
}

echo "\n--- 7. The page is registered where an owner expects it ---\n";

check( is_callable( array( Changelog::class, 'menu' ) ), 'the menu callback is callable' );
check( is_callable( array( Changelog::class, 'render' ) ), 'the render callback is callable' );
check( 'flavor-changelog' === Changelog::PAGE, 'the page slug is stable' );

$source = (string) file_get_contents( FLAVOR_DIR . '/functions.php' );
check( false !== strpos( $source, 'Changelog::init()' ), 'Changelog::init() is wired into functions.php' );
check(
	false !== strpos( $source, "inc/class-changelog.php" ),
	'the class file is required, so the wiring check stays green'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
