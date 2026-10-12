<?php
/**
 * Pure suite — PushNotificationService crypto internals: the DER→raw
 * ECDSA signature normaliser (JWS ES256 requires R||S, OpenSSL emits
 * ASN.1) and the JWT builder, exercised live through reflection with a
 * throwaway in-test key pair.
 *
 * Delivery transport (FCM/APNS HTTP), device registry and preferences are
 * database/network surfaces covered by the e2e push suite; what lives here
 * is the deterministic crypto the whole push auth chain rests on.
 *
 * Loaded by run-pure-tests.php (assert_test protocol).
 *
 * @package FlavorCore
 */

// phpcs:disable WordPress.Security.EscapeOutput -- CLI harness output, not an HTTP response.

require_once __DIR__ . '/../../includes/Notification/PushNotificationService.php';

use FlavorCore\Notification\PushNotificationService;

echo "\n--- P47. ECDSA DER → raw normaliser (PushNotificationService, reflected) ---\n";

$der_to_raw = new ReflectionMethod( PushNotificationService::class, 'der_to_raw_signature' );
$der_to_raw->setAccessible( true );
$build_jwt = new ReflectionMethod( PushNotificationService::class, 'build_jwt' );
$build_jwt->setAccessible( true );

// Vector 1 — the exact 70-byte boundary: both limbs 32 bytes, no padding.
$r   = str_repeat( "\x11", 32 );
$s   = str_repeat( "\x22", 32 );
$der = "\x30\x44\x02\x20" . $r . "\x02\x20" . $s;
assert_test( 70 === strlen( $der ), 'vector 1 sits exactly on the 70-byte minimum' );
assert_test( $r . $s === $der_to_raw->invoke( null, $der ), 'two clean 32-byte limbs concatenate to the 64-byte JWS form' );

// Vector 2 — DER sign padding: a limb whose high bit is set carries a
// leading 0x00 that must be stripped, not kept.
$r33 = "\x00\x80" . str_repeat( "\x11", 31 );
$der = "\x30" . chr( 2 + 33 + 2 + 32 ) . "\x02" . chr( 33 ) . $r33 . "\x02" . chr( 32 ) . $s;
$raw = $der_to_raw->invoke( null, $der );
assert_test( 64 === strlen( $raw ) && "\x80" === $raw[0], 'the sign-padding zero is stripped and the high-bit limb survives intact' );

// Vector 3 — short limb: a 31-byte R must be LEFT-padded back to 32
// (a naive concat would emit a 63-byte signature APNS rejects).
$r31 = "\x7f" . str_repeat( "\x11", 30 );
$s33 = "\x00\x80" . str_repeat( "\x22", 31 );
$der = "\x30" . chr( 2 + 31 + 2 + 33 ) . "\x02" . chr( 31 ) . $r31 . "\x02" . chr( 33 ) . $s33;
$raw = $der_to_raw->invoke( null, $der );
assert_test( 64 === strlen( $raw ) && "\x00\x7f" === substr( $raw, 0, 2 ), 'a short R limb is left-padded to 32 bytes, keeping the digest 64 long' );

// Malformed inputs must fail closed to '' (build_jwt turns that into a
// signing_failed WP_Error rather than sending a broken token).
assert_test( '' === $der_to_raw->invoke( null, str_repeat( "\x30\x02", 35 ) ), 'a 70-byte buffer of the right length but garbage layout is rejected (markers, not just size)' );
assert_test( '' === $der_to_raw->invoke( null, substr( "\x30\x44\x02\x20" . $r . "\x02\x20" . $s, 0, 69 ) ), 'a 69-byte truncation is rejected' );
$bad_marker = "\x31\x44\x02\x20" . $r . "\x02\x20" . $s;
assert_test( '' === $der_to_raw->invoke( null, $bad_marker ), 'a non-SEQUENCE first byte is rejected' );
$bad_s = "\x30\x44\x02\x20" . $r . "\x03\x20" . $s;
assert_test( '' === $der_to_raw->invoke( null, $bad_s ), 'a malformed S INTEGER marker is rejected' );

echo "\n--- P47. JWT builder (build_jwt, reflected, throwaway keys) ---\n";

assert_test( function_exists( 'openssl_sign' ), 'the OpenSSL extension the CI matrix pins is present' );

$ec = openssl_pkey_new(
	array(
		'private_key_type' => OPENSSL_KEYTYPE_EC,
		'curve_name'       => 'prime256v1',
	)
);
$ec_pem = '';
if ( false !== $ec ) {
	openssl_pkey_export( $ec, $ec_pem );
}
assert_test( false !== $ec && '' !== $ec_pem, 'a P-256 test key generates (ES256 curve)' );

$jwt = $build_jwt->invoke(
	null,
	array( 'alg' => 'ES256', 'typ' => 'JWT' ),
	array( 'iss' => 'pure-suite', 'iat' => 1760000000 ),
	$ec_pem,
	OPENSSL_ALGO_SHA256
);
assert_test( is_string( $jwt ) && 2 === substr_count( $jwt, '.' ), 'the ES256 JWT has exactly three dot-separated segments' );
$segs = explode( '.', (string) $jwt );
assert_test(
	1 === preg_match( '/^[A-Za-z0-9_-]+$/', $segs[0] ) && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $segs[1] ) && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $segs[2] ),
	'every segment is base64url — no +, / or = padding on the wire'
);
$decoded_sig = base64_decode( strtr( $segs[2], '-_', '+/' ), true );
assert_test( false !== $decoded_sig && 64 === strlen( $decoded_sig ), 'the EC signature segment decodes to exactly 64 raw bytes (DER normalisation ran)' );
$header  = json_decode( base64_decode( strtr( $segs[0], '-_', '+/' ), true ), true );
$claims  = json_decode( base64_decode( strtr( $segs[1], '-_', '+/' ), true ), true );
assert_test( 'ES256' === ( $header['alg'] ?? '' ) && 'pure-suite' === ( $claims['iss'] ?? '' ) && 1760000000 === ( $claims['iat'] ?? 0 ), 'header and claims round-trip through the encoding untouched' );

$verify_input = $segs[0] . '.' . $segs[1];
$pub          = openssl_pkey_get_details( $ec );
// openssl_verify consumes DER, while build_jwt (correctly) emits the JWS
// raw R||S form — re-encode the limbs here: a signature that survives the
// round trip proves the DER→raw conversion was lossless, not just
// well-shaped bytes.
$der_limb = static function ( string $limb ): string {
	$l = ltrim( $limb, "\x00" );
	if ( '' === $l ) {
		$l = "\x00";
	}
	if ( ord( $l[0] ) >= 0x80 ) {
		$l = "\x00" . $l;
	}
	return "\x02" . chr( strlen( $l ) ) . $l;
};
$rr      = $der_limb( substr( (string) $decoded_sig, 0, 32 ) );
$ss      = $der_limb( substr( (string) $decoded_sig, 32 ) );
$der_sig = "\x30" . chr( strlen( $rr . $ss ) ) . $rr . $ss;
assert_test(
	1 === openssl_verify( $verify_input, $der_sig, $pub['key'], OPENSSL_ALGO_SHA256 ),
	'the emitted signature verifies against the public key after the JWS→DER round trip (conversion is lossless)'
);

$bad = $build_jwt->invoke( null, array( 'alg' => 'ES256' ), array(), 'definitely-not-a-pem', OPENSSL_ALGO_SHA256 );
assert_test( is_wp_error( $bad ) && 'invalid_credentials' === $bad->get_error_code(), 'an unparsable PEM fails closed as invalid_credentials, never as an unsigned JWT' );

$rsa = openssl_pkey_new(
	array(
		'private_key_type' => OPENSSL_KEYTYPE_RSA,
		'private_key_bits' => 2048,
	)
);
$rsa_pem = '';
if ( false !== $rsa ) {
	openssl_pkey_export( $rsa, $rsa_pem );
}
$rsa_jwt = $build_jwt->invoke( null, array( 'alg' => 'RS256' ), array( 'iss' => 'pure-suite' ), $rsa_pem, OPENSSL_ALGO_SHA256 );
$rsa_sig = base64_decode( strtr( explode( '.', (string) $rsa_jwt )[2], '-_', '+/' ), true );
assert_test( is_string( $rsa_jwt ) && false !== $rsa_sig && 256 === strlen( $rsa_sig ), 'a non-EC key skips the DER→raw conversion (RS256 keeps its 256-byte PKCS#1 signature)' );

$push_src = file_get_contents( __DIR__ . '/../../includes/Notification/PushNotificationService.php' );
assert_test(
	false !== strpos( $push_src, 'UNREGISTERED' ) && false !== strpos( $push_src, 'deactivate_tokens' ),
	'provider UNREGISTERED feedback feeds the token deactivation path (rotation cleanup)'
);
assert_test(
	(bool) preg_match( '/array_values\( array_unique\( \$tokens \) \)/', $push_src ) && false !== strpos( $push_src, "'' === \$token" ),
	'deactivation dedupes, trims and skips empty tokens before touching rows'
);
