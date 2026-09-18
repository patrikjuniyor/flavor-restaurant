<?php
/**
 * Mock WordPress & WooCommerce Runtime Environment for Standalone Integration Tests.
 * Provides SQLite database mock for $wpdb, WP_User, WP_REST_Request, WP_REST_Response,
 * WooCommerce order/product objects, and WP action/filter hooks.
 *
 * @package FlavorCore\Tests
 */

if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'ARRAY_N' ) ) define( 'ARRAY_N', 'ARRAY_N' );
if ( ! defined( 'OBJECT' ) ) define( 'OBJECT', 'OBJECT' );
if ( ! defined( 'AUTH_SALT' ) ) define( 'AUTH_SALT', 'mock-test-auth-salt-1234567890' );

// In-memory transients
$GLOBALS['_mock_transients'] = array();
function get_transient( string $transient ) {
	return $GLOBALS['_mock_transients'][ $transient ] ?? false;
}
function set_transient( string $transient, $value, int $expiration = 0 ): bool {
	$GLOBALS['_mock_transients'][ $transient ] = $value;
	return true;
}
function delete_transient( string $transient ): bool {
	unset( $GLOBALS['_mock_transients'][ $transient ] );
	return true;
}

function absint( $maybeint ): int {
	return abs( (int) $maybeint );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function is_email( string $email ) {
	return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

function sanitize_email( string $email ): string {
	return filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: '';
}

function number_format_i18n( $number, int $decimals = 0 ): string {
	return number_format( (float) $number, $decimals );
}

function wp_strip_all_tags( string $string, bool $remove_breaks = false ): string {
	$string = strip_tags( $string );
	if ( $remove_breaks ) {
		$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
	}
	return trim( $string );
}

function wp_parse_args( $args, array $defaults = array() ): array {
	if ( is_object( $args ) ) {
		$parsed_args = get_object_vars( $args );
	} elseif ( is_array( $args ) ) {
		$parsed_args = &$args;
	} else {
		$parsed_args = array();
	}
	return array_merge( $defaults, $parsed_args );
}

function wp_list_pluck( array $list, string $field ): array {
	$out = array();
	foreach ( $list as $key => $value ) {
		if ( is_object( $value ) ) {
			$out[ $key ] = $value->$field ?? null;
		} elseif ( is_array( $value ) ) {
			$out[ $key ] = $value[ $field ] ?? null;
		}
	}
	return $out;
}

function add_query_arg( ...$args ): string {
	return 'https://restaurant.example.com/';
}

function home_url( string $path = '' ): string {
	return 'https://restaurant.example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
}

function site_url( string $path = '' ): string {
	return home_url( $path );
}

function wp_cache_get( $key, $group = '' ) { return false; }
function wp_cache_set( $key, $val, $group = '', $expire = 0 ) { return true; }
function wp_cache_delete( $key, $group = '' ) { return true; }
function wp_set_auth_cookie( int $user_id, bool $remember = false ) {}

/**
 * Mock WP_Term Class.
 */
class WP_Term {
	public int $term_id;
	public string $name;
	public string $slug;
	public string $taxonomy;
	public string $description;
	public int $count;

	public function __construct( int $id, string $name, string $slug, string $tax = 'product_cat', string $desc = '', int $count = 0 ) {
		$this->term_id     = $id;
		$this->name        = $name;
		$this->slug        = $slug;
		$this->taxonomy    = $tax;
		$this->description = $desc;
		$this->count       = $count;
	}
}

/**
 * Mock WP_User Class.
 */
class WP_User {
	public int $ID = 0;
	public array $roles = array();
	public string $user_login = '';
	public string $user_email = '';
	public string $display_name = '';

	public function __construct( $id = 0 ) {
		if ( is_numeric( $id ) && $id > 0 ) {
			$this->ID = (int) $id;
			$this->user_login = 'user_' . $id;
			$this->user_email = "user_{$id}@example.com";
			$this->display_name = "User {$id}";
			$this->roles = array( 'customer' );
		} elseif ( is_object( $id ) || is_array( $id ) ) {
			foreach ( (array) $id as $k => $v ) {
				$this->$k = $v;
			}
		}
	}

	public function exists(): bool {
		return $this->ID > 0;
	}

	public function has_cap( string $cap ): bool {
		if ( in_array( 'administrator', $this->roles, true ) ) return true;
		if ( $cap === 'manage_options' && in_array( 'administrator', $this->roles, true ) ) return true;
		if ( $cap === 'flavor_manage_kitchen' && in_array( 'flavor_kitchen', $this->roles, true ) ) return true;
		return false;
	}
}

/**
 * Mock WC_Product Class.
 */
class WC_Product {
	public int $id;
	public string $name;
	public int $price;
	public function __construct( int $id, string $name, int $price ) {
		$this->id    = $id;
		$this->name  = $name;
		$this->price = $price;
	}
	public function get_id(): int { return $this->id; }
	public function get_name(): string { return $this->name; }
	public function get_slug(): string { return 'dish-' . $this->id; }
	public function get_price(): int { return $this->price; }
	public function get_regular_price(): int { return $this->price; }
	public function is_purchasable(): bool { return true; }
	public function get_status(): string { return 'publish'; }
	public function is_on_sale(): bool { return false; }
	public function get_short_description(): string { return 'تهیه شده از بهترین مواد اولیه ایرانی'; }
	public function get_description(): string { return 'توضیحات کامل غذای اصیل و خوش‌طعم'; }
	public function get_image_id(): int { return 0; }
	public function get_gallery_image_ids(): array { return array(); }
	public function get_average_rating(): float { return 4.9; }
	public function get_rating_count(): int { return 120; }
	public function get_sku(): string { return 'DISH-' . $this->id; }
}

/**
 * Mock WC_Order_Item_Product Class.
 */
class WC_Order_Item_Product {
	public array $ci;
	public function __construct( array $ci ) { $this->ci = $ci; }
	public function get_id(): int { return 1; }
	public function get_product_id(): int { return (int) ( $this->ci['product_id'] ?? 1 ); }
	public function get_name(): string { return $this->ci['data'] ? $this->ci['data']->get_name() : 'کباب کوبیده'; }
	public function get_quantity(): int { return (int) ( $this->ci['quantity'] ?? 1 ); }
	public function get_total(): float { return (float) ( $this->ci['line_total'] ?? 150000 ); }
	public function get_meta( string $k ) {
		if ( $k === '_flavor_modifiers' ) return $this->ci['flavor_modifiers'] ?? array();
		if ( $k === '_flavor_instructions' ) return $this->ci['flavor_instructions'] ?? '';
		return null;
	}
	public function get_product() { return $this->ci['data'] ?? wc_get_product( $this->get_product_id() ); }
}

/**
 * Mock WC_Order Class.
 */
class WC_Order {
	public int $id;
	public string $status = 'pending';
	public int $customer_id = 0;
	public array $meta = array();
	public array $posted = array();
	public array $items = array();

	public function __construct( int $id, array $posted = array() ) {
		$this->id = $id;
		$this->posted = $posted;
		$this->customer_id = get_current_user_id();

		// Populate items from active WC cart
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $ci ) {
				$this->items[] = new WC_Order_Item_Product( $ci );
			}
		}
	}

	public function get_id(): int { return $this->id; }
	public function get_order_number(): string { return (string) $this->id; }
	public function get_customer_id(): int { return $this->customer_id; }
	public function get_status(): string { return $this->status; }
	public function update_status( string $st, string $note = '' ) { $this->status = $st; }
	public function get_payment_method(): string { return $this->posted['payment_method'] ?? 'flavor_pay_at_counter'; }
	public function get_payment_method_title(): string { return 'پرداخت در محل / حضوری'; }
	public function set_payment_method( string $m ) { $this->posted['payment_method'] = $m; }
	public function get_meta( string $k ) { return $this->meta[ $k ] ?? null; }
	public function update_meta_data( string $k, $v ) { $this->meta[ $k ] = $v; }
	public function save() {}
	public function get_items(): array { return $this->items; }
	public function get_total(): float { return 350000.0; }
	public function get_subtotal(): float { return 350000.0; }
	public function get_discount_total(): float { return 0.0; }
	public function get_shipping_total(): float { return 0.0; }
	public function get_currency(): string { return 'IRT'; }
	public function get_date_created() {
		return new class {
			public function date( $f ) { return date( $f ); }
		};
	}
	public function get_formatted_billing_full_name(): string { return $this->posted['billing_first_name'] ?? 'مشتری'; }
	public function get_billing_first_name(): string { return $this->posted['billing_first_name'] ?? ''; }
	public function get_billing_last_name(): string { return ''; }
	public function get_billing_phone(): string { return $this->posted['billing_phone'] ?? ''; }
	public function get_billing_address_1(): string { return $this->posted['billing_address_1'] ?? ''; }
	public function get_billing_city(): string { return $this->posted['billing_city'] ?? 'تهران'; }
	public function get_billing_state(): string { return $this->posted['billing_state'] ?? 'تهران'; }
	public function get_formatted_billing_address(): string { return $this->get_billing_address_1(); }
	public function get_customer_note(): string { return $this->posted['order_comments'] ?? ''; }
	public function get_item_count(): int { return count( $this->items ); }
}

/**
 * Mock $wpdb using in-memory SQLite PDO.
 */
class MockWPDB {
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public \PDO $pdo;

	public function __construct() {
		$this->pdo = new \PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		$this->init_schema();
	}

	private function init_schema(): void {
		$this->pdo->exec( "
			CREATE TABLE wp_flavor_otp_codes (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				mobile TEXT NOT NULL,
				code_hash TEXT NOT NULL,
				attempts INTEGER NOT NULL DEFAULT 0,
				expires_at DATETIME NOT NULL,
				consumed_at DATETIME NULL,
				ip TEXT NULL,
				created_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_auth_tokens (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NOT NULL,
				token_hash TEXT NOT NULL,
				refresh_token_hash TEXT NULL,
				device_id TEXT NULL,
				device_name TEXT NULL,
				ip TEXT NULL,
				user_agent TEXT NULL,
				expires_at DATETIME NOT NULL,
				revoked_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_guest_carts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				cart_token_hash TEXT NOT NULL UNIQUE,
				cart_data TEXT NOT NULL,
				branch_id INTEGER NOT NULL DEFAULT 0,
				expires_at DATETIME NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_kitchen_tickets (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				order_id INTEGER NOT NULL UNIQUE,
				order_number TEXT NOT NULL,
				branch_id INTEGER NOT NULL,
				table_id INTEGER NULL,
				table_number TEXT NULL,
				order_mode TEXT NOT NULL,
				kitchen_status TEXT NOT NULL DEFAULT 'new',
				payment_status TEXT NOT NULL DEFAULT 'pending',
				payment_method TEXT NULL,
				customer_id INTEGER NULL,
				customer_name TEXT NULL,
				customer_mobile TEXT NULL,
				guest_token TEXT NULL,
				delivery_address TEXT NULL,
				delivery_zone_id INTEGER NULL,
				delivery_fee INTEGER NOT NULL DEFAULT 0,
				subtotal INTEGER NOT NULL DEFAULT 0,
				discount_total INTEGER NOT NULL DEFAULT 0,
				total INTEGER NOT NULL DEFAULT 0,
				special_notes TEXT NULL,
				source TEXT NOT NULL DEFAULT 'online',
				placed_at DATETIME NOT NULL,
				accepted_at DATETIME NULL,
				ready_at DATETIME NULL,
				completed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_kitchen_ticket_items (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				ticket_id INTEGER NOT NULL,
				order_item_id INTEGER NOT NULL,
				product_id INTEGER NOT NULL,
				item_name TEXT NOT NULL,
				quantity INTEGER NOT NULL DEFAULT 1,
				modifiers_json TEXT NULL,
				special_instructions TEXT NULL,
				item_status TEXT NOT NULL DEFAULT 'pending',
				prep_time_minutes INTEGER NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_reservations (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER NOT NULL,
				table_id INTEGER NULL,
				section TEXT NULL,
				reservation_date DATE NOT NULL,
				reservation_time TIME NOT NULL,
				duration_minutes INTEGER NOT NULL DEFAULT 90,
				party_size INTEGER NOT NULL,
				customer_id INTEGER NULL,
				customer_name TEXT NOT NULL,
				customer_mobile TEXT NOT NULL,
				guest_token TEXT NULL,
				status TEXT NOT NULL DEFAULT 'pending',
				special_requests TEXT NULL,
				source TEXT NOT NULL DEFAULT 'online',
				reminder_sent_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_tables (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER NOT NULL,
				table_number TEXT NOT NULL,
				label TEXT NULL,
				capacity INTEGER NOT NULL DEFAULT 4,
				section TEXT NOT NULL DEFAULT 'indoor',
				qr_token TEXT NOT NULL UNIQUE,
				is_active INTEGER NOT NULL DEFAULT 1,
				sort_order INTEGER NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_device_tokens (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NULL,
				device_token TEXT NOT NULL UNIQUE,
				platform TEXT NOT NULL DEFAULT 'android',
				app_version TEXT NOT NULL DEFAULT '1.0.0',
				branch_id INTEGER NULL,
				is_active INTEGER NOT NULL DEFAULT 1,
				last_seen_at DATETIME NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NULL
			);

			CREATE TABLE wp_flavor_system_logs (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				level TEXT NOT NULL,
				channel TEXT NOT NULL,
				message TEXT NOT NULL,
				context_json TEXT NULL,
				user_id INTEGER NULL,
				ip TEXT NULL,
				request_uri TEXT NULL,
				duration_ms INTEGER NULL,
				created_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_sms_log (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				provider TEXT NOT NULL,
				event TEXT NOT NULL,
				recipient TEXT NOT NULL,
				template TEXT NULL,
				body TEXT NOT NULL,
				status TEXT NOT NULL,
				provider_message_id TEXT NULL,
				related_type TEXT NULL,
				related_id INTEGER NULL,
				created_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_menu_schedules (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER NOT NULL DEFAULT 0,
				name TEXT NOT NULL,
				slug TEXT NOT NULL,
				start_time TEXT NOT NULL,
				end_time TEXT NOT NULL,
				days_json TEXT NOT NULL,
				is_active INTEGER NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_delivery_zones (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER NOT NULL,
				name TEXT NOT NULL,
				zone_type TEXT NOT NULL DEFAULT 'neighborhoods',
				center_lat REAL NULL,
				center_lng REAL NULL,
				radius_km REAL NULL,
				neighborhoods_json TEXT NULL,
				delivery_fee INTEGER NOT NULL DEFAULT 0,
				min_order INTEGER NOT NULL DEFAULT 0,
				estimated_minutes INTEGER NOT NULL DEFAULT 45,
				is_active INTEGER NOT NULL DEFAULT 1,
				sort_order INTEGER NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_loyalty_ledger (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				customer_id INTEGER NOT NULL,
				order_id INTEGER NULL,
				points_delta INTEGER NOT NULL,
				balance_after INTEGER NOT NULL,
				reason TEXT NOT NULL,
				note TEXT NULL,
				created_at DATETIME NOT NULL
			);
		" );

		// Seed some tables for Branch 1 & Branch 2
		$this->pdo->exec( "
			INSERT INTO wp_flavor_tables (branch_id, table_number, label, capacity, section, qr_token, is_active, sort_order, created_at, updated_at)
			VALUES
			(1, 'T-01', 'میز پنجره ۱', 4, 'window', 'qr_token_1', 1, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
			(1, 'T-02', 'میز خانوادگی', 6, 'indoor', 'qr_token_2', 1, 2, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
			(1, 'T-03', 'میز حیاط', 4, 'outdoor', 'qr_token_3', 1, 3, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
			(1, 'T-04', 'میز سالن', 4, 'indoor', 'qr_token_4', 1, 4, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

			INSERT INTO wp_flavor_delivery_zones (branch_id, name, zone_type, neighborhoods_json, delivery_fee, min_order, estimated_minutes, is_active, created_at, updated_at)
			VALUES
			(1, 'منطقه ونک و مرکز تهران', 'neighborhoods', '[\"تهران\",\"ونک\",\"ملاصدرا\"]', 25000, 100000, 35, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
			(2, 'منطقه سعادت آباد', 'neighborhoods', '[\"تهران\",\"سعادت آباد\",\"شهرک غرب\"]', 30000, 150000, 40, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00');
		" );
	}

	public function get_charset_collate(): string {
		return '';
	}

	public function prepare( string $query, ...$args ): string {
		if ( count( $args ) === 1 && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$idx = 0;
		return preg_replace_callback( '/%([sdfF])/', function ( $match ) use ( &$idx, $args ) {
			$type = $match[1];
			$val  = $args[ $idx++ ] ?? '';
			if ( is_null( $val ) ) return 'NULL';
			if ( 'd' === $type ) return (string) (int) $val;
			if ( 'f' === $type || 'F' === $type ) return (string) (float) $val;
			return "'" . addslashes( (string) $val ) . "'";
		}, $query );
	}

	public function query( string $sql ) {
		try {
			return $this->pdo->exec( $sql );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function insert( string $table, array $data, array $format = null ): bool {
		$cols = array_keys( $data );
		$placeholders = array_fill( 0, count( $cols ), '?' );
		$sql = "INSERT INTO {$table} (" . implode( ',', $cols ) . ") VALUES (" . implode( ',', $placeholders ) . ")";
		try {
			$stmt = $this->pdo->prepare( $sql );
			$stmt->execute( array_values( $data ) );
			$this->insert_id = (int) $this->pdo->lastInsertId();
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function replace( string $table, array $data ): bool {
		$cols = array_keys( $data );
		$placeholders = array_fill( 0, count( $cols ), '?' );
		$sql = "INSERT OR REPLACE INTO {$table} (" . implode( ',', $cols ) . ") VALUES (" . implode( ',', $placeholders ) . ")";
		try {
			$stmt = $this->pdo->prepare( $sql );
			$stmt->execute( array_values( $data ) );
			$this->insert_id = (int) $this->pdo->lastInsertId();
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function update( string $table, array $data, array $where, array $format = null, array $where_format = null ): bool {
		$sets = array();
		$vals = array();
		foreach ( $data as $k => $v ) {
			$sets[] = "{$k} = ?";
			$vals[] = $v;
		}
		$where_clauses = array();
		foreach ( $where as $k => $v ) {
			$where_clauses[] = "{$k} = ?";
			$vals[] = $v;
		}
		$sql = "UPDATE {$table} SET " . implode( ',', $sets ) . " WHERE " . implode( ' AND ', $where_clauses );
		try {
			$stmt = $this->pdo->prepare( $sql );
			return $stmt->execute( $vals );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function delete( string $table, array $where, array $where_format = null ): bool {
		$where_clauses = array();
		$vals = array();
		foreach ( $where as $k => $v ) {
			$where_clauses[] = "{$k} = ?";
			$vals[] = $v;
		}
		$sql = "DELETE FROM {$table} WHERE " . implode( ' AND ', $where_clauses );
		try {
			$stmt = $this->pdo->prepare( $sql );
			return $stmt->execute( $vals );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function get_row( string $query, string $output = OBJECT ) {
		try {
			$stmt = $this->pdo->query( $query );
			$row = $stmt->fetch( \PDO::FETCH_ASSOC );
			if ( ! $row ) return null;
			return ( $output === OBJECT ) ? (object) $row : $row;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public function get_results( string $query, string $output = OBJECT ): array {
		try {
			$stmt = $this->pdo->query( $query );
			$rows = $stmt->fetchAll( \PDO::FETCH_ASSOC ) ?: array();
			if ( $output === OBJECT ) {
				return array_map( fn( $r ) => (object) $r, $rows );
			}
			return $rows;
		} catch ( \Exception $e ) {
			return array();
		}
	}

	public function get_var( string $query ) {
		try {
			$stmt = $this->pdo->query( $query );
			return $stmt->fetchColumn() ?: null;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public function get_col( string $query, int $col_offset = 0 ): array {
		try {
			$stmt = $this->pdo->query( $query );
			return $stmt->fetchAll( \PDO::FETCH_COLUMN, $col_offset ) ?: array();
		} catch ( \Exception $e ) {
			return array();
		}
	}
}

global $wpdb;
$wpdb = new MockWPDB();

// Mock WP User & Meta Storage
$GLOBALS['_mock_users'] = array(
	1 => new WP_User( (object) array( 'ID' => 1, 'user_login' => 'admin', 'display_name' => 'مدیر سیستم', 'user_email' => 'admin@flavor.local', 'roles' => array( 'administrator' ) ) ),
	101 => new WP_User( (object) array( 'ID' => 101, 'user_login' => '09123456789', 'display_name' => 'علی رضایی', 'user_email' => 'ali@example.com', 'roles' => array( 'customer' ) ) ),
	102 => new WP_User( (object) array( 'ID' => 102, 'user_login' => '09120000002', 'display_name' => 'سارا احمدی', 'user_email' => 'sara@example.com', 'roles' => array( 'customer' ) ) ),
	202 => new WP_User( (object) array( 'ID' => 202, 'user_login' => '09120000202', 'display_name' => 'رضا حسینی', 'user_email' => 'reza@example.com', 'roles' => array( 'customer' ) ) ),
	301 => new WP_User( (object) array( 'ID' => 301, 'user_login' => 'chef1', 'display_name' => 'سرآشپز شعبه ۱', 'user_email' => 'chef1@flavor.local', 'roles' => array( 'flavor_kitchen' ) ) ),
);

$GLOBALS['_mock_user_meta'] = array(
	101 => array( '_flavor_mobile' => '09123456789' ),
	102 => array( '_flavor_mobile' => '09120000002' ),
	202 => array( '_flavor_mobile' => '09120000202' ),
);

$GLOBALS['_mock_current_user_id'] = 0;
$GLOBALS['_last_sent_otp_code'] = '';

function wp_set_current_user( int $id ) {
	$GLOBALS['_mock_current_user_id'] = $id;
}
function get_current_user_id(): int {
	return (int) ( $GLOBALS['_mock_current_user_id'] ?? 0 );
}
function wp_get_current_user(): WP_User {
	$uid = get_current_user_id();
	return $GLOBALS['_mock_users'][ $uid ] ?? new WP_User( 0 );
}
function is_user_logged_in(): bool {
	return get_current_user_id() > 0;
}
function get_userdata( int $id ): ?WP_User {
	return $GLOBALS['_mock_users'][ $id ] ?? null;
}
function get_user_by( string $field, $value ): ?WP_User {
	foreach ( $GLOBALS['_mock_users'] as $u ) {
		if ( $field === 'id' && $u->ID === (int) $value ) return $u;
		if ( $field === 'login' && $u->user_login === (string) $value ) return $u;
		if ( $field === 'email' && $u->user_email === (string) $value ) return $u;
	}
	return null;
}
function get_users( array $args = array() ): array {
	$results = array();
	$meta_key = $args['meta_key'] ?? '';
	$meta_val = $args['meta_value'] ?? '';
	foreach ( $GLOBALS['_mock_users'] as $uid => $u ) {
		if ( $meta_key && isset( $GLOBALS['_mock_user_meta'][ $uid ][ $meta_key ] ) ) {
			if ( $GLOBALS['_mock_user_meta'][ $uid ][ $meta_key ] === $meta_val ) {
				$results[] = $u;
			}
		}
	}
	return $results;
}
function wp_insert_user( array $userdata ) {
	$new_id = max( array_keys( $GLOBALS['_mock_users'] ) ) + 1;
	$user = new WP_User( (object) array(
		'ID'           => $new_id,
		'user_login'   => $userdata['user_login'] ?? "user_{$new_id}",
		'user_email'   => $userdata['user_email'] ?? "user_{$new_id}@example.com",
		'display_name' => $userdata['display_name'] ?? "User {$new_id}",
		'roles'        => array( $userdata['role'] ?? 'customer' ),
	) );
	$GLOBALS['_mock_users'][ $new_id ] = $user;
	return $new_id;
}
function get_user_meta( int $user_id, string $key, bool $single = false ) {
	$val = $GLOBALS['_mock_user_meta'][ $user_id ][ $key ] ?? null;
	return $single ? $val : ( $val !== null ? array( $val ) : array() );
}
function update_user_meta( int $user_id, string $key, $val ): bool {
	$GLOBALS['_mock_user_meta'][ $user_id ][ $key ] = $val;
	return true;
}
function current_user_can( string $cap ): bool {
	$user = wp_get_current_user();
	if ( ! $user || empty( $user->ID ) ) return false;
	return $user->has_cap( $cap );
}
function user_can( $user, string $cap ): bool {
	$u = is_numeric( $user ) ? get_userdata( (int) $user ) : $user;
	if ( ! $u ) return false;
	return $u->has_cap( $cap );
}

// Mock Options
$GLOBALS['_mock_options'] = array(
	'flavor_core_db_version' => '1.4.0',
	'blogname'               => 'رستوران سنتی شاندیز',
	'blogdescription'        => 'لذیذترین غذاهای اصیل ایرانی',
	'flavor_core_settings'   => array(
		'sms_provider'       => 'dev',
		'otp_length'         => 5,
		'otp_ttl_minutes'    => 2,
		'otp_max_per_10min'  => 5,
		'guest_checkout'     => 'yes',
	),
);
function get_option( string $key, $default = false ) {
	return $GLOBALS['_mock_options'][ $key ] ?? $default;
}
function update_option( string $key, $val, $autoload = null ): bool {
	$GLOBALS['_mock_options'][ $key ] = $val;
	return true;
}
function delete_option( string $key ): bool {
	unset( $GLOBALS['_mock_options'][ $key ] );
	return true;
}
function get_theme_mod( string $key, $default = false ) {
	return $default;
}
function get_bloginfo( string $key ) {
	return 'رستوران شاندیز';
}

// Mock Time & Translation
function current_time( string $type ) {
	if ( 'timestamp' === $type ) {
		return time();
	}
	return date( 'Y-m-d H:i:s' );
}
function __( string $text, string $domain = 'default' ): string {
	return $text;
}
function _e( string $text, string $domain = 'default' ) {
	echo $text;
}
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url_raw( string $url ): string {
	return $url;
}
function sanitize_text_field( string $str ): string {
	return trim( strip_tags( $str ) );
}
function sanitize_textarea_field( string $str ): string {
	return trim( strip_tags( $str ) );
}
function sanitize_key( string $key ): string {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
}
function wp_generate_password( int $length = 12, bool $special_chars = true ): string {
	return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
}
function wp_json_encode( $data ): string {
	return json_encode( $data, JSON_UNESCAPED_UNICODE );
}
function wp_rand( int $min = 0, int $max = 0 ): int {
	return mt_rand( $min, $max ?: mt_getrandmax() );
}
function wp_logout() {
	wp_set_current_user( 0 );
}

// Mock Hooks
function do_action( string $tag, ...$args ) {}
function apply_filters( string $tag, $value, ...$args ) {
	if ( $tag === 'flavor_core_sms_sent' ) {
		// In SmsManager: apply_filters( 'flavor_core_sms_sent', $result, $mobile, $message, $event )
		$message = (string) ( $args[1] ?? '' );
		if ( preg_match( '/(\d{4,6})/', $message, $m ) ) {
			$GLOBALS['_last_sent_otp_code'] = $m[1];
		}
	}
	return $value;
}
function add_action( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ) {}
function add_filter( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ) {}
function register_rest_route( string $ns, string $route, array $args ) {}

// Mock WP_Error
class WP_Error {
	public string $code;
	public string $message;
	public array $data;
	public function __construct( string $code, string $message = '', array $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data(): array { return $this->data; }
}
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

// Mock WP_REST_Request & Response
class WP_REST_Request {
	public string $method;
	public string $route;
	public array $params = array();
	public array $headers = array();
	public array $json_params = array();

	public function __construct( string $method = 'GET', string $route = '' ) {
		$this->method = $method;
		$this->route  = $route;
	}
	public function set_header( string $k, string $v ) { $this->headers[ strtolower( $k ) ] = $v; }
	public function get_header( string $k ) { return $this->headers[ strtolower( $k ) ] ?? ''; }
	public function set_param( string $k, $v ) { $this->params[ $k ] = $v; }
	public function get_param( string $k ) { return $this->params[ $k ] ?? $this->json_params[ $k ] ?? null; }
	public function get_params(): array { return array_merge( $this->params, $this->json_params ); }
	public function set_json_params( array $params ) { $this->json_params = $params; }
	public function get_json_params(): array { return $this->json_params; }
	public function offsetGet( $k ) { return $this->get_param( $k ); }
}

class WP_REST_Response {
	public $data;
	public int $status;
	public array $headers = array();
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function get_status(): int { return $this->status; }
	public function get_data() { return $this->data; }
	public function header( string $k, string $v ) { $this->headers[ $k ] = $v; }
	public function get_headers(): array { return $this->headers; }
}

function rest_ensure_response( $response ) {
	if ( $response instanceof WP_REST_Response ) return $response;
	return new WP_REST_Response( $response, 200 );
}

// Mock WooCommerce Cart & Order Objects
class MockWCCart {
	public array $items = array();
	public array $coupons = array();
	public array $fees = array();

	public function is_empty(): bool { return empty( $this->items ); }
	public function empty_cart() { $this->items = array(); $this->coupons = array(); $this->fees = array(); }
	public function get_cart_contents_count(): int {
		$sum = 0;
		foreach ( $this->items as $i ) $sum += (int) ( $i['quantity'] ?? 1 );
		return $sum;
	}
	public function get_cart(): array { return $this->items; }
	public function get_cart_item( string $key ) { return $this->items[ $key ] ?? null; }
	public function set_quantity( string $key, int $qty ) { if ( isset( $this->items[ $key ] ) ) $this->items[ $key ]['quantity'] = $qty; }
	public function remove_cart_item( string $key ) { unset( $this->items[ $key ] ); }
	public function get_subtotal(): float { return (float) ( $this->get_cart_contents_count() * 150000 ); }
	public function get_total( $mode = 'edit' ): float { return $this->get_subtotal(); }
	public function get_fees(): array { return $this->fees; }
	public function add_fee( string $name, float $amount, bool $taxable = false ) {
		$this->fees[] = (object) array( 'name' => $name, 'amount' => $amount );
	}
	public function needs_payment(): bool { return true; }
	public function get_applied_coupons(): array { return $this->coupons; }
	public function apply_coupon( string $code ): bool { $this->coupons[] = $code; return true; }
	public function remove_coupon( string $code ): bool { $this->coupons = array_diff( $this->coupons, array( $code ) ); return true; }
	public function calculate_totals() {}
	public function add_to_cart( int $product_id, int $qty = 1 ): string {
		$key = 'mock_item_key_' . $product_id;
		$prod = wc_get_product( $product_id );
		$this->items[ $key ] = array(
			'key'                 => $key,
			'product_id'          => $product_id,
			'quantity'            => $qty,
			'data'                => $prod,
			'line_total'          => $qty * ( $prod ? $prod->get_price() : 150000 ),
			'flavor_modifiers'    => array( 'ids' => array() ),
			'flavor_instructions' => '',
		);
		return $key;
	}
}

class MockWCSession {
	public array $data = array();
	public function has_session(): bool { return true; }
	public function set_customer_session_cookie( bool $val ) {}
	public function get( string $key, $default = null ) { return $this->data[ $key ] ?? $default; }
	public function set( string $key, $value ) { $this->data[ $key ] = $value; }
}

class MockWCGateway {
	public string $id;
	public string $title;
	public string $enabled = 'yes';
	public function __construct( string $id, string $title ) {
		$this->id    = $id;
		$this->title = $title;
	}
	public function get_title(): string { return $this->title; }
	public function get_description(): string { return 'روش پرداخت انتخابی رستوران'; }
	public function process_payment( int $order_id ): array {
		return array( 'result' => 'success', 'redirect' => 'https://restaurant.example.com/order-received' );
	}
}

class MockWCPaymentGateways {
	public function get_available_payment_gateways(): array {
		return array(
			'flavor_pay_at_counter'     => new MockWCGateway( 'flavor_pay_at_counter', 'پرداخت حضوری' ),
			'flavor_cod'                => new MockWCGateway( 'flavor_cod', 'پرداخت در محل (نقدی)' ),
			'flavor_card_on_delivery'   => new MockWCGateway( 'flavor_card_on_delivery', 'پرداخت در محل (دستگاه پوز)' ),
		);
	}
}

class MockWCCheckout {
	public static int $order_counter = 1000;
	public function create_order( array $posted ): int {
		self::$order_counter++;
		$order_id = self::$order_counter;
		$order = new WC_Order( $order_id, $posted );
		$GLOBALS['_mock_wc_orders'][ $order_id ] = $order;
		return $order_id;
	}
}

class MockWC {
	public MockWCCart $cart;
	public MockWCSession $session;
	public MockWCPaymentGateways $gateways;
	public function __construct() {
		$this->cart = new MockWCCart();
		$this->session = new MockWCSession();
		$this->gateways = new MockWCPaymentGateways();
	}
	public function payment_gateways(): MockWCPaymentGateways {
		return $this->gateways;
	}
	public function checkout(): MockWCCheckout {
		return new MockWCCheckout();
	}
}

$GLOBALS['_mock_wc'] = new MockWC();
$GLOBALS['_mock_wc_orders'] = array();

function WC(): MockWC {
	return $GLOBALS['_mock_wc'];
}

$GLOBALS['_mock_wc_products'] = array(
	1 => new WC_Product( 1, 'چلو کباب کوبیده مخصوص زعفرانی', 280000 ),
	2 => new WC_Product( 2, 'چلو جوجه کباب فیله زعفرانی', 260000 ),
	3 => new WC_Product( 3, 'زرشک پلو با مرغ مجلسی', 195000 ),
);

function wc_get_product( int $id ) {
	return $GLOBALS['_mock_wc_products'][ $id ] ?? new WC_Product( $id, 'غذای ویژه', 150000 );
}
function wc_get_products( array $args ): object {
	return (object) array(
		'products' => array_values( $GLOBALS['_mock_wc_products'] ),
		'total'    => count( $GLOBALS['_mock_wc_products'] ),
	);
}
function wc_get_order( int $id ): ?WC_Order {
	return $GLOBALS['_mock_wc_orders'][ $id ] ?? null;
}
function wc_price( $amount ): string {
	return number_format( (float) $amount ) . ' تومان';
}
function wc_format_coupon_code( string $code ): string {
	return trim( strtoupper( $code ) );
}
function wc_get_order_status_name( string $status ): string {
	$map = array( 'pending' => 'در انتظار پرداخت', 'processing' => 'در حال پردازش', 'completed' => 'تکمیل شده', 'cancelled' => 'لغو شده' );
	return $map[ $status ] ?? $status;
}
function get_woocommerce_currency(): string {
	return 'IRT';
}
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ): string {
	return 'https://restaurant.example.com/assets/images/sample-dish.jpg';
}
function get_permalink( int $id ): string {
	return "https://restaurant.example.com/dish/{$id}";
}
function get_post_meta( int $post_id, string $key, bool $single = false ) {
	return $single ? '' : array();
}
function get_the_terms( int $id, string $tax ) {
	return array( new WP_Term( 1, 'کباب‌ها', 'kabab' ) );
}
function taxonomy_exists( string $tax ): bool { return true; }
function get_terms( array $args ) {
	return array(
		new WP_Term( 1, 'کباب و گریل', 'kabab', 'product_cat', 'انواع کباب‌های تازه', 8 ),
		new WP_Term( 2, 'خورشت‌های سنتی', 'khoresht', 'product_cat', 'خورشت‌های جاافتاده', 6 ),
		new WP_Term( 3, 'پیش‌غذا و سالاد', 'appetizer', 'product_cat', 'سالادها و ماست‌های محلی', 5 ),
	);
}
function get_term_meta( int $id, string $key, bool $single = false ) { return ''; }
function get_posts( array $args ) { return array( 1, 2 ); }
function get_post_type( int $id ): string { return 'flavor_branch'; }
function get_post( int $id ) {
	return (object) array(
		'ID'           => $id,
		'post_name'    => 'central-branch',
		'post_type'    => 'flavor_branch',
		'post_content' => 'شعبه مرکزی شاندیز',
		'post_title'   => 'شعبه مرکزی',
	);
}
function get_the_title( int $id ): string { return 'شعبه مرکزی شاندیز'; }
function get_avatar_url( int $id, array $args = array() ): string { return 'https://secure.gravatar.com/avatar/sample'; }
function wp_remote_post( string $url, array $args ) {
	return array( 'response' => array( 'code' => 200 ), 'body' => '{"success":true}' );
}
function wp_remote_retrieve_response_code( $resp ): int { return 200; }
function wp_remote_retrieve_body( $resp ): string { return '{"success":true}'; }
function wp_mail( string $to, string $subj, string $body, array $headers = array() ): bool { return true; }
