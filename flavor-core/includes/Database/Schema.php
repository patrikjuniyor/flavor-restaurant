<?php
/**
 * Custom table definitions and migrations.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 */
class Schema {

	/**
	 * Option that stores the installed schema version.
	 */
	public const VERSION_OPTION = 'flavor_core_db_version';

	/**
	 * Table short names without the $wpdb prefix.
	 *
	 * @return string[]
	 */
	public static function table_slugs(): array {
		return array(
			'flavor_kitchen_tickets',
			'flavor_kitchen_ticket_items',
			'flavor_tables',
			'flavor_reservations',
			'flavor_delivery_zones',
			'flavor_availability',
			'flavor_availability_log',
			'flavor_loyalty_ledger',
			'flavor_sms_log',
			'flavor_otp_codes',
			'flavor_customer_addresses',
			'flavor_menu_schedules',
			'flavor_branch_hours',
			'flavor_branch_closures',
		);
	}

	/**
	 * Fully prefixed table name.
	 *
	 * @param string $slug Short name, e.g. flavor_tables.
	 */
	public static function table( string $slug ): string {
		global $wpdb;
		return $wpdb->prefix . $slug;
	}

	/**
	 * Create or upgrade all tables.
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = self::charset_collate();

		foreach ( self::statements( $charset ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::VERSION_OPTION, FLAVOR_CORE_DB_VERSION );
	}

	/**
	 * Run install when the stored version is behind.
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::VERSION_OPTION, '' );
		if ( $installed !== FLAVOR_CORE_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Drop every custom table. Used by uninstall.php only.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( self::table_slugs() as $slug ) {
			$table = self::table( $slug );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Charset clause matching the WP install.
	 */
	private static function charset_collate(): string {
		global $wpdb;
		return $wpdb->get_charset_collate();
	}

	/**
	 * dbDelta-compatible CREATE statements.
	 *
	 * @param string $charset Charset collate clause.
	 * @return string[]
	 */
	private static function statements( string $charset ): array {
		$tickets      = self::table( 'flavor_kitchen_tickets' );
		$items        = self::table( 'flavor_kitchen_ticket_items' );
		$tables       = self::table( 'flavor_tables' );
		$reservations = self::table( 'flavor_reservations' );
		$zones        = self::table( 'flavor_delivery_zones' );
		$avail        = self::table( 'flavor_availability' );
		$avail_log    = self::table( 'flavor_availability_log' );
		$loyalty      = self::table( 'flavor_loyalty_ledger' );
		$sms          = self::table( 'flavor_sms_log' );
		$otp          = self::table( 'flavor_otp_codes' );
		$addresses    = self::table( 'flavor_customer_addresses' );
		$schedules    = self::table( 'flavor_menu_schedules' );
		$hours        = self::table( 'flavor_branch_hours' );
		$closures     = self::table( 'flavor_branch_closures' );

		return array(
			"CREATE TABLE {$tickets} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				order_id BIGINT UNSIGNED NOT NULL,
				order_number VARCHAR(32) NOT NULL,
				branch_id BIGINT UNSIGNED NOT NULL,
				table_id BIGINT UNSIGNED NULL,
				table_number VARCHAR(20) NULL,
				order_mode VARCHAR(20) NOT NULL,
				kitchen_status VARCHAR(20) NOT NULL DEFAULT 'new',
				payment_status VARCHAR(32) NOT NULL DEFAULT 'pending',
				payment_method VARCHAR(64) NULL,
				customer_id BIGINT UNSIGNED NULL,
				customer_name VARCHAR(190) NULL,
				customer_mobile VARCHAR(20) NULL,
				delivery_address TEXT NULL,
				delivery_zone_id BIGINT UNSIGNED NULL,
				delivery_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
				subtotal BIGINT UNSIGNED NOT NULL DEFAULT 0,
				discount_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
				total BIGINT UNSIGNED NOT NULL DEFAULT 0,
				special_notes TEXT NULL,
				source VARCHAR(20) NOT NULL DEFAULT 'online',
				placed_at DATETIME NOT NULL,
				accepted_at DATETIME NULL,
				ready_at DATETIME NULL,
				completed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uk_order (order_id),
				KEY idx_branch_status_placed (branch_id, kitchen_status, placed_at),
				KEY idx_table_status (table_id, kitchen_status),
				KEY idx_mobile (customer_mobile),
				KEY idx_placed (placed_at)
			) {$charset};",

			"CREATE TABLE {$items} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ticket_id BIGINT UNSIGNED NOT NULL,
				order_item_id BIGINT UNSIGNED NOT NULL,
				product_id BIGINT UNSIGNED NOT NULL,
				item_name VARCHAR(190) NOT NULL,
				quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
				modifiers_json LONGTEXT NULL,
				special_instructions VARCHAR(200) NULL,
				item_status VARCHAR(20) NOT NULL DEFAULT 'pending',
				prep_time_minutes SMALLINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_ticket (ticket_id),
				KEY idx_order_item (order_item_id)
			) {$charset};",

			"CREATE TABLE {$tables} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				branch_id BIGINT UNSIGNED NOT NULL,
				table_number VARCHAR(20) NOT NULL,
				label VARCHAR(190) NULL,
				capacity TINYINT UNSIGNED NOT NULL DEFAULT 4,
				section VARCHAR(20) NOT NULL DEFAULT 'indoor',
				qr_token CHAR(32) NOT NULL,
				is_active TINYINT(1) NOT NULL DEFAULT 1,
				sort_order INT NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uk_branch_number (branch_id, table_number),
				UNIQUE KEY uk_qr (qr_token),
				KEY idx_branch_active (branch_id, is_active)
			) {$charset};",

			"CREATE TABLE {$reservations} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				branch_id BIGINT UNSIGNED NOT NULL,
				table_id BIGINT UNSIGNED NULL,
				section VARCHAR(20) NULL,
				reservation_date DATE NOT NULL,
				reservation_time TIME NOT NULL,
				duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
				party_size TINYINT UNSIGNED NOT NULL,
				customer_id BIGINT UNSIGNED NULL,
				customer_name VARCHAR(190) NOT NULL,
				customer_mobile VARCHAR(20) NOT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'pending',
				special_requests TEXT NULL,
				source VARCHAR(20) NOT NULL DEFAULT 'online',
				reminder_sent_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_branch_date_status (branch_id, reservation_date, status),
				KEY idx_mobile (customer_mobile),
				KEY idx_date_time (reservation_date, reservation_time)
			) {$charset};",

			"CREATE TABLE {$zones} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				branch_id BIGINT UNSIGNED NOT NULL,
				name VARCHAR(190) NOT NULL,
				zone_type VARCHAR(20) NOT NULL DEFAULT 'neighborhoods',
				center_lat DECIMAL(10,7) NULL,
				center_lng DECIMAL(10,7) NULL,
				radius_km DECIMAL(6,2) NULL,
				neighborhoods_json LONGTEXT NULL,
				polygon_json LONGTEXT NULL,
				delivery_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
				min_order BIGINT UNSIGNED NOT NULL DEFAULT 0,
				estimated_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 45,
				is_active TINYINT(1) NOT NULL DEFAULT 1,
				sort_order INT NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_branch_active (branch_id, is_active)
			) {$charset};",

			"CREATE TABLE {$avail} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				branch_id BIGINT UNSIGNED NOT NULL,
				product_id BIGINT UNSIGNED NOT NULL,
				is_available TINYINT(1) NOT NULL DEFAULT 1,
				unavailable_until DATETIME NULL,
				reason VARCHAR(190) NULL,
				updated_by BIGINT UNSIGNED NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uk_branch_product (branch_id, product_id)
			) {$charset};",

			"CREATE TABLE {$avail_log} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				branch_id BIGINT UNSIGNED NOT NULL,
				product_id BIGINT UNSIGNED NOT NULL,
				old_available TINYINT(1) NULL,
				new_available TINYINT(1) NOT NULL,
				unavailable_until DATETIME NULL,
				changed_by BIGINT UNSIGNED NULL,
				changed_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_branch_product_time (branch_id, product_id, changed_at)
			) {$charset};",

			"CREATE TABLE {$loyalty} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_id BIGINT UNSIGNED NOT NULL,
				order_id BIGINT UNSIGNED NULL,
				points_delta INT NOT NULL,
				balance_after INT NOT NULL,
				reason VARCHAR(40) NOT NULL,
				note VARCHAR(190) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_customer_time (customer_id, created_at),
				KEY idx_order (order_id)
			) {$charset};",

			"CREATE TABLE {$sms} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				provider VARCHAR(40) NOT NULL,
				event VARCHAR(40) NOT NULL,
				recipient VARCHAR(20) NOT NULL,
				template VARCHAR(64) NULL,
				body TEXT NULL,
				status VARCHAR(20) NOT NULL,
				provider_message_id VARCHAR(64) NULL,
				related_type VARCHAR(40) NULL,
				related_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_recipient_time (recipient, created_at),
				KEY idx_related (related_type, related_id)
			) {$charset};",

			"CREATE TABLE {$otp} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				mobile VARCHAR(20) NOT NULL,
				code_hash CHAR(64) NOT NULL,
				attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
				expires_at DATETIME NOT NULL,
				consumed_at DATETIME NULL,
				ip VARCHAR(45) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_mobile_exp (mobile, expires_at)
			) {$charset};",

			"CREATE TABLE {$addresses} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_id BIGINT UNSIGNED NOT NULL,
				label VARCHAR(80) NULL,
				province VARCHAR(80) NOT NULL,
				city VARCHAR(80) NOT NULL,
				neighborhood VARCHAR(80) NULL,
				address_line TEXT NOT NULL,
				postal_code VARCHAR(10) NULL,
				lat DECIMAL(10,7) NULL,
				lng DECIMAL(10,7) NULL,
				is_default TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY idx_customer_default (customer_id, is_default)
			) {$charset};",

			"CREATE TABLE {$schedules} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				branch_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				name VARCHAR(80) NOT NULL,
				slug VARCHAR(40) NOT NULL,
				start_time TIME NOT NULL,
				end_time TIME NOT NULL,
				days_json VARCHAR(60) NOT NULL,
				is_active TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uk_branch_slug (branch_id, slug)
			) {$charset};",

			"CREATE TABLE {$hours} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				branch_id BIGINT UNSIGNED NOT NULL,
				day_of_week TINYINT UNSIGNED NOT NULL,
				mode VARCHAR(20) NOT NULL DEFAULT 'all',
				open_time TIME NULL,
				close_time TIME NULL,
				is_closed TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY uk_branch_day_mode (branch_id, day_of_week, mode)
			) {$charset};",

			"CREATE TABLE {$closures} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				branch_id BIGINT UNSIGNED NOT NULL,
				closure_date DATE NOT NULL,
				reason VARCHAR(190) NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uk_branch_date (branch_id, closure_date)
			) {$charset};",
		);
	}
}
