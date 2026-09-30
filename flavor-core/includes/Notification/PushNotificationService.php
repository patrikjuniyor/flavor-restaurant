<?php
/**
 * Production Push Notification Service (FCM & APNs).
 * Supports token registration, token rotation, multi-device per customer,
 * order lifecycle notifications, reservation reminders, and preference filtering.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Notification;

use FlavorCore\Database\Schema;
use FlavorCore\Observability\StructuredLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Class PushNotificationService
 */
class PushNotificationService {

	public const PREF_META_KEY = '_flavor_notification_preferences';

	/**
	 * Default user notification preferences.
	 */
	public const DEFAULT_PREFERENCES = array(
		'order_status'   => true,
		'reservations'   => true,
		'promotions'     => true,
		'loyalty_points' => true,
	);

	/**
	 * Register or update device token.
	 *
	 * @param int|null $user_id     User ID if authenticated.
	 * @param string   $device_token FCM or APNs device push token.
	 * @param string   $platform     android|ios|web.
	 * @param string   $app_version  Version string.
	 * @return bool
	 */
	public static function register_device( ?int $user_id, string $device_token, string $platform = 'android', string $app_version = '1.0.0' ): bool {
		$token    = trim( $device_token );
		$platform = sanitize_key( $platform );
		if ( '' === $token ) {
			return false;
		}

		if ( ! in_array( $platform, array( 'android', 'ios', 'web' ), true ) ) {
			$platform = 'android';
		}

		global $wpdb;
		$table = Schema::table( 'flavor_device_tokens' );
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->replace(
			$table,
			array(
				'user_id'      => $user_id && $user_id > 0 ? $user_id : null,
				'device_token' => $token,
				'platform'     => $platform,
				'app_version'  => sanitize_text_field( $app_version ),
				'is_active'    => 1,
				'last_seen_at' => $now,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		StructuredLogger::info(
			'push_notifications',
			'Device registered for push notifications',
			array(
				'user_id'  => $user_id,
				'platform' => $platform,
				'token_prefix' => substr( $token, 0, 8 ) . '...',
			)
		);

		return false !== $ok;
	}

	/**
	 * Unregister or deactivate device token.
	 *
	 * @param string $device_token Device token.
	 * @return bool
	 */
	public static function unregister_device( string $device_token ): bool {
		$token = trim( $device_token );
		if ( '' === $token ) {
			return false;
		}

		global $wpdb;
		$table = Schema::table( 'flavor_device_tokens' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->delete( $table, array( 'device_token' => $token ), array( '%s' ) );
		return false !== $ok;
	}

	/**
	 * Deactivate all devices for a user on logout.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function revoke_user_devices( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		global $wpdb;
		$table = Schema::table( 'flavor_device_tokens' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->update(
			$table,
			array( 'is_active' => 0 ),
			array( 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $ok;
	}

	/**
	 * Get active device tokens for a user grouped by platform.
	 *
	 * @param int $user_id User ID.
	 * @return array{android: string[], ios: string[], web: string[]}
	 */
	public static function get_user_devices( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array( 'android' => array(), 'ios' => array(), 'web' => array() );
		}

		global $wpdb;
		$table = Schema::table( 'flavor_device_tokens' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device_token, platform FROM {$table} WHERE user_id = %d AND is_active = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			),
			ARRAY_A
		);

		$out = array(
			'android' => array(),
			'ios'     => array(),
			'web'     => array(),
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$plat = $r['platform'] ?? 'android';
				if ( isset( $out[ $plat ] ) ) {
					$out[ $plat ][] = (string) $r['device_token'];
				}
			}
		}

		return $out;
	}

	/**
	 * Get user notification preferences.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, bool>
	 */
	public static function get_preferences( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return self::DEFAULT_PREFERENCES;
		}

		$meta = get_user_meta( $user_id, self::PREF_META_KEY, true );
		if ( ! is_array( $meta ) ) {
			return self::DEFAULT_PREFERENCES;
		}

		return array_merge( self::DEFAULT_PREFERENCES, $meta );
	}

	/**
	 * Update user notification preferences.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $prefs   Preferences map.
	 * @return bool
	 */
	public static function update_preferences( int $user_id, array $prefs ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$current = self::get_preferences( $user_id );
		foreach ( self::DEFAULT_PREFERENCES as $key => $default_val ) {
			if ( isset( $prefs[ $key ] ) ) {
				$current[ $key ] = (bool) $prefs[ $key ];
			}
		}

		return (bool) update_user_meta( $user_id, self::PREF_META_KEY, $current );
	}

	/**
	 * Dispatch order status change notification.
	 *
	 * @param int    $order_id   WC Order ID.
	 * @param string $new_status e.g. preparing, ready, completed, cancelled.
	 * @param int    $user_id    Customer User ID (0 for guest).
	 * @param string $mobile     Customer Mobile Phone.
	 * @return array<string, mixed>
	 */
	public static function dispatch_order_status( int $order_id, string $new_status, int $user_id = 0, string $mobile = '' ): array {
		$status_titles = array(
			'preparing' => __( 'سفارش شما در حال آماده‌سازی است', 'flavor-core' ),
			'ready'     => __( 'سفارش شما آماده است', 'flavor-core' ),
			'completed' => __( 'سفارش شما تحویل داده شد', 'flavor-core' ),
			'cancelled' => __( 'سفارش شما لغو شد', 'flavor-core' ),
		);

		$status_bodies = array(
			'preparing' => sprintf( __( 'آشپزخانه رستوران سفارش #%d را تایید کرد و در حال پخت است.', 'flavor-core' ), $order_id ),
			'ready'     => sprintf( __( 'سفارش #%d آماده سرو / تحویل به پیک اختصاصی شد.', 'flavor-core' ), $order_id ),
			'completed' => sprintf( __( 'نوش جان! سفارش #%d تحویل داده شد. ممنون از اعتماد شما.', 'flavor-core' ), $order_id ),
			'cancelled' => sprintf( __( 'سفارش #%d لغو گردید. در صورت بروز مشکل با پشتیبانی تماس بگیرید.', 'flavor-core' ), $order_id ),
		);

		$title = $status_titles[ $new_status ] ?? sprintf( __( 'بروزرسانی سفارش #%d', 'flavor-core' ), $order_id );
		$body  = $status_bodies[ $new_status ] ?? sprintf( __( 'وضعیت سفارش #%d به %s تغییر یافت.', 'flavor-core' ), $order_id, $new_status );

		$data = array(
			'type'     => 'order_status',
			'order_id' => (string) $order_id,
			'status'   => $new_status,
			'click_action' => "flavor://order/{$order_id}",
		);

		// Check preferences if authenticated
		if ( $user_id > 0 ) {
			$prefs = self::get_preferences( $user_id );
			if ( empty( $prefs['order_status'] ) ) {
				return array( 'status' => 'skipped_by_preference' );
			}
		}

		return NotificationHub::send(
			array(
				'event'             => 'order_status_' . $new_status,
				'recipient_user_id' => $user_id > 0 ? $user_id : null,
				'recipient_mobile'  => $mobile ?: null,
				'title'             => $title,
				'body'              => $body,
				'channels'          => array( 'push', 'sms', 'in_app' ),
				'meta'              => $data,
			)
		);
	}

	/**
	 * Dispatch reservation status change notification.
	 *
	 * @param int    $reservation_id Reservation ID.
	 * @param string $status         confirmed|reminder|cancelled.
	 * @param int    $user_id        Customer User ID.
	 * @param string $mobile         Customer Phone.
	 * @param array  $extra_info     Branch and time details.
	 * @return array<string, mixed>
	 */
	public static function dispatch_reservation_status( int $reservation_id, string $status, int $user_id = 0, string $mobile = '', array $extra_info = array() ): array {
		$date   = $extra_info['date'] ?? '';
		$time   = $extra_info['time'] ?? '';
		$branch = $extra_info['branch'] ?? get_bloginfo( 'name' );

		$titles = array(
			'confirmed' => __( 'رزرو میز شما تایید شد', 'flavor-core' ),
			'reminder'  => __( 'یادآوری رزرو میز', 'flavor-core' ),
			'cancelled' => __( 'رزرو میز لغو شد', 'flavor-core' ),
		);

		$bodies = array(
			'confirmed' => sprintf( __( 'رزرو میز شما در %s برای تاریخ %s ساعت %s با موفقیت تایید شد.', 'flavor-core' ), $branch, $date, $time ),
			'reminder'  => sprintf( __( 'یادآوری: رزرو میز شما در %s تا ۲ ساعت آینده (ساعت %s) می‌باشد.', 'flavor-core' ), $branch, $time ),
			'cancelled' => sprintf( __( 'رزرو میز شماره #%d در %s لغو شد.', 'flavor-core' ), $reservation_id, $branch ),
		);

		$title = $titles[ $status ] ?? __( 'اطلاعیه رزرو میز', 'flavor-core' );
		$body  = $bodies[ $status ] ?? sprintf( __( 'وضعیت رزرو #%d تغییر کرد.', 'flavor-core' ), $reservation_id );

		$data = array(
			'type'           => 'reservation_status',
			'reservation_id' => (string) $reservation_id,
			'status'         => $status,
			'click_action'   => 'flavor://reservation',
		);

		return NotificationHub::send(
			array(
				'event'             => 'reservation_' . $status,
				'recipient_user_id' => $user_id > 0 ? $user_id : null,
				'recipient_mobile'  => $mobile ?: null,
				'title'             => $title,
				'body'              => $body,
				'channels'          => array( 'push', 'sms', 'in_app' ),
				'meta'              => $data,
			)
		);
	}

	/**
	 * Send Firebase Cloud Messaging via the current HTTP v1 API.
	 *
	 * Credentials (service account) are resolved SECURELY, in order:
	 *  1. FLAVOR_FCM_SERVICE_ACCOUNT_JSON  — raw JSON (deployment secret managers).
	 *  2. FLAVOR_FCM_SERVICE_ACCOUNT_PATH  — absolute path to the JSON file OUTSIDE the web root.
	 *  3. Option 'flavor_fcm_service_account_path' — path only, never the JSON itself.
	 *
	 * The legacy FLAVOR_FCM_SERVER_KEY API was decommissioned by Google and is
	 * no longer supported. Docs: docs/PUSH-NOTIFICATIONS.md.
	 *
	 * @param string[]             $tokens Device FCM tokens.
	 * @param string               $title  Notification title.
	 * @param string               $body   Notification body.
	 * @param array<string, mixed> $data   Data dictionary (values coerced to string).
	 * @return array<string, mixed> success|error + sent + stale_tokens (unregistered devices).
	 */
	public static function send_fcm( array $tokens, string $title, string $body, array $data = array() ): array {
		$service_account = self::resolve_fcm_service_account();
		if ( is_wp_error( $service_account ) ) {
			return array(
				'success' => false,
				'error'   => $service_account->get_error_code(),
				'message' => $service_account->get_error_message(),
			);
		}

		$access_token = self::fcm_access_token( $service_account );
		if ( is_wp_error( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => $access_token->get_error_code(),
				'message' => $access_token->get_error_message(),
			);
		}

		$project_id = $service_account['project_id'];
		$endpoint   = sprintf( 'https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode( $project_id ) );

		$string_data = array();
		foreach ( $data as $key => $value ) {
			$string_data[ (string) $key ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		}

		$sent  = 0;
		$stale = array();
		$errors = array();

		foreach ( array_values( array_unique( $tokens ) ) as $token ) {
			$message  = array(
				'message' => array(
					'token'        => $token,
					'notification' => array(
						'title' => $title,
						'body'  => $body,
					),
					'data'         => $string_data,
					'android'      => array(
						'priority'     => 'HIGH',
						'notification' => array( 'sound' => 'default' ),
					),
					'apns'         => array(
						'headers' => array( 'apns-priority' => '10' ),
						'payload' => array( 'aps' => array( 'sound' => 'default' ) ),
					),
				),
			);
			$response = wp_remote_post(
				$endpoint,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json; charset=UTF-8',
					),
					'body'    => wp_json_encode( $message ),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$errors[] = $response->get_error_message();
				continue;
			}

			$code    = wp_remote_retrieve_response_code( $response );
			$json    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$status  = is_array( $json ) ? (string) ( $json['error']['status'] ?? '' ) : '';

			if ( 200 === $code ) {
				$sent++;
			} elseif ( 404 === $code || 'UNREGISTERED' === $status ) {
				$stale[] = $token;
			} else {
				$errors[] = is_array( $json ) ? (string) ( $json['error']['message'] ?? "HTTP {$code}" ) : "HTTP {$code}";
			}
		}

		return array(
			'success'      => $sent > 0,
			'sent'         => $sent,
			'stale_tokens' => $stale,
			'errors'       => $errors,
			'provider'     => 'fcm',
		);
	}

	/**
	 * Resolve the FCM service-account definition without ever persisting it.
	 *
	 * @return array{client_email: string, private_key: string, project_id: string, token_uri: string}|\WP_Error
	 */
	private static function resolve_fcm_service_account() {
		$raw_json = (string) getenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_JSON' );

		if ( '' === $raw_json ) {
			$path = (string) ( getenv( 'FLAVOR_FCM_SERVICE_ACCOUNT_PATH' ) ?: get_option( 'flavor_fcm_service_account_path', '' ) );
			if ( '' !== $path && is_readable( $path ) ) {
				$raw_json = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		if ( '' === $raw_json ) {
			StructuredLogger::warning( 'push_notifications', 'FCM service account is not configured' );
			return new \WP_Error(
				'missing_credentials',
				__( 'FCM service account is not configured (FLAVOR_FCM_SERVICE_ACCOUNT_JSON / FLAVOR_FCM_SERVICE_ACCOUNT_PATH).', 'flavor-core' )
			);
		}

		$sa = json_decode( $raw_json, true );
		$required = array( 'client_email', 'private_key', 'project_id', 'token_uri' );
		foreach ( $required as $field ) {
			if ( empty( $sa[ $field ] ) || ! is_string( $sa[ $field ] ) ) {
				return new \WP_Error( 'invalid_credentials', sprintf( 'FCM service account JSON missing field: %s', $field ) );
			}
		}

		return array(
			'client_email' => $sa['client_email'],
			'private_key'  => $sa['private_key'],
			'project_id'   => $sa['project_id'],
			'token_uri'    => $sa['token_uri'] ?: 'https://oauth2.googleapis.com/token',
		);
	}

	/**
	 * Exchange the service-account JWT (RS256) for an OAuth2 access token.
	 * Cached in a transient for (expires_in - 120s) as recommended by Google.
	 *
	 * @param array{client_email: string, private_key: string, token_uri: string} $service_account Service account.
	 * @return string|\WP_Error
	 */
	private static function fcm_access_token( array $service_account ) {
		$cache_key = 'flavor_fcm_oauth_' . md5( $service_account['client_email'] );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$jwt = self::build_jwt(
			array( 'alg' => 'RS256', 'typ' => 'JWT' ),
			array(
				'iss'   => $service_account['client_email'],
				'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
				'aud'   => $service_account['token_uri'],
				'iat'   => time(),
				'exp'   => time() + 3600,
			),
			$service_account['private_key'],
			OPENSSL_ALGO_SHA256
		);
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post(
			$service_account['token_uri'],
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
						'assertion'  => $jwt,
					),
					'',
					'&'
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'oauth_error', $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token = is_array( $json ) ? (string) ( $json['access_token'] ?? '' ) : '';

		if ( 200 !== $code || '' === $token ) {
			return new \WP_Error( 'oauth_error', sprintf( 'FCM OAuth token exchange failed (HTTP %s).', $code ) );
		}

		$ttl = max( 60, (int) ( $json['expires_in'] ?? 3600 ) - 120 );
		set_transient( $cache_key, $token, $ttl );
		return $token;
	}

	/**
	 * Send Apple Push Notifications via APNs provider API (token authentication).
	 *
	 * Configuration is separated from FCM and resolved SECURELY:
	 *  - FLAVOR_APNS_AUTH_KEY_PATH — absolute path to the AuthKey_<KEY_ID>.p8 file OUTSIDE the web root
	 *    (or option 'flavor_apns_auth_key_path' — path only, never the key content).
	 *  - FLAVOR_APNS_KEY_ID, FLAVOR_APNS_TEAM_ID, FLAVOR_APNS_BUNDLE_ID — env or options.
	 *  - FLAVOR_APNS_ENV — 'production' (default) or 'sandbox'.
	 *
	 * @param string[]             $tokens Device APNs tokens.
	 * @param string               $title  Notification title.
	 * @param string               $body   Notification body.
	 * @param array<string, mixed> $data   Custom data dictionary.
	 * @return array<string, mixed> success|error + sent + stale_tokens (410/invalid device tokens).
	 */
	public static function send_apns( array $tokens, string $title, string $body, array $data = array() ): array {
		$config = self::resolve_apns_config();
		if ( is_wp_error( $config ) ) {
			return array(
				'success' => false,
				'error'   => $config->get_error_code(),
				'message' => $config->get_error_message(),
			);
		}

		$jwt = self::build_jwt(
			array( 'alg' => 'ES256', 'kid' => $config['key_id'], 'typ' => 'JWT' ),
			array( 'iss' => $config['team_id'], 'iat' => time() ),
			$config['auth_key'],
			OPENSSL_ALGO_SHA256
		);
		if ( is_wp_error( $jwt ) ) {
			return array( 'success' => false, 'error' => $jwt->get_error_code(), 'message' => $jwt->get_error_message() );
		}

		$host = ( 'sandbox' === $config['env'] )
			? 'https://api.sandbox.push.apple.com'
			: 'https://api.push.apple.com';

		$aps = array(
			'aps' => array(
				'alert' => array(
					'title' => $title,
					'body'  => $body,
				),
				'sound' => 'default',
			),
		);
		$payload = array_merge( $aps, $data );

		$sent   = 0;
		$stale  = array();
		$errors = array();

		foreach ( array_values( array_unique( $tokens ) ) as $token ) {
			$response = wp_remote_post(
				$host . '/3/device/' . rawurlencode( $token ),
				array(
					'httpversion' => '2.0',
					'headers'     => array(
						'authorization'  => 'bearer ' . $jwt,
						'apns-topic'     => $config['bundle_id'],
						'apns-push-type' => 'alert',
						'apns-priority'  => '10',
						'content-type'   => 'application/json',
					),
					'body'        => wp_json_encode( $payload ),
					'timeout'     => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$errors[] = $response->get_error_message();
				continue;
			}

			$code   = wp_remote_retrieve_response_code( $response );
			$json   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$reason = is_array( $json ) ? (string) ( $json['reason'] ?? '' ) : '';

			if ( 200 === $code ) {
				$sent++;
			} elseif ( 410 === $code || in_array( $reason, array( 'BadDeviceToken', 'Unregistered' ), true ) ) {
				$stale[] = $token;
			} else {
				$errors[] = '' !== $reason ? $reason : "HTTP {$code}";
			}
		}

		return array(
			'success'      => $sent > 0,
			'sent'         => $sent,
			'stale_tokens' => $stale,
			'errors'       => $errors,
			'provider'     => 'apns',
		);
	}

	/**
	 * Resolve APNs configuration from env/options without persisting the key.
	 *
	 * @return array{key_id: string, team_id: string, bundle_id: string, auth_key: string, env: string}|\WP_Error
	 */
	private static function resolve_apns_config() {
		$key_id    = (string) ( getenv( 'FLAVOR_APNS_KEY_ID' ) ?: get_option( 'flavor_apns_key_id', '' ) );
		$team_id   = (string) ( getenv( 'FLAVOR_APNS_TEAM_ID' ) ?: get_option( 'flavor_apns_team_id', '' ) );
		$bundle_id = (string) ( getenv( 'FLAVOR_APNS_BUNDLE_ID' ) ?: get_option( 'flavor_apns_bundle_id', '' ) );
		$key_path  = (string) ( getenv( 'FLAVOR_APNS_AUTH_KEY_PATH' ) ?: get_option( 'flavor_apns_auth_key_path', '' ) );
		$env       = 'sandbox' === (string) getenv( 'FLAVOR_APNS_ENV' ) ? 'sandbox' : 'production';

		if ( '' === $key_id || '' === $team_id || '' === $bundle_id || '' === $key_path ) {
			StructuredLogger::warning( 'push_notifications', 'APNs credentials missing in environment' );
			return new \WP_Error(
				'missing_credentials',
				__( 'APNs configuration missing: FLAVOR_APNS_KEY_ID / FLAVOR_APNS_TEAM_ID / FLAVOR_APNS_BUNDLE_ID / FLAVOR_APNS_AUTH_KEY_PATH.', 'flavor-core' )
			);
		}

		if ( ! is_readable( $key_path ) ) {
			return new \WP_Error( 'missing_credentials', sprintf( 'APNs auth key file is not readable: %s', basename( $key_path ) ) );
		}

		$auth_key = (string) file_get_contents( $key_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === strpos( $auth_key, 'BEGIN PRIVATE KEY' ) ) {
			return new \WP_Error( 'invalid_credentials', 'APNs auth key file is not a valid PEM private key.' );
		}

		return array(
			'key_id'    => $key_id,
			'team_id'   => $team_id,
			'bundle_id' => $bundle_id,
			'auth_key'  => $auth_key,
			'env'       => $env,
		);
	}

	/**
	 * Build a signed JWT (RS256/ES256) with an OpenSSL key. No secrets are logged,
	 * passed through the database, or returned to callers of public APIs.
	 *
	 * @param array<string, mixed>   $header JWT header claims.
	 * @param array<string, mixed>   $claims JWT payload claims.
	 * @param string                 $pem    PEM-encoded private key.
	 * @param int                    $algo   Typical OPENSSL_ALGO_SHA256.
	 * @return string|\WP_Error
	 */
	private static function build_jwt( array $header, array $claims, string $pem, int $algo ) {
		if ( ! function_exists( 'openssl_sign' ) ) {
			return new \WP_Error( 'openssl_missing', 'The OpenSSL PHP extension is required for push delivery.' );
		}

		$key = openssl_pkey_get_private( $pem );
		if ( false === $key ) {
			return new \WP_Error( 'invalid_credentials', 'Push signing key could not be parsed (invalid PEM).' );
		}

		$segments = array(
			rtrim( strtr( base64_encode( wp_json_encode( $header ) ), '+/', '-_' ), '=' ),
			rtrim( strtr( base64_encode( wp_json_encode( $claims ) ), '+/', '-_' ), '=' ),
		);
		$signing_input = implode( '.', $segments );

		$signature = '';
		$ok        = openssl_sign( $signing_input, $signature, $key, $algo );

		$details = openssl_pkey_get_details( $key );
		if ( function_exists( 'openssl_free_key' ) ) {
			openssl_free_key( $key );
		}
		if ( ! $ok ) {
			return new \WP_Error( 'signing_failed', 'Push credential provisioning failed: could not sign JWT.' );
		}

		// JWS ES256 requires a raw 64-byte R||S signature; OpenSSL emits ASN.1 DER for EC keys.
		if ( is_array( $details ) && OPENSSL_KEYTYPE_EC === ( $details['type'] ?? -1 ) ) {
			$signature = self::der_to_raw_signature( $signature );
			if ( '' === $signature ) {
				return new \WP_Error( 'signing_failed', 'Could not normalize EC signature to JWS format.' );
			}
		}

		$segments[] = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );
		return implode( '.', $segments );
	}

	/**
	 * Convert an ASN.1 DER ECDSA signature (as produced by OpenSSL) to the raw
	 * 64-byte R||S form required by the JWS ES256 specification.
	 *
	 * @param string $der ASN.1 DER-encoded signature.
	 * @return string Raw signature, or empty string on malformed input.
	 */
	private static function der_to_raw_signature( string $der ): string {
		// Expected DER layout: 30 <len> 02 <lenR> <R> 02 <lenS> <S>, fixed 32-byte limbs.
		if ( strlen( $der ) < 70 || "\x30" !== $der[0] ) {
			return '';
		}
		$r_len = ord( $der[3] );
		$r     = substr( $der, 4, $r_len );
		$s_pos = 4 + $r_len;
		if ( strlen( $der ) < $s_pos + 2 || "\x02" !== $der[ $s_pos ] ) {
			return '';
		}
		$s_len = ord( $der[ $s_pos + 1 ] );
		$s     = substr( $der, $s_pos + 2, $s_len );

		// Strip DER sign-padding zeroes, then left-pad to the 32-byte limb size.
		foreach ( array( &$r, &$s ) as &$limb ) {
			$limb = str_pad( ltrim( $limb, "\x00" ), 32, "\x00", STR_PAD_LEFT );
		}

		return $r . $s;
	}

	/**
	 * Deactivate stale device tokens that push providers reported as unregistered.
	 *
	 * @param string[] $tokens Tokens to deactivate.
	 * @return int Number of rows updated.
	 */
	public static function deactivate_tokens( array $tokens ): int {
		global $wpdb;
		$table   = Schema::table( 'flavor_device_tokens' );
		$updated = 0;
		foreach ( array_values( array_unique( $tokens ) ) as $token ) {
			$token = trim( (string) $token );
			if ( '' === $token ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$res = $wpdb->update(
				$table,
				array( 'is_active' => 0 ),
				array( 'device_token' => $token ),
				array( '%d' ),
				array( '%s' )
			);
			if ( false !== $res ) {
				$updated += (int) $res;
			}
		}
		if ( $updated > 0 ) {
			StructuredLogger::info( 'push_notifications', 'Stale device tokens deactivated by provider callback', array( 'count' => $updated ) );
		}
		return $updated;
	}
}
