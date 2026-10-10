<?php
/**
 * Ed25519 signatures for update manifests (libsodium).
 *
 * The signature proves that a manifest came from the holder of the private
 * key. It does not restrict the GPL: source stays downloadable and the user
 * keeps every freedom the licence grants. It only lets the site refuse a
 * package that was altered on the way.
 *
 * Fails closed: without libsodium nothing verifies, so the channel stays shut.
 *
 * @package Flavor
 */

namespace Flavor\Updates;

defined( 'ABSPATH' ) || exit;

/**
 * Class Signature
 */
final class Signature {

	/**
	 * Whether libsodium is loaded.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return function_exists( 'sodium_crypto_sign_verify_detached' );
	}

	/**
	 * Verify a detached signature over the exact bytes of a manifest.
	 *
	 * @param string $message        Raw manifest bytes.
	 * @param string $signature_b64  Base64 signature (64 bytes once decoded).
	 * @param string $public_key_b64 Base64 public key (32 bytes once decoded).
	 * @return bool
	 * @throws Update_Error When libsodium is missing.
	 */
	public static function verify( string $message, string $signature_b64, string $public_key_b64 ): bool {
		if ( ! self::available() ) {
			throw new Update_Error( 'sodium', 'افزونهٔ sodium روی این سرور فعال نیست؛ بررسی امضای به‌روزرسانی ممکن نیست.' );
		}
		$signature = base64_decode( trim( $signature_b64 ), true );
		$public    = base64_decode( trim( $public_key_b64 ), true );
		if ( false === $signature || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $signature ) ) {
			return false;
		}
		if ( false === $public || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $public ) ) {
			return false;
		}
		return sodium_crypto_sign_verify_detached( $signature, $message, $public );
	}

	/**
	 * Sign a message with a base64 secret key (release tooling only).
	 *
	 * @param string $message        Raw manifest bytes.
	 * @param string $secret_key_b64 Base64 secret key (64 bytes once decoded).
	 * @return string Base64 signature.
	 * @throws Update_Error When the key is malformed or libsodium is missing.
	 */
	public static function sign( string $message, string $secret_key_b64 ): string {
		if ( ! self::available() ) {
			throw new Update_Error( 'sodium', 'افزونهٔ sodium در دسترس نیست.' );
		}
		$secret = base64_decode( trim( $secret_key_b64 ), true );
		if ( false === $secret || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
			throw new Update_Error( 'key', 'کلید خصوصی نامعتبر است.' );
		}
		return base64_encode( sodium_crypto_sign_detached( $message, $secret ) );
	}

	/**
	 * Generate a new keypair (release tooling only). Run it on the release
	 * machine, never on the customer's site.
	 *
	 * @return array{public: string, secret: string} Base64 encoded keys.
	 * @throws Update_Error When libsodium is missing.
	 */
	public static function generate_keypair(): array {
		if ( ! self::available() ) {
			throw new Update_Error( 'sodium', 'افزونهٔ sodium در دسترس نیست.' );
		}
		$pair = sodium_crypto_sign_keypair();
		return array(
			'public' => base64_encode( sodium_crypto_sign_publickey( $pair ) ),
			'secret' => base64_encode( sodium_crypto_sign_secretkey( $pair ) ),
		);
	}
}
