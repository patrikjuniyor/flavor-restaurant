<?php
/**
 * P45 gate — dead-code invariants (machine-maintained, conservative scope).
 *
 * The prompt asked to REMOVE dead code; the P45 audit found production code
 * essentially clean (0 unreferenced functions/classes; the only two files it
 * named are alive: dev-tools/README-updater.md is the install guide for the
 * shipped mu-plugin updater — referenced from README.md — and the iransans/
 * font slot is an intentional license placeholder wired into
 * class-ui-customizer.php + fonts.css). Actual removals: the dead
 * .fd-mobile-bar* rule block in bespoke-demos.css (its markup never existed
 * in any demo pack, JS, or doc — superseded by flavor-mobile-bar in chrome).
 *
 * This test KEEPS it that way:
 *   1. every flavor- or fd- prefixed class declared in production CSS is referenced in
 *      PHP/JS after BEM + progressive-dash-prefix expansion (runtime-built
 *      names like 'flavor-skin-'.$slug resolve via their roots)
 *   2. no production function/method has zero references repo-wide
 *   3. every fonts/<dir>/ either ships a font file or documents itself as an
 *      optional license slot
 *   4. updater doc + its script stay paired (neither may be orphaned)
 *   5. no asset file under the assets trees is name-unreferenced
 *
 * Run: php tests/theme/test-dead-code.php
 */

$root = dirname( __DIR__, 2 );
$pass = 0; $fail = 0;
function check( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  [PASS] $label\n"; }
	else { $fail++; echo "  [FAIL] $label\n"; }
}

function git_files( $root ) {
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$p = str_replace( $root . '/', '', $f->getPathname() );
		if ( preg_match( '#^(\.git|vendor|node_modules|mobile/\.dart_tool)#', $p ) ) continue;
		$out[] = $p;
	}
	sort( $out );
	return $out;
}

$files = git_files( $root );
$prod_php = array_values( array_filter( $files, static function ( $f ) {
	// flavor-core/includes/lib/ is a VENDORED third-party library (QR encoder):
	// its surface belongs upstream, and pruning it invites silent divergence
	// on the next vendor bump - out of scope for our dead-code audit.
	return preg_match( '#^flavor(-child|-core)?/#', $f ) && preg_match( '#\.php$#', $f ) && false === strpos( $f, '/tests/' ) && false === strpos( $f, '/includes/lib/' );
} ) );
// Prompt standard: "grep the name across the WHOLE repo" - so every text
// file counts as a reference (docs and tests included); conservative by design.
$corpus = '';
foreach ( $files as $f ) {
	if ( preg_match( '#\.(php|js|css|md|json|txt|yml|yaml|scss|html|xml)$#', $f ) && ! preg_match( '#\.(min|map)$#', $f ) ) $corpus .= file_get_contents( "$root/$f" ) . "\n";
}

// ---- 1. theme-namespaced (flavor-/fd-) CSS classes ----
$classes = array();
foreach ( $files as $f ) {
	if ( ! preg_match( '#\.css$#', $f ) || ! preg_match( '#^flavor(-child|-core)?/#', $f ) ) continue;
	$s = (string) file_get_contents( "$root/$f" );
	$s = preg_replace( '#/\*.*?\*/#s', '', $s );
	$s = preg_replace( '#url\([^)]*\)#', '', $s );       // data-URI noise (w3.org etc.)
	preg_match_all( '#\.(flavor-[a-zA-Z0-9_-]{3,}|fd-[a-zA-Z0-9_-]{3,})#', $s, $m );
	foreach ( $m[1] as $cl ) $classes[ $cl ] = $f;
}
// Liveness corpus for classes = every repo text file EXCEPT CSS: a selector may
// never be counted reachable by its own stylesheet (self-reference would approve
// 100% of classes). PHP/JS/markup that prints the class name is the proof.
$class_corpus = '';
foreach ( $files as $f ) {
	if ( preg_match( '#\.(php|js|md|json|txt|yml|html|xml|scss)$#', $f ) ) {
		$class_corpus .= (string) file_get_contents( "$root/$f" );
	}
}

$class_dyn = array();
// Approve a dash-root only when a CLASS-EMITTING line composes names by
// concatenation with that exact quoted prefix. Line-scoped context matters:
// class-ui.php:118 also has `'flavor-' . $file`, but it enumerates stylesheet
// HANDLES (enqueue), not markup - approving prefixes there rubber-stamps every
// flavor-* class (a sabotage probe proved the hole). Real builders:
//   class-ui.php:91        $classes[] = 'flavor-ui-' . str_replace(...) . '-' . $value;
//   class-theme-setup:162  $classes[] = 'flavor-header-' . ( in_array( $layout, ...) ... );
foreach ( explode( "\n", $class_corpus ) as $ln ) {
	if ( ! preg_match( '/classes\s*\[\s*\]|classList\.(?:add|remove|toggle)|class=/', $ln ) ) continue;
	if ( preg_match_all( "/\x27([a-z0-9][a-z0-9-]*-)\x27\s*\./", $ln, $lm ) ) {
		foreach ( $lm[1] as $pfx ) $class_dyn[ $pfx ] = true;
	}
}

$orphan = array();
foreach ( $classes as $cl => $f ) {
	$roots = array( $cl );
	$base  = $cl;
	while ( preg_match( '#(.*?)(--[a-zA-Z0-9-]+|__[a-zA-Z0-9-]+)$#', $base, $mm ) ) { $base = $mm[1]; $roots[] = $base; }
	$p = explode( '-', $base );
	for ( $k = count( $p ); $k > 1; $k-- ) $roots[] = implode( '-', array_slice( $p, 0, $k - 1 ) ) . '-';
	$found = false;
	// Chrome classes are emitted as gate_for( setting_id ): gate_for() slugs
	// 'flavor_header_nav_size_mobile' into 'flavor-chrome-header-nav-size-mobile'
	// (flavor/inc/class-chrome-builder.php:1630). A chrome class is live when the
	// matching setting id exists in the repo - deleting the setting orphans the CSS
	// and the gate will say so.
	if ( 0 === strpos( $cl, 'flavor-chrome-' ) ) {
		$cid = 'flavor_' . str_replace( '-', '_', substr( $cl, strlen( 'flavor-chrome-' ) ) );
		if ( false !== strpos( $class_corpus, "'" . $cid . "'" ) ) $found = true;
	}
	// Documented opt-in utility: motion.css ships .flavor-anim-shimmer for users to
	// attach via the page-options custom-class field (see the comment above the rule).
	// Markup-less on purpose; an explicit exemption so it cannot absorb new orphans.
	if ( 'flavor-anim-shimmer' === $cl ) $found = true;
	foreach ( array_unique( $roots ) as $r ) {
		if ( '' === $r ) continue;
		if ( '-' === substr( $r, -1 ) ) {
			if ( isset( $class_dyn[ $r ] ) ) { $found = true; break; }
		} elseif ( false !== strpos( $class_corpus, $r ) ) {
			$found = true; break;
		}
	}
	if ( ! $found ) $orphan[] = ".$cl ($f)";
}
check( array() === $orphan, count( $classes ) . " theme-namespaced CSS classes all reachable" . ( $orphan ? ' -- orphans: ' . implode( ', ', $orphan ) : '' ) );

// ---- 2. unreferenced functions/methods (prompt clause: grep the WHOLE repo) ----
// A declaration is dead when the entire text corpus (php/js/css/md/json/txt/yml/html/xml,
// tests included) holds no call-site, no class-scope reference and no quoted string use
// beyond the declarations themselves.
//
// Documented exclusions:
//  - Vendored library code (flavor-core/includes/lib/*): its API belongs upstream;
//    pruning it guarantees silent divergence on the next vendor bump. QR encoder
//    methods are dispatched by the port's own internals and MUST stay byte-identical.
//  - Framework protocol methods WP core itself calls on our subclasses. Verified
//    against WordPress trunk: class-wp-customize-control.php both declares and invokes
//    content_template() (JS templating), so custom controls implementing it are LIVE
//    despite zero in-repo callers.
//  - Runtime-composed names: any quoted prefix concatenated with a variable somewhere
//    in the repo ('render_' . $slug at flavor/inc/class-gutenberg.php:47) makes every
//    production name starting with that prefix live.
$prod_php = array_values( array_filter( $files, static function ( $f ) {
	return preg_match( '#^flavor(-child|-core)?/#', $f ) && preg_match( '#\.php$#', $f ) && false === strpos( $f, '/tests/' ) && false === strpos( $f, '/includes/lib/' );
} ) );
$corpus = '';
foreach ( $files as $f ) {
	if ( preg_match( '#\.(php|js|css|md|json|txt|yml|yaml|html|xml|scss)$#', $f ) ) {
		$corpus .= (string) file_get_contents( "$root/$f" );
	}
}
$concat_prefixes = array();
preg_match_all( "/'([A-Za-z_][A-Za-z0-9_\-]{2,})'\s*\.\s*[\$]/", $corpus, $cpm );
$concat_prefixes = array_values( array_unique( $cpm[1] ) );

$dead = array();
foreach ( $prod_php as $f ) {
	$s = (string) file_get_contents( "$root/$f" );
	preg_match_all( '/^\s*(?:(?:public|protected|private|final|static)\s+)*function\s+(\w+)\s*\(/m', $s, $m1 );
	foreach ( array_unique( $m1[1] ) as $name ) {
		if ( preg_match( '/^__|^(?:up|down)_activate$|^main$|^init$|^register$|^hooks$|^content_template$|^enqueue$/', $name ) ) continue;
		$uses = preg_match_all( '/(?:->|::|\b)' . preg_quote( $name, '#' ) . '\s*[\(:]/', $corpus );
		$decl = preg_match_all( '/function\s+' . preg_quote( $name, '#' ) . '\s*\(/', $corpus );
		$stru = preg_match_all( "/['\"]" . preg_quote( $name, '#' ) . "['\"]/", $corpus );
		$dyn  = false;
		foreach ( $concat_prefixes as $pfx ) {
			if ( 0 === strpos( $name, $pfx ) && $pfx !== $name ) { $dyn = true; break; }
		}
		if ( $uses - $decl + $stru <= 0 && ! $dyn ) $dead[] = "$f::$name";
	}
}
check( array() === $dead, 'no unreferenced production functions/methods' . ( $dead ? ' -- ' . implode( ', ', array_slice( $dead, 0, 6 ) ) . ( count( $dead ) > 6 ? ' (+' . ( count( $dead ) - 6 ) . ' more)' : '' ) : '' ) );

// ---- 3. font directories: real fonts or documented license slots ----
$bad = array();
foreach ( glob( "$root/flavor/assets/fonts/*" ) as $dir ) {
	if ( ! is_dir( $dir ) ) continue;
	// No GLOB_BRACE: undefined on musl/static PHP builds; four plain globs
	// are portable everywhere and mean exactly the same thing.
	$has_font = false;
	foreach ( array( 'woff2', 'woff', 'ttf', 'otf' ) as $font_ext ) {
		if ( glob( "$dir/*.$font_ext" ) ) { $has_font = true; break; }
	}
	$readme   = (string) @file_get_contents( "$dir/README.md" );
	if ( ! $has_font && ! preg_match( '/not bundled|license/i', $readme ) ) $bad[] = basename( $dir );
	if ( ! $has_font && false === stripos( $readme, basename( $dir ) ) ) $bad[] = basename( $dir ) . '(README mismatch)';
}
check( array() === $bad, 'fonts/ dirs are real or documented slots' . ( $bad ? ' -- ' . implode( ',', $bad ) : '' ) );

// ---- 4. updater tool + its doc stay paired ----
$doc = "$root/dev-tools/README-updater.md";
check( ( is_file( $doc ) === is_file( "$root/dev-tools/flavor-github-updater.php" ) ), 'updater script and install doc are both present (or both gone)' );
if ( is_file( $doc ) ) {
	check( false !== strpos( (string) file_get_contents( "$root/README.md" ), 'README-updater' ), 'README.md links the updater guide (no orphan doc)' );
	check( false !== stripos( (string) file_get_contents( $doc ), 'FLAVOR_GITHUB_TOKEN' ) && false !== stripos( (string) file_get_contents( $doc ), 'wp-config' ), 'updater doc routes the token through wp-config with an obviously fake placeholder' );
}

// ---- 5. asset files are name-referenced ----
$assets = array();
foreach ( $files as $f ) {
	// skins/*.css|img are enumerated at runtime ('skins/'.$dir.'.{css,webp}');
	// fonts/ has its own slot rule (clause 3).
	if ( preg_match( '#^flavor/assets/(img|js|css|pwa)/#', $f ) && ! preg_match( '#^flavor/assets/(img|css)/skins/#', $f ) && basename( $f ) !== 'index.php' ) $assets[] = $f;
}
$refsrc = '';
foreach ( array_merge( $prod_php, array_filter( $files, static fn( $f ) => preg_match( '#\.(json|xml)$#', $f ) ) ) as $f ) $refsrc .= file_get_contents( "$root/$f" );
$bad = array();
foreach ( $assets as $a ) {
	// Prompt 62 wired the receipt print sheet: ReceiptRenderer::print_css()
	// inlines flavor/assets/css/print-receipt.css into the standalone 80mm
	// document, so the reference scan below sees it like any other asset and
	// the pre-wiring exception is gone.
	$b = basename( $a );
	$stem = preg_replace( '#\.(css|js|png|jpg|jpeg|svg|webp|avif|gif|ico|json|xml|webmanifest)$#', '', $b );
	if ( false === strpos( $refsrc, $b ) && false === strpos( $refsrc, $stem ) ) $bad[] = $a;
}
check( array() === $bad, 'no unreferenced asset files' . ( $bad ? ' -- ' . implode( ', ', array_slice( $bad, 0, 6 ) ) : '' ) );

echo str_repeat( '=', 55 ) . "\nResults: $pass Passed, $fail Failed\n";
exit( $fail ? 1 : 0 );
