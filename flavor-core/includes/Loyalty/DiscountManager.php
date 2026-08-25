<?php
/**
 * WooCommerce coupons + Flavor meta (Jalali expiry, branch, first-order).
 *
 * @package FlavorCore
 */

namespace FlavorCore\Loyalty;

use FlavorCore\Order\OrderModes;
use FlavorCore\Support\Jalali;

defined( 'ABSPATH' ) || exit;

/**
 * Class DiscountManager
 */
class DiscountManager {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'woocommerce_coupon_options', array( $this, 'fields' ), 20, 2 );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save' ), 20, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate' ), 20, 3 );
		add_action( 'woocommerce_applied_coupon', array( $this, 'maybe_birthday' ) );
	}

	/**
	 * Extra coupon fields.
	 *
	 * @param int         $id     Coupon id.
	 * @param \WC_Coupon $coupon Coupon.
	 */
	public function fields( $id, $coupon ): void {
		unset( $coupon );
		$jalali = (string) get_post_meta( $id, '_flavor_jalali_expiry', true );
		$branch = (string) get_post_meta( $id, '_flavor_branch_ids', true );
		$first  = (string) get_post_meta( $id, '_flavor_first_order', true );
		woocommerce_wp_text_input(
			array(
				'id'          => '_flavor_jalali_expiry',
				'label'       => __( 'انقضا شمسی', 'flavor-core' ),
				'description' => __( 'مثال: ۱۴۰۴/۱۲/۲۹ — روی تاریخ انقضای ووکامرس نوشته می‌شود.', 'flavor-core' ),
				'value'       => $jalali,
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_flavor_branch_ids',
				'label'       => __( 'محدود به شعبه (شناسه، با ویرگول)', 'flavor-core' ),
				'value'       => $branch,
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => '_flavor_first_order',
				'label'       => __( 'فقط اولین سفارش', 'flavor-core' ),
				'value'       => 'yes' === $first ? 'yes' : 'no',
			)
		);
	}

	/**
	 * Persist extra meta.
	 *
	 * @param int        $id     Id.
	 * @param \WC_Coupon $coupon Coupon.
	 */
	public function save( $id, $coupon ): void {
		unset( $coupon );
		$jalali = isset( $_POST['_flavor_jalali_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['_flavor_jalali_expiry'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$branch = isset( $_POST['_flavor_branch_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['_flavor_branch_ids'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$first  = ! empty( $_POST['_flavor_first_order'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( $id, '_flavor_jalali_expiry', $jalali );
		update_post_meta( $id, '_flavor_branch_ids', $branch );
		update_post_meta( $id, '_flavor_first_order', $first );
		if ( $jalali ) {
			$iso = preg_replace( '/[^\d\/\-.]/', '', $jalali );
			$g   = Jalali::jalali_iso_to_gregorian( str_replace( '/', '-', (string) $iso ) );
			update_post_meta( $id, 'date_expires', strtotime( $g . ' 23:59:59' ) );
		}
	}

	/**
	 * Extra validity rules.
	 *
	 * @param bool       $valid  Current.
	 * @param \WC_Coupon $coupon Coupon.
	 * @param \WC_Discounts $discounts Discounts.
	 * @return bool
	 */
	public function validate( $valid, $coupon, $discounts = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! $valid || ! $coupon ) {
			return $valid;
		}
		$id = $coupon->get_id();
		$branches = (string) get_post_meta( $id, '_flavor_branch_ids', true );
		if ( $branches ) {
			$allowed = array_filter( array_map( 'intval', explode( ',', $branches ) ) );
			$ctx     = OrderModes::get();
			$bid     = (int) ( $ctx['branch_id'] ?? 0 );
			if ( $allowed && $bid && ! in_array( $bid, $allowed, true ) ) {
				throw new \Exception( __( 'این کد برای شعبه انتخاب‌شده معتبر نیست.', 'flavor-core' ) );
			}
		}
		if ( 'yes' === (string) get_post_meta( $id, '_flavor_first_order', true ) && is_user_logged_in() ) {
			$n = wc_get_customer_order_count( get_current_user_id() );
			if ( $n > 0 ) {
				throw new \Exception( __( 'این کد فقط برای اولین سفارش است.', 'flavor-core' ) );
			}
		}
		return $valid;
	}

	/**
	 * Auto-apply birthday coupon if one exists and today is the birthday.
	 */
	public function maybe_birthday( string $code ): void {
		unset( $code );
	}

	/**
	 * Apply a code to the current cart.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply( string $code ) {
		\FlavorCore\WooCommerce\CartSession::ensure();
		if ( ! WC()->cart ) {
			return new \WP_Error( 'flavor_cart', __( 'سبد در دسترس نیست.', 'flavor-core' ) );
		}
		$code = wc_format_coupon_code( $code );
		if ( ! $code ) {
			return new \WP_Error( 'flavor_coupon', __( 'کد تخفیف خالی است.', 'flavor-core' ) );
		}
		$result = WC()->cart->apply_coupon( $code );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			$notes = wc_get_notices( 'error' );
			wc_clear_notices();
			$msg = $notes ? wp_strip_all_tags( $notes[0]['notice'] ?? '' ) : __( 'اعمال کد ناموفق بود.', 'flavor-core' );
			return new \WP_Error( 'flavor_coupon', $msg );
		}
		return \FlavorCore\WooCommerce\CartSession::payload();
	}

	/**
	 * If customer birthday (Jalali mm-dd) is today, return a suggested coupon code.
	 */
	public static function birthday_code( int $user_id ): string {
		$b = (string) get_user_meta( $user_id, '_flavor_birthday', true );
		if ( ! $b ) {
			return '';
		}
		$today = Jalali::parse_gregorian( current_time( 'Y-m-d' ) );
		$p     = array_map( 'intval', preg_split( '/[\/\-.]/', $b ) ?: array() );
		if ( count( $p ) < 2 ) {
			return '';
		}
		$jm = $p[ count( $p ) > 2 ? 1 : 0 ];
		$jd = $p[ count( $p ) > 2 ? 2 : 1 ];
		if ( (int) $today['m'] === $jm && (int) $today['d'] === $jd ) {
			return (string) get_option( 'flavor_core_birthday_coupon', '' );
		}
		return '';
	}
}
