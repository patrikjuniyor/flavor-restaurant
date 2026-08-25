<?php
/**
 * Faraz SMS adapter.
 *
 * @package FlavorCore
 */

namespace FlavorCore\SMS\Providers;

use FlavorCore\SMS\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class FarazProvider
 */
class FarazProvider implements ProviderInterface {

	public function slug(): string {
		return 'faraz';
	}

	public function label(): string {
		return __( 'فراز اس‌ام‌اس', 'flavor-core' );
	}

	public function is_available(): bool {
		return defined( 'FARAZSMS_VERSION' )
			|| class_exists( '\\Farazsms\\Classes\\Sms' )
			|| function_exists( 'farazsms_send' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( string $mobile, string $message, array $context = array() ): array {
		if ( function_exists( 'farazsms_send' ) ) {
			$ok = (bool) farazsms_send( $mobile, $message );
			return array(
				'ok'    => $ok,
				'id'    => $ok ? 'faraz' : null,
				'error' => $ok ? null : __( 'ارسال فراز ناموفق بود.', 'flavor-core' ),
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
		$sent = apply_filters( 'flavor_core_sms_faraz', null, $mobile, $message, $context );
		if ( is_array( $sent ) ) {
			return wp_parse_args( $sent, array( 'ok' => false, 'id' => null, 'error' => null ) );
		}
		return array(
			'ok'    => false,
			'id'    => null,
			'error' => __( 'افزونه فراز اس‌ام‌اس فعال نیست.', 'flavor-core' ),
		);
	}
}
