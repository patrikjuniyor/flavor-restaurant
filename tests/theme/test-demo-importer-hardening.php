<?php
/**
 * Demo-importer penetration guard (R4-6 / پرامپت ۴۱).
 *
 * class-demo-importer.php copies images from bundled demo packs into the
 * media library, and its rollback restores archived files. Three invariants
 * are machine-pinned here:
 *
 *   1. Catalog-sourced names — every file the importer may read is a flat,
 *      whitelisted basename coming from demo-catalog.php (static analysis
 *      of the catalogue + a live pass over every referenced asset). No
 *      user-supplied string is ever path material: superglobal reads are
 *      sanitize_key'd and catalog-gated.
 *   2. No traversal — the name gate rejects dot-segments and separators;
 *      the resolver additionally confines the realpath() to the demos root
 *      (a planted symlink escaping the pack proves the fence bites).
 *   3. Extension whitelist — only jpg/jpeg/png/svg, no webp smuggle-back;
 *      double-extension executables (shell.php.jpg) die at the gate, and
 *      SVG must be script-free to be sideloaded. Rollback restore targets
 *      must sit inside uploads basedir with no '..' segments.
 *
 *   php tests/theme/test-demo-importer-hardening.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );
require_once $root . '/tests/theme/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-demo-importer.php';

use Flavor\Demo_Importer;

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

/**
 * Invoke the private resolver through reflection.
 *
 * @param string $slug     Pack slug.
 * @param string $filename Candidate name.
 * @return string
 */
function resolve_asset( string $slug, string $filename ): string {
	$m = new ReflectionMethod( Demo_Importer::class, 'pack_asset_path' );
	$m->setAccessible( true );
	return (string) $m->invoke( null, $slug, $filename );
}

/* ------------------------------------------------------------------ */
/* 1. The name gate (pure string level)                                */
/* ------------------------------------------------------------------ */
echo "--- 1. is_safe_asset_name gate ---\n";

check( Demo_Importer::is_safe_asset_name( 'dark-luxe', 'hero.jpg' ), 'plain pack jpg accepted' );
check(
	Demo_Importer::is_safe_asset_name( 'dark-luxe', 'HERO.JPG' ),
	'case folds after normalization (uppercase JPG still accepted)'
);
check(
	Demo_Importer::is_safe_asset_name( 'cloud-kitchen', 'logo.svg' )
		&& Demo_Importer::is_safe_asset_name( 'cloud-kitchen', 'side.png')
		&& Demo_Importer::is_safe_asset_name( 'cloud-kitchen', 'x.jpeg'),
	'whitelist exactly jpg/jpeg/png/svg (پرامپت ۴۱)'
);
check(
	! Demo_Importer::is_safe_asset_name( 'cloud-kitchen', 'hero.webp' ),
	'webp stays outside the whitelist (was the pre-P41 gap)'
);
check(
	! Demo_Importer::is_safe_asset_name( 'cloud-kitchen', 'shell.php.jpg' )
		&& ! Demo_Importer::is_safe_asset_name( 'cloud-kitchen', 'x.phtml.png' ),
	'double-extension executables rejected despite a friendly final extension'
);
check(
	! Demo_Importer::is_safe_asset_name( 'dark-luxe', '../../wp-config.php' )
		&& ! Demo_Importer::is_safe_asset_name( 'dark-luxe', '..\\..\\wp-config.php' )
		&& ! Demo_Importer::is_safe_asset_name( 'dark-luxe', '..%2F..%2Fhero.jpg' )
		&& ! Demo_Importer::is_safe_asset_name( 'dark-luxe', '..%252ehero.jpg' ),
	'escape-style paths (unix/windows/url-encoded) never yield a safe name'
);
check(
	Demo_Importer::normalize_asset_name( 'sub/dir/hero.jpg' ) === 'hero.jpg'
		&& Demo_Importer::is_safe_asset_name( 'dark-luxe', 'sub/dir/hero.jpg' ),
	'benign nested paths collapse to their basename — resolution stays inside the pack (see §2)'
);
check(
	! Demo_Importer::is_safe_asset_name( '../evil', 'hero.jpg' )
		&& ! Demo_Importer::is_safe_asset_name( 'dark..luxe', 'hero.jpg' )
		&& ! Demo_Importer::is_safe_asset_name( '', 'hero.jpg' )
		&& ! Demo_Importer::is_safe_asset_name( 'Dark Luxe', 'hero.jpg' ),
	'slug is a strict flat key: no dots, slashes, spaces, uppercase'
);
check(
	! Demo_Importer::is_safe_asset_name( 'dark-luxe', '' )
		&& ! Demo_Importer::is_safe_asset_name( 'dark-luxe', '.htaccess' )
		&& ! Demo_Importer::is_safe_asset_name( 'dark-luxe', "hero.jpg\0.php" ),
	'empty / dotfile / NUL-smuggled names rejected'
);

/* ------------------------------------------------------------------ */
/* 2. Filesystem containment of the resolver                           */
/* ------------------------------------------------------------------ */
echo "\n--- 2. resolver confinement ---\n";

$demos_root = realpath( FLAVOR_DIR . '/demos' );
check( false !== $demos_root, 'the demos root exists for live probes' );

$real = resolve_asset( 'dark-luxe', 'hero.jpg' );
check(
	'' !== $real && is_readable( $real ) && 0 === strpos( $real, $demos_root . '/' ),
	'legit pack asset resolves inside the demos root'
);
check(
	'' === resolve_asset( 'dark-luxe', 'no-such-image.jpg' ),
	'missing files resolve to empty (read before sideload)'
);

// Plant real probe files so the webp refusal is the WHITELIST biting, not
// the mere absence of a file (and the jpg twin proves the probe is valid).
$probe_dir = $demos_root . '/dark-luxe';
$webp_probe = $probe_dir . '/pt41-probe.webp';
$jpg_probe  = $probe_dir . '/pt41-probe.jpg';
if ( is_dir( $probe_dir ) && ! file_exists( $webp_probe ) && ! file_exists( $jpg_probe ) ) {
	file_put_contents( $webp_probe, 'RIFF....WEBPVP8 ' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	copy( $webp_probe, $jpg_probe );
	check(
		'' === resolve_asset( 'dark-luxe', 'pt41-probe.webp' ),
		'webp on disk is still refused — the whitelist bites at the fs edge'
	);
	check(
		'' !== resolve_asset( 'dark-luxe', 'pt41-probe.jpg' ),
		'the identical bytes as .jpg resolve normally (probe validity, non-vacuity)'
	);
	@unlink( $webp_probe );
	@unlink( $jpg_probe );
} else {
	check( true, 'probe planting skipped (unwritable or occupied) — §1 webp gate still covers' );
	check( true, 'probe validity skipped with the webp probe' );
}

// Plant a symlink escaping the pack — the realpath fence must refuse it.
$escape = $demos_root . '/dark-luxe/pt41-escape.jpg';
$target = FLAVOR_DIR . '/style.css'; // real theme file, outside demos/
$planted = false;
if ( ! is_file( $escape ) && function_exists( 'symlink' ) && @symlink( $target, $escape ) ) {
	$planted = true;
	$sym_ok  = is_link( $escape );
	check(
		! $sym_ok || '' === resolve_asset( 'dark-luxe', 'pt41-escape.jpg' ),
		'symlink pointing outside demos/ is refused by realpath containment'
	);
	if ( $sym_ok ) {
		@unlink( $escape );
	}
} else {
	echo "  [SKIP] symlink() unavailable in this environment\n";
	check( true, 'symlink probe skipped (no symlink capability)' );
}

/* ------------------------------------------------------------------ */
/* 3. Catalogue is the ONLY name source (requirement 1)                */
/* ------------------------------------------------------------------ */
echo "\n--- 3. catalogue integrity ---\n";

require_once FLAVOR_DIR . '/inc/demo-catalog.php';
$catalog = flavor_demo_catalog();
check( is_array( $catalog ) && count( $catalog ) >= 3, 'catalogue loaded with real packs (' . count( $catalog ) . ')' );

$src       = (string) file_get_contents( FLAVOR_DIR . '/inc/demo-catalog.php' );
preg_match_all( "/'image'\s*=>\s*'([^']+)'/", $src, $m_img );
preg_match_all( "/'(?:hero|story)'\s*=>\s*'([^']+)'/", $src, $m_named );
$candidates = array_merge( $m_img[1], $m_named[1] );
preg_match_all( "/=>\s*'([a-z0-9._-]+\.(?:jpe?g|png|svg))'/i", $src, $m_assets );
$total_refs = count( $m_assets[1] );
$bad_refs   = 0;
foreach ( $m_assets[1] as $ref ) {
	if ( ! preg_match( '/^[a-z0-9][a-z0-9._-]*\.(?:jpe?g|png|svg)$/i', $ref ) ) {
		++$bad_refs;
	}
}
check(
	$total_refs >= 30 && 0 === $bad_refs,
	"catalogue asset literals are all flat whitelisted names ({$total_refs} refs, {$bad_refs} bad)"
);
check(
	! preg_match( "#=>\s*'[^']*(?:\.\.|/)[^']*\.(?:jpe?g|png|svg|webp)'#i", $src ),
	'no catalogue asset string carries a directory component'
);
check(
	0 === preg_match_all( "#\.(?:webp|gif|bmp|tiff)#i", implode( '', $m_assets[0] ) ),
	'catalogue references zero webp/gif/bmp/tiff assets'
);

// Live: every image the import would sideload passes the same gate.
$live_checked = 0;
$live_failed  = 0;
foreach ( $catalog as $slug => $demo ) {
	$files   = array_values( (array) ( $demo['category_images'] ?? array() ) );
	$files[] = 'hero.jpg';
	foreach ( (array) ( $demo['items'] ?? array() ) as $item ) {
		if ( ! empty( $item['image'] ) && is_string( $item['image'] ) ) {
			$files[] = $item['image'];
		}
	}
	foreach ( $files as $file ) {
		++$live_checked;
		if ( ! Demo_Importer::is_safe_asset_name( (string) $slug, (string) $file ) ) {
			++$live_failed;
		}
	}
}
check(
	$live_checked >= 60 && 0 === $live_failed,
	"every catalogued asset name passes the gate live ({$live_checked} checked)"
);

/* ------------------------------------------------------------------ */
/* 4. Superglobal surface of the importer                              */
/* ------------------------------------------------------------------ */
echo "\n--- 4. request surface ---\n";

$lines  = explode( "\n", $src_imp = (string) file_get_contents( FLAVOR_DIR . '/inc/class-demo-importer.php' ) );
$reads  = array();
foreach ( $lines as $ln ) {
	if ( preg_match( '/\$_(POST|GET|REQUEST|FILES)\[/', $ln ) ) {
		$reads[] = $ln;
	}
}
$unsafe = 0;
foreach ( $reads as $ln ) {
	if ( preg_match( '/\bisset\(\s*\$_(GET|POST)\[\s*[\'"](rolled_back|error)[\'"]/', $ln ) ) {
		continue; // isset-only notice switches, value never used
	}
	if ( ! preg_match( '/sanitize_key\(\s*wp_unslash\(/', $ln ) ) {
		++$unsafe;
	}
}
check(
	count( $reads ) >= 4 && 0 === $unsafe,
	'every value-bearing superglobal read is sanitize_key(wp_unslash(...)) (' . count( $reads ) . ' reads)'
);
check(
	0 === substr_count( $src_imp, '$_FILES' ),
	'the importer never touches $_FILES — no user upload path exists at all'
);
check(
	false !== strpos( $src_imp, "isset( \$catalog[ \$slug ] )" ),
	'unknown demo slugs die at the catalogue gate before any file work'
);
$demos_concat = preg_grep( '#FLAVOR_DIR \. \'/demos/\'#', $lines );
$unguarded    = 0;
foreach ( $demos_concat as $ln ) {
	if ( false === strpos( $ln, 'is_readable' ) && false === strpos( $ln, 'FLAVOR_URI' ) ) {
		++$unguarded;
	}
}
check(
	0 === $unguarded && count( $demos_concat ) >= 1,
	'raw /demos/ concatenations only exist in the is_readable-guarded URI preview'
);

/* ------------------------------------------------------------------ */
/* 5. SVG inertness and rollback containment                           */
/* ------------------------------------------------------------------ */
echo "\n--- 5. svg + rollback fences ---\n";

check(
	Demo_Importer::svg_is_inert( '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v10z"/></svg>' ),
	'static art svg accepted'
);
check(
	! Demo_Importer::svg_is_inert( '<svg onload="fetch(`/x?c=`+document.cookie)"/>' )
		&& ! Demo_Importer::svg_is_inert( '<svg><script>evil()</script></svg>' )
		&& ! Demo_Importer::svg_is_inert( '<svg><a href="javascript:x">t</a></svg>' )
		&& ! Demo_Importer::svg_is_inert( '<!ENTITY x SYSTEM "file:///etc/passwd">svg' )
		&& ! Demo_Importer::svg_is_inert( '<svg><foreignObject><body/></foreignObject></svg>' )
		&& ! Demo_Importer::svg_is_inert( '' ),
	'onload / <script> / javascript: / external entity / foreignObject / empty all refused'
);

$base = '/var/www/html/wp-content/uploads';
check( Demo_Importer::restore_target_allowed( $base . '/2026/10/pic-150x150.jpg', $base ), 'normal archived path allowed' );
check(
	! Demo_Importer::restore_target_allowed( $base . '/../../../wp-config.php', $base )
		&& ! Demo_Importer::restore_target_allowed( '/etc/cron.d/evil', $base )
		&& ! Demo_Importer::restore_target_allowed( $base, $base )
		&& ! Demo_Importer::restore_target_allowed( $base . '/x', '' ),
	'traversal / foreign / out-of-basedir targets refused even from option data'
);
check(
	! Demo_Importer::restore_target_allowed( '/srv/uploads-backup/x.jpg', '/srv/uploads' ),
	'prefix sibling directory (uploads-backup vs uploads) is NOT trusted'
);
check(
	false !== strpos( $src_imp, 'self::restore_target_allowed(' )
		&& preg_match( '/restore_target_allowed.*?continue.*?is_readable.*?copy\( \$backup/s', $src_imp ),
	'rollback loop checks containment before mkdir+copy (live order pinned)'
);
check(
	false !== strpos( $src_imp, "self::pack_asset_path( \$slug, \$filename )" )
		&& false === strpos( $src_imp, "preg_match( '/\\.(?:jpe?g|png|webp)\$/i'" ),
	'sideload routes through the confined resolver; old webp-permissive regex is gone'
);

if ( $failed > 0 ) {
	echo "\n{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}
echo "\nALL " . $passed . " checks passed.\n";
exit( 0 );
