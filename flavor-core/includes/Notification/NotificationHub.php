<?php
/**
 * Unified Multi-Channel Notification Hub.
 * Dispatches notifications across Web In-App, SMS, Email, and Mobile Push.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Notification;

use FlavorCore\Database\Schema;
use FlavorCore\Observability\StructuredLogger;
use FlavorCore\SMS\SmsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class NotificationHub
 */
class NotificationHub {

	/**
	 * Send multi-channel notification.
	 *
	 * @param array<string, mixed> $params Notification parameters:
	 *   - event (string)
	 *   - recipient_mobile (string|null)
	 *   - recipient_email (string|null)
	 *   - recipient_user_id (int|null)
	 *   - title (string)
	 *   - body (string)
	 *   - channels (array: ['sms', 'push', 'email', 'in_app'])
	 *   - meta (array)
	 * @return array<string, bool>
	 */
	public static function send( array $params ): array {
		$channels = $params['channels'] ?? array( 'sms', 'push', 'in_app' );
		$results  = array();

		// 1. SMS Channel
		if ( in_array( 'sms', $channels, true ) && ! empty( $params['recipient_mobile'] ) ) {
			$related = array();
			if ( ! empty( $params['related_type'] ) ) {
				$related['related_type'] = (string) $params['related_type'];
			}
			if ( ! empty( $params['related_id'] ) ) {
				$related['related_id'] = (int) $params['related_id'];
			}

			$sms_res = SmsManager::send(
				(string) $params['recipient_mobile'],
				(string) $params['body'],
				$params['event'] ?? 'general_notice',
				$related
			);
			$results['sms'] = ! is_wp_error( $sms_res );
		}

		// 2. Email Channel
		if ( in_array( 'email', $channels, true ) && ! empty( $params['recipient_email'] ) ) {
			$title   = $params['title'] ?? get_bloginfo( 'name' );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$html    = self::build_email_html( $title, (string) $params['body'] );
			$email_res = wp_mail( (string) $params['recipient_email'], $title, $html, $headers );
			$results['email'] = (bool) $email_res;
		}

		// 3. Mobile Push Channel
		if ( in_array( 'push', $channels, true ) && ! empty( $params['recipient_user_id'] ) ) {
			$push_res = self::dispatch_push( (int) $params['recipient_user_id'], (string) ( $params['title'] ?? '' ), (string) $params['body'], $params['meta'] ?? array() );
			$results['push'] = $push_res;
		}

		StructuredLogger::info( 'notifications', 'Notification dispatched', array( 'event' => $params['event'] ?? 'general', 'channels' => $channels, 'results' => $results ) );

		return $results;
	}

	/**
	 * Deliver push notification to the active device tokens of a user.
	 *
	 * Real delivery — not a hook: tokens are partitioned by platform and sent
	 * through {@see PushNotificationService::send_fcm()} /
	 * {@see PushNotificationService::send_apns()} with the provider-reported
	 * stale tokens deactivated as a token-rotation cleanup. The
	 * `flavor_dispatch_push_tokens` action fires afterwards, for observability
	 * only, and carries the delivery results.
	 *
	 * @param int                  $user_id Customer or staff ID.
	 * @param string               $title Title.
	 * @param string               $body Body.
	 * @param array<string, mixed> $data Custom data payload.
	 * @return bool True when at least one provider accepted a message.
	 */
	private static function dispatch_push( int $user_id, string $title, string $body, array $data = array() ): bool {
		$devices = PushNotificationService::get_user_devices( $user_id );

		$android_tokens = $devices['android'] ?? array();
		$ios_tokens     = $devices['ios'] ?? array();

		if ( empty( $android_tokens ) && empty( $ios_tokens ) ) {
			return false;
		}

		$results = array( 'fcm' => array(), 'apns' => array() );
		$stale   = array();

		if ( ! empty( $android_tokens ) ) {
			$fcm = PushNotificationService::send_fcm( $android_tokens, $title, $body, $data );
			$results['fcm'] = $fcm;
			$stale = array_merge( $stale, (array) ( $fcm['stale_tokens'] ?? array() ) );
		}

		if ( ! empty( $ios_tokens ) ) {
			$apns = PushNotificationService::send_apns( $ios_tokens, $title, $body, $data );
			$results['apns'] = $apns;
			$stale = array_merge( $stale, (array) ( $apns['stale_tokens'] ?? array() ) );
		}

		if ( ! empty( $stale ) ) {
			PushNotificationService::deactivate_tokens( $stale );
		}

		// Observability extension point — delivery itself must NOT depend on any listener.
		do_action( 'flavor_dispatch_push_tokens', array_merge( $android_tokens, $ios_tokens ), $title, $body, $data, $results );

		StructuredLogger::info(
			'push_notifications',
			'Push delivery completed',
			array(
				'user_id'   => $user_id,
				'android'   => count( $android_tokens ),
				'ios'       => count( $ios_tokens ),
				'stale'     => count( $stale ),
				'fcm_ok'    => ! empty( $results['fcm']['success'] ),
				'apns_ok'   => ! empty( $results['apns']['success'] ),
			)
		);

		return ! empty( $results['fcm']['success'] ) || ! empty( $results['apns']['success'] );
	}

	/**
	 * Format responsive HTML email wrapper.
	 */
	private static function build_email_html( string $title, string $body ): string {
		$site_name = get_bloginfo( 'name' );
		return "<!DOCTYPE html>
		<html dir='rtl' lang='fa'>
		<head><meta charset='UTF-8'><style>body{font-family:Tahoma,sans-serif;background:#f6f7f7;padding:20px;color:#333;}.card{background:#fff;border-radius:8px;padding:24px;max-width:560px;margin:0 auto;border:1px solid #e2e8f0;}.header{border-bottom:2px solid #e53935;padding-bottom:12px;margin-bottom:16px;font-size:18px;font-weight:bold;color:#1e293b;}.footer{margin-top:24px;font-size:12px;color:#94a3b8;text-align:center;}</style></head>
		<body>
		<div class='card'>
			<div class='header'>{$site_name} - {$title}</div>
			<div class='content'>" . nl2br( esc_html( $body ) ) . "</div>
			<div class='footer'>این پیام به صورت خودکار از سیستم رستوران {$site_name} ارسال شده است.</div>
		</div>
		</body>
		</html>";
	}
}
