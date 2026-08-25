<?php
/**
 * Mobile OTP login against WordPress users.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Customer;

use FlavorCore\Database\Schema;
use FlavorCore\SMS\SmsManager;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class OtpAuth
 */
class OtpAuth {

	public const META_MOBILE = '_flavor_mobile';

	/**
	 * Request a code. Rate-limited: max N per 10 minutes per number.
	 *
	 * @param string $raw_mobile Posted mobile.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function request( string $raw_mobile ) {
		$mobile = Iran::normalize_mobile( $raw_mobile );
		if ( '' === $mobile ) {
			return new \WP_Error( 'flavor_bad_mobile', __( 'شماره موبایل معتبر نیست. قالب: 09xxxxxxxxx', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$max = (int) Settings::get( 'otp_max_per_10min', 3 );
		if ( self::recent_count( $mobile ) >= $max ) {
			return new \WP_Error( 'flavor_otp_rate', __( 'تعداد درخواست کد بیش از حد مجاز است. ۱۰ دقیقه دیگر تلاش کنید.', 'flavor-core' ), array( 'status' => 429 ) );
		}

		$length = (int) Settings::get( 'otp_length', 5 );
		$ttl    = (int) Settings::get( 'otp_ttl_minutes', 2 );
		$code   = self::generate_code( $length );
		$hash   = self::hash_code( $mobile, $code );
		$now    = current_time( 'mysql' );
		$exp    = gmdate( 'Y-m-d H:i:s', time() + ( $ttl * MINUTE_IN_SECONDS ) );
		// expires_at stored in site local to match current_time comparisons.
		$exp_local = date( 'Y-m-d H:i:s', strtotime( $now ) + ( $ttl * MINUTE_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			Schema::table( 'flavor_otp_codes' ),
			array(
				'mobile'      => $mobile,
				'code_hash'   => $hash,
				'attempts'    => 0,
				'expires_at'  => $exp_local,
				'consumed_at' => null,
				'ip'          => self::ip(),
				'created_at'  => $now,
			)
		);

		SmsManager::send_event(
			'otp',
			$mobile,
			array( 'code' => $code ),
			array(
				'related_type' => 'otp',
				'related_id'   => (int) $wpdb->insert_id,
			)
		);

		$payload = array(
			'ok'      => true,
			'mobile'  => $mobile,
			'ttl'     => $ttl,
			'dev'     => 'dev' === SmsManager::active()->slug(),
		);

		if ( $payload['dev'] && current_user_can( 'manage_options' ) ) {
			$payload['dev_code'] = $code;
		}

		/**
		 * OTP issued.
		 *
		 * @param string $mobile Mobile.
		 */
		do_action( 'flavor_core_otp_requested', $mobile );

		return $payload;
	}

	/**
	 * Verify a code and log the user in.
	 *
	 * @param string $raw_mobile Mobile.
	 * @param string $code       Code.
	 * @param string $name       Optional name for new users.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function verify( string $raw_mobile, string $code, string $name = '' ) {
		$mobile = Iran::normalize_mobile( $raw_mobile );
		$code   = preg_replace( '/\D+/', '', $code );
		if ( '' === $mobile || ! is_string( $code ) || '' === $code ) {
			return new \WP_Error( 'flavor_bad_otp', __( 'کد یا شماره نامعتبر است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = Schema::table( 'flavor_otp_codes' );
		$now   = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE mobile = %s AND consumed_at IS NULL AND expires_at >= %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$mobile,
				$now
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new \WP_Error( 'flavor_otp_expired', __( 'کد منقضی شده یا وجود ندارد. دوباره درخواست کنید.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		if ( (int) $row['attempts'] >= 5 ) {
			return new \WP_Error( 'flavor_otp_locked', __( 'تعداد تلاش بیش از حد. کد جدید بگیرید.', 'flavor-core' ), array( 'status' => 429 ) );
		}

		if ( ! hash_equals( (string) $row['code_hash'], self::hash_code( $mobile, $code ) ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array( 'attempts' => (int) $row['attempts'] + 1 ),
				array( 'id' => (int) $row['id'] )
			);
			return new \WP_Error( 'flavor_otp_mismatch', __( 'کد نادرست است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array( 'consumed_at' => $now ),
			array( 'id' => (int) $row['id'] )
		);

		$user = self::find_or_create( $mobile, $name );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		/**
		 * Customer logged in via OTP.
		 *
		 * @param \WP_User $user   User.
		 * @param string   $mobile Mobile.
		 */
		do_action( 'flavor_core_otp_verified', $user, $mobile );

		return array(
			'ok'      => true,
			'user_id' => $user->ID,
			'name'    => $user->display_name,
			'mobile'  => $mobile,
			'new'     => (bool) get_user_meta( $user->ID, '_flavor_just_created', true ),
		);
	}

	/**
	 * Find user by mobile meta or create one.
	 *
	 * @param string $mobile Mobile.
	 * @param string $name   Name.
	 * @return \WP_User|\WP_Error
	 */
	public static function find_or_create( string $mobile, string $name = '' ) {
		$users = get_users(
			array(
				'meta_key'   => self::META_MOBILE,
				'meta_value' => $mobile,
				'number'     => 1,
			)
		);
		if ( ! empty( $users ) ) {
			return $users[0];
		}

		$by_login = get_user_by( 'login', $mobile );
		if ( $by_login ) {
			update_user_meta( $by_login->ID, self::META_MOBILE, $mobile );
			return $by_login;
		}

		$email = $mobile . '@otp.flavor.local';
		$uid   = wp_insert_user(
			array(
				'user_login'   => $mobile,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'user_email'   => $email,
				'display_name' => $name ?: $mobile,
				'nickname'     => $name ?: $mobile,
				'role'         => 'customer',
			)
		);
		if ( is_wp_error( $uid ) ) {
			return $uid;
		}
		update_user_meta( $uid, self::META_MOBILE, $mobile );
		update_user_meta( $uid, '_flavor_just_created', 1 );
		$user = get_userdata( (int) $uid );
		return $user ?: new \WP_Error( 'flavor_user', __( 'ساخت حساب انجام نشد.', 'flavor-core' ) );
	}

	/**
	 * How many OTP rows in the last 10 minutes.
	 */
	private static function recent_count( string $mobile ): int {
		global $wpdb;
		$table = Schema::table( 'flavor_otp_codes' );
		$since = date( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - ( 10 * MINUTE_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE mobile = %s AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$mobile,
				$since
			)
		);
	}

	/**
	 * Numeric code.
	 */
	private static function generate_code( int $length ): string {
		$length = min( 6, max( 4, $length ) );
		$max    = ( 10 ** $length ) - 1;
		try {
			$n = random_int( 0, $max );
		} catch ( \Exception $e ) {
			$n = wp_rand( 0, $max );
		}
		return str_pad( (string) $n, $length, '0', STR_PAD_LEFT );
	}

	/**
	 * HMAC of the code. Secret is AUTH_SALT.
	 */
	private static function hash_code( string $mobile, string $code ): string {
		$secret = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'flavor-otp';
		return hash_hmac( 'sha256', $mobile . '|' . $code, $secret );
	}

	/**
	 * Remote IP.
	 */
	private static function ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
