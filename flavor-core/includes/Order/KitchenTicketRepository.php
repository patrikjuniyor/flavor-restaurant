<?php
/**
 * Kitchen ticket persistence. Operational source of truth.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Order;

use FlavorCore\Database\Schema;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class KitchenTicketRepository
 */
class KitchenTicketRepository {

	public const STATUSES = array( 'new', 'preparing', 'ready', 'completed', 'cancelled' );

	public const TRANSITIONS = array(
		'new'        => array( 'preparing', 'cancelled' ),
		'preparing'  => array( 'ready', 'cancelled' ),
		'ready'      => array( 'completed', 'cancelled' ),
		'completed'  => array(),
		'cancelled'  => array(),
	);

	/**
	 * Tickets table.
	 */
	public static function table(): string {
		return Schema::table( 'flavor_kitchen_tickets' );
	}

	/**
	 * Items table.
	 */
	public static function items_table(): string {
		return Schema::table( 'flavor_kitchen_ticket_items' );
	}

	/**
	 * Find by WooCommerce order id.
	 *
	 * @param int $order_id Order id.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_order( int $order_id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Find by ticket id.
	 *
	 * @param int $id Ticket id.
	 * @return array<string, mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Insert a ticket if one does not already exist for the order.
	 *
	 * @param array<string, mixed> $data Ticket columns.
	 * @return int Ticket id.
	 */
	public static function create_idempotent( array $data ): int {
		$existing = self::find_by_order( (int) $data['order_id'] );
		if ( $existing ) {
			return (int) $existing['id'];
		}

		global $wpdb;
		$now = current_time( 'mysql' );

		$row = array(
			'order_id'          => (int) $data['order_id'],
			'order_number'      => sanitize_text_field( (string) ( $data['order_number'] ?? $data['order_id'] ) ),
			'branch_id'         => (int) $data['branch_id'],
			'table_id'          => isset( $data['table_id'] ) ? (int) $data['table_id'] : null,
			'table_number'      => isset( $data['table_number'] ) ? sanitize_text_field( (string) $data['table_number'] ) : null,
			'order_mode'        => sanitize_key( (string) ( $data['order_mode'] ?? 'dine_in' ) ),
			'kitchen_status'    => 'new',
			'payment_status'    => sanitize_key( (string) ( $data['payment_status'] ?? 'pending' ) ),
			'payment_method'    => isset( $data['payment_method'] ) ? sanitize_key( (string) $data['payment_method'] ) : null,
			'customer_id'       => isset( $data['customer_id'] ) ? (int) $data['customer_id'] : null,
			'customer_name'     => isset( $data['customer_name'] ) ? sanitize_text_field( (string) $data['customer_name'] ) : null,
			'customer_mobile'   => isset( $data['customer_mobile'] ) ? sanitize_text_field( (string) $data['customer_mobile'] ) : null,
			'delivery_address'  => isset( $data['delivery_address'] ) ? sanitize_textarea_field( (string) $data['delivery_address'] ) : null,
			'delivery_zone_id'  => isset( $data['delivery_zone_id'] ) ? (int) $data['delivery_zone_id'] : null,
			'delivery_fee'      => (int) ( $data['delivery_fee'] ?? 0 ),
			'subtotal'          => (int) ( $data['subtotal'] ?? 0 ),
			'discount_total'    => (int) ( $data['discount_total'] ?? 0 ),
			'total'             => (int) ( $data['total'] ?? 0 ),
			'special_notes'     => isset( $data['special_notes'] ) ? sanitize_textarea_field( (string) $data['special_notes'] ) : null,
			'source'            => sanitize_key( (string) ( $data['source'] ?? 'online' ) ),
			'placed_at'         => $data['placed_at'] ?? $now,
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::table(), $row );
		if ( ! $ok ) {
			$existing = self::find_by_order( (int) $data['order_id'] );
			return $existing ? (int) $existing['id'] : 0;
		}

		$ticket_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a kitchen ticket is created.
		 *
		 * @param int                  $ticket_id Ticket id.
		 * @param array<string, mixed> $row       Inserted row.
		 */
		do_action( 'flavor_core_kitchen_ticket_created', $ticket_id, $row );

		return $ticket_id;
	}

	/**
	 * Replace ticket items from a WC order.
	 *
	 * @param int       $ticket_id Ticket.
	 * @param \WC_Order $order     Order.
	 */
	public static function sync_items( int $ticket_id, $order ): void {
		if ( ! $order ) {
			return;
		}

		global $wpdb;
		$items_table = self::items_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $items_table, array( 'ticket_id' => $ticket_id ), array( '%d' ) );

		$now = current_time( 'mysql' );
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$mods = $item->get_meta( '_flavor_modifiers' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$items_table,
				array(
					'ticket_id'            => $ticket_id,
					'order_item_id'        => (int) $item->get_id(),
					'product_id'           => (int) $item->get_product_id(),
					'item_name'            => $item->get_name(),
					'quantity'             => (int) $item->get_quantity(),
					'modifiers_json'       => is_array( $mods ) ? wp_json_encode( $mods ) : null,
					'special_instructions' => (string) $item->get_meta( '_flavor_instructions' ),
					'item_status'          => 'pending',
					'prep_time_minutes'    => (int) get_post_meta( $item->get_product_id(), '_flavor_prep_time', true ) ?: null,
					'created_at'           => $now,
					'updated_at'           => $now,
				)
			);
		}
	}

	/**
	 * Advance or set kitchen status with a legal transition.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param string $to        Target status.
	 * @return bool
	 */
	public static function transition( int $ticket_id, string $to ): bool {
		$to     = sanitize_key( $to );
		$ticket = self::find( $ticket_id );
		if ( ! $ticket || ! in_array( $to, self::STATUSES, true ) ) {
			return false;
		}

		$from    = (string) $ticket['kitchen_status'];
		$allowed = self::TRANSITIONS[ $from ] ?? array();
		if ( ! in_array( $to, $allowed, true ) ) {
			return false;
		}

		global $wpdb;
		$now  = current_time( 'mysql' );
		$data = array(
			'kitchen_status' => $to,
			'updated_at'     => $now,
		);
		if ( 'preparing' === $to ) {
			$data['accepted_at'] = $now;
		}
		if ( 'ready' === $to ) {
			$data['ready_at'] = $now;
		}
		if ( 'completed' === $to || 'cancelled' === $to ) {
			$data['completed_at'] = $now;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::table(), $data, array( 'id' => $ticket_id ) );

		/**
		 * Fires after a kitchen status change.
		 *
		 * @param int    $ticket_id Ticket.
		 * @param string $from      Previous.
		 * @param string $to        Next.
		 */
		do_action( 'flavor_core_kitchen_status_changed', $ticket_id, $from, $to );

		return true;
	}

	/**
	 * Open tickets for a branch, newest first.
	 *
	 * @param int         $branch_id Branch.
	 * @param string|null $status    Optional status filter.
	 * @param int         $limit     Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_kitchen( int $branch_id, ?string $status = null, int $limit = 100 ): array {
		global $wpdb;
		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE branch_id = %d";
		$args  = array( $branch_id );

		if ( $status ) {
			$sql   .= ' AND kitchen_status = %s';
			$args[] = $status;
		} else {
			$sql .= " AND kitchen_status IN ('new','preparing','ready')";
		}

		$sql   .= ' ORDER BY placed_at ASC LIMIT %d';
		$args[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Items of a ticket.
	 *
	 * @param int $ticket_id Ticket.
	 * @return array<int, array<string, mixed>>
	 */
	public static function items( int $ticket_id ): array {
		global $wpdb;
		$table = self::items_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE ticket_id = %d", $ticket_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		foreach ( $rows as &$row ) {
			$row['modifiers'] = array();
			if ( ! empty( $row['modifiers_json'] ) ) {
				$decoded = json_decode( (string) $row['modifiers_json'], true );
				$row['modifiers'] = is_array( $decoded ) ? $decoded : array();
			}
		}
		return $rows;
	}

	/**
	 * Public payload for the kitchen UI.
	 *
	 * @param array<string, mixed> $ticket Ticket row.
	 * @return array<string, mixed>
	 */
	public static function to_card( array $ticket ): array {
		$placed = strtotime( (string) $ticket['placed_at'] );
		$age    = $placed ? max( 0, time() - $placed ) : 0;

		return array(
			'id'              => (int) $ticket['id'],
			'order_id'        => (int) $ticket['order_id'],
			'order_number'    => (string) $ticket['order_number'],
			'branch_id'       => (int) $ticket['branch_id'],
			'table_id'        => $ticket['table_id'] ? (int) $ticket['table_id'] : null,
			'table_number'    => $ticket['table_number'],
			'order_mode'      => (string) $ticket['order_mode'],
			'kitchen_status'  => (string) $ticket['kitchen_status'],
			'payment_status'  => (string) $ticket['payment_status'],
			'payment_method'  => (string) $ticket['payment_method'],
			'customer_name'   => (string) $ticket['customer_name'],
			'customer_mobile' => (string) $ticket['customer_mobile'],
			'total_formatted' => Currency::format( (int) $ticket['total'] ),
			'total'           => (int) $ticket['total'],
			'source'          => (string) $ticket['source'],
			'placed_at'       => (string) $ticket['placed_at'],
			'age_seconds'     => $age,
			'urgency'         => $age > 1800 ? 'red' : ( $age > 900 ? 'yellow' : 'ok' ),
			'items'           => self::items( (int) $ticket['id'] ),
		);
	}
}
