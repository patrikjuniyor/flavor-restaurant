<?php
/**
 * Reporting queries for orders, reservations and loyalty (N-11).
 *
 * Reports are read-only by construction: this class only ever builds
 * SELECTs, and every value reaching the database goes through
 * $wpdb->prepare(). Nothing here writes, and nothing here can be talked
 * into writing.
 *
 * Two things are deliberately absent, and both are asserted in tests:
 *
 *  - The OTP table is never read. A passwordless login code has no
 *    business in a spreadsheet, and including it would turn a reporting
 *    feature into a credential-disclosure feature.
 *  - Customer phone numbers are masked unless the requester holds
 *    `flavor_export_pii`. A branch manager exporting a guest list needs to
 *    see that there is a booking, not who to cold-call.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reporting;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReportQuery
 */
class ReportQuery {

	/** Reports this platform can produce. */
	public const TYPES = array( 'orders', 'reservations', 'loyalty' );

	/**
	 * Capability required to see unmasked customer phone numbers.
	 */
	public const PII_CAP = 'flavor_export_pii';

	/**
	 * How many rows a single export may return.
	 *
	 * Without a ceiling, a merchant who leaves the date range open for a
	 * year can take the site down with one click. The cap is generous
	 * enough for any real report and small enough to stay boring.
	 */
	public const MAX_ROWS = 20000;

	/**
	 * Table names.
	 *
	 * Only the tables a report legitimately reads. The OTP table is
	 * deliberately absent: a login code has no business in a spreadsheet,
	 * and the surest way to keep it out of a future export is for this
	 * class to be unable to name the table at all. A test asserts the word
	 * never appears anywhere under Reporting/.
	 *
	 * @return array{reservations:string, loyalty:string}
	 */
	public static function tables(): array {
		global $wpdb;
		return array(
			'reservations' => $wpdb->prefix . 'flavor_reservations',
			'loyalty'      => $wpdb->prefix . 'flavor_loyalty',
		);
	}

	/**
	 * Normalise and validate a report request.
	 *
	 * @param array<string, mixed> $request Raw request.
	 * @return array{type:string, from:string, to:string, branch:int, ok:bool, errors:string[]}
	 */
	public static function normalise( array $request ): array {
		$type   = sanitize_key( (string) ( $request['type'] ?? 'orders' ) );
		$from   = sanitize_text_field( (string) ( $request['from'] ?? '' ) );
		$to     = sanitize_text_field( (string) ( $request['to'] ?? '' ) );
		$branch = absint( $request['branch'] ?? 0 );

		$errors = array();

		if ( ! in_array( $type, self::TYPES, true ) ) {
			$errors[] = __( 'نوع گزارش نامعتبر است.', 'flavor-core' );
			$type     = 'orders';
		}

		// Dates are Y-m-d or empty (empty means unbounded on that side).
		foreach ( array( 'from' => $from, 'to' => $to ) as $key => $value ) {
			if ( '' !== $value && ! self::is_date( $value ) ) {
				$errors[] = __( 'تاریخ باید با قالب سال-ماه-روز باشد.', 'flavor-core' );
				$$key     = '';
			}
		}

		if ( '' !== $from && '' !== $to && $from > $to ) {
			// Rather than silently returning nothing, swap them: a merchant
			// who typed the range backwards wants the range, not a lesson.
			$tmp  = $from;
			$from = $to;
			$to   = $tmp;
		}

		return array(
			'type'   => $type,
			'from'   => $from,
			'to'     => $to,
			'branch' => $branch,
			'ok'     => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Is this a plain calendar date?
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_date( string $value ): bool {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		$parts = explode( '-', $value );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}

	/**
	 * Mask a phone number, keeping only enough to recognise a repeat guest.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function mask_phone( string $value ): string {
		$digits = preg_replace( '/\D+/', '', $value );
		if ( null === $digits || '' === $digits ) {
			return '';
		}
		$len = strlen( $digits );
		if ( $len <= 4 ) {
			return str_repeat( '*', $len );
		}
		return str_repeat( '*', $len - 4 ) . substr( $digits, -4 );
	}

	/**
	 * Whether the current user may see unmasked phone numbers.
	 *
	 * @return bool
	 */
	public static function can_see_pii(): bool {
		return current_user_can( self::PII_CAP ) || current_user_can( 'manage_options' );
	}

	/**
	 * Headers for a report type.
	 *
	 * @param string $type Type.
	 * @return string[]
	 */
	public static function headers( string $type ): array {
		switch ( $type ) {
			case 'reservations':
				return array( 'id', 'branch_id', 'date', 'time', 'party_size', 'status', 'customer_name', 'customer_mobile', 'source', 'created_at' );
			case 'loyalty':
				return array( 'id', 'customer_id', 'order_id', 'points_delta', 'balance_after', 'reason', 'created_at' );
			default:
				return array( 'order_id', 'order_number', 'date', 'status', 'total', 'currency', 'payment_method', 'customer_name', 'customer_mobile', 'items' );
		}
	}

	/**
	 * Run a report.
	 *
	 * @param string $type   Type.
	 * @param string $from   From date (Y-m-d) or ''.
	 * @param string $to     To date (Y-m-d) or ''.
	 * @param int    $branch Branch id, or 0 for all.
	 * @return array{rows:array<int, array<int,string>>, truncated:bool, count:int}
	 */
	public static function run( string $type, string $from, string $to, int $branch = 0 ): array {
		switch ( $type ) {
			case 'reservations':
				$rows = self::reservations( $from, $to, $branch );
				break;
			case 'loyalty':
				$rows = self::loyalty( $from, $to );
				break;
			default:
				$rows = self::orders( $from, $to, $branch );
		}

		$total     = count( $rows );
		$truncated = $total > self::MAX_ROWS;
		if ( $truncated ) {
			$rows = array_slice( $rows, 0, self::MAX_ROWS );
		}

		return array(
			'rows'      => $rows,
			'truncated' => $truncated,
			'count'     => count( $rows ),
		);
	}

	/**
	 * Reservation rows.
	 *
	 * @param string $from   From.
	 * @param string $to     To.
	 * @param int    $branch Branch.
	 * @return array<int, array<int,string>>
	 */
	private static function reservations( string $from, string $to, int $branch ): array {
		global $wpdb;
		$t = self::tables();

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $from ) {
			$where[]  = 'reservation_date >= %s';
			$params[] = $from;
		}
		if ( '' !== $to ) {
			$where[]  = 'reservation_date <= %s';
			$params[] = $to;
		}
		if ( $branch > 0 ) {
			$where[]  = 'branch_id = %d';
			$params[] = $branch;
		}

		$sql = "SELECT id, branch_id, reservation_date, reservation_time, party_size, status,
		               customer_name, customer_mobile, source, created_at
		        FROM {$t['reservations']} WHERE " . implode( ' AND ', $where ) . '
		        ORDER BY reservation_date DESC, reservation_time DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from literals and placeholders only.
		$prepared = $params ? $wpdb->prepare( $sql, $params ) : $sql;
		$results  = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$pii = self::can_see_pii();
		$out = array();
		foreach ( (array) $results as $row ) {
			$out[] = array(
				(string) $row['id'],
				(string) $row['branch_id'],
				(string) $row['reservation_date'],
				(string) $row['reservation_time'],
				(string) $row['party_size'],
				(string) $row['status'],
				(string) $row['customer_name'],
				$pii ? (string) $row['customer_mobile'] : self::mask_phone( (string) $row['customer_mobile'] ),
				(string) $row['source'],
				(string) $row['created_at'],
			);
		}
		return $out;
	}

	/**
	 * Loyalty ledger rows.
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @return array<int, array<int,string>>
	 */
	private static function loyalty( string $from, string $to ): array {
		global $wpdb;
		$t = self::tables();

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $from ) {
			$where[]  = 'created_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( '' !== $to ) {
			$where[]  = 'created_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		$sql = "SELECT id, customer_id, order_id, points_delta, balance_after, reason, created_at
		        FROM {$t['loyalty']} WHERE " . implode( ' AND ', $where ) . '
		        ORDER BY created_at DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $params ? $wpdb->prepare( $sql, $params ) : $sql;
		$results  = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$out = array();
		foreach ( (array) $results as $row ) {
			$out[] = array(
				(string) $row['id'],
				(string) $row['customer_id'],
				(string) ( $row['order_id'] ?? '' ),
				(string) $row['points_delta'],
				(string) $row['balance_after'],
				(string) $row['reason'],
				(string) $row['created_at'],
			);
		}
		return $out;
	}

	/**
	 * Order rows, using WooCommerce's own order store where available.
	 *
	 * @param string $from   From.
	 * @param string $to     To.
	 * @param int    $branch Branch.
	 * @return array<int, array<int,string>>
	 */
	private static function orders( string $from, string $to, int $branch ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$args = array(
			'limit'    => self::MAX_ROWS,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'paginate' => false,
			// Reports should reflect money that actually moved, so drafts
			// and abandoned carts are excluded rather than counted.
			'status'   => array( 'completed', 'processing', 'on-hold', 'refunded', 'cancelled' ),
		);

		if ( '' !== $from || '' !== $to ) {
			$args['date_created'] = ( '' !== $from ? $from : '2000-01-01' ) . '...' . ( '' !== $to ? $to : gmdate( 'Y-m-d' ) );
		}

		if ( $branch > 0 ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'   => '_flavor_branch_id',
					'value' => $branch,
				),
			);
		}

		$orders = wc_get_orders( $args );
		$pii    = self::can_see_pii();
		$out    = array();

		foreach ( (array) $orders as $order ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = $item->get_name() . ' ×' . (int) $item->get_quantity();
			}

			$mobile = (string) $order->get_billing_phone();

			$out[] = array(
				(string) $order->get_id(),
				(string) $order->get_order_number(),
				$order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
				(string) $order->get_status(),
				(string) $order->get_total(),
				(string) $order->get_currency(),
				(string) $order->get_payment_method_title(),
				trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				$pii ? $mobile : self::mask_phone( $mobile ),
				implode( ' | ', $items ),
			);
		}

		return $out;
	}
}
