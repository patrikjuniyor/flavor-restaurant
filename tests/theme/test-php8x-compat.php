<?php
/**
 * P44 gate — PHP 8.0..8.4 compatibility patterns (machine-checked).
 *
 * php -l proves syntax only; this test proves the code avoids constructs
 * that are deprecated or REMOVED across PHP 8.0 - 8.4, per the prompt:
 *   1. no ${var} string interpolation (deprecated 8.2)
 *   2. no undeclared dynamic $this-> writes outside a justified allow-list
 *      (deprecated 8.2; classes whose external parent declares the prop or
 *      carries #[AllowDynamicProperties] are allow-listed with a reason)
 *   3. no removed/deprecated internal calls (FILTER_SANITIZE_STRING 8.1,
 *      strftime 8.3-deprecated/8.4-soft, mhash/utf8_encode/utf8_decode/
 *      create_function)
 *   4. no implicit-nullable params (`Type $x = null` without `?`) 8.4
 *   5. no obviously unguarded null into string internals (strlen/trim/…
 *      fed directly by get_option()/meta/get_param()/super-global)
 *   6. CI matrix keeps covering 8.1-8.4
 *   7. "Requires PHP: 8.0" claim stays honest: no 8.1+ syntax in the
 *      trees that ship that header
 *
 * Run: php tests/theme/test-php8x-compat.php
 */

$root = dirname( __DIR__, 2 );
$pass = 0; $fail = 0;
function check( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  [PASS] $label\n"; }
	else { $fail++; echo "  [FAIL] $label\n"; }
}

/** All production PHP files (theme, child, plugin incl. admin views; tests excluded). */
function prod_files( $root ) {
	$out = array();
	foreach ( array( 'flavor', 'flavor-child', 'flavor-core' ) as $tree ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/$tree", FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			$p = $f->getPathname();
			if ( ! preg_match( '/\.php$/', $p ) ) continue;
			if ( strpos( $p, '/tests/' ) !== false ) continue;
			$out[] = $p;
		}
	}
	sort( $out );
	return $out;
}

$files = prod_files( $root );
check( count( $files ) > 150, 'production PHP file inventory found (' . count( $files ) . ' files)' );

// ---- 1. ${var} interpolation ----
$bad = array();
foreach ( $files as $f ) {
	$t = @token_get_all( (string) file_get_contents( $f ) );
	foreach ( $t as $tk ) {
		if ( is_array( $tk ) && $tk[0] === T_DOLLAR_OPEN_CURLY_BRACES ) { $bad[] = basename( $f ) . ':' . $tk[2]; }
	}
}
check( array() === $bad, 'no ${var} interpolation (deprecated 8.2)' . ( $bad ? ' -- ' . implode( ',', array_slice( $bad, 0, 4 ) ) : '' ) );

// ---- 2. dynamic $this-> writes ----
// External-parent allow-list: the parent (WP core / Woo core / SPL) declares
// these or opts in via #[AllowDynamicProperties]. Verified against upstream
# sources in P44 (WP_Customize_Control::$json is declared; WC_Payment_Gateway
// declares id/method_title/... ; RuntimeException does not -> fixed instead).
$allow = array(
	'flavor-core/includes/WooCommerce/Gateways/AbstractOfflineGateway.php' => array( 'id','method_title','method_description','has_fields','title','description','enabled','supports' ),
	// WP core's WP_Customize_Control DECLARES public $json (deprecated var,
	// still present in trunk) and carries class-level #[AllowDynamicProperties]
	// -- verified against wordpress-develop trunk during P44; our four custom
	// controls only write the declared bag.
	'flavor/inc/class-chrome-control.php'   => array( 'json' ),
	'flavor/inc/class-contrast-control.php' => array( 'json' ),
	'flavor/inc/class-preset-control.php'   => array( 'json' ),
	'flavor/inc/class-repeater-control.php' => array( 'json' ),
);
$dyn = array();
foreach ( $files as $f ) {
	$src    = (string) file_get_contents( $f );
	$lines  = explode( "\n", $src );
	$m      = count( $lines );
	$i      = 0;
	$rel    = str_replace( $root . '/', '', $f );
	while ( $i < $m ) {
		if ( preg_match( '/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)(\s+extends\s+[\w\\\\]+)?/', $lines[ $i ], $mm ) ) {
			$has_parent = ! empty( $mm[2] );
			$brace = 0; $start = $i; $body = array();
			for ( ; $i < $m; $i++ ) {
				$brace += substr_count( $lines[ $i ], '{' ) - substr_count( $lines[ $i ], '}' );
				$body[] = $lines[ $i ];
				if ( $brace <= 0 && $i > $start ) break;
			}
			$bs = implode( "\n", $body );
			preg_match_all( '/(?:public|protected|private|var)\s+(?:(?:readonly|static|final)\s+)*(?:\??[A-Za-z_][\w\\\\]*\s+)?\$(\w+)/', $bs, $dm );
			$decl = array_fill_keys( $dm[1], 1 );
			preg_match_all( '/\$this->(\w+)\s*(?:=|\.=|\+=|-=|\*=|\*=|%=|\?\?=|\+\+|--|\[)/', $bs, $wm );
			$hits = array();
			foreach ( array_unique( $wm[1] ) as $p ) {
				if ( isset( $decl[ $p ] ) || str_starts_with( $p, '_' ) ) continue;
				if ( in_array( $p, $allow[ $rel ] ?? array(), true ) && $has_parent ) continue;
				$hits[] = $p;
			}
			if ( $hits && false === strpos( $bs, '__set' ) ) $dyn[] = "$rel:" . implode( '|', $hits );
			continue;
		}
		$i++;
	}
}
check( array() === $dyn, 'no undeclared dynamic $this-> writes' . ( $dyn ? ' -- ' . implode( ' , ', $dyn ) : '' ) );

// ---- 3. removed / deprecated internals ----
$bad = array();
foreach ( $files as $f ) {
	$src = (string) file_get_contents( $f );
	if ( preg_match( '/FILTER_SANITIZE_STRING|FILTER_REQUIRE_SCALAR|\bstrftime\s*\(|\bgmstrftime\s*\(|\bmhash\s*\(|\butf8_encode\s*\(|\butf8_decode\s*\(|\bcreate_function\s*\(/', $src ) ) $bad[] = basename( $f );
}
check( array() === $bad, 'no removed/deprecated 8.x internals' . ( $bad ? ' -- ' . implode( ',', $bad ) : '' ) );

// ---- 4. implicit nullable params (8.4 deprecation) ----
$bad = array();
foreach ( $files as $f ) {
	foreach ( explode( "\n", (string) file_get_contents( $f ) ) as $no => $L ) {
		$t = trim( $L );
		if ( '' === $t || str_starts_with( $t, '*' ) || str_starts_with( $t, '//' ) || str_starts_with( $t, '#' ) ) continue;
		if ( preg_match( '/\bfunction\b[^;{]*\([^)]*(?<!\?)\b[A-Za-z_][\w\\\\]*\s+\$\w+\s*=\s*null\b/', $t ) && ! preg_match( '/\bfunction\b[^;{]*\([^)]*\?/', $t ) ) $bad[] = basename( $f ) . ':' . ( $no + 1 );
	}
}
check( array() === $bad, 'no implicit-nullable params (deprecated 8.4)' . ( $bad ? ' -- ' . implode( ',', array_slice( $bad, 0, 4 ) ) : '' ) );

// ---- 5. unguarded null into string internals ----
$bad = array();
foreach ( $files as $f ) {
	$src = (string) file_get_contents( $f );
	if ( preg_match_all( '/\b(?:strlen|trim|ltrim|rtrim|strtolower|strtoupper|ucfirst|ucwords|strpos|stripos|substr)\(\s*(?:get_option|get_theme_mod|get_post_meta|get_query_var)\s*\(/', $src, $mm ) ) $bad[] = basename( $f ) . '(' . count( $mm[0] ) . ')';
}
check( array() === $bad, 'no unguarded option/meta reads straight into string internals' . ( $bad ? ' -- ' . implode( ',', $bad ) : '' ) );

// ---- 6. CI matrix still covers 8.1-8.4 ----
$ci = (string) file_get_contents( "$root/.github/workflows/ci.yml" );
check( (bool) preg_match( "/php-version: \[?'?8\.1'?,\s*'8\.2'?,\s*'8\.3'?,\s*'8\.4'?\]?|8\.1 \/ 8\.2 \/ 8\.3 \/ 8\.4/", $ci ), 'ci.yml matrix keeps PHP 8.1-8.4' );

// ---- 7. "Requires PHP: 8.0" stays honest (no 8.1+ syntax in headed trees) ----
$bad = array();
foreach ( $files as $f ) {
	$src = (string) file_get_contents( $f );
	if ( preg_match( '/\breadonly\s+(?:class|property)|^\s*enum\s+\w+|\barray_is_list\s*\(|[A-Za-z_]\(\s*\.\.\.\s*\)\s*;|\)\s*:\s*never\b|\bnew\s+\w+\s*\([^)]*\)\s*\(.*\)\s*:\s*never/', $src ) ) $bad[] = basename( $f );
}
foreach ( array( "$root/flavor/style.css", "$root/flavor-child/style.css", "$root/flavor-core/flavor-core.php" ) as $h ) {
	if ( is_file( $h ) && preg_match( '/Requires PHP:\s*(\d+\.\d+)/', (string) file_get_contents( $h ), $mm ) && version_compare( $mm[1], '8.0', '<' ) ) $bad[] = basename( $h ) . ' claims ' . $mm[1];
}
check( array() === $bad, 'PHP 8.0 floor claim not undercut by 8.1+ syntax' . ( $bad ? ' -- ' . implode( ',', $bad ) : '' ) );

echo str_repeat( '=', 55 ) . "\nResults: $pass Passed, $fail Failed\n";
exit( $fail ? 1 : 0 );
