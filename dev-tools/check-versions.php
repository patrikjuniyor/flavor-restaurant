<?php
/**
 * Assert that every file which states a version states the same one.
 *
 * Six places carry a version number and nothing kept them in step. Before
 * this script existed, the repository shipped:
 *
 *   flavor/style.css                 Version: 1.6.1
 *   README.md                        "نسخه پایدار: قالب ۱.۶.۰"
 *   flavor-core/flavor-core.php      Version: 1.5.1
 *   flavor-core/readme.txt           Stable tag: 1.1.0      <-- four minors behind
 *   CHANGELOG.md                     latest release: 1.4.0
 *
 * A buyer reading readme.txt would have believed the plugin was 1.1.0. This
 * script makes that class of drift impossible to merge.
 *
 * Usage:  php dev-tools/check-versions.php
 *
 * @package Flavor
 */

declare( strict_types=1 );

$root  = dirname( __DIR__ );
$fails = array();
$warns = array();

$fail = static function ( string $message ) use ( &$fails ): void {
	$fails[] = $message;
};
$warn = static function ( string $message ) use ( &$warns ): void {
	$warns[] = $message;
};

/**
 * Read a "Key: Value" header out of the first comment block of a file.
 *
 * @param string $file File to read.
 * @param string $key  Header name.
 * @return string
 */
$header = static function ( string $file, string $key ): string {
	$body = (string) file_get_contents( $file );
	// Only the leading header block, which is what WordPress itself parses.
	$head = substr( $body, 0, 8192 );
	if ( preg_match( '/^[ \t*]*' . preg_quote( $key, '/' ) . '\s*:\s*([^\r\n*]+)/mi', $head, $m ) ) {
		return trim( $m[1] );
	}
	return '';
};

/* -------------------------------------------------------------------------
 * 1. Theme version: style.css must agree with README.md and CHANGELOG.md.
 * ---------------------------------------------------------------------- */

$theme_version = $header( $root . '/flavor/style.css', 'Version' );

if ( '' === $theme_version ) {
	$fail( 'flavor/style.css: no "Version:" header found.' );
}

$readme = (string) file_get_contents( $root . '/README.md' );
// "نسخه پایدار: **قالب ۱.۶.۱ · هسته ۱.۵.۱**" — Persian digits included.
if ( preg_match( '/نسخه\s*پایدار:\s*\*\*قالب\s*([0-9۰-۹.]+)/u', $readme, $m ) ) {
	$readme_theme = $m[1];
	// Normalise Persian digits to ASCII for comparison.
	$readme_theme = strtr(
		$readme_theme,
		array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' )
	);
	if ( $readme_theme !== $theme_version ) {
		$fail( sprintf( 'README.md says theme "%s" but flavor/style.css says "%s".', $readme_theme, $theme_version ) );
	}
} else {
	$fail( 'README.md: could not find the "نسخه پایدار" line.' );
}

$changelog = (string) file_get_contents( $root . '/CHANGELOG.md' );
if ( preg_match_all( '/^##\s*\[([0-9][^\]\/]*)(?:\s*\/\s*Core\s*([0-9.]+))?\]/mi', $changelog, $cm ) ) {
	$released = array();
	foreach ( $cm[1] as $i => $v ) {
		$v = trim( $v );
		if ( 'Unreleased' === $v ) {
			continue;
		}
		$released[] = $v;
		if ( isset( $cm[2][ $i ] ) && '' !== trim( (string) $cm[2][ $i ] ) ) {
			$released[] = 'core ' . trim( (string) $cm[2][ $i ] );
		}
	}
	if ( ! in_array( $theme_version, $released, true ) ) {
		$fail( sprintf( 'CHANGELOG.md has no release section for theme %s (latest: %s).', $theme_version, $released[0] ?? 'none' ) );
	}
} else {
	$warn( 'CHANGELOG.md: no versioned release sections found.' );
}

/* -------------------------------------------------------------------------
 * 2. Plugin version: header, constant and readme.txt must all agree.
 * ---------------------------------------------------------------------- */

$core_file = $root . '/flavor-core/flavor-core.php';
$core_ver  = $header( $core_file, 'Version' );

$core_src = (string) file_get_contents( $core_file );
if ( preg_match( "/define\(\s*'FLAVOR_CORE_VERSION'\s*,\s*'([^']+)'/", $core_src, $m ) ) {
	if ( $m[1] !== $core_ver ) {
		$fail( sprintf( 'flavor-core.php header says "%s" but FLAVOR_CORE_VERSION is "%s".', $core_ver, $m[1] ) );
	}
} else {
	$fail( 'flavor-core.php: FLAVOR_CORE_VERSION constant not found.' );
}

$core_readme    = $root . '/flavor-core/readme.txt';
$stable_tag     = $header( $core_readme, 'Stable tag' );
$readme_content = (string) file_get_contents( $core_readme );

if ( '' === $stable_tag ) {
	$fail( 'flavor-core/readme.txt: no "Stable tag:" header.' );
} elseif ( $stable_tag !== $core_ver ) {
	$fail( sprintf( 'flavor-core/readme.txt "Stable tag" is %s but the plugin is %s.', $stable_tag, $core_ver ) );
}

// A readme changelog that stops four releases back reads as an abandoned plugin.
if ( ! preg_match( '/^=\s*' . preg_quote( $core_ver, '/' ) . '\s*=/m', $readme_content ) ) {
	$warn( sprintf( 'flavor-core/readme.txt has no changelog entry for %s.', $core_ver ) );
}

/* -------------------------------------------------------------------------
 * 3. Compatibility headers: internally sane, and not left to rot.
 * ---------------------------------------------------------------------- */

// Known-good floors at the time this guard was written. Bump deliberately.
$current_wp  = '7.1';
$current_wc  = '11.0';
$max_lag_wp  = 2; // majors behind before we complain.
$max_lag_wc  = 2;

$check_compat = static function ( string $label, string $file, string $tested_key, string $current, int $max_lag, callable $fail, callable $warn, callable $header ): void {
	$tested  = $header( $file, $tested_key );
	$minimum = $header( $file, 'Requires at least' );

	if ( '' === $tested ) {
		$fail( sprintf( '%s: no "%s:" header.', $label, $tested_key ) );
		return;
	}
	if ( '' === $minimum ) {
		$warn( sprintf( '%s: no "Requires at least:" header.', $label ) );
	}

	if ( version_compare( $minimum, $tested, '>' ) ) {
		$fail( sprintf( '%s: "Requires at least" (%s) is newer than "Tested up to" (%s).', $label, $minimum, $tested ) );
	}

	$lag = 0;
	$cur_major = (int) explode( '.', $current )[0];
	$tst_major = (int) explode( '.', $tested )[0];
	$lag       = $cur_major - $tst_major;

	if ( $lag > $max_lag ) {
		$fail( sprintf( '%s: "%s" is %s (current: %s) — %d major(s) behind.', $label, $tested_key, $tested, $current, $lag ) );
	} elseif ( $lag > 0 ) {
		$warn( sprintf( '%s: "%s" is %s, current is %s.', $label, $tested_key, $tested, $current ) );
	}
};

$check_compat( 'flavor/style.css', $root . '/flavor/style.css', 'Tested up to', $current_wp, $max_lag_wp, $fail, $warn, $header );
$check_compat( 'flavor-core/readme.txt', $core_readme, 'Tested up to', $current_wp, $max_lag_wp, $fail, $warn, $header );
$check_compat( 'flavor-core/flavor-core.php', $core_file, 'WC tested up to', $current_wc, $max_lag_wc, $fail, $warn, $header );
$check_compat( 'flavor-core/readme.txt', $core_readme, 'WC tested up to', $current_wc, $max_lag_wc, $fail, $warn, $header );

/* -------------------------------------------------------------------------
 * Report.
 * ---------------------------------------------------------------------- */

echo "Theme version : {$theme_version}\n";
echo "Core version  : {$core_ver}\n\n";

foreach ( $warns as $w ) {
	echo "WARN  {$w}\n";
}
foreach ( $fails as $f ) {
	echo "FAIL  {$f}\n";
}

if ( [] === $fails && [] === $warns ) {
	echo "OK    version and compatibility headers are consistent.\n";
	exit( 0 );
}

if ( [] !== $fails ) {
	printf( "\n%d error(s), %d warning(s).\n", count( $fails ), count( $warns ) );
	exit( 1 );
}

printf( "\n0 error(s), %d warning(s).\n", count( $warns ) );
exit( 0 );
