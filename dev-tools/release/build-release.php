<?php
/**
 * Release tooling for the Flavor update channel. Runs on the release machine only.
 *
 * Never shipped to customers (dev-tools/ is outside the sellable zip).
 *
 *   php dev-tools/release/build-release.php keygen --out=/secure/keys
 *   php dev-tools/release/build-release.php sign \
 *       --zip=/build/flavor-1.7.0.zip --version=1.7.0 \
 *       --url=https://releases.example.com/flavor-1.7.0.zip \
 *       --secret-key=/secure/keys/secret.key --out=/build/release \
 *       [--released=2026-10-20] [--expires-days=14] \
 *       [--requires-php=8.0] [--requires-wp=6.4] [--changelog=/build/changes.txt]
 *
 * `sign` refuses to produce a manifest for a package whose archive entries
 * escape the theme folder, so a bad build cannot be signed by accident.
 *
 * @package Flavor
 */

// The classes guard on ABSPATH; this CLI is not WordPress, so provide a sentinel.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Seconds in a day, without depending on WordPress constants. */
const DAY_IN_SECONDS_LOCAL = 86400;

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

$command = $argv[1] ?? '';
$opts    = release_options( $argv );

if ( 'keygen' === $command ) {
	$out = rtrim( $opts['out'] ?? '', '/' );
	if ( '' === $out || ! is_dir( $out ) ) {
		release_fail( 'پوشهٔ خروجی --out باید از قبل وجود داشته باشد.' );
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
	echo "کلید عمومی: {$out}/public.key  (در wp-config با FLAVOR_UPDATE_PUBLIC_KEY قرار دهید)\n";
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

echo <<<'USAGE'
استفاده:
  php dev-tools/release/build-release.php keygen --out=DIR
  php dev-tools/release/build-release.php sign --zip=FILE --version=X.Y.Z --url=https://... --secret-key=FILE --out=DIR
           [--released=YYYY-MM-DD] [--expires-days=14] [--requires-php=8.0] [--requires-wp=6.4] [--changelog=FILE]

USAGE;
exit( 1 );

