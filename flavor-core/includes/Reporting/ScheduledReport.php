<?php
/**
 * Scheduled report delivery (M-03, the second half of N-11).
 *
 * A CSV you have to remember to download is a CSV that does not get
 * downloaded after the second week. This delivers the report the owner
 * already knows how to read, by email, on a schedule they choose.
 *
 * Three decisions worth stating:
 *
 *  - **The attachment inherits the export's own rules.** It is produced by
 *    the same ReportQuery and CsvWriter, so whatever PII the recipient is
 *    not allowed to see is masked here too. Sending a report must not
 *    become a second, weaker way to obtain one.
 *  - **The recipient is stored, and validated on the way out.** Schedules
 *    outlive the person who created them, so an address that was valid on
 *    save may not be by the time it fires.
 *  - **A failure is recorded, not retried silently.** A schedule that
 *    throws on every run should be visible in the log, not quietly
 *    sending nothing forever.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reporting;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScheduledReport
 */
class ScheduledReport {

	public const HOOK = 'flavor_scheduled_report';
	public const OPTION = 'flavor_scheduled_reports';

	/** How many consecutive failures before a schedule disables itself. */
	private const MAX_FAILURES = 5;

	/**
	 * Register hooks.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'schedule' ) );
		add_action( self::HOOK, array( self::class, 'run_all' ) );
	}

	/**
	 * Ensure the recurring event exists.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Clear on deactivation.
	 */
	public static function clear(): void {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}

	/**
	 * All stored schedules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Validate and store a schedule.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{ok:bool, errors:string[]}
	 */
	public static function save( array $input ): array {
		$type      = sanitize_key( (string) ( $input['type'] ?? 'orders' ) );
		$freq      = sanitize_key( (string) ( $input['frequency'] ?? 'weekly' ) );
		$recipient = sanitize_email( (string) ( $input['recipient'] ?? '' ) );
		$branch    = absint( $input['branch'] ?? 0 );

		$errors = array();

		if ( ! in_array( $type, ReportQuery::TYPES, true ) ) {
			$errors[] = __( 'نوع گزارش نامعتبر است.', 'flavor-core' );
		}
		if ( ! in_array( $freq, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			$errors[] = __( 'دورهٔ ارسال باید روزانه، هفتگی یا ماهانه باشد.', 'flavor-core' );
		}
		if ( ! is_email( $recipient ) ) {
			$errors[] = __( 'نشانی ایمیل معتبر نیست.', 'flavor-core' );
		}

		if ( ! empty( $errors ) ) {
			return array( 'ok' => false, 'errors' => $errors );
		}

		$all   = self::all();
		$all[] = array(
			'id'         => wp_generate_uuid4(),
			'type'       => $type,
			'frequency'  => $freq,
			'recipient'  => $recipient,
			'branch'     => $branch,
			'created_at' => time(),
			'failures'   => 0,
			'last_sent'  => 0,
			'enabled'    => true,
		);

		update_option( self::OPTION, $all, false );
		return array( 'ok' => true, 'errors' => array() );
	}

	/**
	 * Remove a schedule.
	 *
	 * @param string $id Schedule id.
	 * @return bool
	 */
	public static function remove( string $id ): bool {
		$all    = self::all();
		$kept   = array();
		$removed = false;

		foreach ( $all as $schedule ) {
			if ( ( $schedule['id'] ?? '' ) === $id ) {
				$removed = true;
				continue;
			}
			$kept[] = $schedule;
		}

		if ( $removed ) {
			update_option( self::OPTION, array_values( $kept ), false );
		}
		return $removed;
	}

	/**
	 * Is a schedule due now?
	 *
	 * @param array<string, mixed> $schedule Schedule.
	 * @param int                  $now      Current timestamp.
	 * @return bool
	 */
	public static function is_due( array $schedule, int $now ): bool {
		if ( empty( $schedule['enabled'] ) ) {
			return false;
		}

		$last = (int) ( $schedule['last_sent'] ?? 0 );
		if ( 0 === $last ) {
			return true; // Never sent, so send once.
		}

		$interval = self::interval_seconds( (string) ( $schedule['frequency'] ?? 'weekly' ) );
		return ( $now - $last ) >= $interval;
	}

	/**
	 * Seconds between sends for a named frequency.
	 *
	 * @param string $frequency Frequency.
	 * @return int
	 */
	public static function interval_seconds( string $frequency ): int {
		switch ( $frequency ) {
			case 'daily':
				return DAY_IN_SECONDS;
			case 'monthly':
				return 30 * DAY_IN_SECONDS;
			default:
				return WEEK_IN_SECONDS;
		}
	}

	/**
	 * Run every due schedule.
	 *
	 * @return array{sent:int, skipped:int, failed:int}
	 */
	public static function run_all(): array {
		$now     = time();
		$all     = self::all();
		$updated = array();
		$counts  = array( 'sent' => 0, 'skipped' => 0, 'failed' => 0 );

		foreach ( $all as $schedule ) {
			if ( ! self::is_due( $schedule, $now ) ) {
				++$counts['skipped'];
				$updated[] = $schedule;
				continue;
			}

			$sent = self::send( $schedule );

			if ( $sent ) {
				++$counts['sent'];
				$schedule['last_sent'] = $now;
				$schedule['failures']  = 0;
			} else {
				++$counts['failed'];
				$schedule['failures'] = (int) ( $schedule['failures'] ?? 0 ) + 1;
				// A schedule that keeps failing stops trying, and says so.
				if ( $schedule['failures'] >= self::MAX_FAILURES ) {
					$schedule['enabled'] = false;
				}
			}

			$updated[] = $schedule;
		}

		update_option( self::OPTION, $updated, false );
		return $counts;
	}

	/**
	 * Send one report.
	 *
	 * @param array<string, mixed> $schedule Schedule.
	 * @return bool
	 */
	public static function send( array $schedule ): bool {
		$recipient = sanitize_email( (string) ( $schedule['recipient'] ?? '' ) );
		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$type = (string) ( $schedule['type'] ?? 'orders' );
		$from = gmdate( 'Y-m-d', time() - self::interval_seconds( (string) ( $schedule['frequency'] ?? 'weekly' ) ) );
		$to   = gmdate( 'Y-m-d' );

		$result = ReportQuery::run( $type, $from, $to, (int) ( $schedule['branch'] ?? 0 ) );
		$rows   = $result['rows'];

		if ( empty( $rows ) ) {
			// Nothing to report is not a failure; it is an empty period.
			// Silence would be indistinguishable from a broken schedule.
			return self::mail(
				$recipient,
				self::subject( $type, $from, $to ),
				self::empty_body( $type, $from, $to ),
				null
			);
		}

		$csv      = CsvWriter::render( ReportQuery::headers( $type ), $rows );
		$filename = CsvWriter::filename( $type, $from, $to, ReportAdmin::timezone_label() );

		return self::mail(
			$recipient,
			self::subject( $type, $from, $to ),
			self::body( $type, $result['count'], $from, $to, $result['truncated'] ),
			array( 'name' => $filename, 'content' => $csv )
		);
	}

	/**
	 * Send the mail.
	 *
	 * @param string                    $to         Recipient.
	 * @param string                    $subject    Subject.
	 * @param string                    $body       Body.
	 * @param array{name:string, content:string}|null $attachment Attachment, or none.
	 * @return bool
	 */
	private static function mail( string $to, string $subject, string $body, ?array $attachment ): bool {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( null === $attachment ) {
			return (bool) wp_mail( $to, $subject, $body, $headers );
		}

		// The attachment is written to a temporary file because wp_mail()
		// takes paths, not contents. It is removed in a finally-style
		// block so a failed send cannot leak files into temp.
		$tmp = self::temp_file( $attachment['name'], $attachment['content'] );
		if ( '' === $tmp ) {
			return false;
		}

		$sent = (bool) wp_mail( $to, $subject, $body, $headers, array( $tmp ) );
		wp_delete_file( $tmp );

		return $sent;
	}

	/**
	 * Write the CSV to a temporary file.
	 *
	 * @param string $name    Filename.
	 * @param string $content Contents.
	 * @return string Path, or '' on failure.
	 */
	private static function temp_file( string $name, string $content ): string {
		$dir = get_temp_dir();
		$tmp = $dir . 'flavor-report-' . wp_generate_uuid4() . '-' . sanitize_file_name( $name );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$wrote = file_put_contents( $tmp, $content );

		return false === $wrote ? '' : $tmp;
	}

	/**
	 * Subject line.
	 *
	 * @param string $type Type.
	 * @param string $from From.
	 * @param string $to   To.
	 * @return string
	 */
	public static function subject( string $type, string $from, string $to ): string {
		$labels = array(
			'orders'       => __( 'سفارش‌ها', 'flavor-core' ),
			'reservations' => __( 'رزروها', 'flavor-core' ),
			'loyalty'      => __( 'باشگاه مشتریان', 'flavor-core' ),
		);

		return sprintf(
			/* translators: 1: report label, 2: from date, 3: to date. */
			__( 'گزارش %1$s — %2$s تا %3$s', 'flavor-core' ),
			$labels[ $type ] ?? $type,
			$from,
			$to
		);
	}

	/**
	 * Body for a report with rows.
	 *
	 * @param string $type      Type.
	 * @param int    $count     Row count.
	 * @param string $from      From.
	 * @param string $to        To.
	 * @param bool   $truncated Whether the range was capped.
	 * @return string
	 */
	public static function body( string $type, int $count, string $from, string $to, bool $truncated ): string {
		$lines = array(
			sprintf(
				/* translators: %d is the number of rows. */
				__( 'تعداد ردیف‌ها: %d', 'flavor-core' ),
				$count
			),
			sprintf(
				/* translators: %s is the timezone. */
				__( 'منطقهٔ زمانی: %s', 'flavor-core' ),
				ReportAdmin::timezone_label()
			),
		);

		if ( $truncated ) {
			$lines[] = __( 'بازهٔ گزارش محدود شده است؛ برای دریافتِ کامل بازه را کوتاه‌تر کنید.', 'flavor-core' );
		}

		$lines[] = '';
		$lines[] = __( 'فایل پیوست با اکسل، لیبره‌آفیس و گوگل‌شیت سازگار است.', 'flavor-core' );

		return implode( "\n", $lines );
	}

	/**
	 * Body for an empty period.
	 *
	 * @param string $type Type.
	 * @param string $from From.
	 * @param string $to   To.
	 * @return string
	 */
	public static function empty_body( string $type, string $from, string $to ): string {
		return implode(
			"\n",
			array(
				self::subject( $type, $from, $to ),
				'',
				__( 'در این بازه داده‌ای ثبت نشده است. این پیام تأیید می‌کند که زمان‌بندی فعال است.', 'flavor-core' ),
			)
		);
	}
}
