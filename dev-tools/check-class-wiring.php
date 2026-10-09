<?php
/**
 * Verify every theme class is loaded before it is used.
 *
 * Two bugs of exactly this shape shipped:
 *
 *   - `Flavor\Block_Patterns::init()` was called from functions.php while
 *     class-block-patterns.php was never required. The theme fataled on
 *     every request. The tests did not notice, because they require the
 *     class file themselves.
 *   - The control classes extended WP_Customize_Control from functions.php,
 *     where the parent class does not exist outside the Customizer.
 *
 * Both are invisible to unit tests: a test that requires the file directly
 * has the class, whatever the theme does at runtime. Only loading the theme
 * the way WordPress loads it exposes them.
 *
 * This check is the cheaper guard: it reads functions.php the way PHP reads
 * it and reports a class used where its file was never loaded.
 *
 * Usage:
 *   php dev-tools/check-class-wiring.php
 *
 * @package Flavor
 */

declare( strict_types=1 );

$root  = dirname( __DIR__ );
$entry = $root . '/flavor/functions.php';
$fail  = 0;

if ( ! is_file( $entry ) ) {
	fwrite( STDERR, "functions.php not found at {$entry}\n" );
	exit( 1 );
}

$source = (string) file_get_contents( $entry );

/**
 * Class-file name WordPress-conventionally derived from a class name.
 *
 * @param string $class Class name, without the namespace.
 * @return string
 */
function flavor_class_file( string $class ): string {
	return 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
}

/* ---------- 1. Which inc/ files does functions.php load? ---------- */

$required = array();

if ( preg_match_all( '#require_once\s+FLAVOR_DIR\s*\.\s*\'/inc/([^\']+)\'#', $source, $matches ) ) {
	foreach ( $matches[1] as $file ) {
		$required[ $file ] = true;
	}
}

/* ---------- 2. Which Flavor classes does functions.php use? ---------- */

$used = array();

// Flavor\Foo::bar() — the pattern every init() call follows.
if ( preg_match_all( '#Flavor\\\\([A-Za-z_][A-Za-z0-9_]*)::#', $source, $matches ) ) {
	foreach ( $matches[1] as $class ) {
		$used[ $class ] = true;
	}
}

// new Flavor\Foo( ... ) and Flavor\Foo::class, for completeness.
if ( preg_match_all( '#new\s+Flavor\\\\([A-Za-z_][A-Za-z0-9_]*)#', $source, $matches ) ) {
	foreach ( $matches[1] as $class ) {
		$used[ $class ] = true;
	}
}

/* ---------- 3. Every used class must be loaded ---------- */

echo "== Classes used by functions.php and the file that defines them ==\n";

$missing = array();

ksort( $used );

foreach ( array_keys( $used ) as $class ) {
	$file = flavor_class_file( $class );

	if ( isset( $required[ $file ] ) ) {
		printf( "[PASS] %-28s <- inc/%s\n", $class, $file );
		continue;
	}

	// A class may legitimately be loaded somewhere other than the top of
	// functions.php — the Customizer control classes are, because their
	// parent only exists inside the Customizer. Accept a require anywhere
	// in the theme's PHP.
	$found_elsewhere = false;
	$iterator        = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/flavor' ) );
	foreach ( $iterator as $candidate ) {
		if ( ! $candidate->isFile() || 'php' !== $candidate->getExtension() ) {
			continue;
		}
		$body = (string) file_get_contents( $candidate->getPathname() );
		if ( false !== strpos( $body, "inc/" . $file ) ) {
			$found_elsewhere = true;
			printf(
				"[PASS] %-28s <- inc/%s (loaded later, out of functions.php)\n",
				$class,
				$file
			);
			break;
		}
	}

	if ( ! $found_elsewhere ) {
		printf( "[FAIL] %-28s -> inc/%s is never required\n", $class, $file );
		$missing[] = $class;
	}
}

/* ---------- 4. A class extending a conditional parent must load late ---------- */

echo "\n== Control classes with a parent that core loads lazily ==\n";

// WP_Customize_Control only exists once WP_Customize_Manager boots. A
// control declared from functions.php fatals on every other request.
$control_classes = array( 'Preset_Control', 'Contrast_Control', 'Repeater_Control' );

foreach ( $control_classes as $control ) {
	$file = flavor_class_file( $control );
	$path = $root . '/flavor/inc/' . $file;

	if ( ! is_file( $path ) ) {
		printf( "[FAIL] %-28s -> inc/%s does not exist\n", $control, $file );
		++$fail;
		continue;
	}

	if ( isset( $required[ $file ] ) ) {
		printf(
			"[FAIL] %-28s is required from functions.php, but WP_Customize_Control\n       does not exist there; the theme fatals outside the Customizer.\n",
			$control
		);
		++$fail;
		continue;
	}

	// It must be loaded somewhere that runs after the parent exists.
	$customizer = (string) file_get_contents( $root . '/flavor/inc/class-customizer.php' );
	if ( false !== strpos( $customizer, 'inc/' . $file ) ) {
		printf( "[PASS] %-28s loads from Customizer::register()\n", $control );
	} else {
		printf( "[FAIL] %-28s is not loaded anywhere; its control will not exist\n", $control );
		++$fail;
	}
}

/* ---------- 5. Report ---------- */

if ( ! empty( $missing ) ) {
	++$fail;
}

echo "\n";

if ( $fail > 0 ) {
	fwrite( STDERR, "بررسیِ اتصالِ کلاس‌ها ناموفق بود.\n" );
	exit( 1 );
}

echo "بررسیِ اتصالِ کلاس‌ها موفق بود.\n";
