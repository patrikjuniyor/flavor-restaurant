<?php
/**
 * Mobile CI/CD Build Manager.
 * Orchestrates remote build triggers, HMAC webhook dispatch, CI callbacks, and artifact metadata.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Mobile;

use FlavorCore\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class BuildManager
 */
class BuildManager {

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

		$secret = (string) $cfg['ci_webhook_secret'];
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
	 * Callback handler called by CI when build state changes or completes.
	 *
	 * @param string               $uuid      Build UUID.
	 * @param array<string, mixed> $data      Payload from CI.
	 * @param string               $signature HMAC signature.
	 * @return bool|\WP_Error
	 */
	public static function update_build_from_ci( string $uuid, array $data, string $signature = '' ) {
		$cfg    = MobileConfigManager::get_all();
		$secret = (string) $cfg['ci_webhook_secret'];

		// Verify HMAC signature if provided
		if ( ! empty( $signature ) && ! empty( $secret ) ) {
			$expected_sig = hash_hmac( 'sha256', wp_json_encode( $data ), $secret );
			if ( ! hash_equals( $expected_sig, $signature ) ) {
				return new \WP_Error( 'flavor_invalid_sig', __( 'امضای وب‌هوک معتبر نیست.', 'flavor-core' ), array( 'status' => 403 ) );
			}
		}

		$existing = self::get_build_by_uuid( $uuid );
		if ( ! $existing ) {
			return new \WP_Error( 'flavor_build_not_found', __( 'شناسه بیلد یافت نشد.', 'flavor-core' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$now = current_time( 'mysql' );

		$update = array(
			'updated_at' => $now,
		);

		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'queued', 'building', 'success', 'failed', 'cancelled' ), true ) ) {
			$update['status'] = $data['status'];
			if ( 'building' === $data['status'] && empty( $existing['started_at'] ) ) {
				$update['started_at'] = $now;
			}
			if ( in_array( $data['status'], array( 'success', 'failed', 'cancelled' ), true ) ) {
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
		if ( isset( $data['log_append'] ) ) {
			$prev_log = (string) ( $existing['build_log'] ?? '' );
			$update['build_log'] = $prev_log . "\n" . sanitize_textarea_field( (string) $data['log_append'] );
		}
		if ( isset( $data['error_message'] ) ) {
			$update['error_message'] = sanitize_textarea_field( (string) $data['error_message'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::table(), $update, array( 'build_uuid' => $uuid ) );
		return true;
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
			'completed_at'            => (string) ( $r['completed_at'] ?? '' ),
		);
	}
}
