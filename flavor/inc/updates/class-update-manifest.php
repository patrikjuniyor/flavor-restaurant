<?php
/**
 * Signed update manifest: what version exists, where it is, and what it hashes to.
 *
 * The manifest is a JSON document signed as raw bytes. Verification happens
 * before any field is trusted, so the parser never has to defend against an
 * attacker-controlled document. Validation after the signature check is still
 * strict, because a correctly signed mistake is still a mistake.
 *
 * Example:
 *   {
 *     "schema": 1, "product": "flavor", "version": "1.7.0",
 *     "expires": 1790000000, "released": "2026-10-20",
 *     "package": {"url": "https://example.com/flavor-1.7.0.zip",
 *                 "sha256": "<64 hex>", "size": 1400000},
 *     "requires_php": "8.0", "requires_wp": "6.4",
 *     "changelog": ["..."]
 *   }
 *
 * @package Flavor
 */

namespace Flavor\Updates;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manifest
 */
final class Manifest {

	/** Manifest format version this code understands. */
	public const SCHEMA = 1;

	/** Only this product is accepted from a channel. */
	public const PRODUCT = 'flavor';

	/** Largest package accepted, in bytes (50 MB). */
	public const MAX_PACKAGE_BYTES = 52428800;

	/**
	 * Verify the signature, then parse and validate the manifest.
	 *
	 * @param string $json           Raw manifest bytes exactly as downloaded.
	 * @param string $signature_b64  Base64 signature from the .sig file.
	 * @param string $public_key_b64 Base64 public key the owner trusts.
	 * @param int    $now            Current unix time.
	 * @return array Normalised manifest.
	 * @throws Update_Error On any failure.
	 */
	public static function verified( string $json, string $signature_b64, string $public_key_b64, int $now ): array {
		if ( ! Signature::verify( $json, $signature_b64, $public_key_b64 ) ) {
			throw new Update_Error( 'signature', 'امضای مانیفست معتبر نیست؛ به‌روزرسانی رد شد.' );
		}
		return self::validate( $json, $now );
	}

	/**
	 * Parse and validate a manifest. Call only after the signature has passed.
	 *
	 * @param string $json Raw manifest JSON.
	 * @param int    $now  Current unix time.
	 * @return array Normalised manifest.
	 * @throws Update_Error On any failure.
	 */
	public static function validate( string $json, int $now ): array {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			throw new Update_Error( 'json', 'مانیفست به‌روزرسانی قابل خواندن نیست.' );
		}
		if ( self::SCHEMA !== ( $data['schema'] ?? null ) ) {
			throw new Update_Error( 'schema', 'نسخهٔ قالب مانیفست پشتیبانی نمی‌شود.' );
		}
		if ( self::PRODUCT !== ( $data['product'] ?? null ) ) {
			throw new Update_Error( 'product', 'این مانیفست برای Flavor نیست.' );
		}

		$version = $data['version'] ?? null;
		if ( ! is_string( $version ) || ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			throw new Update_Error( 'version', 'شمارهٔ نسخه در مانیفست نامعتبر است.' );
		}

		$expires = $data['expires'] ?? null;
		if ( ! is_int( $expires ) ) {
			throw new Update_Error( 'expires', 'مهلت مانیفست مشخص نشده است.' );
		}
		if ( $expires <= $now ) {
			// A signed but old manifest could be replayed to hide a newer release.
			throw new Update_Error( 'expired', 'مهلت این مانیفست گذشته است؛ دوباره بررسی کنید.' );
		}

		$package = $data['package'] ?? null;
		if ( ! is_array( $package ) ) {
			throw new Update_Error( 'package', 'اطلاعات بسته در مانیفست نیست.' );
		}
		$url = $package['url'] ?? null;
		if ( ! is_string( $url ) || 0 !== strpos( $url, 'https://' ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new Update_Error( 'url', 'نشانی بسته باید https و معتبر باشد.' );
		}
		$sha256 = $package['sha256'] ?? null;
		if ( ! is_string( $sha256 ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
			throw new Update_Error( 'sha256', 'checksum بسته در مانیفست نامعتبر است.' );
		}
		$size = $package['size'] ?? null;
		if ( ! is_int( $size ) || $size < 1 || $size > self::MAX_PACKAGE_BYTES ) {
			throw new Update_Error( 'size', 'حجم بسته در مانیفست نامعتبر است.' );
		}

		$changelog = $data['changelog'] ?? array();
		if ( ! is_array( $changelog ) || count( $changelog ) > 30 ) {
			throw new Update_Error( 'changelog', 'فهرست تغییرات در مانیفست نامعتبر است.' );
		}
		foreach ( $changelog as $line ) {
			if ( ! is_string( $line ) || strlen( $line ) > 300 ) {
				throw new Update_Error( 'changelog', 'یکی از خطوط تغییرات نامعتبر است.' );
			}
		}

		return array(
			'version'      => $version,
			'expires'      => $expires,
			'released'     => is_string( $data['released'] ?? null ) ? $data['released'] : '',
			'requires_php' => is_string( $data['requires_php'] ?? null ) ? $data['requires_php'] : '',
			'requires_wp'  => is_string( $data['requires_wp'] ?? null ) ? $data['requires_wp'] : '',
			'changelog'    => array_values( $changelog ),
			'package'      => array(
				'url'    => $url,
				'sha256' => $sha256,
				'size'   => $size,
			),
		);
	}

	/**
	 * Whether a validated manifest is newer than the installed version.
	 *
	 * Equal or older is never offered: a channel cannot push a downgrade.
	 *
	 * @param array  $manifest  Output of validate().
	 * @param string $installed Installed version, e.g. "1.6.1".
	 * @return bool
	 */
	public static function offers_update( array $manifest, string $installed ): bool {
		return version_compare( (string) $manifest['version'], $installed, '>' );
	}

	/**
	 * Build and validate a manifest JSON document (release tooling only).
	 *
	 * The returned string is the exact byte sequence that must be signed.
	 *
	 * @param array $fields Manifest fields, same shape as the example above.
	 * @param int   $now    Current unix time.
	 * @return string JSON.
	 * @throws Update_Error When the fields do not validate.
	 */
	public static function build( array $fields, int $now ): string {
		$json = json_encode(
			$fields,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		if ( false === $json ) {
			throw new Update_Error( 'json', 'مانیفست را نمی‌توان ساخت.' );
		}
		self::validate( $json, $now );
		return $json . "\n";
	}
}
