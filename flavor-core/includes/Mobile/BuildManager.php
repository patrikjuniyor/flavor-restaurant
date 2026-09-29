<?php
/**
 * Mobile CI/CD Build Manager.
 * Orchestrates remote build triggers, HMAC webhook dispatch, CI callbacks, and artifact metadata.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Mobile;

use FlavorCore\Database\Schema;
use FlavorCore\Observability\StructuredLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Class BuildManager
 */
class BuildManager {

	/**
	 * Freshness window (seconds) for signed CI callbacks — replay protection.
	 */
	public const CALLBACK_WINDOW = 300;

	/**
	 * Build statuses accepted in callbacks.
	 *
	 * @var string[]
	 */
	private const STATUSES = array( 'queued', 'building', 'success', 'failed', 'cancelled' );

	/**
	 * Terminal statuses — completed builds reject every further transition.
	 *
	 * @var string[]
	 */
	private const TERMINAL_STATUSES = array( 'success', 'failed', 'cancelled' );

	/**
	 * Table name.
	 */
	public static function table(): string {
		return Schema::table( 'flavor_mobile_builds' );
	}

	/**
	 * Trigger a new remote build via CI webhook / GitHub Actions.
	 *
	 * @param string   $platform    android | ios | all
	 * @param string   $environment dev | staging | prod
	 * @param int|null $user_id     Admin user ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function trigger_build( string $platform = 'all', string $environment = 'prod', ?int $user_id = null ) {
		$cfg = MobileConfigManager::get_all();

		$uuid = wp_generate_uuid4();
		$now  = current_time( 'mysql' );

		$record = array(
			'build_uuid'        => $uuid,
			'platform'          => in_array( $platform, array( 'android', 'ios', 'all' ), true ) ? $platform : 'all',
			'environment'       => in_array( $environment, array( 'dev', 'staging', 'prod' ), true ) ? $environment : 'prod',
			'version_name'      => (string) $cfg['version_name'],
			'version_code'      => (int) $cfg['version_code'],
			'status'            => 'queued',
			'triggered_by'      => $user_id ?: get_current_user_id(),
			'ci_provider'       => ! empty( $cfg['github_repo'] ) ? 'github_actions' : 'custom_ci',
			'build_log'         => sprintf( "[%s] درخواست بیلد برای پلتفرم %s با موفقیت در وردپرس ایجاد شد.\n", $now, $platform ),
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::table(), $record );
		if ( ! $ok ) {
			return new \WP_Error( 'flavor_build_insert', __( 'ثبت رکورد بیلد با خطا مواجه شد.', 'flavor-core' ) );
		}

		$build_id = (int) $wpdb->insert_id;

		// Dispatch Webhook to CI
		$dispatch_result = self::dispatch_ci_event( $uuid, $record, $cfg );
		if ( is_wp_error( $dispatch_result ) ) {
			// Update status with error note but keep record
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				self::table(),
				array(
					'status'        => 'failed',
					'error_message' => $dispatch_result->get_error_message(),
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => $build_id )
			);
			return $dispatch_result;
		}

		return self::get_build_by_uuid( $uuid );
	}

	/**
	 * Dispatches webhook / GitHub repository_dispatch event to CI.
	 *
	 * @param string               $uuid   Build UUID.
	 * @param array<string, mixed> $record Build record.
	 * @param array<string, mixed> $cfg    Mobile config.
	 * @return true|\WP_Error
	 */
	private static function dispatch_ci_event( string $uuid, array $record, array $cfg ) {
		$payload = array(
			'event'            => 'flavor_build_request',
			'build_uuid'       => $uuid,
			'platform'         => $record['platform'],
			'environment'      => $record['environment'],
			'version_name'     => $record['version_name'],
			'version_code'     => $record['version_code'],
			'config_url'       => rest_url( 'flavor/v1/mobile/config' ),
			'callback_url'     => rest_url( 'flavor/v1/mobile/builds/callback' ),
			'tenant_id'        => $cfg['tenant_id'],
			'server_timestamp' => time(),
		);

		// Always the persisted, generate-once secret — never an inline default.
		$secret = MobileConfigManager::get_webhook_secret();
		$body   = wp_json_encode( $payload );
		$sig    = hash_hmac( 'sha256', $body, $secret );

		// 1. GitHub Actions repository_dispatch integration
		if ( ! empty( $cfg['github_repo'] ) && ! empty( $cfg['github_token'] ) ) {
			$gh_url = "https://api.github.com/repos/{$cfg['github_repo']}/dispatches";
			$gh_response = wp_remote_post(
				$gh_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $cfg['github_token'],
						'Accept'        => 'application/vnd.github.v3+json',
						'Content-Type'  => 'application/json',
						'User-Agent'    => 'Flavor-Restaurant-Provisioner',
					),
					'body'    => wp_json_encode(
						array(
							'event_type'     => 'flavor_build',
							'client_payload' => $payload,
						)
					),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $gh_response ) ) {
				return $gh_response;
			}
			$status_code = wp_remote_retrieve_response_code( $gh_response );
			if ( $status_code < 200 || $status_code >= 300 ) {
				return new \WP_Error(
					'flavor_gh_dispatch_failed',
					sprintf( 'ارسال رویداد به GitHub Actions با خطای HTTP %d مواجه شد.', $status_code )
				);
			}
			return true;
		}

		// 2. Generic Custom CI Webhook integration
		if ( ! empty( $cfg['ci_webhook_url'] ) ) {
			$ci_response = wp_remote_post(
				$cfg['ci_webhook_url'],
				array(
					'headers' => array(
						'Content-Type'       => 'application/json',
						'X-Flavor-Signature' => $sig,
						'X-Flavor-UUID'      => $uuid,
					),
					'body'    => $body,
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $ci_response ) ) {
				return $ci_response;
			}
			return true;
		}

		// If no remote CI configured, record is queued for manual polling or local CLI runner.
		return true;
	}

	/**
	 * Handle a signed callback from CI when build state changes or completes.
	 *
	 * Security protocol (must match .github/workflows/build-mobile.yml exactly):
	 *  1. The request MUST carry an `X-Flavor-Signature: <64-hex>` header —
	 *     HMAC-SHA256 of the exact raw request body, keyed with the persisted
	 *     ci_webhook_secret. Missing / missing-server-secret / malformed /
	 *     mismatching signatures are rejected with HTTP 403 before any JSON is
	 *     decoded or trusted.
	 *  2. The signed payload MUST include a numeric `timestamp` (unix) inside a
	 *     CALLBACK_WINDOW-second freshness window — replay protection.
	 *  3. State transitions are validated with a strict state machine; terminal
	 *     builds reject every further update (second replay protection layer).
	 *  4. Artifact URLs are only writable on a `success` transition.
	 *
	 * @param string   $raw_body  Exact raw HTTP request body (unparsed).
	 * @param string   $signature X-Flavor-Signature header value.
	 * @param int|null $now_ts    Current unix time (injectable for tests).
	 * @return bool|\WP_Error
	 */
	public static function update_build_from_ci( string $raw_body, string $signature = '', ?int $now_ts = null ) {
		$now_ts = null === $now_ts ? time() : $now_ts;

		// 1a. Server-side secret — generated once, persisted; no fallbacks.
		$secret = MobileConfigManager::get_webhook_secret();
		if ( '' === $secret ) {
			self::log_security_event( 'missing_server_secret' );
			return new \WP_Error( 'flavor_missing_secret', __( 'سکرت وب‌هوک CI در سرور پیکربندی نشده است.', 'flavor-core' ), array( 'status' => 403 ) );
		}

		// 1b. Signature header is mandatory for every callback.
		$signature = trim( $signature );
		if ( '' === $signature ) {
			self::log_security_event( 'missing_signature' );
			return new \WP_Error( 'flavor_missing_sig', __( 'امضای وب‌هوک ارسال نشده است.', 'flavor-core' ), array( 'status' => 403 ) );
		}
		if ( ! preg_match( '/\A[0-9a-f]{64}\z/', $signature ) ) {
			self::log_security_event( 'malformed_signature' );
			return new \WP_Error( 'flavor_malformed_sig', __( 'قالب امضای وب‌هوک نامعتبر است.', 'flavor-core' ), array( 'status' => 403 ) );
		}

		// 1c. Verify against the exact raw body — never decode/re-encode first.
		$expected_sig = hash_hmac( 'sha256', $raw_body, $secret );
		if ( ! hash_equals( $expected_sig, $signature ) ) {
			self::log_security_event( 'invalid_signature' );
			return new \WP_Error( 'flavor_invalid_sig', __( 'امضای وب‌هوک معتبر نیست.', 'flavor-core' ), array( 'status' => 403 ) );
		}

		// ── Payload is authenticated from this point on ─────────────────────
		$data = json_decode( $raw_body, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'flavor_invalid_json', __( 'بدنهٔ درخواست JSON معتبر نیست.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$uuid = sanitize_text_field( (string) ( $data['build_uuid'] ?? '' ) );
		if ( '' === $uuid ) {
			return new \WP_Error( 'missing_uuid', __( 'شناسه بیلد ارسال نشده است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		// 2. Replay protection: the signed timestamp must be fresh.
		if ( empty( $data['timestamp'] ) || ! is_numeric( $data['timestamp'] ) ) {
			return new \WP_Error( 'flavor_missing_timestamp', __( 'مهر زمانی معتبر در کال‌بک ارسال نشده است.', 'flavor-core' ), array( 'status' => 400 ) );
		}
		if ( abs( $now_ts - (int) $data['timestamp'] ) > self::CALLBACK_WINDOW ) {
			self::log_security_event( 'stale_callback', $uuid );
			return new \WP_Error( 'flavor_stale_request', __( 'مهلتٔ اعتبار کال‌بک به پایان رسیده است.', 'flavor-core' ), array( 'status' => 403 ) );
		}

		$existing = self::get_build_by_uuid( $uuid );
		if ( ! $existing ) {
			return new \WP_Error( 'flavor_build_not_found', __( 'شناسه بیلد یافت نشد.', 'flavor-core' ), array( 'status' => 404 ) );
		}

		// 3. State machine: validates transitions and rejects replays on terminal builds.
		$current_status = (string) ( $existing['status'] ?? 'queued' );
		$new_status     = null;
		if ( isset( $data['status'] ) ) {
			if ( ! in_array( $data['status'], self::STATUSES, true ) ) {
				return new \WP_Error( 'flavor_unknown_status', __( 'وضعیت بیلد ارسال‌شده معتبر نیست.', 'flavor-core' ), array( 'status' => 400 ) );
			}
			if ( ! in_array( $data['status'], self::allowed_transitions( $current_status ), true ) ) {
				self::log_security_event( 'invalid_transition:' . $current_status . '->' . $data['status'], $uuid );
				return new \WP_Error(
					'flavor_invalid_transition',
					sprintf(
						/* translators: 1: current status, 2: requested status */
						__( 'گذار وضعیت از «%1$s» به «%2$s» مجاز نیست.', 'flavor-core' ),
						$current_status,
						(string) $data['status']
					),
					array( 'status' => 409 )
				);
			}
			$new_status = (string) $data['status'];
		}

		global $wpdb;
		$now = current_time( 'mysql' );

		$update = array(
			'updated_at' => $now,
		);

		if ( $new_status ) {
			$update['status'] = $new_status;
			if ( 'building' === $new_status && empty( $existing['started_at'] ) ) {
				$update['started_at'] = $now;
			}
			if ( in_array( $new_status, self::TERMINAL_STATUSES, true ) ) {
				$update['completed_at'] = $now;
				if ( ! empty( $existing['started_at'] ) ) {
					$update['duration_seconds'] = max( 1, strtotime( $now ) - strtotime( $existing['started_at'] ) );
				}
			}
		}

		if ( isset( $data['ci_build_id'] ) ) {
			$update['ci_build_id'] = sanitize_text_field( (string) $data['ci_build_id'] );
		}
		if ( isset( $data['ci_build_url'] ) ) {
			$update['ci_build_url'] = esc_url_raw( (string) $data['ci_build_url'] );
		}
		if ( isset( $data['commit_sha'] ) ) {
			$update['commit_sha'] = sanitize_text_field( (string) $data['commit_sha'] );
		}

		// Metadata capable of pointing to money-moving downloads is only writable
		// on a verified `success` transition — never on arbitrary signed payloads.
		if ( 'success' === $new_status ) {
			if ( isset( $data['artifact_apk_url'] ) ) {
				$update['artifact_apk_url'] = esc_url_raw( (string) $data['artifact_apk_url'] );
			}
			if ( isset( $data['artifact_aab_url'] ) ) {
				$update['artifact_aab_url'] = esc_url_raw( (string) $data['artifact_aab_url'] );
			}
			if ( isset( $data['artifact_ipa_url'] ) ) {
				$update['artifact_ipa_url'] = esc_url_raw( (string) $data['artifact_ipa_url'] );
			}
			if ( isset( $data['artifact_size_bytes'] ) ) {
				$update['artifact_size_bytes'] = absint( $data['artifact_size_bytes'] );
			}
			if ( isset( $data['artifact_checksum_sha256'] ) ) {
				$update['artifact_checksum_sha256'] = sanitize_text_field( (string) $data['artifact_checksum_sha256'] );
			}

			// GitHub workflow protocol: structured artifacts[] array.
			if ( isset( $data['artifacts'] ) && is_array( $data['artifacts'] ) ) {
				$update = array_merge( $update, self::map_workflow_artifacts( $data['artifacts'] ) );
			}
		}

		// Workflow protocol sends `logs`; earlier generic CI protocol used `log_append`.
		$log_append = null;
		if ( isset( $data['logs'] ) ) {
			$log_append = $data['logs'];
		} elseif ( isset( $data['log_append'] ) ) {
			$log_append = $data['log_append'];
		}
		if ( null !== $log_append ) {
			$prev_log = (string) ( $existing['build_log'] ?? '' );
			$update['build_log'] = $prev_log . "\n" . sanitize_textarea_field( (string) $log_append );
		}

		if ( isset( $data['error_message'] ) ) {
			$update['error_message'] = sanitize_textarea_field( (string) $data['error_message'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$written = $wpdb->update( self::table(), $update, array( 'build_uuid' => $uuid ) );
		if ( false === $written ) {
			self::log_security_event( 'db_update_failed', $uuid );
			return new \WP_Error( 'flavor_build_update_failed', __( 'به‌روزرسانی رکورد بیلد با خطا مواجه شد.', 'flavor-core' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Allowed build-state transitions from a given status.
	 *
	 * queued → building / failed / cancelled · building → success / failed / cancelled
	 * Terminal statuses allow nothing (replay + tamper protection).
	 *
	 * @param string $status Current status.
	 * @return string[]
	 */
	private static function allowed_transitions( string $status ): array {
		$map = array(
			'queued'    => array( 'building', 'failed', 'cancelled' ),
			'building'  => array( 'success', 'failed', 'cancelled' ),
			'success'   => array(),
			'failed'    => array(),
			'cancelled' => array(),
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : array();
	}

	/**
	 * Map the GitHub workflow `artifacts[]` payload onto flat build columns.
	 *
	 * @param array<int, array<string, mixed>> $artifacts Workflow artifacts payload.
	 * @return array<string, mixed>
	 */
	private static function map_workflow_artifacts( array $artifacts ): array {
		$out = array();
		foreach ( $artifacts as $artifact ) {
			if ( ! is_array( $artifact ) || empty( $artifact['type'] ) ) {
				continue;
			}
			$type = sanitize_key( (string) $artifact['type'] );
			$url  = isset( $artifact['download_url'] ) ? esc_url_raw( (string) $artifact['download_url'] ) : '';

			if ( 'android_apk' === $type ) {
				if ( $url ) {
					$out['artifact_apk_url'] = $url;
				}
				if ( isset( $artifact['file_size'] ) ) {
					$out['artifact_size_bytes'] = absint( $artifact['file_size'] );
				}
				if ( ! empty( $artifact['checksum_sha256'] ) && preg_match( '/\A[0-9a-f]{64}\z/', (string) $artifact['checksum_sha256'] ) ) {
					$out['artifact_checksum_sha256'] = (string) $artifact['checksum_sha256'];
				}
			} elseif ( 'android_aab' === $type && $url ) {
				$out['artifact_aab_url'] = $url;
			} elseif ( 'ios_ipa' === $type && $url ) {
				$out['artifact_ipa_url'] = $url;
			}
		}
		return $out;
	}

	/**
	 * Record a security-relevant build event; must never break the request flow.
	 *
	 * @param string $event     Event slug.
	 * @param string $build_uuid Related build UUID, when known.
	 */
	private static function log_security_event( string $event, string $build_uuid = '' ): void {
		try {
			StructuredLogger::warning(
				'mobile_build',
				'CI callback security event: ' . $event,
				array(
					'build_uuid' => $build_uuid,
					'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				)
			);
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Observability must never break the request flow.
		}
	}

	/**
	 * Get single build by UUID.
	 *
	 * @param string $uuid UUID.
	 * @return array<string, mixed>|null
	 */
	public static function get_build_by_uuid( string $uuid ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE build_uuid = %s", $uuid ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $row ? self::format_build( $row ) : null;
	}

	/**
	 * Get build history.
	 *
	 * @param int $limit  Limit.
	 * @param int $offset Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_history( int $limit = 20, int $offset = 0 ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return is_array( $rows ) ? array_map( array( self::class, 'format_build' ), $rows ) : array();
	}

	/**
	 * Helper: Format build record with friendly labels and status.
	 *
	 * @param array<string, mixed> $r Raw row.
	 * @return array<string, mixed>
	 */
	private static function format_build( array $r ): array {
		$status = (string) $r['status'];
		$status_labels = array(
			'queued'    => __( 'در صف انتظار', 'flavor-core' ),
			'building'  => __( 'در حال کامپایل', 'flavor-core' ),
			'success'   => __( 'موفق و آماده دانلود', 'flavor-core' ),
			'failed'    => __( 'خطا در بیلد', 'flavor-core' ),
			'cancelled' => __( 'لغو شده', 'flavor-core' ),
		);

		return array(
			'id'                      => (int) $r['id'],
			'build_uuid'              => (string) $r['build_uuid'],
			'platform'                => (string) $r['platform'],
			'environment'             => (string) $r['environment'],
			'version_name'            => (string) $r['version_name'],
			'version_code'            => (int) $r['version_code'],
			'status'                  => $status,
			'status_label'            => $status_labels[ $status ] ?? $status,
			'ci_provider'             => (string) $r['ci_provider'],
			'ci_build_id'             => (string) ( $r['ci_build_id'] ?? '' ),
			'ci_build_url'            => (string) ( $r['ci_build_url'] ?? '' ),
			'commit_sha'              => (string) ( $r['commit_sha'] ?? '' ),
			'artifact_apk_url'        => (string) ( $r['artifact_apk_url'] ?? '' ),
			'artifact_aab_url'        => (string) ( $r['artifact_aab_url'] ?? '' ),
			'artifact_ipa_url'        => (string) ( $r['artifact_ipa_url'] ?? '' ),
			'artifact_size_formatted' => ! empty( $r['artifact_size_bytes'] ) ? size_format( (int) $r['artifact_size_bytes'] ) : '',
			'artifact_checksum'       => (string) ( $r['artifact_checksum_sha256'] ?? '' ),
			'build_log'               => (string) ( $r['build_log'] ?? '' ),
			'error_message'           => (string) ( $r['error_message'] ?? '' ),
			'duration_formatted'      => ! empty( $r['duration_seconds'] ) ? sprintf( '%d ثانیه', (int) $r['duration_seconds'] ) : '',
			'created_at'              => (string) $r['created_at'],
			'started_at'              => (string) ( $r['started_at'] ?? '' ),
			'completed_at'            => (string) ( $r['completed_at'] ?? '' ),
		);
	}
}
