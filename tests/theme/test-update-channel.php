<?php
/**
 * Update channel contract tests (M-04).
 *
 * Covers the server-independent half of the channel: signatures, manifest
 * validation, package checks, backup and rollback, the release tool's
 * round trip, and the guarantee that an unconfigured channel makes no request.
 *
 *   php tests/theme/test-update-channel.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/updates/class-update-error.php';
require_once FLAVOR_DIR . '/inc/updates/class-update-signature.php';
require_once FLAVOR_DIR . '/inc/updates/class-update-manifest.php';
require_once FLAVOR_DIR . '/inc/updates/class-update-package.php';
require_once FLAVOR_DIR . '/inc/updates/class-update-backup.php';
require_once FLAVOR_DIR . '/inc/class-update-channel.php';

use Flavor\Update_Channel;
use Flavor\Updates\Backup;
use Flavor\Updates\Manifest;
use Flavor\Updates\Package;
use Flavor\Updates\Signature;
use Flavor\Updates\Update_Error;

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

/** True when $fn throws an Update_Error with the given code. */
function throws_code( callable $fn, string $code ): bool {
	try {
		$fn();
	} catch ( Update_Error $e ) {
		return $e->code_name() === $code;
	}
	return false;
}

/** Recursively delete a test directory. */
function rm_tree( string $dir ): void {
	if ( is_link( $dir ) || is_file( $dir ) ) {
		@unlink( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return;
	}
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( array_diff( (array) scandir( $dir ), array( '.', '..' ) ) as $entry ) {
		rm_tree( $dir . '/' . $entry );
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

$work = sys_get_temp_dir() . '/flavor-upd-' . bin2hex( random_bytes( 4 ) );
mkdir( $work, 0700, true );

$now  = 1800000000;
$keys = Signature::generate_keypair();
$pub  = $keys['public'];
$sec  = $keys['secret'];

$good = array(
	'schema'       => 1,
	'product'      => 'flavor',
	'version'      => '1.7.0',
	'expires'      => $now + 1000,
	'released'     => '2026-10-20',
	'requires_php' => '8.0',
	'requires_wp'  => '6.4',
	'changelog'    => array( 'تغییر یک', 'تغییر دو' ),
	'package'      => array(
		'url'    => 'https://example.com/flavor-1.7.0.zip',
		'sha256' => str_repeat( 'a', 64 ),
		'size'   => 1400000,
	),
);

echo "\n--- 1. Signatures ---\n";
$json = Manifest::build( $good, $now );
$sig  = Signature::sign( $json, $sec );
check( Signature::verify( $json, $sig, $pub ), 'a signature from the matching key verifies' );
check( ! Signature::verify( $json . ' ', $sig, $pub ), 'one changed byte in the manifest fails verification' );
$other = Signature::generate_keypair();
check( ! Signature::verify( $json, $sig, $other['public'] ), 'a signature checked against a different key fails' );
check( ! Signature::verify( $json, 'not base64 !!', $pub ), 'a non-base64 signature fails closed' );
check( ! Signature::verify( $json, base64_encode( str_repeat( "\0", 10 ) ), $pub ), 'a short signature fails closed' );
check( throws_code( fn() => Signature::sign( $json, 'AAAA' ), 'key' ), 'a malformed secret key is refused' );

echo "\n--- 2. Manifest: signature gate and replay ---\n";
$m = Manifest::verified( $json, $sig, $pub, $now );
check( '1.7.0' === $m['version'] && 1400000 === $m['package']['size'], 'a verified manifest is returned normalised' );
check( throws_code( fn() => Manifest::verified( $json, $sig, $other['public'], $now ), 'signature' ), 'a manifest signed by another key is rejected' );
check( throws_code( fn() => Manifest::verified( $json, $sig, $pub, $now + 5000 ), 'expired' ), 'an expired manifest is rejected (no replay of an old release)' );
check( throws_code( fn() => Manifest::validate( $json, $now + 1000 ), 'expired' ), 'expiry is exact: now equal to expires is already expired' );

echo "\n--- 3. Manifest: field validation (signed but wrong) ---\n";
$raw = static function ( array $change ) use ( $good ): string {
	return (string) json_encode( array_replace_recursive( $good, $change ), JSON_UNESCAPED_SLASHES );
};
check( throws_code( fn() => Manifest::validate( $raw( array( 'product' => 'other' ) ), $now ), 'product' ), 'another product is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'schema' => 2 ) ), $now ), 'schema' ), 'an unknown schema is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'version' => '1.7' ) ), $now ), 'version' ), 'a two-part version is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'package' => array( 'url' => 'http://example.com/a.zip' ) ) ), $now ), 'url' ), 'a plain http package URL is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'package' => array( 'url' => 'https://' ) ) ), $now ), 'url' ), 'an https URL without a host is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'package' => array( 'sha256' => strtoupper( str_repeat( 'a', 64 ) ) ) ) ), $now ), 'sha256' ), 'an upper-case checksum is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'package' => array( 'size' => 0 ) ) ), $now ), 'size' ), 'a zero-byte package is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'package' => array( 'size' => 900000000 ) ) ), $now ), 'size' ), 'a package over the size ceiling is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'changelog' => array_fill( 0, 31, 'x' ) ) ), $now ), 'changelog' ), 'more than 30 changelog lines is refused' );
check( throws_code( fn() => Manifest::validate( $raw( array( 'expires' => 'soon' ) ), $now ), 'expires' ), 'a non-integer expiry is refused' );
check( throws_code( fn() => Manifest::validate( '{not json', $now ), 'json' ), 'broken JSON is refused' );
$no_package = $good;
unset( $no_package['package'] );
check( throws_code( fn() => Manifest::validate( (string) json_encode( $no_package ), $now ), 'package' ), 'a manifest without a package is refused' );

echo "\n--- 4. Manifest: never offer a downgrade ---\n";
$as_m = static function ( string $version ) use ( $good, $now ): array {
	return Manifest::validate( (string) json_encode( array_replace( $good, array( 'version' => $version ) ) ), $now );
};
check( Manifest::offers_update( $as_m( '1.7.0' ), '1.6.1' ), 'a newer version is offered' );
check( ! Manifest::offers_update( $as_m( '1.6.1' ), '1.6.1' ), 'the same version is not offered' );
check( ! Manifest::offers_update( $as_m( '1.5.9' ), '1.6.1' ), 'an older version is never offered' );
check( Manifest::offers_update( $as_m( '1.10.0' ), '1.9.9' ), 'versions compare numerically (1.10 > 1.9), not as text' );
check( throws_code( fn() => Manifest::build( array_replace( $good, array( 'version' => '1.7' ) ), $now ), 'version' ), 'build refuses to produce an invalid manifest' );

echo "\n--- 5. Package file checks ---\n";
$file = $work . '/pkg.bin';
file_put_contents( $file, 'hello' );
$digest = hash( 'sha256', 'hello' );
Package::verify_file( $file, $digest, 5 );
check( true, 'a file matching size and sha256 passes' );
check( throws_code( fn() => Package::verify_file( $file, $digest, 6 ), 'size' ), 'a size mismatch is refused' );
check( throws_code( fn() => Package::verify_file( $file, str_repeat( 'b', 64 ), 5 ), 'sha256' ), 'a checksum mismatch is refused' );
check( throws_code( fn() => Package::verify_file( $work . '/absent.zip', $digest, 5 ), 'missing' ), 'a missing file is refused' );

echo "\n--- 6. Archive entry checks (zip-slip) ---\n";
$ok_names = array( 'flavor/', 'flavor/style.css', 'flavor/inc/a.php' );
Package::check_entries( $ok_names, 'flavor' );
check( true, 'a normal theme archive passes' );
$bad_paths = array(
	'parent traversal'       => array( 'flavor/../../evil.php' ),
	'absolute path'          => array( '/etc/passwd' ),
	'windows backslash'      => array( 'flavor\\..\\evil.php' ),
	'drive letter'           => array( 'C:/evil.php' ),
	'dot segment'            => array( 'flavor/./x.php' ),
	'empty segment'          => array( 'flavor//x.php' ),
	'empty name'             => array( '' ),
	'NUL byte'               => array( "flavor/x\0.php" ),
);
foreach ( $bad_paths as $label => $names ) {
	check( throws_code( fn() => Package::check_entries( $names, 'flavor' ), 'path' ), "rejects {$label}" );
}
check( throws_code( fn() => Package::check_entries( array( 'other/style.css' ), 'flavor' ), 'root' ), 'rejects a second top-level folder' );
check( throws_code( fn() => Package::check_entries( array_fill( 0, 5001, 'flavor/x' ), 'flavor' ), 'too_many' ), 'rejects an archive with too many entries' );

$zip_path = $work . '/good.zip';
$zip      = new \ZipArchive();
$zip->open( $zip_path, \ZipArchive::CREATE );
$zip->addFromString( 'flavor/style.css', '/* theme */' );
$zip->addFromString( 'flavor/inc/a.php', '<?php' );
$zip->close();
Package::check_zip( $zip_path, 'flavor' );
check( true, 'a real ZIP with a normal layout passes' );

$evil_path = $work . '/evil.zip';
$zip       = new \ZipArchive();
$zip->open( $evil_path, \ZipArchive::CREATE );
$zip->addFromString( 'flavor/style.css', '/* theme */' );
$zip->addFromString( '../outside.php', '<?php // escape' );
$zip->close();
check( throws_code( fn() => Package::check_zip( $evil_path, 'flavor' ), 'path' ), 'a real ZIP with an escaping entry is refused' );

echo "\n--- 7. Backup and rollback ---\n";
$theme = $work . '/flavor';
mkdir( $theme . '/inc', 0755, true );
file_put_contents( $theme . '/style.css', 'v1 css' );
file_put_contents( $theme . '/inc/a.php', 'v1 php' );
$backups = new Backup( $work . '/flavor-backups' );
$path    = $backups->create( $theme, '1.6.1' );
check( is_file( $path . '/inc/a.php' ), 'a backup copies nested files' );
check( 1 === count( $backups->list() ) && '1.6.1' === $backups->list()[0]['version'], 'the backup is listed with its version' );
check( throws_code( fn() => $backups->create( $work . '/no-such-theme', '1.6.1' ), 'source' ), 'a missing theme folder produces no backup' );

file_put_contents( $theme . '/style.css', 'broken new css' );
file_put_contents( $theme . '/new-file.php', 'added later' );
$id = basename( $path );
$backups->restore( $id, $theme );
check( 'v1 css' === file_get_contents( $theme . '/style.css' ), 'restore brings back the backed-up content' );
check( ! file_exists( $theme . '/new-file.php' ), 'restore removes files that were added after the backup' );
$leftovers = array_filter( (array) scandir( $work ), static fn( $n ) => false !== strpos( $n, '.new-' ) || false !== strpos( $n, '.old-' ) );
check( array() === $leftovers, 'restore leaves no staging or old folders behind' );

check( throws_code( fn() => $backups->resolve( '../etc' ), 'id' ), 'a traversal id cannot resolve' );
check( throws_code( fn() => $backups->resolve( '.hidden' ), 'id' ), 'a dot-prefixed id cannot resolve' );
check( throws_code( fn() => $backups->restore( 'no-such-backup', $theme ), 'id' ), 'an unknown backup id is refused and the theme is untouched' );
check( 'v1 css' === file_get_contents( $theme . '/style.css' ), 'the theme is still intact after a refused restore' );

if ( function_exists( 'symlink' ) ) {
	$outside = $work . '/outside-secret.txt';
	file_put_contents( $outside, 'do not copy' );
	@symlink( $outside, $theme . '/link.txt' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( is_link( $theme . '/link.txt' ) ) {
		$linked = $backups->create( $theme, '1.6.1' );
		check( ! file_exists( $linked . '/link.txt' ), 'a symlink in the theme is not followed into the backup' );
		rm_tree( $theme . '/link.txt' );
	}
}

for ( $i = 0; $i < 5; $i++ ) {
	$backups->create( $theme, '1.6.1' );
}
$backups->prune( 3 );
check( 3 === count( $backups->list() ), 'prune keeps only the newest three backups' );

echo "\n--- 8. An unconfigured channel makes no request ---\n";
check( ! Update_Channel::enabled(), 'the channel is off by default' );
check( '' !== Update_Channel::disabled_reason(), 'an off channel says why it is off' );
check( null === Update_Channel::check(), 'checking an off channel returns null without any network call' );

echo "\n--- 9. Release tool round trip (sign, then verify as the site would) ---\n";
$keydir = $work . '/keys';
$outdir = $work . '/release';
mkdir( $keydir, 0700 );
mkdir( $outdir, 0755 );
$tool = dirname( __DIR__, 2 ) . '/dev-tools/release/build-release.php';
exec( 'php ' . escapeshellarg( $tool ) . ' keygen --out=' . escapeshellarg( $keydir ) . ' 2>&1', $out1, $code1 );
check( 0 === $code1 && is_file( $keydir . '/public.key' ), 'keygen writes a public and a secret key' );
$tool_pub = trim( (string) file_get_contents( $keydir . '/public.key' ) );
exec(
	'php ' . escapeshellarg( $tool ) . ' sign --zip=' . escapeshellarg( $zip_path ) .
	' --version=1.7.0 --url=' . escapeshellarg( 'https://example.com/flavor-1.7.0.zip' ) .
	' --secret-key=' . escapeshellarg( $keydir . '/secret.key' ) . ' --out=' . escapeshellarg( $outdir ) .
	' --released=2026-10-20 --requires-php=8.0 --requires-wp=6.4 2>&1',
	$out2,
	$code2
);
check( 0 === $code2 && is_file( $outdir . '/manifest.json' ), 'sign produces manifest.json and its signature' );
$m_json = (string) file_get_contents( $outdir . '/manifest.json' );
$m_sig  = (string) file_get_contents( $outdir . '/manifest.json.sig' );
$tool_m = Manifest::verified( $m_json, $m_sig, $tool_pub, time() );
check( '1.7.0' === $tool_m['version'], 'the site verifies the tool-signed manifest with the published key' );
check( hash_file( 'sha256', $zip_path ) === $tool_m['package']['sha256'], 'the manifest checksum matches the package' );

exec(
	'php ' . escapeshellarg( $tool ) . ' sign --zip=' . escapeshellarg( $evil_path ) .
	' --version=1.7.1 --url=' . escapeshellarg( 'https://example.com/x.zip' ) .
	' --secret-key=' . escapeshellarg( $keydir . '/secret.key' ) . ' --out=' . escapeshellarg( $outdir ) . ' 2>&1',
	$out3,
	$code3
);
check( 0 !== $code3, 'the tool refuses to sign a package with an escaping entry' );

rm_tree( $work );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
