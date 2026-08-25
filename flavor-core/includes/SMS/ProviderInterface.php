<?php
/**
 * SMS provider contract.
 *
 * @package FlavorCore
 */

namespace FlavorCore\SMS;

defined( 'ABSPATH' ) || exit;

/**
 * Interface ProviderInterface
 */
interface ProviderInterface {

	/**
	 * Machine slug.
	 */
	public function slug(): string;

	/**
	 * Human label.
	 */
	public function label(): string;

	/**
	 * Whether this driver can actually send (plugin/API present).
	 */
	public function is_available(): bool;

	/**
	 * Send a plain-text SMS.
	 *
	 * @param string               $mobile  Normalized 09xxxxxxxxx.
	 * @param string               $message Body.
	 * @param array<string, mixed> $context Extra.
	 * @return array{ok:bool,id:?string,error:?string}
	 */
	public function send( string $mobile, string $message, array $context = array() ): array;
}
