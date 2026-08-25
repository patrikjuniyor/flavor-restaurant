<?php
/**
 * Editable SMS templates.
 *
 * @package FlavorCore
 */

namespace FlavorCore\SMS;

use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Templates
 */
class Templates {

	/**
	 * Built-in templates.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'otp'                     => __( 'کد ورود رستوران مستقیم: {code}', 'flavor-core' ),
			'order_confirmation'      => __( '{customer_name} عزیز، سفارش {order_number} ثبت شد. مبلغ: {total}', 'flavor-core' ),
			'order_status'            => __( 'سفارش {order_number} الان «{status}» است.', 'flavor-core' ),
			'order_ready'             => __( 'سفارش {order_number} آماده است. بفرمایید.', 'flavor-core' ),
			'reservation_confirm'     => __( 'رزرو شما در {branch} برای {date} ساعت {time} تایید شد.', 'flavor-core' ),
			'reservation_reminder'    => __( 'یادآوری: رزرو شما دو ساعت دیگر در {branch} است.', 'flavor-core' ),
		);
	}

	/**
	 * Stored or default body.
	 */
	public static function get( string $event ): string {
		$all = Settings::get( 'sms_templates', array() );
		if ( is_array( $all ) && ! empty( $all[ $event ] ) ) {
			return (string) $all[ $event ];
		}
		$defaults = self::defaults();
		return $defaults[ $event ] ?? '';
	}

	/**
	 * Replace {placeholders}.
	 *
	 * @param string               $event   Event.
	 * @param array<string, string> $vars   Vars.
	 */
	public static function render( string $event, array $vars ): string {
		$body = self::get( $event );
		foreach ( $vars as $key => $value ) {
			$body = str_replace( '{' . $key . '}', (string) $value, $body );
		}
		return $body;
	}
}
