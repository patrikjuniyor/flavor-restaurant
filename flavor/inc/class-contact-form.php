<?php
/**
 * Contact endpoint for the theme's message form (prompt 53).
 *
 * The contact page had no form at all — visitors could only use the listed
 * phone, address and e-mail. This adds the theme's first submitting form:
 * `flavor/v1/contact` accepts a name, a message and an optional reply
 * channel, and mails it to the store's contact address. The form itself
 * (template-contact.php) and its script (contact.js) render only when that
 * address is configured; without it the page keeps showing the direct
 * channels alone and the endpoint answers with a clear 503.
 *
 * Security posture mirrors the Core store routes: the wp_rest nonce travels
 * in X-WP-Nonce, every field is sanitised, a honeypot absorbs bots, a
 * per-IP transient rate-limits delivery, and nothing about the message is
 * stored — delivery is one-way through wp_mail().
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the contact route and delivers messages.
 */
class Contact_Form {

	/**
	 * Hook the route registration.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Register POST flavor/v1/contact.
	 */
	public static function routes(): void {
		register_rest_route(
			'flavor/v1',
			'/contact',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'deliver' ),
				'permission_callback' => array( __CLASS__, 'nonce' ),
			)
		);
	}

	/**
	 * The same nonce gate the Core store routes use (RestStore::nonce).
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 */
	public static function nonce( \WP_REST_Request $request ): bool {
		return (bool) wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
	}

	/**
	 * The store's reply address; the form renders only when it is valid.
	 */
	public static function recipient(): string {
		$email = (string) get_theme_mod( 'flavor_ui_contact_email', '' );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Validate and deliver one message.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 */
	public static function deliver( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		/* Honeypot: a filled "website" field means a bot — report success
		   without sending anything, so the bot gets no signal to adapt to. */
		if ( '' !== trim( (string) ( $params['website'] ?? '' ) ) ) {
			return self::respond( true, self::sent_message() );
		}

		$recipient = self::recipient();
		if ( '' === $recipient ) {
			return self::respond( false, __( 'ایمیل تماس مجموعه تنظیم نشده است؛ لطفاً از شمارهٔ تلفن درج‌شده استفاده کنید.', 'flavor' ), 'recipient_missing', 503 );
		}

		// Rate limit: three delivered messages per IP hash per ten minutes.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
		$key  = 'flavor_contact_' . md5( $ip . wp_salt( 'nonce' ) );
		$hits = (int) get_transient( $key );
		if ( $hits >= 3 ) {
			return self::respond( false, __( 'پیام‌های زیادی از اتصال شما ارسال شده است؛ چند دقیقه بعد دوباره تلاش کنید.', 'flavor' ), 'rate_limited', 429 );
		}

		$name    = sanitize_text_field( (string) ( $params['name'] ?? '' ) );
		$message = sanitize_textarea_field( (string) ( $params['message'] ?? '' ) );
		$mobile  = preg_replace( '/\D/', '', (string) ( $params['mobile'] ?? '' ) );
		$email   = sanitize_email( (string) ( $params['email'] ?? '' ) );

		if ( '' === $name ) {
			return self::respond( false, __( 'نام خود را وارد کنید؛ بدون نام، پیام قابل پیگیری نیست.', 'flavor' ), 'name_required', 400 );
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $message ) : strlen( $message );
		if ( $length < 10 ) {
			return self::respond( false, __( 'پیام را کامل‌تر بنویسید؛ حداقل ۱۰ نویسه لازم است.', 'flavor' ), 'message_short', 400 );
		}
		if ( '' === $mobile && '' === $email ) {
			return self::respond( false, __( 'شمارهٔ موبایل یا ایمیل را وارد کنید تا بتوانیم پاسخ دهیم.', 'flavor' ), 'reply_required', 400 );
		}
		if ( '' !== $mobile && ! preg_match( '/^09\d{9}$/', $mobile ) ) {
			return self::respond( false, __( 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود؛ مانند ۰۹۱۲۱۲۳۴۵۶۷.', 'flavor' ), 'mobile_invalid', 400 );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			return self::respond( false, __( 'قالب ایمیل درست نیست؛ کامل وارد کنید، مانند name@example.com.', 'flavor' ), 'email_invalid', 400 );
		}

		$subject = sprintf(
			/* translators: %s visitor name. */
			__( 'پیام تماس از %s', 'flavor' ),
			$name
		);
		$lines   = array( $message, '' );
		$lines[] = sprintf(
			/* translators: %s visitor name. */
			__( 'نام: %s', 'flavor' ),
			$name
		);
		if ( '' !== $mobile ) {
			$lines[] = sprintf(
				/* translators: %s mobile number. */
				__( 'موبایل: %s', 'flavor' ),
				$mobile
			);
		}
		if ( '' !== $email ) {
			$lines[] = sprintf(
				/* translators: %s e-mail address. */
				__( 'ایمیل: %s', 'flavor' ),
				$email
			);
		}
		$lines[] = sprintf(
			/* translators: %s submission time in the site timezone. */
			__( 'ارسال‌شده در: %s', 'flavor' ),
			wp_date( 'Y-m-d H:i' )
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( '' !== $email ) {
			$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
		}

		if ( ! wp_mail( $recipient, $subject, implode( "\n", $lines ), $headers ) ) {
			return self::respond( false, __( 'ارسال پیام ناموفق بود؛ کمی بعد دوباره تلاش کنید یا تلفنی هماهنگ کنید.', 'flavor' ), 'mail_failed', 500 );
		}

		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		return self::respond( true, self::sent_message() );
	}

	/**
	 * Success copy — one place, so the human and bot paths never diverge.
	 */
	private static function sent_message(): string {
		return __( 'پیام شما ارسال شد؛ از راهی که وارد کرده‌اید پاسخ می‌دهیم.', 'flavor' );
	}

	/**
	 * Answer in the envelope ui.request() reads.
	 *
	 * @param bool   $ok      Success flag.
	 * @param string $message Visitor-facing copy.
	 * @param string $code    Machine code for failures.
	 * @param int    $status  HTTP status.
	 */
	private static function respond( bool $ok, string $message, string $code = '', int $status = 200 ): \WP_REST_Response {
		$body = array(
			'success' => $ok,
			'message' => $message,
		);
		if ( $ok ) {
			$body['data'] = array( 'message' => $message );
		} else {
			$body['code'] = $code;
		}
		return new \WP_REST_Response( $body, $status );
	}
}
