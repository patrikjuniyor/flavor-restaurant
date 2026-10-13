<?php
/**
 * Mock WordPress & WooCommerce Runtime Environment for Standalone Integration Tests.
 * Provides SQLite database mock for $wpdb, WP_User, WP_REST_Request, WP_REST_Response,
 * WooCommerce order/product objects, and WP action/filter hooks.
 *
 * @package FlavorCore\Tests
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.DateTime.RestrictedFunctions.date_date, WordPress.DB.RestrictedClasses, WordPress.Security.EscapeOutput -- the mock IS WordPress: it stubs $wpdb (PDO), core globals and echoes CLI reports on purpose.

if ( ! defined( 'ARRAY_A' ) ) {
define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
define( 'ARRAY_N', 'ARRAY_N' );
}
if ( ! defined( 'OBJECT' ) ) {
define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'AUTH_SALT' ) ) {
define( 'AUTH_SALT', 'mock-test-auth-salt-1234567890' );
}

// Core time constants. WordPress defines these in wp-includes/default-constants.php,
// which the mock does not load, so anything scheduling work needs them here.
foreach ( array(
	'MINUTE_IN_SECONDS' => 60,
	'HOUR_IN_SECONDS'   => 3600,
	'DAY_IN_SECONDS'    => 86400,
	'WEEK_IN_SECONDS'   => 604800,
	'MONTH_IN_SECONDS'  => 2592000,
	'YEAR_IN_SECONDS'   => 31536000,
) as $const => $value ) {
	if ( ! defined( $const ) ) {
		define( $const, $value );
	}
}

// In-memory transients with REAL expiry semantics — the old store kept every
// transient alive forever, so window logic (RateLimit buckets, OTP counters)
// could never be observed expiring the way it does on a live site.
$GLOBALS['_mock_transients'] = array();
function get_transient( string $transient ) {
	$row = $GLOBALS['_mock_transients'][ $transient ] ?? null;
	if ( null === $row ) {
		return false;
	}
	if ( $row['expires'] > 0 && time() > $row['expires'] ) {
		unset( $GLOBALS['_mock_transients'][ $transient ] );
		return false;
	}
	return $row['value'];
}
function set_transient( string $transient, $value, int $expiration = 0 ): bool {
	$GLOBALS['_mock_transients'][ $transient ] = array(
		'value'   => $value,
		'expires' => $expiration > 0 ? time() + $expiration : 0,
	);
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
	// Real wp_unslash() is stripslashes_deep(): arrays and objects recurse.
	if ( is_string( $value ) ) {
		return stripslashes( $value );
	}
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return $value;
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
		$parsed_args = $args;
	} elseif ( is_string( $args ) ) {
		// Real wp_parse_args() parses query strings too.
		parse_str( $args, $parsed_args );
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
	// Real signatures: (key, value, url) | (array_of_args, url) | (key, value)
	// against the current request. The old constant ignored every argument, so
	// URL-building code could never be wrong inside the suite.
	if ( isset( $args[0] ) && is_array( $args[0] ) ) {
		$params = $args[0];
		$url    = (string) ( $args[1] ?? home_url( '/' ) );
	} elseif ( count( $args ) >= 3 ) {
		$params = array( $args[0] => $args[1] );
		$url    = (string) $args[2];
	} else {
		$params = array( (string) ( $args[0] ?? '' ) => $args[1] ?? '' );
		$url    = home_url( '/' );
	}
	$parts = parse_url( $url ) ?: array();
	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}
	foreach ( $params as $k => $v ) {
		if ( null === $v ) {
			unset( $query[ $k ] );
		} else {
			$query[ $k ] = $v;
		}
	}
	$base = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'restaurant.example.com' );
	$base .= $parts['path'] ?? '';
	return $query ? $base . '?' . http_build_query( $query ) : $base;
}

function home_url( string $path = '' ): string {
	return 'https://restaurant.example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
}

function site_url( string $path = '' ): string {
	return home_url( $path );
}

// In-memory object cache with real per-(key,group) get/set/delete semantics;
// the old always-miss stub made read-your-writes paths untestable.
$GLOBALS['_mock_object_cache'] = isset( $GLOBALS['_mock_object_cache'] ) && is_array( $GLOBALS['_mock_object_cache'] ) ? $GLOBALS['_mock_object_cache'] : array();
function wp_cache_get( $key, $group = '' ) {
	return $GLOBALS['_mock_object_cache'][ $group ][ $key ] ?? false;
}
function wp_cache_set( $key, $val, $group = '', $expire = 0 ) {
	unset( $expire );
	$GLOBALS['_mock_object_cache'][ $group ][ $key ] = $val;
	return true;
}
function wp_cache_delete( $key, $group = '' ) {
	unset( $GLOBALS['_mock_object_cache'][ $group ][ $key ] );
	return true;
}
function wp_cache_flush(): bool {
	$GLOBALS['_mock_object_cache'] = array();
	return true;
}
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
		$caps = $GLOBALS['_mock_user_caps'][ $this->ID ] ?? array();
		if ( isset( $caps[ $cap ] ) ) {
			return (bool) $caps[ $cap ];
		}
		if ( in_array( 'administrator', $this->roles, true ) ) {
return true;
		}
		if ( $cap === 'manage_options' && in_array( 'administrator', $this->roles, true ) ) {
return true;
		}
		if ( $cap === 'flavor_manage_kitchen' && in_array( 'flavor_kitchen', $this->roles, true ) ) {
return true;
		}
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
	public function get_id(): int {
return $this->id; }
	public function get_name(): string {
return $this->name; }
	public function get_slug(): string {
return 'dish-' . $this->id; }
	public function get_price(): int {
return $this->price; }
	public function get_regular_price(): int {
return $this->price; }
	public function is_purchasable(): bool {
return true; }
	public function get_status(): string {
return 'publish'; }
	public function is_on_sale(): bool {
return false; }
	public function get_short_description(): string {
return 'تهیه شده از بهترین مواد اولیه ایرانی'; }
	public function get_description(): string {
return 'توضیحات کامل غذای اصیل و خوش‌طعم'; }
	public function get_image_id(): int {
return 0; }
	public function get_gallery_image_ids(): array {
return array(); }
	public function get_average_rating(): float {
return 4.9; }
	public function get_rating_count(): int {
return 120; }
	public function get_sku(): string {
return 'DISH-' . $this->id; }
	public function get_permalink(): string {
return home_url( '/product/dish-' . $this->id . '/' ); }
	public function get_price_html(): string {
return number_format( $this->price ) . ' تومان'; }
	public function is_in_stock(): bool {
return true; }
}

/**
 * Mock WC_Order_Item_Product Class.
 */
class WC_Order_Item_Product {
	public array $ci;
	public function __construct( array $ci ) {
$this->ci = $ci; }
	public function get_id(): int {
return 1; }
	public function get_product_id(): int {
return (int) ( $this->ci['product_id'] ?? 1 ); }
	public function get_name(): string {
return $this->ci['data'] ? $this->ci['data']->get_name() : 'کباب کوبیده'; }
	public function get_quantity(): int {
return (int) ( $this->ci['quantity'] ?? 1 ); }
	public function get_total(): float {
return (float) ( $this->ci['line_total'] ?? 150000 ); }
	public function get_meta( string $k ) {
		if ( $k === '_flavor_modifiers' ) {
return $this->ci['flavor_modifiers'] ?? array();
		}
		if ( $k === '_flavor_instructions' ) {
return $this->ci['flavor_instructions'] ?? '';
		}
		return null;
	}
	public function get_product() {
return $this->ci['data'] ?? wc_get_product( $this->get_product_id() ); }
}

/**
 * WC_DateTime double: extends DateTime like the real one and adds
 * WooCommerce's OWN ->date() method — PHP's DateTime has no date(); the
 * real class renders through date_i18n in the store timezone, so
 * get_date_created()->date('Y-m-d H:i:s') is the site-local wall clock.
 */
class WC_DateTime extends DateTime {
	public function date( $format ) {
		return wp_date( (string) $format, $this->getTimestamp(), wp_timezone() );
	}
	public function getOffsetTimestamp(): int {
		return $this->getTimestamp() + $this->getOffset();
	}
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
	/** Real WC_Order::get_date_created() hands back a WC_DateTime (site tz). */
	public WC_DateTime $date_created;
	public float $total    = 0.0;
	public float $subtotal = 0.0;

	public function __construct( int $id, array $posted = array() ) {
		$this->id = $id;
		$this->posted = $posted;
		$this->customer_id = get_current_user_id();
		// Real order dates are site-timezone instants; the old anonymous
		// double rendered raw PHP-local date() strings (UTC), which silently
		// disagreed with current_time('mysql') the moment the mock site got a
		// real timezone.
		$this->date_created = new WC_DateTime( 'now', wp_timezone() );

		// Populate items from active WC cart
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $ci ) {
				$this->items[] = new WC_Order_Item_Product( $ci );
			}
		}
		// Real orders total their line items; the old constant 350000.0 made
		// every money-adjacent assertion independent of the actual cart.
		foreach ( $this->items as $item ) {
			$this->total += $item->get_total();
		}
		$this->subtotal = $this->total;
		// Real WC_Checkout copies cart fees onto the order: get_total() covers
		// items + fees while get_subtotal() stays items-only.
		if ( WC()->cart ) {
			foreach ( WC()->cart->fees as $fee ) {
				$this->total += (float) ( $fee->amount ?? 0 );
			}
		}
	}

	public function get_id(): int {
return $this->id; }
	public function get_order_number(): string {
return (string) $this->id; }
	public function get_customer_id(): int {
return $this->customer_id; }
	public function get_status(): string {
return $this->status; }
	public function update_status( string $st, string $note = '' ) {
$this->status = $st; }
	public function get_payment_method(): string {
return $this->posted['payment_method'] ?? 'flavor_pay_at_counter'; }
	public function get_payment_method_title(): string {
return 'پرداخت در محل / حضوری'; }
	public function set_payment_method( string $m ) {
$this->posted['payment_method'] = $m; }
	public function get_meta( string $k ) {
return $this->meta[ $k ] ?? null; }
	public function update_meta_data( string $k, $v ) {
$this->meta[ $k ] = $v; }
	public function save() {}
	public function get_items(): array {
return $this->items; }
	public function get_total(): float {
return $this->total; }
	public function get_subtotal(): float {
return $this->subtotal; }
	public function get_discount_total(): float {
return 0.0; }
	public function get_shipping_total(): float {
return 0.0; }
	public function get_currency(): string {
return 'IRT'; }
	public function get_date_created(): WC_DateTime {
		return $this->date_created;
	}
	public function get_formatted_billing_full_name(): string {
return $this->posted['billing_first_name'] ?? 'مشتری'; }
	public function get_billing_first_name(): string {
return $this->posted['billing_first_name'] ?? ''; }
	public function get_billing_last_name(): string {
return ''; }
	public function get_billing_phone(): string {
return $this->posted['billing_phone'] ?? ''; }
	public function get_billing_address_1(): string {
return $this->posted['billing_address_1'] ?? ''; }
	public function get_billing_city(): string {
return $this->posted['billing_city'] ?? 'تهران'; }
	public function get_billing_state(): string {
return $this->posted['billing_state'] ?? 'تهران'; }
	public function get_formatted_billing_address(): string {
return $this->get_billing_address_1(); }
	public function get_customer_note(): string {
return $this->posted['order_comments'] ?? ''; }
	public function get_item_count(): int {
return count( $this->items ); }
}

/**
 * Mock $wpdb using in-memory SQLite PDO.
 */
class MockWPDB {
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public \PDO $pdo;

	// Real wpdb exposes resolved table names and code interpolates some of
	// them ({$wpdb->options} in SearchIndex, {$wpdb->postmeta} in uninstall).
	public string $options   = 'wp_options';
	public string $posts     = 'wp_posts';
	public string $postmeta  = 'wp_postmeta';
	public string $users     = 'wp_users';
	public string $usermeta  = 'wp_usermeta';
	public string $comments  = 'wp_comments';
	public string $terms     = 'wp_terms';
	public string $termmeta  = 'wp_termmeta';

	/**
	 * Last SQL error message, exactly like the real $wpdb->last_error.
	 * Every catch below records it instead of swallowing failures silently.
	 */
	public string $last_error = '';

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
				refresh_expires_at DATETIME NULL,
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
				device_token TEXT NOT NULL,
				platform TEXT NOT NULL DEFAULT 'android',
				app_version TEXT NOT NULL DEFAULT '1.0.0',
				branch_id INTEGER NULL,
				is_active INTEGER NOT NULL DEFAULT 1,
				last_seen_at DATETIME NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NULL,
				UNIQUE (device_token, platform)
			);

			CREATE TABLE wp_flavor_mobile_builds (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				build_uuid TEXT NOT NULL UNIQUE,
				platform TEXT NOT NULL DEFAULT 'all',
				environment TEXT NOT NULL DEFAULT 'prod',
				version_name TEXT NOT NULL DEFAULT '1.1.0',
				version_code INTEGER NOT NULL DEFAULT 101,
				status TEXT NOT NULL DEFAULT 'queued',
				triggered_by INTEGER NULL,
				commit_sha TEXT NULL,
				ci_provider TEXT NOT NULL DEFAULT 'github_actions',
				ci_build_id TEXT NULL,
				ci_build_url TEXT NULL,
				artifact_apk_url TEXT NULL,
				artifact_aab_url TEXT NULL,
				artifact_ipa_url TEXT NULL,
				artifact_size_bytes INTEGER NULL,
				artifact_checksum_sha256 TEXT NULL,
				build_log TEXT NULL,
				error_message TEXT NULL,
				duration_seconds INTEGER NULL,
				started_at DATETIME NULL,
				completed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
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

			CREATE TABLE wp_flavor_webhooks (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name TEXT NOT NULL,
				target_url TEXT NOT NULL,
				secret TEXT NOT NULL,
				events_json TEXT NOT NULL,
				is_active INTEGER NOT NULL DEFAULT 1,
				failure_count INTEGER NOT NULL DEFAULT 0,
				last_triggered_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_webhook_deliveries (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				webhook_id INTEGER NOT NULL,
				event TEXT NOT NULL,
				payload_json TEXT NOT NULL,
				response_code INTEGER NULL,
				response_body TEXT NULL,
				duration_ms INTEGER NULL,
				attempt_count INTEGER NOT NULL DEFAULT 1,
				status TEXT NOT NULL DEFAULT 'pending',
				error_message TEXT NULL,
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

			CREATE TABLE wp_flavor_availability (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER NOT NULL,
				product_id INTEGER NOT NULL,
				is_available INTEGER NOT NULL DEFAULT 1,
				unavailable_until DATETIME NULL,
				reason TEXT NULL,
				updated_by INTEGER NULL,
				updated_at DATETIME NOT NULL
			);

			CREATE TABLE wp_flavor_availability_log (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER NOT NULL,
				product_id INTEGER NOT NULL,
				old_available INTEGER NULL,
				new_available INTEGER NOT NULL,
				unavailable_until DATETIME NULL,
				changed_by INTEGER NULL,
				changed_at DATETIME NOT NULL
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

			CREATE TABLE IF NOT EXISTS wp_options (
				option_id INTEGER PRIMARY KEY AUTOINCREMENT,
				option_name TEXT NOT NULL UNIQUE,
				option_value TEXT NOT NULL DEFAULT '',
				autoload TEXT DEFAULT 'yes'
			);

			CREATE TABLE IF NOT EXISTS wp_postmeta (
				meta_id INTEGER PRIMARY KEY AUTOINCREMENT,
				post_id INTEGER NOT NULL,
				meta_key TEXT NULL,
				meta_value TEXT NULL
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

	/**
	 * Faithful port of wpdb::prepare() (WordPress 6.8) for the SQLite double.
	 *
	 * Same placeholder grammar (%d %f %F %s %i, numbered/padding specifiers),
	 * same quoted-'%s' normalisation, same "too few arguments" contract
	 * (_doing_it_wrong + an EMPTY string — never a half-built query), the same
	 * mysqli_real_escape_string character set, NULL/non-scalar collapsing to
	 * '' exactly like _real_escape(), %f forced to locale-unaware %F, %i
	 * backticked, literal percents doubled for the vsprintf pass. The real
	 * method returns its output through add_placeholder_escape() and query()
	 * strips that marker again — the net executed SQL is what this port
	 * returns, so mock prepare() === real prepare() ∘ remove_placeholder_escape().
	 */
	public function prepare( string $query, ...$args ): string {
		if ( false === strpos( $query, '%' ) ) {
			trigger_error( 'wpdb::prepare was called without a placeholder (real WP: _doing_it_wrong).', E_USER_WARNING );
			return $query;
		}
		$allowed_format = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'\.)?[-+0-9]*(?:\.[0-9]+)?';
		// Quoted '%s' collapses to a bare %s; prepare() re-quotes it below.
		$query = str_replace( "'%s'", '%s', $query );
		$query = str_replace( '"%s"', '%s', $query );
		// Escape any unescaped percents (anything unrecognised becomes a literal).
		$query = (string) preg_replace( "/%(?:%|$|(?!($allowed_format)?[sdfFi]))/", '%%\\1', $query );
		$split = preg_split( "/(^|[^%]|(?:%%)+)(%(?:$allowed_format)?[sdfFi])/", $query, -1, PREG_SPLIT_DELIM_CAPTURE );
		$split = is_array( $split ) ? $split : array( $query );
		$split_count       = count( $split );
		$placeholder_count = ( $split_count - 1 ) / 3;

		$passed_as_array = isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args );
		if ( $passed_as_array ) {
			$args = $args[0];
		}

		$new_query       = '';
		$key             = 2;
		$arg_id          = 0;
		$arg_identifiers = array();
		while ( $key < $split_count ) {
			$placeholder = $split[ $key ];
			$format      = substr( $placeholder, 1, -1 );
			$type        = substr( $placeholder, -1 );
			if ( 'f' === $type ) {
				// Force floats to be locale-unaware, like real wpdb.
				$type        = 'F';
				$placeholder = '%' . $format . $type;
			}
			if ( 'i' === $type ) {
				$placeholder       = '`%' . $format . 's`';
				$argnum_pos        = strpos( $format, '$' );
				$arg_identifiers[] = false !== $argnum_pos ? ( (int) substr( $format, 0, $argnum_pos ) ) - 1 : $arg_id;
			} elseif ( 'd' !== $type && 'F' !== $type ) {
				// %s: re-quote unless the caller wrote its own quotes (LIKE '%%%s%%').
				if ( '' === $format && '%' !== substr( (string) $split[ $key - 1 ], -1 ) ) {
					$placeholder = "'%" . $format . "s'";
				}
			}
			$new_query .= $split[ $key - 2 ] . $split[ $key - 1 ] . $placeholder;
			$key       += 3;
			++$arg_id;
		}
		$query = $new_query . $split[ $key - 2 ];

		if ( count( $args ) !== $placeholder_count ) {
			trigger_error(
				sprintf( 'wpdb::prepare: the query contains %d placeholder(s) but %d argument(s) were passed (real WP: _doing_it_wrong).', (int) $placeholder_count, count( $args ) ),
				E_USER_WARNING
			);
			if ( count( $args ) < $placeholder_count ) {
				// Real WP 6.2+: an empty string, never a half-substituted query.
				return '';
			}
		}

		$args_escaped = array();
		foreach ( $args as $i => $value ) {
			if ( in_array( $i, $arg_identifiers, true ) ) {
				// Real _escape_identifier_value(): inner backticks are DOUBLED
				// (a`b becomes a``b); the `...` wrapper comes from the %i placeholder.
				$args_escaped[] = str_replace( '`', '``', (string) $value );
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$args_escaped[] = $value;
			} else {
				if ( ! is_scalar( $value ) && ! is_null( $value ) ) {
					trigger_error( 'wpdb::prepare: unsupported value type (' . gettype( $value ) . ') (real WP: _doing_it_wrong).', E_USER_WARNING );
					$value = '';
				}
				$args_escaped[] = $this->_real_escape( $value );
			}
		}
		$prepared = vsprintf( $query, $args_escaped );
		return is_string( $prepared ) ? $prepared : '';
	}

	/**
	 * mysqli_real_escape_string's character set (NUL, \n, \r, backslash,
	 * single quote, double quote, Ctrl-Z) — NOT plain addslashes — with NULL
	 * and non-scalars collapsing to '' exactly like the real _real_escape().
	 * The real method also marker-escapes percents (add_placeholder_escape)
	 * and query() strips the markers again; since vsprintf never rescans its
	 * arguments, a value's % passes through this port literally — the same
	 * net SQL a real site executes.
	 */
	public function _real_escape( $data ): string {
		if ( ! is_scalar( $data ) ) {
			return '';
		}
		return addcslashes( (string) $data, "\0\n\r\\'\"\x1a" );
	}

	public function query( string $sql ) {
		$this->last_error = '';
		try {
			return $this->pdo->exec( $sql );
		} catch ( \Exception $e ) {
			// Real wpdb records the failure on ->last_error instead of hiding it.
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	public function insert( string $table, array $data, array $format = null ) {
		unset( $format );
		$cols = array_keys( $data );
		$placeholders = array_fill( 0, count( $cols ), '?' );
		$sql = "INSERT INTO {$table} (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', $placeholders ) . ')';
		$this->last_error = '';
		try {
			$stmt = $this->pdo->prepare( $sql );
			$stmt->execute( array_values( $data ) );
			$this->insert_id = (int) $this->pdo->lastInsertId();
			// Real wpdb: int|false — the number of rows inserted, not a bool.
			return $stmt->rowCount();
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	public function replace( string $table, array $data ) {
		$cols = array_keys( $data );
		$placeholders = array_fill( 0, count( $cols ), '?' );
		$sql = "INSERT OR REPLACE INTO {$table} (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', $placeholders ) . ')';
		$this->last_error = '';
		try {
			$stmt = $this->pdo->prepare( $sql );
			$stmt->execute( array_values( $data ) );
			$this->insert_id = (int) $this->pdo->lastInsertId();
			return $stmt->rowCount();
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	public function update( string $table, array $data, array $where, array $format = null, array $where_format = null ) {
		$sets = array();
		$vals = array();
		foreach ( $data as $k => $v ) {
			$sets[] = "{$k} = ?";
			$vals[] = $v;
		}
		$where_clauses = array();
		foreach ( $where as $k => $v ) {
			// Match real wpdb semantics: a NULL where-value becomes "IS NULL".
			if ( null === $v ) {
				$where_clauses[] = "{$k} IS NULL";
			} else {
				$where_clauses[] = "{$k} = ?";
				$vals[] = $v;
			}
		}
		$sql = "UPDATE {$table} SET " . implode( ',', $sets ) . ' WHERE ' . implode( ' AND ', $where_clauses );
		$this->last_error = '';
		try {
			$stmt = $this->pdo->prepare( $sql );
			$stmt->execute( $vals );
			// Real wpdb: int|false — 0 affected rows is NOT true (a bool here let
			// no-op updates masquerade as successes in every caller check).
			return $stmt->rowCount();
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	public function delete( string $table, array $where, array $where_format = null ) {
		$where_clauses = array();
		$vals = array();
		foreach ( $where as $k => $v ) {
			// Match real wpdb semantics: a NULL where-value becomes "IS NULL".
			if ( null === $v ) {
				$where_clauses[] = "{$k} IS NULL";
			} else {
				$where_clauses[] = "{$k} = ?";
				$vals[] = $v;
			}
		}
		$sql = "DELETE FROM {$table} WHERE " . implode( ' AND ', $where_clauses );
		$this->last_error = '';
		try {
			$stmt = $this->pdo->prepare( $sql );
			$stmt->execute( $vals );
			return $stmt->rowCount();
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	public function get_row( string $query, string $output = OBJECT ) {
		$this->last_error = '';
		try {
			$stmt = $this->pdo->query( $query );
			$row  = $stmt->fetch( \PDO::FETCH_ASSOC );
			if ( ! $row ) {
				return null;
			}
			if ( ARRAY_N === $output ) {
				// Real wpdb returns a NUMERIC array for ARRAY_N, not an assoc one.
				return array_values( $row );
			}
			return ( OBJECT === $output ) ? (object) $row : $row;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return null;
		}
	}

	public function get_results( string $query, string $output = OBJECT ): array {
		$this->last_error = '';
		try {
			$stmt = $this->pdo->query( $query );
			$rows = $stmt->fetchAll( \PDO::FETCH_ASSOC ) ?: array();
			if ( ARRAY_N === $output ) {
				return array_map( 'array_values', $rows );
			}
			if ( OBJECT === $output ) {
				return array_map( fn( $r ) => (object) $r, $rows );
			}
			return $rows;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return array();
		}
	}

	public function get_var( string $query ) {
		$this->last_error = '';
		try {
			$stmt = $this->pdo->query( $query );
			$val  = $stmt->fetchColumn();
			// Real wpdb: '' becomes null, but '0'/0 is a VALUE and must survive
			// (`?:` used to swallow COUNT(*)=0 and SUM()=0 into null — a silent
			// no-rows-vs-zero conflation the real database never makes).
			return ( false !== $val && '' !== $val ) ? $val : null;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return null;
		}
	}

	public function get_col( string $query, int $col_offset = 0 ): array {
		$this->last_error = '';
		try {
			$stmt = $this->pdo->query( $query );
			return $stmt->fetchAll( \PDO::FETCH_COLUMN, $col_offset ) ?: array();
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return array();
		}
	}
}

global $wpdb;
$wpdb = new MockWPDB();

// Mock WP User & Meta Storage
$GLOBALS['_mock_users'] = array(
	1 => new WP_User( (object) array(
		'ID' => 1,
		'user_login' => 'admin',
		'display_name' => 'مدیر سیستم',
		'user_email' => 'admin@flavor.local',
		'roles' => array( 'administrator' ),
	) ),
	101 => new WP_User( (object) array(
		'ID' => 101,
		'user_login' => '09123456789',
		'display_name' => 'علی رضایی',
		'user_email' => 'ali@example.com',
		'roles' => array( 'customer' ),
	) ),
	102 => new WP_User( (object) array(
		'ID' => 102,
		'user_login' => '09120000002',
		'display_name' => 'سارا احمدی',
		'user_email' => 'sara@example.com',
		'roles' => array( 'customer' ),
	) ),
	202 => new WP_User( (object) array(
		'ID' => 202,
		'user_login' => '09120000202',
		'display_name' => 'رضا حسینی',
		'user_email' => 'reza@example.com',
		'roles' => array( 'customer' ),
	) ),
	301 => new WP_User( (object) array(
		'ID' => 301,
		'user_login' => 'chef1',
		'display_name' => 'سرآشپز شعبه ۱',
		'user_email' => 'chef1@flavor.local',
		'roles' => array( 'flavor_kitchen' ),
	) ),
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
function get_userdata( $id ) {
	// Real get_userdata() returns WP_User|false — never null.
	return $GLOBALS['_mock_users'][ (int) $id ] ?? false;
}
function get_user_by( string $field, $value ) {
	foreach ( $GLOBALS['_mock_users'] as $u ) {
		if ( $field === 'id' && $u->ID === (int) $value ) {
return $u;
		}
		if ( $field === 'login' && $u->user_login === (string) $value ) {
return $u;
		}
		if ( $field === 'email' && $u->user_email === (string) $value ) {
return $u;
		}
	}
	// Real get_user_by() returns WP_User|false — never null.
	return false;
}
function get_users( array $args = array() ): array {
	// Real get_users() without a meta query returns EVERY matching user; the
	// old body returned an empty array unless meta_key was set.
	$results  = array();
	$meta_key = $args['meta_key'] ?? '';
	$meta_val = $args['meta_value'] ?? '';
	foreach ( $GLOBALS['_mock_users'] as $uid => $u ) {
		if ( '' === $meta_key ) {
			$results[] = $u;
			continue;
		}
		if ( isset( $GLOBALS['_mock_user_meta'][ $uid ][ $meta_key ] )
			&& (string) $GLOBALS['_mock_user_meta'][ $uid ][ $meta_key ] === (string) $meta_val ) {
			$results[] = $u;
		}
	}
	if ( isset( $args['number'] ) && (int) $args['number'] > 0 ) {
		$results = array_slice( $results, 0, (int) $args['number'] );
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
	$exists = isset( $GLOBALS['_mock_user_meta'][ $user_id ][ $key ] );
	$val    = $exists ? $GLOBALS['_mock_user_meta'][ $user_id ][ $key ] : null;
	// Real get_metadata(): a missing single value is '' — never null. Code
	// doing `$meta === ''` vs `null === $meta` saw two different worlds.
	if ( $single ) {
		return $exists ? $val : '';
	}
	return $exists ? array( $val ) : array();
}
function update_user_meta( int $user_id, string $key, $val ): bool {
	$GLOBALS['_mock_user_meta'][ $user_id ][ $key ] = $val;
	return true;
}
function current_user_can( string $cap ): bool {
	$user = wp_get_current_user();
	if ( ! $user || empty( $user->ID ) ) {
return false;
	}
	return $user->has_cap( $cap );
}
function user_can( $user, string $cap ): bool {
	$u = is_numeric( $user ) ? get_userdata( (int) $user ) : $user;
	if ( ! $u ) {
return false;
	}
	return $u->has_cap( $cap );
}

// Mock Options
$GLOBALS['_mock_theme_mods'] = array();

$GLOBALS['_mock_options'] = array(
	'flavor_core_db_version' => '1.5.0',
	'blogname'               => 'رستوران سنتی شاندیز',
	'blogdescription'        => 'لذیذترین غذاهای اصیل ایرانی',
	// The production site runs on Tehran time — the mock must too, otherwise
	// every naive-string vs current_time() pairing cancels out at offset zero
	// and +3:30 bugs (prompt 48's trio) sail through the suite blind.
	'timezone_string'        => 'Asia/Tehran',
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
/**
 * Theme mods are empty by default (every test sees production defaults), but a
 * suite may seed `$GLOBALS['_mock_theme_mods']` to exercise a configured site.
 */
function get_theme_mod( string $key, $default = false ) {
	return $GLOBALS['_mock_theme_mods'][ $key ] ?? $default;
}
function get_bloginfo( string $key, string $filter = 'raw' ): string {
	unset( $filter );
	// Real bloginfo() answers per key from site options — the old constant
	// ignored the key entirely (name and description returned one string).
	switch ( $key ) {
		case 'name':
			return (string) get_option( 'blogname', '' );
		case 'description':
			return (string) get_option( 'blogdescription', '' );
		case 'url':
		case 'wpurl':
		case 'siteurl':
			return home_url();
		case 'charset':
			return 'UTF-8';
		case 'language':
			return 'fa-IR';
		default:
			return '';
	}
}

// Mock Time & Translation
/**
 * Faithful current_time(): real WP shifts the epoch by the SITE offset, it
 * does not return time(). `timestamp` = time() + site offset (unless $gmt),
 * `mysql` = that localized epoch rendered through gmdate(), `datetime` =
 * current_datetime(). The old body returned time()/date() verbatim, i.e. it
 * simulated a UTC site no production install of this plugin runs on.
 */
function current_time( string $type, $gmt = 0 ) {
	if ( 'timestamp' === $type || 'U' === $type ) {
		$timestamp = time();
		if ( $gmt ) {
			return $timestamp;
		}
		return $timestamp + (int) wp_timezone()->getOffset( new DateTimeImmutable( '@' . $timestamp ) );
	}
	if ( 'datetime' === $type ) {
		return current_datetime();
	}
	if ( 'mysql' === $type ) {
		return gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp', $gmt ) );
	}
	return gmdate( $type, (int) current_time( 'timestamp', $gmt ) );
}

function size_format( $bytes, int $decimals = 0 ) {
	// Real size_format() defaults to 0 decimals and walks the full tier list
	// up to PB; the old double defaulted to 2 and stopped at MB.
	$bytes = (float) $bytes;
	foreach (
		array(
			'PB' => 1125899906842624,
			'TB' => 1099511627776,
			'GB' => 1073741824,
			'MB' => 1048576,
			'KB' => 1024,
		) as $unit => $size
	) {
		if ( $bytes >= $size ) {
			return number_format_i18n_proxy( $bytes / $size, $decimals ) . ' ' . $unit;
		}
	}
	return (int) $bytes . ' B';
}
function number_format_i18n_proxy( float $num, int $decimals ): string {
return number_format( $num, $decimals ); }

function rest_url( string $path = '' ): string {
	return 'https://mock.flavor.local/wp-json/' . ltrim( $path, '/' );
}

function sanitize_hex_color( $color ) {
	// Real sanitize_hex_color(): '' stays '', a VALID '#xxx'/'#xxxxxx' is
	// returned verbatim (no ltrim/re-hash), and anything else returns NULL —
	// not ''. Callers using `?:` keep working; callers comparing to '' don't.
	if ( '' === $color ) {
		return '';
	}
	if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color ) ) {
		return $color;
	}
	return null;
}

function wp_timezone(): DateTimeZone {
	// Same source as the real function, minus the option sanitising: a fixed
	// offset keeps tests deterministic. A test may update_option() the
	// 'timezone_string' key to a named zone (Asia/Tehran) to prove the display
	// path follows the *site* clock and not the server's.
	$value = get_option( 'timezone_string', '+00:00' ) ?: '+00:00';
	try {
		return new DateTimeZone( (string) $value );
	} catch ( Exception $e ) {
		return new DateTimeZone( 'UTC' );
	}
}

function wp_date( string $format, ?int $timestamp = null ): string {
	// The real wp_date() also applies the date-format filters; the mock keeps the
	// part the code under test depends on: an instant is rendered in the *site*
	// timezone, never in date_default_timezone_get().
	$moment = new DateTimeImmutable( '@' . ( null === $timestamp ? time() : $timestamp ) );
	return $moment->setTimezone( wp_timezone() )->format( $format );
}

function current_datetime(): DateTimeImmutable {
	return ( new DateTimeImmutable( 'now' ) )->setTimezone( wp_timezone() );
}

function wp_timezone_string(): string {
	// A fixed offset keeps tests deterministic; the real function reads
	// the site's timezone option.
	return get_option( 'timezone_string', '+00:00' ) ?: '+00:00';
}


function wp_generate_uuid4(): string {
	$data = '';
	for ( $i = 0; $i < 16; $i++ ) {
		$data .= chr( mt_rand( 0, 255 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
	}
	$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
	$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
}

function get_temp_dir(): string {
	return sys_get_temp_dir() . '/';
}

function wp_delete_file( string $file ): void {
	if ( is_file( $file ) ) {
		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
}


/**
 * Faithful sanitize_title_with_dashes(): real WordPress STRIPS every
 * non-ASCII character (Persian included). The old body preserved the Arabic
 * script block — a kindness production does not have, which let Persian
 * slugs/tenant ids look fine in the suite while going empty on a live site.
 */
function sanitize_title( $title ) {
	$title = strip_tags( (string) $title );
	$title = (string) preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title );
	$title = (string) preg_replace( '/&.+?;/', '', $title );
	$title = str_replace( '.', '-', $title );
	$title = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
	$title = (string) preg_replace( '/[^%a-z0-9 _-]/', '', $title );
	$title = (string) preg_replace( '/\s+/', '-', $title );
	$title = (string) preg_replace( '/-+/', '-', $title );
	$title = (string) preg_replace( '|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title );
	return trim( $title, '-' );
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
	// Real esc_url_raw() rejects disallowed schemes outright (javascript: and
	// friends become ''); the old identity function let anything through, so
	// URL-sanitisation paths were blind-green.
	$url = trim( $url );
	$url = (string) preg_replace( '/[\x00-\x20\x7f]+/', '', $url );
	if ( '' === $url ) {
		return '';
	}
	if ( preg_match( '#^([a-z0-9.+-]+):#i', $url, $m ) ) {
		$allowed = array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'nntp', 'tel', 'callto', 'sms', 'urn', 'xmpp', 'irc', 'gopher' );
		if ( ! in_array( strtolower( $m[1] ), $allowed, true ) ) {
			return '';
		}
	}
	return str_replace( ' ', '%20', $url );
}
/**
 * Faithful _sanitize_text_fields(): strip script/style blocks and tags,
 * REMOVE percent-encoded octets, collapse all whitespace runs to single
 * spaces and trim. The old trim(strip_tags()) kept newlines and octets no
 * production sanitize_text_field() ever returns.
 */
function _mock_strip_octets( string $str ): string {
	$found = true;
	while ( $found ) {
		$found = false;
		if ( preg_match( '/%[a-f0-9]{2}/i', $str, $match ) ) {
			$str   = str_replace( $match[0], '', $str );
			$found = true;
		}
	}
	return $str;
}
function sanitize_text_field( string $str ): string {
	$str = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $str );
	$str = strip_tags( $str );
	$str = (string) preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( _mock_strip_octets( $str ) );
}
function sanitize_textarea_field( string $str ): string {
	// Real textarea sanitisation KEEPS line breaks (that is the whole point of
	// the textarea variant) — the old body collapsed both fields identically.
	$str = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $str );
	$str = strip_tags( $str );
	return trim( _mock_strip_octets( $str ) );
}
function sanitize_key( string $key ): string {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
}
function wp_generate_password( int $length = 12, bool $special_chars = true ): string {
	return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
}
function wp_json_encode( $data, int $options = 0, int $depth = 512 ): string {
	// Mirror the real signature so callers that pass JSON_* flags are exercised
	// exactly as they are in WordPress.
	return (string) json_encode( $data, $options ?: JSON_UNESCAPED_UNICODE, $depth );
}
function wp_rand( int $min = 0, int $max = 0 ): int {
	return mt_rand( $min, $max ?: mt_getrandmax() );
}
function wp_logout() {
	wp_set_current_user( 0 );
}

// Mock Hooks — minimal but functional WP action/filter registry, so suites
// can wire listeners (e.g. WebhookManager::hooks()) and fire real do_action()s.
$GLOBALS['_mock_actions'] = isset( $GLOBALS['_mock_actions'] ) && is_array( $GLOBALS['_mock_actions'] ) ? $GLOBALS['_mock_actions'] : array();

/**
 * Sorted listeners for a tag. Real WP runs callbacks in priority order
 * (stable within a priority); registration order alone let listeners fire
 * in whatever order files happened to load.
 *
 * @param string $tag Hook name.
 * @return array<int, array<string, mixed>>
 */
function _mock_sorted_hooks( string $tag ): array {
	$hooks = $GLOBALS['_mock_actions'][ $tag ] ?? array();
	usort( $hooks, static fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
	return $hooks;
}
function do_action( string $tag, ...$args ) {
	foreach ( _mock_sorted_hooks( $tag ) as $hook ) {
		call_user_func_array( $hook['callback'], array_slice( $args, 0, $hook['accepted_args'] ) );
	}
}
/**
 * Real apply_filters() runs every registered listener and chains the value.
 * The old body was a hardcoded no-op with one magic special case — filters
 * the plugin itself registers (the SMS test seams, 'the_content', gateway
 * registration) never ran, so any behaviour that depended on a filter was
 * untestable and silently green.
 */
function apply_filters( string $tag, $value, ...$args ) {
	foreach ( _mock_sorted_hooks( $tag ) as $hook ) {
		$value = call_user_func_array(
			$hook['callback'],
			array_merge( array( $value ), array_slice( $args, 0, max( 0, $hook['accepted_args'] - 1 ) ) )
		);
	}
	return $value;
}
function add_action( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ) {
	$GLOBALS['_mock_actions'][ $tag ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => max( 1, $accepted_args ),
	);
}
function add_filter( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ) {
	// Real WP: actions and filters share one registry — add_action() and
	// add_filter() are the same function. The old no-op body meant every
	// add_filter() call in the plugin was dead weight inside the suite.
	$GLOBALS['_mock_actions'][ $tag ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => max( 1, $accepted_args ),
	);
}
function has_filter( string $tag, $callback = false ) {
	$hooks = $GLOBALS['_mock_actions'][ $tag ] ?? array();
	if ( false === $callback ) {
		return ! empty( $hooks );
	}
	foreach ( $hooks as $hook ) {
		if ( $hook['callback'] === $callback ) {
			return true;
		}
	}
	return false;
}
function has_action( string $tag, $callback = false ) {
	return has_filter( $tag, $callback );
}

// The OTP capture the old apply_filters() hardcoded — now a real registered
// listener, so it exercises the very machinery production relies on.
add_filter(
	'flavor_core_sms_sent',
	static function ( $result, $mobile, $message, $event ) {
		unset( $mobile, $event );
		if ( preg_match( '/(\d{4,6})/', (string) $message, $m ) ) {
			$GLOBALS['_last_sent_otp_code'] = $m[1];
		}
		return $result;
	},
	10,
	4
);

/**
 * Minimal-but-faithful wpautop: plain text becomes <p>-wrapped HTML, exactly
 * the transformation MenuController/BranchController descriptions go through
 * on a real site via apply_filters( 'the_content', ... ). Without it the mock
 * returned raw strings no production REST response ever contains.
 */
function wpautop( string $text ): string {
	$t = trim( $text );
	if ( '' === $t ) {
		return '';
	}
	$t = (string) preg_replace( '/\n{2,}/', "</p>\n<p>", $t );
	return '<p>' . $t . "</p>\n";
}
add_filter( 'the_content', 'wpautop', 10, 1 );

// Cron scheduling registry — tests assert queued async work.
$GLOBALS['_mock_cron_events'] = isset( $GLOBALS['_mock_cron_events'] ) && is_array( $GLOBALS['_mock_cron_events'] ) ? $GLOBALS['_mock_cron_events'] : array();
function wp_schedule_single_event( int $timestamp, string $hook, array $args = array() ): bool {
	$GLOBALS['_mock_cron_events'][] = array(
		'timestamp' => $timestamp,
		'hook'      => $hook,
		'args'      => $args,
	);
	return true;
}
// Real nonce machinery (pluggable.php): tick-bound HMAC over
// tick|action|uid|session-token, returning 1 (current tick), 2 (previous
// tick) or false. The old body accepted ANY non-empty string, so every
// permission_callback that checks a nonce was blind-green in the suite.
function wp_salt( string $scheme = 'auth' ): string {
	return AUTH_SALT . '|' . $scheme;
}
function wp_hash( string $data, string $scheme = 'auth' ): string {
	return hash_hmac( 'md5', $data, wp_salt( $scheme ) );
}
function wp_get_session_token(): string {
	return 'mock-session-token';
}
function wp_nonce_tick( $action = -1 ): int {
	unset( $action );
	return (int) ceil( time() / ( 12 * HOUR_IN_SECONDS ) );
}
function wp_create_nonce( $action = -1 ): string {
	$uid = get_current_user_id();
	$i   = wp_nonce_tick( $action );
	return substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . wp_get_session_token(), 'nonce' ), -12, 10 );
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	$nonce = (string) $nonce;
	if ( '' === $nonce ) {
		return false;
	}
	$uid   = get_current_user_id();
	$token = wp_get_session_token();
	$i     = wp_nonce_tick( $action );
	$expected = substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
	if ( hash_equals( $expected, $nonce ) ) {
		return 1;
	}
	$expected = substr( wp_hash( ( $i - 1 ) . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
	if ( hash_equals( $expected, $nonce ) ) {
		return 2;
	}
	return false;
}
function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}
/**
 * Record every REST route registration so tests can assert the final route
 * map (duplicate detection, permission audits).
 *
 * Shape per record: array( ns, route, methods(string), perm(string|callback) ).
 *
 * @var array<int, array<string, mixed>>
 */
$GLOBALS['_mock_rest_routes'] = isset( $GLOBALS['_mock_rest_routes'] ) && is_array( $GLOBALS['_mock_rest_routes'] ) ? $GLOBALS['_mock_rest_routes'] : array();

function register_rest_route( string $ns, string $route, array $args ) {
	$endpoints = $args;
	// Single-endpoint shape: array( 'methods' => ..., 'callback' => ... ).
	if ( isset( $args['methods'] ) ) {
		$endpoints = array( $args );
	}
	foreach ( $endpoints as $ep ) {
		if ( ! is_array( $ep ) ) {
			continue;
		}
		$m = $ep['methods'] ?? 'GET';
		$m = is_array( $m ) ? implode( ',', array_map( 'strval', $m ) ) : (string) $m;
		$GLOBALS['_mock_rest_routes'][] = array(
			'ns'      => $ns,
			'route'   => $route,
			'methods' => $m,
			'perm'    => $ep['permission_callback'] ?? null,
		);
	}
}

// Mock WP_Error
class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		// Real WP_Error accepts mixed $data (string|int|array) and a default
		// empty code; the typed constructor fataled on shapes production allows.
		$this->code    = (string) $code;
		$this->message = (string) $message;
		$this->data    = $data;
	}
	public function get_error_code(): string {
return $this->code; }
	public function get_error_message(): string {
return $this->message; }
	public function get_error_data( $code = '' ) {
		unset( $code );
		return $this->data;
	}
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
	public string $body = '';

	public function __construct( string $method = 'GET', string $route = '' ) {
		$this->method = $method;
		$this->route  = $route;
	}
	public function set_header( string $k, string $v ) {
$this->headers[ strtolower( $k ) ] = $v; }
	public function get_header( string $k ) {
		// Real WP_REST_Request::get_header() returns null when the header is
		// absent — '' conflated "sent empty" with "not sent".
		return $this->headers[ strtolower( $k ) ] ?? null;
	}
	public function set_param( string $k, $v ) {
$this->params[ $k ] = $v; }
	public function get_param( string $k ) {
return $this->params[ $k ] ?? $this->json_params[ $k ] ?? null; }
	public function get_params(): array {
return array_merge( $this->params, $this->json_params ); }
	public function set_json_params( array $params ) {
$this->json_params = $params; }
	public function get_json_params(): array {
return $this->json_params; }
	public function set_body( string $body ) {
		$this->body        = $body;
		$decoded           = json_decode( $body, true );
		$this->json_params = is_array( $decoded ) ? $decoded : array();
	}
	public function get_body(): string {
return $this->body; }
	public function offsetGet( $k ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHP ArrayAccess interface contract pins this exact name.
return $this->get_param( $k ); }
}

class WP_REST_Response {
	public $data;
	public int $status;
	public array $headers = array();
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function get_status(): int {
return $this->status; }
	public function get_data() {
return $this->data; }
	public function header( string $k, string $v ) {
$this->headers[ $k ] = $v; }
	public function get_headers(): array {
return $this->headers; }
}

function rest_ensure_response( $response ) {
	if ( $response instanceof WP_REST_Response ) {
return $response;
	}
	return new WP_REST_Response( $response, 200 );
}

// Mock WooCommerce Cart & Order Objects
class MockWCCart {
	public array $items = array();
	public array $coupons = array();
	public array $fees = array();

	public function is_empty(): bool {
return empty( $this->items ); }
	public function empty_cart() {
$this->items = array();
$this->coupons = array();
$this->fees = array(); }
	public function get_cart_contents_count(): int {
		$sum = 0;
		foreach ( $this->items as $i ) {
$sum += (int) ( $i['quantity'] ?? 1 );
		}
		return $sum;
	}
	public function get_cart(): array {
return $this->items; }
	public function get_cart_item( string $key ) {
return $this->items[ $key ] ?? null; }
	public function set_quantity( string $key, int $qty ) {
if ( isset( $this->items[ $key ] ) ) {
$this->items[ $key ]['quantity'] = $qty;
	} }
	public function remove_cart_item( string $key ) {
unset( $this->items[ $key ] ); }
	public function get_subtotal(): float {
		// Real WC sums the actual line totals — the old count * 150000
		// constant ignored every real product price in the catalog.
		$sum = 0.0;
		foreach ( $this->items as $item ) {
			$sum += (float) ( $item['line_total'] ?? 0 );
		}
		return $sum;
	}
	public function get_total( $mode = 'edit' ): float {
		unset( $mode );
		$total = $this->get_subtotal();
		foreach ( $this->fees as $fee ) {
			$total += (float) ( $fee->amount ?? 0 );
		}
		return $total;
	}
	public function get_fees(): array {
return $this->fees; }
	public function add_fee( string $name, float $amount, bool $taxable = false ) {
		$this->fees[] = (object) array(
			'name' => $name,
			'amount' => $amount,
		);
	}
	public function needs_payment(): bool {
return true; }
	public function get_applied_coupons(): array {
return $this->coupons; }
	public function apply_coupon( string $code ): bool {
$this->coupons[] = $code;
return true; }
	public function remove_coupon( string $code ): bool {
$this->coupons = array_diff( $this->coupons, array( $code ) );
return true; }
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
	public function has_session(): bool {
return true; }
	public function set_customer_session_cookie( bool $val ) {}
	public function get( string $key, $default = null ) {
return $this->data[ $key ] ?? $default; }
	public function set( string $key, $value ) {
$this->data[ $key ] = $value; }
}

class MockWCGateway {
	public string $id;
	public string $title;
	public string $enabled = 'yes';
	public function __construct( string $id, string $title ) {
		$this->id    = $id;
		$this->title = $title;
	}
	public function get_title(): string {
return $this->title; }
	public function get_description(): string {
return 'روش پرداخت انتخابی رستوران'; }
	public function process_payment( int $order_id ): array {
		return array(
			'result' => 'success',
			'redirect' => 'https://restaurant.example.com/order-received',
		);
	}
}

class MockWCFailingGateway extends MockWCGateway {
	public function process_payment( int $order_id ): array {
		return array(
			'result' => 'failure',
			'messages' => 'تراکنش توسط بانک رد شد.',
		);
	}
}

class MockWCOnlineGateway extends MockWCGateway {
	public function process_payment( int $order_id ): array {
		return array(
			'result' => 'success',
			'redirect' => 'https://bank.example.com/pay/' . $order_id,
		);
	}
}

class MockWCPaymentGateways {
	/**
	 * Test seam: when set, replaces the default gateway catalog.
	 *
	 * @var array<string, MockWCGateway>|null
	 */
	public static ?array $catalog_override = null;

	public function get_available_payment_gateways(): array {
		if ( null !== self::$catalog_override ) {
			return self::$catalog_override;
		}
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
		++self::$order_counter;
		$order_id = self::$order_counter;
		$order = new WC_Order( $order_id, $posted );
		$GLOBALS['_mock_wc_orders'][ $order_id ] = $order;
		// Real WC_Checkout::create_order() fires this right before $order->save()
		// — it is the exact hook OrderModes::stamp_order() rides in production,
		// and it never fired inside the suite before.
		do_action( 'woocommerce_checkout_create_order', $order, $posted );
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

function WC(): MockWC { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- WooCommerce's global factory function is named WC().
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
/**
 * Fetch products, honoring `paginate` the way WooCommerce does.
 *
 * WooCommerce returns an ARRAY of WC_Product objects normally, and a stdClass
 * with `products`/`total` only when `paginate => true` is passed. This double
 * used to return the paginated object unconditionally, which silently broke
 * every caller that does not ask for pagination — the theme's own
 * item-card.php, featured.php and class-schema-output.php all iterate the
 * result directly, so they were walking two properties instead of products.
 *
 * @param array<string, mixed> $args Query args.
 * @return array<int, WC_Product>|object
 */
function wc_get_products( array $args ) {
	$products = array_values( $GLOBALS['_mock_wc_products'] );

	if ( ! empty( $args['paginate'] ) ) {
		return (object) array(
			'products' => $products,
			'total'    => count( $products ),
		);
	}

	// Respect `limit` so a widget asking for three items can be told apart
	// from one asking for all of them.
	$limit = isset( $args['limit'] ) ? (int) $args['limit'] : -1;
	if ( $limit > 0 ) {
		$products = array_slice( $products, 0, $limit );
	}

	return $products;
}
/**
 * Permalink of a WooCommerce page (shop, cart, checkout, myaccount).
 *
 * @param string $page Page slug.
 */
function wc_get_page_permalink( string $page ): string {
	return home_url( '/' . $page . '/' );
}

/**
 * Checkout URL.
 */
function wc_get_checkout_url(): string {
	return home_url( '/checkout/' );
}

function wc_get_order( $id ) {
	// Real wc_get_order() returns WC_Order|false — never null.
	return $GLOBALS['_mock_wc_orders'][ (int) $id ] ?? false;
}
function wc_get_orders( array $args ) {
	$orders = array_values( $GLOBALS['_mock_wc_orders'] );
	if ( ! empty( $args['customer_id'] ) ) {
		$cid    = (int) $args['customer_id'];
		$orders = array_values( array_filter( $orders, fn( $o ) => (int) $o->get_customer_id() === $cid ) );
	}
	// Real wc_get_orders() honours 'status' (with the wc- prefix stripped) and
	// 'date_created' => 'from...to' ranges; the old double ignored both, so
	// report windows and status filters were blind inside the suite.
	if ( ! empty( $args['status'] ) ) {
		$wanted = array_map(
			static function ( $st ) {
				$st = (string) $st;
				return str_starts_with( $st, 'wc-' ) ? substr( $st, 3 ) : $st;
			},
			(array) $args['status']
		);
		$orders = array_values( array_filter( $orders, static fn( $o ) => in_array( $o->get_status(), $wanted, true ) ) );
	}
	if ( ! empty( $args['date_created'] ) && is_string( $args['date_created'] ) && str_contains( $args['date_created'], '...' ) ) {
		list( $from, $to ) = explode( '...', $args['date_created'], 2 );
		$orders = array_values(
			array_filter(
				$orders,
				static function ( $o ) use ( $from, $to ) {
					$day = $o->get_date_created()->format( 'Y-m-d' );
					return ( '' === $from || $day >= $from ) && ( '' === $to || $day <= $to );
				}
			)
		);
	}
	if ( ! empty( $args['meta_key'] ) ) {
		$mk     = (string) $args['meta_key'];
		$mv     = $args['meta_value'] ?? null;
		$orders = array_values( array_filter( $orders, function ( $o ) use ( $mk, $mv ) {
			$val = $o->get_meta( $mk );
			if ( null === $mv ) {
				return null !== $val && '' !== $val;
			}
			return (string) $val === (string) $mv;
		} ) );
	}
	$dir = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 1 : -1;
	usort( $orders, fn( $a, $b ) => ( $a->get_id() <=> $b->get_id() ) * $dir );
	$total = count( $orders );
	if ( ! empty( $args['paginate'] ) ) {
		$limit = max( 1, (int) ( $args['limit'] ?? 10 ) );
		$page  = max( 1, (int) ( $args['page'] ?? 1 ) );
		return (object) array(
			'orders' => array_slice( $orders, ( $page - 1 ) * $limit, $limit ),
			'total'  => $total,
		);
	}
	if ( isset( $args['limit'] ) && (int) $args['limit'] > 0 ) {
		$orders = array_slice( $orders, 0, (int) $args['limit'] );
	}
	return $orders;
}
function wc_price( $amount ): string {
	return number_format( (float) $amount ) . ' تومان';
}
function wc_format_coupon_code( string $code ): string {
	return trim( strtoupper( $code ) );
}
function wc_get_order_status_name( string $status ): string {
	$map = array(
		'pending' => 'در انتظار پرداخت',
		'processing' => 'در حال پردازش',
		'completed' => 'تکمیل شده',
		'cancelled' => 'لغو شده',
	);
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
/**
 * Post meta, backed by a global store.
 *
 * This used to return '' unconditionally, which made any feature that reads
 * and writes meta impossible to test. Reading from an empty store still
 * yields '', so nothing that relied on the old behaviour changes.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param bool   $single  Return a single value.
 * @return mixed
 */
function get_post_meta( int $post_id, string $key, bool $single = false ) {
	$all = $GLOBALS['_mock_post_meta'][ $post_id ][ $key ] ?? array();
	return $single ? ( $all[0] ?? '' ) : $all;
}

function update_post_meta( int $post_id, string $key, $value ) {
	$GLOBALS['_mock_post_meta'][ $post_id ][ $key ] = array( $value );
	return true;
}

function add_post_meta( int $post_id, string $key, $value ) {
	$GLOBALS['_mock_post_meta'][ $post_id ][ $key ][] = $value;
	return true;
}

function delete_post_meta( int $post_id, string $key, $value = '' ) {
	if ( '' === $value ) {
		unset( $GLOBALS['_mock_post_meta'][ $post_id ][ $key ] );
		return true;
	}
	$all = $GLOBALS['_mock_post_meta'][ $post_id ][ $key ] ?? array();
	$kept = array_values( array_filter( $all, static fn( $v ) => $v !== $value ) );
	if ( empty( $kept ) ) {
		unset( $GLOBALS['_mock_post_meta'][ $post_id ][ $key ] );
	} else {
		$GLOBALS['_mock_post_meta'][ $post_id ][ $key ] = $kept;
	}
	return true;
}
function get_the_terms( int $id, string $tax ) {
	return array( new WP_Term( 1, 'کباب‌ها', 'kabab' ) );
}
function taxonomy_exists( string $tax ): bool {
return true; }
function get_terms( array $args ) {
	return array(
		new WP_Term( 1, 'کباب و گریل', 'kabab', 'product_cat', 'انواع کباب‌های تازه', 8 ),
		new WP_Term( 2, 'خورشت‌های سنتی', 'khoresht', 'product_cat', 'خورشت‌های جاافتاده', 6 ),
		new WP_Term( 3, 'پیش‌غذا و سالاد', 'appetizer', 'product_cat', 'سالادها و ماست‌های محلی', 5 ),
	);
}
$GLOBALS['_mock_term_meta'] = isset( $GLOBALS['_mock_term_meta'] ) && is_array( $GLOBALS['_mock_term_meta'] ) ? $GLOBALS['_mock_term_meta'] : array();
function get_term_meta( int $id, string $key, bool $single = false ) {
	// Storage-backed like the real meta API; the old constant '' made every
	// term-meta read/write path untestable (MenuController reads thumbnail_id
	// and _flavor_icon through it).
	$all = $GLOBALS['_mock_term_meta'][ $id ][ $key ] ?? array();
	return $single ? ( $all[0] ?? '' ) : $all;
}
function update_term_meta( int $id, string $key, $value ): bool {
	$GLOBALS['_mock_term_meta'][ $id ][ $key ] = array( $value );
	return true;
}
/**
 * Faithful get_posts(): real WP returns WP_Post OBJECTS by default and an
 * array of IDs only for 'fields' => 'ids'. The old body returned bare ints
 * unconditionally — every caller doing $b->ID would fatal on a live site
 * while sailing through the suite.
 */
function get_posts( array $args ) {
	$ids = array( 1, 2 );
	$per_page = $args['posts_per_page'] ?? $args['numberposts'] ?? null;
	if ( null !== $per_page && (int) $per_page > 0 ) {
		$ids = array_slice( $ids, 0, (int) $per_page );
	}
	if ( 'ids' === ( $args['fields'] ?? '' ) ) {
		return $ids;
	}
	return array_map(
		static function ( $id ) {
			return (object) array(
				'ID'           => $id,
				'post_title'   => get_the_title( $id ),
				'post_name'    => 'central-branch',
				'post_type'    => get_post_type( $id ),
				'post_status'  => get_post_status( $id ),
				'post_content' => 'شعبه مرکزی شاندیز',
			);
		},
		$ids
	);
}

// Test seams for branch validation: mark posts as missing or override status.
$GLOBALS['_mock_missing_posts'] = isset( $GLOBALS['_mock_missing_posts'] ) && is_array( $GLOBALS['_mock_missing_posts'] ) ? $GLOBALS['_mock_missing_posts'] : array();
$GLOBALS['_mock_post_status']   = isset( $GLOBALS['_mock_post_status'] ) && is_array( $GLOBALS['_mock_post_status'] ) ? $GLOBALS['_mock_post_status'] : array();

function get_post_type( $id ): string {
	// Seam for negative tests: $_mock_post_type[ $id ] overrides the default.
	// Returning 'flavor_branch' for EVERY id meant post-type guards could
	// never observe a non-branch post.
	$id = is_object( $id ) ? (int) ( $id->ID ?? 0 ) : (int) $id;
	return (string) ( $GLOBALS['_mock_post_type'][ $id ] ?? 'flavor_branch' );
}
function get_post_status( $id ): string {
	$id = is_object( $id ) ? (int) ( $id->ID ?? 0 ) : (int) $id;
	return (string) ( $GLOBALS['_mock_post_status'][ $id ] ?? 'publish' );
}
function get_post( int $id ) {
	if ( ! empty( $GLOBALS['_mock_missing_posts'][ $id ] ) ) {
		return null;
	}
	return (object) array(
		'ID'           => $id,
		'post_name'    => 'central-branch',
		'post_type'    => 'flavor_branch',
		'post_status'  => get_post_status( $id ),
		'post_content' => 'شعبه مرکزی شاندیز',
		'post_title'   => 'شعبه مرکزی',
	);
}
function get_the_title( int $id ): string {
return 'شعبه مرکزی شاندیز'; }
function get_avatar_url( int $id, array $args = array() ): string {
return 'https://secure.gravatar.com/avatar/sample'; }
/**
 * Recorded outbound HTTP calls: array of {url, args}.
 * Tests inspect this to verify real delivery attempts were made.
 *
 * @var array<int, array{url: string, args: array}>
 */
$_mock_http_requests = array();

/**
 * Optional test-controlled responder: callable(string $url, array $args): array{code: int, body: string}
 * Return null to fall back to the generic success response.
 *
 * @var callable|null
 */
$_mock_http_responder = null;

function wp_remote_post( string $url, array $args ) {
	global $_mock_http_requests, $_mock_http_responder;
	$_mock_http_requests[] = array(
		'url'  => $url,
		'args' => $args,
	);
	if ( is_callable( $_mock_http_responder ) ) {
		$res = call_user_func( $_mock_http_responder, $url, $args );
		if ( $res instanceof WP_Error ) {
			// Real wp_remote_post() returns WP_Error on transport failures —
			// WebhookManager branches on is_wp_error() and that branch was
			// unreachable from any test before.
			return $res;
		}
		if ( null !== $res ) {
			return array(
				'response' => array( 'code' => (int) ( $res['code'] ?? 200 ) ),
				'body'     => (string) ( $res['body'] ?? '' ),
			);
		}
	}
	return array(
		'response' => array( 'code' => 200 ),
		'body' => '{"success":true}',
	);
}
function wp_remote_retrieve_response_code( $resp ): int {
return (int) ( $resp['response']['code'] ?? 200 ); }
function wp_remote_retrieve_body( $resp ): string {
return (string) ( $resp['body'] ?? '' ); }
/**
 * Sent-mail log for assertions; set $GLOBALS['_mock_mail_fail'] = true to
 * exercise wp_mail()'s real failure branch (ScheduledReport::deliver returns
 * `(bool) wp_mail(...)` and that bool was pinned true forever).
 *
 * @var array<int, array<string, mixed>>
 */
$GLOBALS['_mock_sent_mail'] = isset( $GLOBALS['_mock_sent_mail'] ) && is_array( $GLOBALS['_mock_sent_mail'] ) ? $GLOBALS['_mock_sent_mail'] : array();
function wp_mail( string $to, string $subj, string $body, $headers = array(), $attachments = array() ): bool {
	$GLOBALS['_mock_sent_mail'][] = array(
		'to'          => $to,
		'subject'     => $subj,
		'body'        => $body,
		'headers'     => $headers,
		'attachments' => $attachments,
	);
	return empty( $GLOBALS['_mock_mail_fail'] );
}
