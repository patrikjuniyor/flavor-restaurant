<?php
/**
 * Melipayamak (ملی پیامک) adapter.
 *
 * Hooks into the official plugin if present; otherwise the generic
 * `flavor_core_sms_send` filter / `wp_sms_send`.
 *
 * @package FlavorCore
 */

namespace FlavorCore\SMS\Providers;

use FlavorCore\SMS\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class MelipayamakProvider
 */
class MelipayamakProvider implements ProviderInterface {

	public function slug(): string {
		return 'melipayamak';
	}

	public function label(): string {
		return __( 'ملی پیامک', 'flavor-core' );
	}

	public function is_available(): bool {
		return class_exists( '\\Melipayamak\\MelipayamakApi' )
			|| function_exists( 'melipayamak' )
			|| defined( 'MELIPAYAMAK_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( string $mobile, string $message, array $context = array() ): array {
		if ( function_exists( 'wp_sms_send' ) ) {
			$result = wp_sms_send( $mobile, $message );
			$ok     = ! is_wp_error( $result );
			return array(
				'ok'    => $ok,
				'id'    => $ok ? (string) $result : null,
				'error' => $ok ? null : $result->get_error_message(),
			);
		}

		/**
		 * Let a glue snippet talk to the Melipayamak SDK.
		 *
		 * @param array<string, mixed>|null $sent    Return array or null to skip.
		 * @param string                    $mobile  Mobile.
		 * @param string                    $message Body.
		 * @param array<string, mixed>      $context Context.
		 */
		$sent = apply_filters( 'flavor_core_sms_melipayamak', null, $mobile, $message, $context );
		if ( is_array( $sent ) ) {
			return wp_parse_args(
				$sent,
				array(
					'ok'    => false,
					'id'    => null,
					'error' => null,
				)
			);
		}

		return array(
			'ok'    => false,
			'id'    => null,
			'error' => __( 'افزونه ملی پیامک فعال نیست.', 'flavor-core' ),
		);
	}
}
