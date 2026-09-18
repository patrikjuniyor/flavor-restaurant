<?php
/**
 * Restaurant OS Executive Analytics Engine.
 * Computes deep KPIs: Sales, Orders, AOV, Popular Dishes, Peak Hours,
 * Reservations, and Customer Retention.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Analytics;

use FlavorCore\Database\Schema;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsManager
 */
class AnalyticsManager {

	/**
	 * Get executive overview metrics for a branch and date range.
	 *
	 * @param int|null $branch_id Branch ID filter (null = all accessible).
	 * @param string   $start_date Y-m-d start.
	 * @param string   $end_date Y-m-d end.
	 * @return array<string, mixed>
	 */
	public static function get_overview( ?int $branch_id = null, string $start_date = '', string $end_date = '' ): array {
		global $wpdb;

		if ( empty( $start_date ) ) {
			$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( empty( $end_date ) ) {
			$end_date = gmdate( 'Y-m-d' );
		}

		$tickets_tbl = Schema::table( 'flavor_kitchen_tickets' );
		$items_tbl   = Schema::table( 'flavor_kitchen_ticket_items' );
		$res_tbl     = Schema::table( 'flavor_reservations' );

		$branch_sql = '';
		$params     = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );

		if ( $branch_id && $branch_id > 0 ) {
			$branch_sql = ' AND branch_id = %d';
			$params[]   = $branch_id;
		}

		// 1. Sales & Orders Summary
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sales_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(id) AS total_orders,
					COALESCE(SUM(total), 0) AS gross_revenue,
					COALESCE(SUM(subtotal), 0) AS net_revenue,
					COALESCE(SUM(discount_total), 0) AS total_discounts,
					COALESCE(SUM(delivery_fee), 0) AS total_delivery_fees,
					COALESCE(AVG(total), 0) AS avg_order_value
				FROM {$tickets_tbl}
				WHERE placed_at BETWEEN %s AND %s {$branch_sql}",
				...$params
			),
			ARRAY_A
		);

		$total_orders = (int) ( $sales_row['total_orders'] ?? 0 );
		$gross_rev    = (int) ( $sales_row['gross_revenue'] ?? 0 );
		$aov          = (int) ( $sales_row['avg_order_value'] ?? 0 );

		// 2. Order Modes Breakdown
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$modes_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_mode, COUNT(id) AS order_count, COALESCE(SUM(total), 0) AS mode_revenue
				FROM {$tickets_tbl}
				WHERE placed_at BETWEEN %s AND %s {$branch_sql}
				GROUP BY order_mode",
				...$params
			),
			ARRAY_A
		);

		$modes_data = array(
			'dine_in'  => array( 'count' => 0, 'revenue' => 0, 'percentage' => 0 ),
			'takeaway' => array( 'count' => 0, 'revenue' => 0, 'percentage' => 0 ),
			'delivery' => array( 'count' => 0, 'revenue' => 0, 'percentage' => 0 ),
		);

		foreach ( $modes_rows as $mr ) {
			$mode = $mr['order_mode'] ?? 'dine_in';
			if ( isset( $modes_data[ $mode ] ) ) {
				$cnt = (int) $mr['order_count'];
				$modes_data[ $mode ] = array(
					'count'      => $cnt,
					'revenue'    => (int) $mr['mode_revenue'],
					'percentage' => $total_orders > 0 ? round( ( $cnt / $total_orders ) * 100, 1 ) : 0,
				);
			}
		}

		// 3. Reservation Stats
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$res_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(id) AS total_reservations,
					COALESCE(SUM(party_size), 0) AS total_guests,
					SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
					SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
				FROM {$res_tbl}
				WHERE reservation_date BETWEEN %s AND %s {$branch_sql}",
				$start_date,
				$end_date,
				...( $branch_id ? array( $branch_id ) : array() )
			),
			ARRAY_A
		);

		// 4. Customer Retention & Repeat Purchases
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cust_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT customer_mobile, COUNT(id) as orders_count
				FROM {$tickets_tbl}
				WHERE customer_mobile IS NOT NULL AND customer_mobile != ''
				  AND placed_at BETWEEN %s AND %s {$branch_sql}
				GROUP BY customer_mobile",
				...$params
			),
			ARRAY_A
		);

		$unique_customers = count( $cust_rows );
		$repeat_customers = 0;
		foreach ( $cust_rows as $cr ) {
			if ( (int) $cr['orders_count'] > 1 ) {
				$repeat_customers++;
			}
		}
		$retention_rate = $unique_customers > 0 ? round( ( $repeat_customers / $unique_customers ) * 100, 1 ) : 0;

		return array(
			'date_range'        => array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
			),
			'sales'             => array(
				'gross_revenue'      => $gross_rev,
				'net_revenue'        => (int) ( $sales_row['net_revenue'] ?? 0 ),
				'total_discounts'    => (int) ( $sales_row['total_discounts'] ?? 0 ),
				'total_delivery_fees'=> (int) ( $sales_row['total_delivery_fees'] ?? 0 ),
				'total_orders'       => $total_orders,
				'avg_order_value'    => $aov,
			),
			'order_modes'       => $modes_data,
			'reservations'      => array(
				'total_reservations' => (int) ( $res_row['total_reservations'] ?? 0 ),
				'total_guests'       => (int) ( $res_row['total_guests'] ?? 0 ),
				'confirmed'          => (int) ( $res_row['confirmed_count'] ?? 0 ),
				'cancelled'          => (int) ( $res_row['cancelled_count'] ?? 0 ),
			),
			'customers'         => array(
				'total_active'       => $unique_customers,
				'repeat_customers'   => $repeat_customers,
				'retention_rate_pct' => $retention_rate,
			),
		);
	}

	/**
	 * Compute 24-hour Peak Hours order and revenue histogram.
	 *
	 * @param int|null $branch_id Branch ID filter.
	 * @param string   $start_date Start date.
	 * @param string   $end_date End date.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_peak_hours( ?int $branch_id = null, string $start_date = '', string $end_date = '' ): array {
		global $wpdb;

		if ( empty( $start_date ) ) {
			$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( empty( $end_date ) ) {
			$end_date = gmdate( 'Y-m-d' );
		}

		$tickets_tbl = Schema::table( 'flavor_kitchen_tickets' );
		$branch_sql  = '';
		$params      = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );

		if ( $branch_id && $branch_id > 0 ) {
			$branch_sql = ' AND branch_id = %d';
			$params[]   = $branch_id;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					HOUR(placed_at) AS order_hour,
					COUNT(id) AS order_count,
					COALESCE(SUM(total), 0) AS total_revenue
				FROM {$tickets_tbl}
				WHERE placed_at BETWEEN %s AND %s {$branch_sql}
				GROUP BY HOUR(placed_at)
				ORDER BY order_hour ASC",
				...$params
			),
			ARRAY_A
		);

		// Map to 24 slots (00:00 to 23:00)
		$histogram = array();
		for ( $h = 0; $h < 24; $h++ ) {
			$histogram[ $h ] = array(
				'hour'          => sprintf( '%02d:00', $h ),
				'order_count'   => 0,
				'total_revenue' => 0,
			);
		}

		foreach ( $rows as $r ) {
			$hr = (int) $r['order_hour'];
			if ( isset( $histogram[ $hr ] ) ) {
				$histogram[ $hr ]['order_count']   = (int) $r['order_count'];
				$histogram[ $hr ]['total_revenue'] = (int) $r['total_revenue'];
			}
		}

		return array_values( $histogram );
	}

	/**
	 * Get most popular dishes by volume and revenue contribution.
	 *
	 * @param int|null $branch_id Branch ID filter.
	 * @param int      $limit Number of top dishes.
	 * @param string   $start_date Start date.
	 * @param string   $end_date End date.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_popular_dishes( ?int $branch_id = null, int $limit = 10, string $start_date = '', string $end_date = '' ): array {
		global $wpdb;

		if ( empty( $start_date ) ) {
			$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( empty( $end_date ) ) {
			$end_date = gmdate( 'Y-m-d' );
		}

		$tickets_tbl = Schema::table( 'flavor_kitchen_tickets' );
		$items_tbl   = Schema::table( 'flavor_kitchen_ticket_items' );

		$branch_sql = '';
		$params     = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );

		if ( $branch_id && $branch_id > 0 ) {
			$branch_sql = ' AND t.branch_id = %d';
			$params[]   = $branch_id;
		}

		$params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					i.product_id,
					i.item_name,
					SUM(i.quantity) AS total_quantity,
					COUNT(DISTINCT i.ticket_id) AS tickets_count
				FROM {$items_tbl} i
				INNER JOIN {$tickets_tbl} t ON i.ticket_id = t.id
				WHERE t.placed_at BETWEEN %s AND %s {$branch_sql}
				GROUP BY i.product_id, i.item_name
				ORDER BY total_quantity DESC
				LIMIT %d",
				...$params
			),
			ARRAY_A
		);

		return array_map(
			static function( $row ) {
				return array(
					'product_id'     => (int) $row['product_id'],
					'item_name'      => $row['item_name'],
					'total_quantity' => (int) $row['total_quantity'],
					'tickets_count'  => (int) $row['tickets_count'],
				);
			},
			$rows
		);
	}

	/**
	 * Summary comparison across all branches.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_branches_summary( string $start_date = '', string $end_date = '' ): array {
		global $wpdb;

		if ( empty( $start_date ) ) {
			$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( empty( $end_date ) ) {
			$end_date = gmdate( 'Y-m-d' );
		}

		$tickets_tbl = Schema::table( 'flavor_kitchen_tickets' );
		$branches    = get_posts( array( 'post_type' => 'flavor_branch', 'numberposts' => -1, 'post_status' => 'publish' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					branch_id,
					COUNT(id) AS total_orders,
					COALESCE(SUM(total), 0) AS gross_revenue,
					COALESCE(AVG(total), 0) AS avg_order_value
				FROM {$tickets_tbl}
				WHERE placed_at BETWEEN %s AND %s
				GROUP BY branch_id",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		$indexed = array();
		foreach ( $rows as $r ) {
			$indexed[ (int) $r['branch_id'] ] = $r;
		}

		$out = array();
		foreach ( $branches as $b ) {
			$bid = (int) $b->ID;
			$stat = $indexed[ $bid ] ?? array( 'total_orders' => 0, 'gross_revenue' => 0, 'avg_order_value' => 0 );
			$out[] = array(
				'branch_id'       => $bid,
				'branch_name'     => $b->post_title,
				'total_orders'    => (int) $stat['total_orders'],
				'gross_revenue'   => (int) $stat['gross_revenue'],
				'avg_order_value' => (int) $stat['avg_order_value'],
			);
		}

		return $out;
	}
}
