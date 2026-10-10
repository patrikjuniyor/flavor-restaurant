<?php
/**
 * Error raised by the update channel.
 *
 * Every rejection in the channel is one of these, carrying a stable machine
 * code and a Persian message that is safe to show an owner. Callers catch the
 * type rather than a generic Throwable, so an unrelated bug still surfaces.
 *
 * @package Flavor
 */

namespace Flavor\Updates;

defined( 'ABSPATH' ) || exit;

/**
 * Class Update_Error
 */
final class Update_Error extends \RuntimeException {

	/** @var string Stable code, e.g. "signature", "sha256", "expired". */
	private string $code_name;

	/**
	 * @param string $code    Stable machine code.
	 * @param string $message Persian message for the owner.
	 */
	public function __construct( string $code, string $message ) {
		parent::__construct( $message );
		$this->code_name = $code;
	}

	/**
	 * Stable machine code.
	 *
	 * @return string
	 */
	public function code_name(): string {
		return $this->code_name;
	}
}
