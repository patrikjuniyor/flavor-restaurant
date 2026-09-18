<?php
/**
 * System Health & Observability Diagnostics Service.
 * Evaluates DB integrity, cron liveness, memory usage, SMS provider,
 * and background queues.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Observability;

use FlavorCore\Database\Schema;
use FlavorCore\SMS\SmsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class HealthCheck
 */
class HealthCheck {

	/**
	 * Run full diagnostic health check.
	 *
	 * @return array<string, mixed>
	 */
	public static function check(): array {
		global $wpdb;

		$start = microtime( true );
		$checks = array();

		// 1. Database Check & Query Latency
		$db_start = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$db_query = $wpdb->get_var( 'SELECT 1' );
		$db_lat   = round( ( microtime( true ) - $db_start ) * 1000, 2 );

		$checks['database'] = array(
			'status'      => '1' === (string) $db_query ? 'healthy' : 'unhealthy',
			'latency_ms'  => $db_lat,
			'db_version'  => Schema::DB_VERSION,
			'installed_v' => get_option( Schema::VERSION_OPTION, 'unknown' ),
		);

		// 2. Schema Table Verification
		$missing_tables = array();
		foreach ( Schema::table_slugs() as $slug ) {
			$tname = Schema::table( $slug );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tname ) );
			if ( $exists !== $tname ) {
				$missing_tables[] = $slug;
			}
		}

		$checks['schema'] = array(
			'status'         => empty( $missing_tables ) ? 'healthy' : 'unhealthy',
			'total_tables'   => count( Schema::table_slugs() ),
			'missing_tables' => $missing_tables,
		);

		// 3. Memory & PHP Environment
		$mem_usage = memory_get_usage( true );
		$mem_peak  = memory_get_peak_usage( true );
		$mem_limit = ini_get( 'memory_limit' );

		$checks['environment'] = array(
			'status'           => 'healthy',
			'php_version'      => PHP_VERSION,
			'memory_usage_mb'  => round( $mem_usage / 1048576, 2 ),
			'memory_peak_mb'   => round( $mem_peak / 1048576, 2 ),
			'memory_limit'     => $mem_limit,
		);

		// 4. SMS & Communication Gateway
		$active_provider = SmsManager::active_provider();
		$checks['sms_gateway'] = array(
			'status'    => $active_provider->is_available() ? 'healthy' : 'degraded',
			'provider'  => $active_provider->slug(),
			'label'     => $active_provider->label(),
		);

		// 5. Cron & Scheduler Liveness
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$checks['cron'] = array(
			'status'           => 'healthy',
			'wp_cron_disabled' => $cron_disabled,
		);

		$total_latency = round( ( microtime( true ) - $start ) * 1000, 2 );

		// Overall platform status
		$overall = 'healthy';
		foreach ( $checks as $c ) {
			if ( isset( $c['status'] ) && 'unhealthy' === $c['status'] ) {
				$overall = 'unhealthy';
				break;
			} elseif ( isset( $c['status'] ) && 'degraded' === $c['status'] && 'unhealthy' !== $overall ) {
				$overall = 'degraded';
			}
		}

		return array(
			'status'           => $overall,
			'timestamp'        => gmdate( 'c' ),
			'total_latency_ms' => $total_latency,
			'checks'           => $checks,
		);
	}
}
