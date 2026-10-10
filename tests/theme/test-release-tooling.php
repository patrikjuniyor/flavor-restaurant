<?php
/**
 * Release tooling tests: publishing guards, verify command, and the public
 * release-site contents.
 *
 * Everything runs with throwaway keys and zips under the system temp dir.
 * No real key is created, nothing is written inside the repository, and the
 * release tool is exercised exactly as the owner and CI will run it.
 *
 *   php tests/theme/test-release-tooling.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/updates/class-update-error.php';
require_once FLAVOR_DIR . '/inc/updates/class-update-signature.php';
require_once FLAVOR_DIR . '/inc/updates/class-update-manifest.php';
require_once FLAVOR_DIR . '/inc/updates/class-update-package.php';

use Flavor\Updates\Manifest;
use Flavor\Updates\Signature;

$passed = 0;
$failed = 0;

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
 * Run the release tool and return its exit code and combined output.
 *
 * @param string $args Arguments, already shell-escaped.
 * @return array{0:int,1:string}
 */
function run_tool( string $args ): array {
	$tool = dirname( __DIR__, 2 ) . '/dev-tools/release/build-release.php';
	$cmd  = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $tool ) . ' ' . $args . ' 2>&1';
	exec( $cmd, $lines, $code );
	return array( $code, implode( "\n", $lines ) );
}

/**
 * Make a minimal theme zip. Version defaults to 1.7.0; pass null to omit it.
 *
 * @param string      $path    Destination.
 * @param string|null $version Version in style.css, or null for none.
 * @param string      $extra   Extra file contents for flavor/functions.php.
 * @return void
 */
function make_zip( string $path, ?string $version = '1.7.0', string $extra = '<?php' ): void {
	$header = null === $version ? '/* Theme Name: Flavor */' : "/*\nTheme Name: Flavor\nVersion: {$version}\n*/";
	$zip    = new \ZipArchive();
	$zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
	$zip->addFromString( 'flavor/style.css', $header );
	$zip->addFromString( 'flavor/functions.php', $extra );
	$zip->close();
}

function rm_tree( string $path ): void {
	if ( is_link( $path ) || is_file( $path ) ) {
		unlink( $path );
		return;
	}
	if ( ! is_dir( $path ) ) {
		return;
	}
	foreach ( array_diff( (array) scandir( $path ), array( '.', '..' ) ) as $entry ) {
		rm_tree( $path . '/' . $entry );
	}
	rmdir( $path );
}

$repo  = dirname( __DIR__, 2 );
$work  = sys_get_temp_dir() . '/flavor-release-test-' . bin2hex( random_bytes( 4 ) );
$keys  = $work . '/keys';
$other = $work . '/other-keys';
$out   = $work . '/release';
mkdir( $keys, 0700, true );
mkdir( $other, 0700, true );
mkdir( $out, 0755, true );

$url_prefix = 'https://owner.example.com/flavor/';
$good_zip   = $work . '/flavor-1.7.0.zip';
make_zip( $good_zip );

echo "\n--- 1. keygen and sign refuse to touch the repository ---\n";
[ $code, ] = run_tool( 'keygen --out=' . escapeshellarg( $repo . '/dev-tools' ) );
check( 0 !== $code, 'keygen refuses an --out inside the repository' );
check( ! file_exists( $repo . '/dev-tools/secret.key' ), 'a refused keygen writes no secret key into the repo' );

[ $code, $msg ] = run_tool( 'keygen --out=' . escapeshellarg( $keys ) );
check( 0 === $code && is_file( $keys . '/secret.key' ) && is_file( $keys . '/public.key' ), 'keygen outside the repo writes both keys' );
check( 0600 === ( fileperms( $keys . '/secret.key' ) & 0777 ), 'the secret key file is mode 0600' );

[ $code, ] = run_tool( 'keygen --out=' . escapeshellarg( $keys ) );
check( 0 !== $code, 'keygen refuses to overwrite existing keys' );

$public_key = trim( (string) file_get_contents( $keys . '/public.key' ) );
$secret_key = $keys . '/secret.key';

[ $code, ] = run_tool(
	'sign --zip=' . escapeshellarg( $good_zip ) . ' --version=1.7.0 --url=' . escapeshellarg( $url_prefix . 'flavor-1.7.0.zip' ) .
	' --secret-key=' . escapeshellarg( $repo . '/README.md' ) . ' --out=' . escapeshellarg( $out )
);
check( 0 !== $code, 'sign refuses a secret key path inside the repository' );

// sign --out may point inside the repo on purpose (release-site/flavor); only the secret must stay out.

echo "\n--- 2. sign checks the version inside the package ---\n";
[ $code, $msg ] = run_tool(
	'sign --zip=' . escapeshellarg( $good_zip ) . ' --version=1.7.1 --url=' . escapeshellarg( $url_prefix . 'flavor-1.7.0.zip' ) .
	' --secret-key=' . escapeshellarg( $secret_key ) . ' --out=' . escapeshellarg( $out )
);
check( 0 !== $code && false === strpos( $msg, 'manifest.json' ), 'a manifest version that differs from the zip is refused' );
check( ! file_exists( $out . '/manifest.json' ), 'a refused sign leaves no manifest behind' );

$no_version = $work . '/no-version.zip';
make_zip( $no_version, null );
[ $code, ] = run_tool(
	'sign --zip=' . escapeshellarg( $no_version ) . ' --version=1.7.0 --url=' . escapeshellarg( $url_prefix . 'x.zip' ) .
	' --secret-key=' . escapeshellarg( $secret_key ) . ' --out=' . escapeshellarg( $out )
);
check( 0 !== $code, 'a package without a Version header in style.css is refused' );

echo "\n--- 3. sign produces a manifest that verify accepts ---\n";
[ $code, ] = run_tool(
	'sign --zip=' . escapeshellarg( $good_zip ) . ' --version=1.7.0 --url=' . escapeshellarg( $url_prefix . 'flavor-1.7.0.zip' ) .
	' --secret-key=' . escapeshellarg( $secret_key ) . ' --out=' . escapeshellarg( $out ) .
	' --released=2026-10-20 --requires-php=8.0 --requires-wp=6.4'
);
check( 0 === $code, 'sign succeeds for a matching package' );
// Publish the package beside the manifest, as release-site/flavor/ will hold it.
copy( $good_zip, $out . '/flavor-1.7.0.zip' );

[ $code, $msg ] = run_tool(
	'verify --dir=' . escapeshellarg( $out ) . ' --public-key=' . escapeshellarg( $keys . '/public.key' ) .
	' --url-prefix=' . escapeshellarg( $url_prefix )
);
check( 0 === $code && false !== strpos( $msg, 'امضا معتبر است' ), 'verify accepts a correctly signed release' );

echo "\n--- 4. verify refuses every kind of tampering ---\n";
[ $code, ] = run_tool( 'verify --dir=' . escapeshellarg( $out ) . ' --public-key=' . escapeshellarg( $other . '/public.key' ) );
check( 0 !== $code, 'verify refuses a key other than the one that signed' );

run_tool( 'keygen --out=' . escapeshellarg( $other ) );
[ $code, ] = run_tool( 'verify --dir=' . escapeshellarg( $out ) . ' --public-key=' . escapeshellarg( $other . '/public.key' ) );
check( 0 !== $code, 'verify refuses a second keypair (not the signer)' );

$tampered = $work . '/tampered';
mkdir( $tampered );
copy( $out . '/manifest.json', $tampered . '/manifest.json' );
copy( $out . '/manifest.json.sig', $tampered . '/manifest.json.sig' );
copy( $out . '/flavor-1.7.0.zip', $tampered . '/flavor-1.7.0.zip' );
file_put_contents( $tampered . '/manifest.json', str_replace( '"1.7.0"', '"1.7.1"', (string) file_get_contents( $tampered . '/manifest.json' ) ) );
[ $code, ] = run_tool( 'verify --dir=' . escapeshellarg( $tampered ) . ' --public-key=' . escapeshellarg( $keys . '/public.key' ) );
check( 0 !== $code, 'verify refuses a manifest changed after signing' );

copy( $out . '/manifest.json', $tampered . '/manifest.json' );
file_put_contents( $tampered . '/flavor-1.7.0.zip', 'not the package' );
[ $code, ] = run_tool( 'verify --dir=' . escapeshellarg( $tampered ) . ' --public-key=' . escapeshellarg( $keys . '/public.key' ) );
check( 0 !== $code, 'verify refuses a package whose sha256 or size differs' );

copy( $out . '/flavor-1.7.0.zip', $tampered . '/flavor-1.7.0.zip' );
unlink( $tampered . '/flavor-1.7.0.zip' );
[ $code, ] = run_tool( 'verify --dir=' . escapeshellarg( $tampered ) . ' --public-key=' . escapeshellarg( $keys . '/public.key' ) );
check( 0 !== $code, 'verify refuses a release whose package file is missing' );

[ $code, ] = run_tool(
	'verify --dir=' . escapeshellarg( $out ) . ' --public-key=' . escapeshellarg( $keys . '/public.key' ) .
	' --url-prefix=' . escapeshellarg( 'https://elsewhere.example.com/' )
);
check( 0 !== $code, 'verify refuses a package URL outside the expected prefix' );

echo "\n--- 5. the release site holds only public material ---\n";
$site = $repo . '/release-site';
check( is_file( $site . '/index.html' ), 'release-site/index.html exists' );
check( is_file( $site . '/.nojekyll' ), 'release-site/.nojekyll exists (Pages serves underscore paths)' );
check( is_file( $site . '/status.txt' ) && 'ok' === trim( (string) file_get_contents( $site . '/status.txt' ) ), 'release-site/status.txt holds "ok"' );

$leaks = array();
if ( is_dir( $site ) ) {
	$iter = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $site, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iter as $file ) {
		$name = $file->getFilename();
		if ( preg_match( '/secret|private|\.pem$|\.key$/i', $name ) && 'public.key' !== $name ) {
			$leaks[] = $name;
		}
	}
}
check( array() === $leaks, 'release-site contains no secret or private key file' );

if ( is_file( $site . '/public.key' ) ) {
	$raw = base64_decode( trim( (string) file_get_contents( $site . '/public.key' ) ), true );
	check( false !== $raw && 32 === strlen( $raw ), 'release-site/public.key is a 32-byte Ed25519 public key' );
} else {
	check( true, 'release-site has no public.key yet (the owner commits it after keygen)' );
}

$html = is_file( $site . '/index.html' ) ? (string) file_get_contents( $site . '/index.html' ) : '';
check( 1 !== preg_match( '/(src|href)\s*=\s*["\']?(https?:)?\/\//i', $html ), 'index.html loads no external resource' );

$workflow = (string) @file_get_contents( $repo . '/.github/workflows/publish-release.yml' );
check( '' !== $workflow, 'the publish workflow exists' );
check( false === strpos( $workflow, 'FLAVOR_RELEASE_SECRET_KEY' ) && false === strpos( $workflow, 'secrets.' ), 'the publish workflow never sees a signing secret' );
check( false !== strpos( $workflow, 'verify' ), 'the publish workflow verifies the manifest before deploying' );

echo "\n--- 6. secret patterns are ignored by git ---\n";
if ( is_dir( $repo . '/.git' ) && '' !== trim( (string) shell_exec( 'command -v git' ) ) ) {
	foreach ( array( 'secret.key', 'flavor.secret.key', 'release-keys/x.txt' ) as $sample ) {
		exec( 'git -C ' . escapeshellarg( $repo ) . ' check-ignore -q ' . escapeshellarg( $sample ), $_, $ignored );
		check( 0 === $ignored, "git ignores {$sample}" );
	}
} else {
	echo "  [SKIP] git is not available here\n";
}

rm_tree( $work );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";
exit( $failed > 0 ? 1 : 0 );
