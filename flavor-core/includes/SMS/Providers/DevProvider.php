<?php
/**
 * Development / fallback provider: writes to flavor_sms_log only.
 *
 * @package FlavorCore
 */

namespace FlavorCore\SMS\Providers;

use FlavorCore\SMS\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class DevProvider
 */
class DevProvider implements ProviderInterface {

	public function slug(): string {
		return 'dev';
	}

	public function label(): string {
		return __( 'حالت توسعه (بدون ارسال واقعی)', 'flavor-core' );
	}

	public function is_available(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( string $mobile, string $message, array $context = array() ): array {
		unset( $mobile, $message, $context );
		return array(
			'ok'    => true,
			'id'    => 'dev-' . wp_generate_password( 8, false ),
			'error' => null,
		);
	}
}
