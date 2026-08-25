<?php
/**
 * Kavenegar adapter.
 *
 * @package FlavorCore
 */

namespace FlavorCore\SMS\Providers;

use FlavorCore\SMS\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class KavenegarProvider
 */
class KavenegarProvider implements ProviderInterface {

	public function slug(): string {
		return 'kavenegar';
	}

	public function label(): string {
		return __( 'کاوه نگار', 'flavor-core' );
	}

	public function is_available(): bool {
		return class_exists( '\\Kavenegar\\KavenegarApi' )
			|| defined( 'KAVENEGAR_VERSION' )
			|| function_exists( 'kavenegar_send' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( string $mobile, string $message, array $context = array() ): array {
		if ( function_exists( 'kavenegar_send' ) ) {
			$ok = (bool) kavenegar_send( $mobile, $message );
			return array(
				'ok'    => $ok,
				'id'    => $ok ? 'kavenegar' : null,
				'error' => $ok ? null : __( 'ارسال کاوه‌نگار ناموفق بود.', 'flavor-core' ),
			);
		}
		if ( function_exists( 'wp_sms_send' ) ) {
			$result = wp_sms_send( $mobile, $message );
			$ok     = ! is_wp_error( $result );
			return array(
				'ok'    => $ok,
				'id'    => $ok ? (string) $result : null,
				'error' => $ok ? null : $result->get_error_message(),
			);
		}
		$sent = apply_filters( 'flavor_core_sms_kavenegar', null, $mobile, $message, $context );
		if ( is_array( $sent ) ) {
			return wp_parse_args( $sent, array( 'ok' => false, 'id' => null, 'error' => null ) );
		}
		return array(
			'ok'    => false,
			'id'    => null,
			'error' => __( 'افزونه کاوه‌نگار فعال نیست.', 'flavor-core' ),
		);
	}
}
