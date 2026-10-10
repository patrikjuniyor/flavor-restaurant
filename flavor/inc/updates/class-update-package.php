<?php
/**
 * Checks on a downloaded update package before WordPress unpacks it.
 *
 * Three independent checks, each of which fails the update on its own:
 *  1. the file's size and sha256 match the signed manifest;
 *  2. every archive entry stays inside the theme folder (no zip-slip);
 *  3. the archive is not absurdly large in entry count.
 *
 * @package Flavor
 */

namespace Flavor\Updates;

defined( 'ABSPATH' ) || exit;

/**
 * Class Package
 */
final class Package {

	/** Entry-count ceiling. The theme has a few hundred files, not thousands. */
	public const MAX_ENTRIES = 5000;

	/**
	 * Confirm the downloaded file is exactly the one the manifest describes.
	 *
	 * @param string $path   Local path of the downloaded file.
	 * @param string $sha256 Expected lowercase hex digest.
	 * @param int    $size   Expected size in bytes.
	 * @return void
	 * @throws Update_Error On a size or digest mismatch.
	 */
	public static function verify_file( string $path, string $sha256, int $size ): void {
		if ( ! is_file( $path ) ) {
			throw new Update_Error( 'missing', 'فایل دانلودشدهٔ به‌روزرسانی پیدا نشد.' );
		}
		$actual = filesize( $path );
		if ( false === $actual || $actual !== $size ) {
			throw new Update_Error( 'size', 'حجم فایل دانلودشده با مانیفست برابر نیست؛ نصب متوقف شد.' );
		}
		$digest = hash_file( 'sha256', $path );
		if ( false === $digest || ! hash_equals( $sha256, $digest ) ) {
			throw new Update_Error( 'sha256', 'checksum فایل دانلودشده با مانیفست برابر نیست؛ نصب متوقف شد.' );
		}
	}

	/**
	 * Check archive entry names. Pure, so it can be tested without a ZIP.
	 *
	 * @param array  $names Entry names as stored in the archive.
	 * @param string $root  The only allowed top-level folder, e.g. "flavor".
	 * @return void
	 * @throws Update_Error When any entry escapes the root or is malformed.
	 */
	public static function check_entries( array $names, string $root ): void {
		if ( count( $names ) > self::MAX_ENTRIES ) {
			throw new Update_Error( 'too_many', 'تعداد فایل‌های بسته از حد مجاز بیشتر است.' );
		}
		foreach ( $names as $name ) {
			if ( ! is_string( $name ) || '' === $name ) {
				throw new Update_Error( 'path', 'یکی از مسیرهای بسته نامعتبر است.' );
			}
			// Backslashes, NUL bytes, absolute paths and drive letters are never legitimate.
			if ( false !== strpbrk( $name, "\0\\" ) || '/' === $name[0] || 1 === preg_match( '#^[A-Za-z]:#', $name ) ) {
				throw new Update_Error( 'path', 'بسته شامل مسیر مطلق یا نامعتبر است.' );
			}
			$parts = explode( '/', rtrim( $name, '/' ) );
			if ( in_array( '', $parts, true ) || in_array( '.', $parts, true ) || in_array( '..', $parts, true ) ) {
				throw new Update_Error( 'path', 'بسته شامل مسیر «..» یا نامعتبر است.' );
			}
			if ( $root !== $parts[0] ) {
				throw new Update_Error( 'root', 'ساختار بسته نامعتبر است؛ ریشه باید «' . $root . '» باشد.' );
			}
		}
	}

	/**
	 * Check every entry of a ZIP archive.
	 *
	 * @param string $zip_path Local path to the ZIP.
	 * @param string $root     Allowed top-level folder.
	 * @return void
	 * @throws Update_Error When the archive is unreadable or unsafe.
	 */
	public static function check_zip( string $zip_path, string $root ): void {
		if ( ! class_exists( '\ZipArchive' ) ) {
			throw new Update_Error( 'zip', 'افزونهٔ zip روی این سرور فعال نیست؛ بسته بررسی نشد.' );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new Update_Error( 'zip', 'بستهٔ ZIP خراب است.' );
		}
		$names = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			$name = $zip->getNameIndex( $i );
			if ( false === $name ) {
				$zip->close();
				throw new Update_Error( 'zip', 'یکی از فایل‌های بسته قابل خواندن نیست.' );
			}
			$names[] = $name;
		}
		$zip->close();
		self::check_entries( $names, $root );
	}
}
