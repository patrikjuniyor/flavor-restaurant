<?php
/**
 * Structured Logging & Telemetry Logger.
 * Records operational events into `flavor_system_logs`.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Observability;

use FlavorCore\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class StructuredLogger
 */
class StructuredLogger {

	public const DEBUG    = 'debug';
	public const INFO     = 'info';
	public const WARNING  = 'warning';
	public const ERROR    = 'error';
	public const CRITICAL = 'critical';

	/**
	 * Log a message with structured context.
	 *
	 * @param string               $level Log level.
	 * @param string               $channel Subsystem channel.
	 * @param string               $message Description.
	 * @param array<string, mixed> $context Structured metadata.
	 * @param int|null             $duration_ms Optional execution time.
	 */
	public static function log( string $level, string $channel, string $message, array $context = array(), ?int $duration_ms = null ): void {
		global $wpdb;

		$tbl = Schema::table( 'flavor_system_logs' );
		$wpdb->insert(
			$tbl,
			array(
				'level'        => sanitize_key( $level ),
				'channel'      => sanitize_key( $channel ),
				'message'      => sanitize_text_field( $message ),
				'context_json' => wp_json_encode( $context ),
				'user_id'      => get_current_user_id() ?: null,
				'ip'           => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
				'request_uri'  => sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' ),
				'duration_ms'  => $duration_ms,
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	public static function info( string $channel, string $message, array $context = array() ): void {
		self::log( self::INFO, $channel, $message, $context );
	}

	public static function warning( string $channel, string $message, array $context = array() ): void {
		self::log( self::WARNING, $channel, $message, $context );
	}

	public static function error( string $channel, string $message, array $context = array() ): void {
		self::log( self::ERROR, $channel, $message, $context );
	}

	public static function critical( string $channel, string $message, array $context = array() ): void {
		self::log( self::CRITICAL, $channel, $message, $context );
	}
}
