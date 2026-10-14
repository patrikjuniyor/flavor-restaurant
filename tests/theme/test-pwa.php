<?php
/**
 * PWA installability + offline-shell contract tests (prompt 63).
 *
 * The manifest half already had a gate (test-theme-meta.php §2-3: name,
 * standalone, rtl, hex colours, short_name limit, icon files/purposes/sizes).
 * Prompt 63 adds the second half of an installable app — the service worker
 * — and pins the whole contract:
 *
 *  - sw-source.js is the minimal offline shell: precache the menu page on
 *    install, network-first navigations that refresh that cache on success,
 *    and a cached-menu fallback for any navigation that fails offline;
 *    nothing else is intercepted;
 *  - class-pwa.php serves it at /flavor-sw.js with Service-Worker-Allowed: /
 *    (root scope) and no-cache, substitutes the live menu URL + a filemtime
 *    cache version, and enqueues the register script only in secure
 *    contexts (https or localhost/127.0.0.1/::1) with the URL+scope localized;
 *  - pwa-register.js guards on navigator.serviceWorker AND isSecureContext
 *    and swallows registration failures (progressive enhancement);
 *  - manifest gains a stable `id` and keeps start_url on the menu page;
 *  - the three icon PNGs really are 192/512/512 pixels on disk (a manifest
 *    may lie about sizes; Chrome measures).
 *
 *   php tests/theme/test-pwa.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-meta.php';
require_once FLAVOR_DIR . '/inc/template-tags.php';
require_once FLAVOR_DIR . '/inc/class-pwa.php';

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
		echo '  [PASS] ' . $message . PHP_EOL;
	} else {
		++$failed;
		echo '  [FAIL] ' . $message . PHP_EOL;
	}
}

/**
 * Read a repo-relative file.
 *
 * @param string $rel Path below repo root.
 * @return string Contents.
 */
function read_rel( string $rel ): string {
	$body = file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	if ( false === $body ) {
		echo '  [FAIL] missing file ' . $rel . PHP_EOL;
		exit( 1 );
	}
	return $body;
}

echo "== prompt 63: PWA installability + offline shell ==\n";

$sw     = read_rel( 'flavor/assets/pwa/sw-source.js' );
$pwa    = read_rel( 'flavor/inc/class-pwa.php' );
$regjs  = read_rel( 'flavor/assets/js/pwa-register.js' );

// --- 1. offline shell behaviour --------------------------------------------
check( str_contains( $sw, "const CACHE = 'flavor-offline-v__CACHE_VERSION__';" ), 'cache name carries the filemtime version placeholder' );
check( str_contains( $sw, "const MENU_URL = '__MENU_URL__';" ), 'menu url placeholder present' );
check( (bool) preg_match( "/addEventListener\( 'install'.*?caches\.open\( CACHE \)\s*\.then\( \( cache \) => cache\.addAll\( \[ MENU_URL \] \) \)/s", $sw ), 'install precaches the menu page' );
check( str_contains( $sw, 'self.skipWaiting()' ), 'install skips waiting (shell lands immediately)' );
check( (bool) preg_match( "/addEventListener\( 'activate'.*?keys\.filter\( \( key \) => key !== CACHE \).*?caches\.delete/s", $sw ), 'activate evicts old cache generations' );
check( str_contains( $sw, 'self.clients.claim()' ), 'activate claims clients' );
check( str_contains( $sw, "if ( request.method !== 'GET' )" ) && str_contains( $sw, "if ( request.mode !== 'navigate' )" ), 'only same-origin GET navigations are handled' );
check( str_contains( $sw, "new URL( request.url ).origin !== self.location.origin" ), 'cross-origin requests stay untouched' );
check( (bool) preg_match( '/respondWith\(\s*fetch\( request \)/', $sw ), 'navigations are network-first' );
check( (bool) preg_match( "/response\.ok && new URL\( request\.url \)\.pathname === new URL\( MENU_URL \)\.pathname.*?cache\.put\( MENU_URL, copy \)/s", $sw ), 'successful menu navigation refreshes the cache' );
check( (bool) preg_match( "/catch\( \(\) => caches\.match\( MENU_URL \)\.then\( \( hit \) => hit \|\| Response\.error\(\) \) \)/", $sw ), 'failed navigation falls back to the cached menu' );

// --- 2. serving + registration wiring ---------------------------------------
check( str_contains( $pwa, "const SW_QUERY_VAR = 'flavor_sw';" ), 'service worker query var registered' );
check( str_contains( $pwa, "add_rewrite_rule( '^flavor-sw\\.js$', 'index.php?' . self::SW_QUERY_VAR . '=1', 'top' )" ), 'rewrite rule answers /flavor-sw.js at the root' );
check( str_contains( $pwa, "header( 'Service-Worker-Allowed: /' )" ), 'Service-Worker-Allowed grants root scope' );
check( str_contains( $pwa, "header( 'Content-Type: application/javascript; charset=utf-8' )" ), 'worker served as javascript' );
check( str_contains( $pwa, "header( 'Cache-Control: no-cache' )" ), 'worker never cached by intermediaries' );
	check( (bool) preg_match( '/str_replace\(\s*array\( \'__CACHE_VERSION__\', \'__MENU_URL__\' \),\s*array\( \(string\) filemtime\( \$source \), self::menu_url\(\) \)/s', $pwa ), 'placeholders substituted with live version + menu url' );
check( str_contains( $pwa, "in_array( \$host, array( 'localhost', '127.0.0.1', '::1' ), true )" ), 'registration enqueue gated on secure contexts' );
check( (bool) preg_match( "/wp_localize_script\(\s*'flavor-pwa-register',\s*'flavorPwa',\s*array\(\s*'sw'    => self::sw_url\(\),\s*'scope' => home_url\( '\/' \)/s", $pwa ), 'register script receives sw url + scope' );
check( str_contains( $pwa, 'sw-source.js' ) && str_contains( $pwa, 'assets/js/pwa-register.js' ), 'both new assets referenced (dead-code scan sees them)' );

// --- 3. register script guards ------------------------------------------------
check( str_contains( $regjs, "'serviceWorker' in navigator" ), 'register guards on navigator.serviceWorker' );
check( str_contains( $regjs, 'window.isSecureContext' ), 'register guards on secure context' );
check( (bool) preg_match( "/navigator\.serviceWorker\.register\( cfg\.sw, \{ scope: cfg\.scope \|\| '\/' \} \)/", $regjs ), 'register uses the localized url + scope' );
check( (bool) preg_match( '/\.catch\( function \(\) \{/', $regjs ), 'registration failure never breaks the page' );

// --- 4. manifest contract ------------------------------------------------------
check( (bool) preg_match( "/'id'               => home_url\( '\/' \)/", $pwa ), 'manifest carries a stable id' );
check( str_contains( $pwa, "'start_url'        => self::menu_url()," ), 'start_url stays on the menu page' );
$manifest = PWA::manifest();
check( 3 === count( $manifest['icons'] ), 'manifest declares exactly the three shipped icons' );
check( home_url( '/' ) === $manifest['id'], 'manifest id equals the site root' );

// --- 5. icons really are the declared pixel sizes -------------------------------
foreach ( array( 'icon-192.png' => 192, 'icon-512.png' => 512, 'icon-maskable-512.png' => 512 ) as $file => $px ) {
	$info = getimagesize( FLAVOR_DIR . '/assets/pwa/' . $file );
	check( is_array( $info ) && $px === $info[0] && $px === $info[1] && IMAGETYPE_PNG === $info[2], "{$file} is a real {$px}x{$px} PNG" );
}

// --- 6. substitution leaves no placeholder behind --------------------------------
$built = str_replace( array( '__CACHE_VERSION__', '__MENU_URL__' ), array( '1', 'https://example.test/menu/' ), $sw );
check( ! str_contains( $built, '__CACHE_VERSION__' ) && ! str_contains( $built, '__MENU_URL__' ), 'substituted worker carries no placeholders' );
check( substr_count( $sw, '{' ) === substr_count( $sw, '}' ), 'worker source braces balanced' );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
