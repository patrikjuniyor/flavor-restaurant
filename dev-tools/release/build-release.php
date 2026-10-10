<?php
/**
 * Release tooling for the Flavor update channel.
 *
 * Runs on the release machine (keygen, sign) and in CI (verify). Never shipped
 * to customers: dev-tools/ is outside the sellable zip.
 *
 *   php dev-tools/release/build-release.php keygen --out=/secure/keys
 *   php dev-tools/release/build-release.php sign \
 *       --zip=/build/flavor-1.7.0.zip --version=1.7.0 \
 *       --url=https://patrikjuniyor.github.io/flavor-restaurant/flavor/flavor-1.7.0.zip \
 *       --secret-key=/secure/keys/secret.key --out=release-site/flavor \
 *       [--released=2026-10-20] [--expires-days=14] \
 *       [--requires-php=8.0] [--requires-wp=6.4] [--changelog=/build/changes.txt]
 *   php dev-tools/release/build-release.php verify \
 *       --dir=release-site/flavor --public-key=release-site/public.key \
 *       [--url-prefix=https://patrikjuniyor.github.io/flavor-restaurant/flavor/]
 *
 * Safety rules:
 *  - keygen and sign refuse any path inside this repository. The secret key
 *    must live outside the repo, so it cannot be committed by accident.
 *  - sign refuses a package whose archive entries escape the theme folder, and
 *    a package whose internal style.css version differs from --version.
 *  - verify is what CI runs before publishing: signature, expiry, sha256, size,
 *    archive structure, internal version, and the URL prefix.
 *
 * @package Flavor
 */

// The classes guard on ABSPATH; this CLI is not WordPress, so provide a sentinel.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Seconds in a day, without depending on WordPress constants. */
const DAY_IN_SECONDS_LOCAL = 86400;

const RELEASE_REPO_ROOT = __DIR__ . '/../..';

$root = dirname( __DIR__, 2 ) . '/flavor/inc/updates/';
require_once $root . 'class-update-error.php';
require_once $root . 'class-update-signature.php';
require_once $root . 'class-update-manifest.php';
require_once $root . 'class-update-package.php';

use Flavor\Updates\Manifest;
use Flavor\Updates\Package;
use Flavor\Updates\Signature;
use Flavor\Updates\Update_Error;

/**
 * Print an error and exit non-zero.
 *
 * @param string $message Message.
 * @return void
 */
function release_fail( string $message ): void {
	fwrite( STDERR, 'خطا: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Parse --key=value options.
 *
 * @param array $argv Raw arguments.
 * @return array<string, string>
 */
function release_options( array $argv ): array {
	$options = array();
	foreach ( array_slice( $argv, 2 ) as $arg ) {
		if ( 0 === strpos( $arg, '--' ) && false !== strpos( $arg, '=' ) ) {
			[ $key, $value ] = explode( '=', substr( $arg, 2 ), 2 );
			$options[ $key ] = $value;
		}
	}
	return $options;
}

/**
 * Whether a path (existing or not) resolves inside the repository.
 *
 * @param string $path Path as given on the command line.
 * @return bool
 */
function release_inside_repo( string $path ): bool {
	$repo = realpath( RELEASE_REPO_ROOT );
	if ( false === $repo ) {
		return false;
	}
	$real = realpath( $path );
	if ( false === $real ) {
		// Not created yet: judge by the nearest existing parent directory.
		$parent = realpath( dirname( $path ) );
		if ( false === $parent ) {
			return false;
		}
		$real = $parent . '/' . basename( $path );
	}
	return $real === $repo || 0 === strpos( $real, $repo . '/' );
}

/**
 * Read the Version header of flavor/style.css from inside a zip.
 *
 * @param string $zip_path Zip path.
 * @return string|null Version, or null when not found.
 */
function release_zip_version( string $zip_path ): ?string {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return null;
	}
	$css = $zip->getFromName( 'flavor/style.css' );
	$zip->close();
	if ( false === $css ) {
		return null;
	}
	if ( 1 !== preg_match( '/^[\s*]*Version:\s*(\d+\.\d+\.\d+)/mi', $css, $m ) ) {
		return null;
	}
	return $m[1];
}

$command = $argv[1] ?? '';
$opts    = release_options( $argv );

if ( 'keygen' === $command ) {
	$out = rtrim( $opts['out'] ?? '', '/' );
	if ( '' === $out || ! is_dir( $out ) ) {
		release_fail( 'پوشهٔ خروجی --out باید از قبل وجود داشته باشد.' );
	}
	if ( release_inside_repo( $out ) ) {
		release_fail( 'کلیدها نباید داخل مخزن ساخته شوند. یک پوشهٔ خارج از مخزن برای --out بدهید.' );
	}
	if ( file_exists( $out . '/secret.key' ) || file_exists( $out . '/public.key' ) ) {
		release_fail( 'کلید از قبل در این پوشه هست؛ برای جلوگیری از بازنویسی متوقف شد.' );
	}
	try {
		$pair = Signature::generate_keypair();
	} catch ( Update_Error $e ) {
		release_fail( $e->getMessage() );
	}
	file_put_contents( $out . '/public.key', $pair['public'] . "\n" );
	file_put_contents( $out . '/secret.key', $pair['secret'] . "\n" );
	chmod( $out . '/secret.key', 0600 );
	echo "کلید عمومی: {$out}/public.key  (این فایل را در release-site/public.key کپی و commit کنید)\n";
	echo "کلید خصوصی: {$out}/secret.key  (هرگز وارد مخزن یا سایت نکنید)\n";
	exit( 0 );
}

if ( 'sign' === $command ) {
	foreach ( array( 'zip', 'version', 'url', 'secret-key', 'out' ) as $required ) {
		if ( empty( $opts[ $required ] ) ) {
			release_fail( "گزینهٔ --{$required} لازم است." );
		}
	}
	$zip = $opts['zip'];
	if ( ! is_file( $zip ) ) {
		release_fail( 'فایل ZIP پیدا نشد.' );
	}
	if ( ! is_file( $opts['secret-key'] ) ) {
		release_fail( 'فایل کلید خصوصی پیدا نشد.' );
	}
	if ( release_inside_repo( $opts['secret-key'] ) ) {
		release_fail( 'کلید خصوصی داخل مخزن است. آن را به پوشهٔ امن خارج از مخزن منتقل کنید.' );
	}
	if ( 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $opts['version'] ) ) {
		release_fail( 'نسخه باید به شکل x.y.z باشد.' );
	}
	if ( ! is_dir( $opts['out'] ) ) {
		release_fail( 'پوشهٔ --out پیدا نشد.' );
	}

	try {
		Package::check_zip( $zip, 'flavor' );
	} catch ( Update_Error $e ) {
		release_fail( 'بسته رد شد: ' . $e->getMessage() );
	}

	// A manifest that says 1.7.0 over a 1.6.1 archive would make every site
	// install 1.6.1 while believing it is 1.7.0. Refuse that before signing.
	$inside = release_zip_version( $zip );
	if ( null === $inside ) {
		release_fail( 'نسخه در flavor/style.css داخل ZIP پیدا نشد.' );
	}
	if ( $inside !== $opts['version'] ) {
		release_fail( "نسخهٔ داخل ZIP ({$inside}) با --version ({$opts['version']}) برابر نیست." );
	}

	$days    = isset( $opts['expires-days'] ) ? (int) $opts['expires-days'] : 14;
	$changes = array();
	if ( ! empty( $opts['changelog'] ) ) {
		$lines = file( $opts['changelog'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( false === $lines ) {
			release_fail( 'فایل تغییرات خوانده نشد.' );
		}
		$changes = array_map( 'trim', $lines );
	}

	$now    = time();
	$fields = array(
		'schema'       => Manifest::SCHEMA,
		'product'      => Manifest::PRODUCT,
		'version'      => $opts['version'],
		'expires'      => $now + max( 1, $days ) * DAY_IN_SECONDS_LOCAL,
		'released'     => $opts['released'] ?? gmdate( 'Y-m-d', $now ),
		'requires_php' => $opts['requires-php'] ?? '',
		'requires_wp'  => $opts['requires-wp'] ?? '',
		'changelog'    => $changes,
		'package'      => array(
			'url'    => $opts['url'],
			'sha256' => hash_file( 'sha256', $zip ),
			'size'   => (int) filesize( $zip ),
		),
	);

	try {
		$json      = Manifest::build( $fields, $now );
		$secret    = trim( (string) file_get_contents( $opts['secret-key'] ) );
		$signature = Signature::sign( $json, $secret );
	} catch ( Update_Error $e ) {
		release_fail( $e->getMessage() );
	}

	$out = rtrim( $opts['out'], '/' );
	file_put_contents( $out . '/manifest.json', $json );
	file_put_contents( $out . '/manifest.json.sig', $signature . "\n" );
	echo "مانیفست نوشته شد: {$out}/manifest.json\n";
	echo "امضا نوشته شد:     {$out}/manifest.json.sig\n";
	echo "sha256: {$fields['package']['sha256']}\n";
	exit( 0 );
}

if ( 'verify' === $command ) {
	$dir = rtrim( $opts['dir'] ?? '', '/' );
	$pub = $opts['public-key'] ?? '';
	if ( '' === $dir || ! is_file( $dir . '/manifest.json' ) || ! is_file( $dir . '/manifest.json.sig' ) ) {
		release_fail( 'در --dir باید manifest.json و manifest.json.sig باشند.' );
	}
	if ( '' === $pub || ! is_file( $pub ) ) {
		release_fail( 'فایل کلید عمومی (--public-key) پیدا نشد.' );
	}

	$json      = (string) file_get_contents( $dir . '/manifest.json' );
	$signature = (string) file_get_contents( $dir . '/manifest.json.sig' );
	$public    = trim( (string) file_get_contents( $pub ) );

	try {
		$manifest = Manifest::verified( $json, $signature, $public, time() );
	} catch ( Update_Error $e ) {
		release_fail( 'مانیفست رد شد: ' . $e->getMessage() );
	}

	$url = (string) $manifest['package']['url'];
	if ( ! empty( $opts['url-prefix'] ) && 0 !== strpos( $url, $opts['url-prefix'] ) ) {
		release_fail( 'نشانی بسته با پیشوند مورد انتظار یکی نیست: ' . $url );
	}

	// The package file is expected next to the manifest, named as in its URL.
	$zip = $dir . '/' . basename( (string) parse_url( $url, PHP_URL_PATH ) );
	if ( ! is_file( $zip ) ) {
		release_fail( 'فایل ZIP ' . basename( $zip ) . ' کنار مانیفست پیدا نشد.' );
	}

	try {
		Package::verify_file( $zip, $manifest['package']['sha256'], $manifest['package']['size'] );
		Package::check_zip( $zip, 'flavor' );
	} catch ( Update_Error $e ) {
		release_fail( 'بسته رد شد: ' . $e->getMessage() );
	}

	$inside = release_zip_version( $zip );
	if ( $inside !== $manifest['version'] ) {
		release_fail( "نسخهٔ داخل ZIP ({$inside}) با مانیفست ({$manifest['version']}) برابر نیست." );
	}

	echo "امضا معتبر است.\n";
	echo "نسخه: {$manifest['version']}\n";
	echo "مهلت مانیفست: " . gmdate( 'Y-m-d H:i', $manifest['expires'] ) . " (UTC)\n";
	echo "بسته: {$url}\n";
	echo "sha256 و حجم بسته با مانیفست یکی است؛ ساختار ZIP درست است.\n";
	exit( 0 );
}

echo <<<'USAGE'
استفاده:
  php dev-tools/release/build-release.php keygen --out=DIR            (خارج از مخزن)
  php dev-tools/release/build-release.php sign --zip=FILE --version=X.Y.Z --url=https://... --secret-key=FILE --out=DIR
           [--released=YYYY-MM-DD] [--expires-days=14] [--requires-php=8.0] [--requires-wp=6.4] [--changelog=FILE]
  php dev-tools/release/build-release.php verify --dir=DIR --public-key=FILE [--url-prefix=https://...]

USAGE;
exit( 1 );
